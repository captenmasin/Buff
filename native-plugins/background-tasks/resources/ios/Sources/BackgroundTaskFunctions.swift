import Foundation
import UserNotifications

private let mealIds = ["breakfast", "lunch", "dinner"]
private let reminderDays = 20
private let reminderIdentifiers = mealIds.flatMap { meal in
    let base = "buff-meal-reminder-\(meal)"
    return [base] + (1...7).map { "\(base)-weekday-\($0)" } + (0..<reminderDays).map { "\(base)-day-\($0)" }
}

enum BackgroundTaskFunctions {
    class RegisterMealReminders: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let reminders = try parseMealReminders(parameters)
            let status = try MealReminderScheduler.shared.sync(
                reminders.filter(\.enabled),
                logged: Set(parameters["logged_meals"] as? [String] ?? []),
                requestPermission: parameters["request_permission"] as? Bool ?? true
            )

            return BridgeResponse.success(data: ["status": status, "scheduled": reminders.filter(\.enabled).count])
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

        let request = response.notification.request
        guard let route = request.content.userInfo["notification_url"] as? String,
              var link = URLComponents(string: "buff:\(route)") else {
            return
        }

        if request.identifier.hasPrefix("buff-meal-reminder-") {
            let components = reminderCalendar().dateComponents([.year, .month, .day], from: response.notification.date)
            if let date = reminderDateKey(components) {
                link.queryItems = (link.queryItems ?? []) + [URLQueryItem(name: "date", value: date)]
            }
        }

        guard let url = link.url else {
            return
        }

        DispatchQueue.main.async {
            DeepLinkRouter.shared.handle(url: url)
        }
    }
}

private struct MealReminder: Sendable {
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

private func reminderCalendar() -> Calendar {
    var calendar = Calendar(identifier: .gregorian)
    calendar.timeZone = .autoupdatingCurrent
    return calendar
}

private func reminderDateKey(_ date: DateComponents) -> String? {
    guard let year = date.year, let month = date.month, let day = date.day else {
        return nil
    }
    return String(format: "%04d-%02d-%02d", year, month, day)
}

private struct MealReminderRequest: Equatable {
    let meal: String
    let identifier: String
    let components: DateComponents
    let repeats: Bool
}

private final class MealReminderScheduler: @unchecked Sendable {
    static let shared = MealReminderScheduler()

    private let queue = DispatchQueue(label: "com.buff.meal-reminders")
    private var lastPlan: [MealReminderRequest]?
    private var pendingPermission: (reminders: [MealReminder], logged: Set<String>)?
    private var requestingPermission = false

    func sync(_ reminders: [MealReminder], logged: Set<String>, requestPermission: Bool) throws -> String {
        try queue.sync {
            let center = UNUserNotificationCenter.current()

            if reminders.isEmpty {
                pendingPermission = nil
                try install([], using: center)
                return "disabled"
            }

            switch try authorizationStatus(center) {
            case .notDetermined:
                pendingPermission = (reminders, logged)
                if requestPermission && !requestingPermission {
                    requestAuthorization(using: center)
                }
                return requestingPermission ? "permission_requested" : "notifications_disabled"
            case .denied:
                pendingPermission = nil
                try install([], using: center)
                return "notifications_disabled"
            default:
                pendingPermission = nil
                try install(plan(reminders, logged: logged), using: center)
                return "scheduled"
            }
        }
    }

    private func requestAuthorization(using center: UNUserNotificationCenter) {
        requestingPermission = true
        center.requestAuthorization(options: [.alert, .sound]) { granted, error in
            self.queue.async {
                let pending = self.pendingPermission
                self.pendingPermission = nil
                self.requestingPermission = false

                if let error {
                    NSLog("BuffMealReminders: notification permission failed: %@", error.localizedDescription)
                }

                if granted, let pending {
                    do {
                        try self.install(self.plan(pending.reminders, logged: pending.logged), using: center)
                    } catch {
                        NSLog("BuffMealReminders: scheduling failed: %@", error.localizedDescription)
                    }
                }
            }
        }
    }

    private func plan(_ reminders: [MealReminder], logged: Set<String>) -> [MealReminderRequest] {
        let now = Date()
        let calendar = reminderCalendar()
        let today = calendar.startOfDay(for: now)

        return reminders.flatMap { reminder -> [MealReminderRequest] in
            let meal = reminder.id
            let base = "buff-meal-reminder-\(meal)"
            let occurrences = (0..<reminderDays).compactMap { offset -> (offset: Int, date: DateComponents, weekday: Int, logged: Bool)? in
                guard let day = calendar.date(byAdding: .day, value: offset, to: today),
                      let fire = calendar.date(bySettingHour: reminder.hour, minute: reminder.minute, second: 0, of: day),
                      calendar.isDate(fire, inSameDayAs: day), fire > now else {
                    return nil
                }

                var date = calendar.dateComponents([.year, .month, .day, .hour, .minute, .weekday], from: fire)
                date.calendar = calendar
                guard let day = reminderDateKey(date), let weekday = date.weekday else {
                    return nil
                }
                return (offset, date, weekday, logged.contains("\(day):\(meal)"))
            }
            let excludedWeekdays = Set(occurrences.filter(\.logged).map(\.weekday))
            let time = DateComponents(hour: reminder.hour, minute: reminder.minute)

            if excludedWeekdays.isEmpty {
                return [MealReminderRequest(meal: meal, identifier: base, components: time, repeats: true)]
            }

            let weeklyRequests = (1...7).filter { !excludedWeekdays.contains($0) }.map { weekday in
                var components = time
                components.weekday = weekday
                return MealReminderRequest(meal: meal, identifier: "\(base)-weekday-\(weekday)", components: components, repeats: true)
            }
            // shortcut: iOS cannot skip an occurrence of a repeating trigger. Use dated requests for affected weekdays until Buff opens again.
            let datedRequests = occurrences.filter { excludedWeekdays.contains($0.weekday) && !$0.logged }.map {
                MealReminderRequest(meal: meal, identifier: "\(base)-day-\($0.offset)", components: $0.date, repeats: false)
            }
            return weeklyRequests + datedRequests
        }
    }

    private func install(_ plan: [MealReminderRequest], using center: UNUserNotificationCenter) throws {
        guard lastPlan != plan else {
            return
        }
        lastPlan = nil
        let active = Set(plan.map(\.identifier))
        center.removePendingNotificationRequests(withIdentifiers: reminderIdentifiers.filter { !active.contains($0) })

        for entry in plan {
            let content = UNMutableNotificationContent()
            let label = entry.meal.prefix(1).uppercased() + entry.meal.dropFirst()
            content.title = "\(label) reminder"
            content.body = "Time to log your \(entry.meal) in Buff."
            content.sound = .default
            content.userInfo = ["notification_url": "/add?mode=food&meal=\(entry.meal)&reminder=1"]

            let trigger = UNCalendarNotificationTrigger(dateMatching: entry.components, repeats: entry.repeats)
            let request = UNNotificationRequest(identifier: entry.identifier, content: content, trigger: trigger)
            let semaphore = DispatchSemaphore(value: 0)
            var schedulingError: Error?

            center.add(request) { error in
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

        lastPlan = plan
    }
}
