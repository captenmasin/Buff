import Foundation
import UserNotifications

private let mealIds = ["breakfast", "lunch", "dinner"]
private let reminderIdentifiers = mealIds.map { "buff-meal-reminder-\($0)" }

enum BackgroundTaskFunctions {
    class RegisterMealReminders: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let reminders = try parseMealReminders(parameters)
            let enabledCount = reminders.filter(\.enabled).count
            let notificationCenter = UNUserNotificationCenter.current()

            if enabledCount == 0 {
                notificationCenter.removePendingNotificationRequests(withIdentifiers: reminderIdentifiers)

                return BridgeResponse.success(data: [
                    "status": "disabled",
                    "scheduled": 0
                ])
            }

            let status = try authorizationStatus(notificationCenter)

            if status == .notDetermined {
                notificationCenter.removePendingNotificationRequests(withIdentifiers: reminderIdentifiers)
                requestAuthorizationAndSchedule(reminders)

                return BridgeResponse.success(data: [
                    "status": "permission_requested",
                    "scheduled": enabledCount
                ])
            }

            try schedule(reminders, using: notificationCenter)

            return BridgeResponse.success(data: [
                "status": status == .denied ? "notifications_disabled" : "scheduled",
                "scheduled": enabledCount
            ])
        }
    }

    class SendNotification: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let notification = try parseNotification(parameters)
            let notificationCenter = UNUserNotificationCenter.current()
            let status = try authorizationStatus(notificationCenter)

            if status == .notDetermined {
                requestAuthorizationAndSend(notification)

                return BridgeResponse.success(data: ["status": "permission_requested"])
            }

            guard status != .denied else {
                return BridgeResponse.success(data: ["status": "notifications_disabled"])
            }

            try send(notification, using: notificationCenter)

            return BridgeResponse.success(data: ["status": "sent"])
        }
    }
}

final class BackgroundTasksNotifications: NSObject, UNUserNotificationCenterDelegate {
    static let shared = BackgroundTasksNotifications()

    static func start() {
        UNUserNotificationCenter.current().delegate = shared
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .list, .sound])
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        defer { completionHandler() }

        guard let route = response.notification.request.content.userInfo["notification_url"] as? String,
              let url = URL(string: "buff:\(route)") else {
            return
        }

        DispatchQueue.main.async {
            DeepLinkRouter.shared.handle(url: url)
        }
    }
}

private struct MealReminder {
    let id: String
    let enabled: Bool
    let hour: Int
    let minute: Int
}

private struct DeviceNotification {
    let title: String
    let body: String
    let url: String?
}

private func parseNotification(_ parameters: [String: Any]) throws -> DeviceNotification {
    guard Set(parameters.keys).isSubset(of: ["title", "body", "url"]) else {
        throw BridgeError.invalidParameters("only title, body, and url are supported")
    }
    guard let title = parameters["title"] as? String,
          !title.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
          title.count <= 120 else {
        throw BridgeError.invalidParameters("title must be between 1 and 120 characters")
    }
    guard let body = parameters["body"] as? String,
          !body.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
          body.count <= 500 else {
        throw BridgeError.invalidParameters("body must be between 1 and 500 characters")
    }

    let url = parameters["url"] as? String

    if parameters["url"] != nil && url == nil {
        throw BridgeError.invalidParameters("url must be a string")
    }
    if let url,
       (url.count > 2048 || !url.hasPrefix("/") || url.hasPrefix("//")) {
        throw BridgeError.invalidParameters("url must be an internal path")
    }

    return DeviceNotification(title: title, body: body, url: url)
}

private func parseMealReminders(_ parameters: [String: Any]) throws -> [MealReminder] {
    guard let reminders = parameters["reminders"] as? [String: Any],
          Set(reminders.keys) == Set(mealIds) else {
        throw BridgeError.invalidParameters("breakfast, lunch, and dinner reminders are required")
    }

    return try mealIds.map { mealId in
        guard let reminder = reminders[mealId] as? [String: Any] else {
            throw BridgeError.invalidParameters("\(mealId) must be an object")
        }
        guard let enabled = reminder["enabled"] as? Bool else {
            throw BridgeError.invalidParameters("\(mealId).enabled must be a boolean")
        }
        guard let time = reminder["time"] as? String,
              let (hour, minute) = parseTime(time) else {
            throw BridgeError.invalidParameters("\(mealId).time must use HH:mm")
        }

        return MealReminder(id: mealId, enabled: enabled, hour: hour, minute: minute)
    }
}

private func parseTime(_ time: String) -> (Int, Int)? {
    let parts = time.split(separator: ":", omittingEmptySubsequences: false)

    guard parts.count == 2,
          parts[0].count == 2,
          parts[1].count == 2,
          let hour = Int(parts[0]),
          let minute = Int(parts[1]),
          (0...23).contains(hour),
          (0...59).contains(minute) else {
        return nil
    }

    return (hour, minute)
}

private func authorizationStatus(_ notificationCenter: UNUserNotificationCenter) throws -> UNAuthorizationStatus {
    let semaphore = DispatchSemaphore(value: 0)
    var status: UNAuthorizationStatus?

    notificationCenter.getNotificationSettings { settings in
        status = settings.authorizationStatus
        semaphore.signal()
    }

    guard semaphore.wait(timeout: .now() + 5) == .success,
          let status else {
        throw BridgeError.executionFailed("notification permission check timed out")
    }

    return status
}

private func requestAuthorizationAndSchedule(_ reminders: [MealReminder]) {
    UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound]) { granted, error in
        if let error {
            NSLog("BuffMealReminders: notification permission failed: %@", error.localizedDescription)
            return
        }

        guard granted else {
            return
        }

        DispatchQueue.global(qos: .utility).async {
            do {
                try schedule(reminders, using: UNUserNotificationCenter.current())
            } catch {
                NSLog("BuffMealReminders: scheduling failed: %@", error.localizedDescription)
            }
        }
    }
}

private func requestAuthorizationAndSend(_ notification: DeviceNotification) {
    UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound]) { granted, error in
        if let error {
            NSLog("BuffNotifications: notification permission failed: %@", error.localizedDescription)
            return
        }

        guard granted else {
            return
        }

        DispatchQueue.global(qos: .utility).async {
            do {
                try send(notification, using: UNUserNotificationCenter.current())
            } catch {
                NSLog("BuffNotifications: sending failed: %@", error.localizedDescription)
            }
        }
    }
}

private func send(_ notification: DeviceNotification, using notificationCenter: UNUserNotificationCenter) throws {
    let content = UNMutableNotificationContent()
    content.title = notification.title
    content.body = notification.body
    content.sound = .default

    if let url = notification.url {
        content.userInfo = ["notification_url": url]
    }

    let request = UNNotificationRequest(
        identifier: "buff-local-\(UUID().uuidString)",
        content: content,
        trigger: nil
    )
    let semaphore = DispatchSemaphore(value: 0)
    var sendingError: Error?

    notificationCenter.add(request) { error in
        sendingError = error
        semaphore.signal()
    }

    guard semaphore.wait(timeout: .now() + 5) == .success else {
        throw BridgeError.executionFailed("notification sending timed out")
    }

    if let sendingError {
        throw BridgeError.executionFailed(sendingError.localizedDescription)
    }
}

private func schedule(_ reminders: [MealReminder], using notificationCenter: UNUserNotificationCenter) throws {
    notificationCenter.removePendingNotificationRequests(withIdentifiers: reminderIdentifiers)

    for reminder in reminders where reminder.enabled {
        let content = UNMutableNotificationContent()
        let label = reminder.id.prefix(1).uppercased() + reminder.id.dropFirst()
        content.title = "\(label) reminder"
        content.body = "Time to log your \(reminder.id) in Buff."
        content.sound = .default
        content.userInfo = [
            "notification_url": "/add?mode=food&meal=\(reminder.id)"
        ]

        var components = DateComponents()
        components.hour = reminder.hour
        components.minute = reminder.minute

        // ponytail: iOS repeating triggers cannot query Laravel at delivery; reschedule on meal writes if logged-meal suppression becomes required.
        let request = UNNotificationRequest(
            identifier: "buff-meal-reminder-\(reminder.id)",
            content: content,
            trigger: UNCalendarNotificationTrigger(dateMatching: components, repeats: true)
        )
        let semaphore = DispatchSemaphore(value: 0)
        var schedulingError: Error?

        notificationCenter.add(request) { error in
            schedulingError = error
            semaphore.signal()
        }

        guard semaphore.wait(timeout: .now() + 5) == .success else {
            throw BridgeError.executionFailed("notification scheduling timed out")
        }

        if let schedulingError {
            throw BridgeError.executionFailed(schedulingError.localizedDescription)
        }
    }
}
