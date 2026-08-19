import Foundation
import HealthKit

enum AppleHealthPlugin {
    private static let requestedAuthorizationKey = "buff-apple-health-requested"
    private static let syncQueue = DispatchQueue(label: "com.buff.apple-health.sync")
    private static let store = HKHealthStore()
    private static var observerQuery: HKObserverQuery?
    private static var syncQueued = false

    static func isAvailable() -> Bool {
        HKHealthStore.isHealthDataAvailable()
    }

    static func hasRequestedAuthorization() -> Bool {
        isAvailable() && UserDefaults.standard.bool(forKey: requestedAuthorizationKey)
    }

    static func status() -> [String: Any] {
        let available = isAvailable()
        let requested = hasRequestedAuthorization()

        return [
            "supported": true,
            "available": available,
            "has_permissions": requested,
            "foreground_granted": requested,
            "background_granted": requested,
            "background_available": available,
            "status": statusName(available: available, requested: requested)
        ]
    }

    static func requestAuthorization() {
        guard isAvailable() else {
            return
        }

        DispatchQueue.main.async {
            store.requestAuthorization(toShare: nil, read: readTypes) { success, error in
                if let error {
                    NSLog("BuffAppleHealth: authorization failed: %@", error.localizedDescription)
                    return
                }

                if !success {
                    NSLog("BuffAppleHealth: authorization was not completed.")
                    return
                }

                UserDefaults.standard.set(true, forKey: requestedAuthorizationKey)
                startObserving()
                enqueueImmediateSync()
            }
        }
    }

    static func startObserving() {
        guard isAvailable(), hasRequestedAuthorization() else {
            return
        }

        enableBackgroundDelivery()

        if observerQuery != nil {
            return
        }

        let query = HKObserverQuery(sampleType: HKObjectType.workoutType(), predicate: nil) { _, completionHandler, error in
            if let error {
                NSLog("BuffAppleHealth: observer failed: %@", error.localizedDescription)
                completionHandler()
                return
            }

            enqueueImmediateSync()
            completionHandler()
        }

        observerQuery = query
        store.execute(query)
        enqueueImmediateSync()
    }

    static func enqueueImmediateSync() {
        syncQueue.async {
            if syncQueued {
                return
            }

            syncQueued = true
            defer { syncQueued = false }

            do {
                try syncNow()
            } catch {
                NSLog("BuffAppleHealth: sync failed: %@", error.localizedDescription)
            }
        }
    }

    private static func enableBackgroundDelivery() {
        store.enableBackgroundDelivery(for: HKObjectType.workoutType(), frequency: .immediate) { success, error in
            if let error {
                NSLog("BuffAppleHealth: background delivery failed: %@", error.localizedDescription)
                return
            }

            if !success {
                NSLog("BuffAppleHealth: background delivery was not enabled.")
            }
        }
    }

    private static func syncNow() throws {
        guard hasRequestedAuthorization() else {
            return
        }

        let payload = try readPayload()
        let file = FileManager.default.urls(for: .cachesDirectory, in: .userDomainMask).first!
            .appendingPathComponent("buff-apple-health-\(Int(Date().timeIntervalSince1970 * 1000)).json")
        try JSONSerialization.data(withJSONObject: payload, options: []).write(to: file)

        defer { try? FileManager.default.removeItem(at: file) }

        let output = AppleHealthPHP.artisan("apple-health:import --payload=\(file.path)")
        NSLog("BuffAppleHealth: Import output: %@", String(output.prefix(300)))

        if !output.contains("BUFF_APPLE_HEALTH_IMPORT_OK") {
            throw AppleHealthError.importFailed(output.trimmingCharacters(in: .whitespacesAndNewlines))
        }
    }

    private static func readPayload() throws -> [String: Any] {
        let end = Date()
        let start = Calendar.current.date(byAdding: .day, value: -30, to: end) ?? end.addingTimeInterval(-30 * 24 * 60 * 60)
        let workouts = try queryWorkouts(start: start, end: end)
        var records: [[String: Any]] = []
        var workoutsRead = 0
        var workoutsWithCalories = 0

        for workout in workouts {
            workoutsRead += 1

            guard let record = workoutJSON(workout) else {
                continue
            }

            workoutsWithCalories += 1
            records.append(record)
        }

        NSLog(
            "BuffAppleHealth: Read %d Apple Health workouts; importing %d with calories.",
            workoutsRead,
            workoutsWithCalories
        )

        return [
            "synced_at": iso8601(Date()),
            "window_start": iso8601(start),
            "window_end": iso8601(end),
            "records": records
        ]
    }

    private static func queryWorkouts(start: Date, end: Date) throws -> [HKWorkout] {
        let semaphore = DispatchSemaphore(value: 0)
        var workouts: [HKWorkout] = []
        var queryError: Error?

        let query = HKSampleQuery(
            sampleType: HKObjectType.workoutType(),
            predicate: HKQuery.predicateForSamples(withStart: start, end: end, options: .strictStartDate),
            limit: HKObjectQueryNoLimit,
            sortDescriptors: [NSSortDescriptor(key: HKSampleSortIdentifierStartDate, ascending: true)]
        ) { _, samples, error in
            queryError = error
            workouts = (samples as? [HKWorkout]) ?? []
            semaphore.signal()
        }

        store.execute(query)

        if semaphore.wait(timeout: .now() + 30) == .timedOut {
            throw AppleHealthError.queryTimedOut
        }

        if let queryError {
            throw queryError
        }

        return workouts
    }

    private static func workoutJSON(_ workout: HKWorkout) -> [String: Any]? {
        let calories = caloriesForWorkout(workout)

        guard calories >= 1 else {
            NSLog(
                "BuffAppleHealth: Skipping workout %@ from %@; no positive calories found.",
                workout.uuid.uuidString,
                workout.sourceRevision.source.bundleIdentifier
            )
            return nil
        }

        let sourcePackage = workout.sourceRevision.source.bundleIdentifier
        let sourceName = workout.sourceRevision.source.name

        return [
            "external_id": workout.uuid.uuidString,
            "title": workoutTitle(workout),
            "calories_burned": calories,
            "date": localDate(workout.startDate),
            "started_at": iso8601(workout.startDate),
            "ended_at": iso8601(workout.endDate),
            "duration_seconds": Int(workout.duration.rounded()),
            "source_name": sourceName,
            "source_package": sourcePackage
        ]
    }

    private static func caloriesForWorkout(_ workout: HKWorkout) -> Int {
        if let energy = workout.totalEnergyBurned {
            return Int(energy.doubleValue(for: .kilocalorie()).rounded())
        }

        if let statistics = workout.statistics(for: HKQuantityType(.activeEnergyBurned)),
           let energy = statistics.sumQuantity() {
            return Int(energy.doubleValue(for: .kilocalorie()).rounded())
        }

        return queryActiveEnergy(start: workout.startDate, end: workout.endDate)
    }

    private static func queryActiveEnergy(start: Date, end: Date) -> Int {
        guard let energyType = HKQuantityType.quantityType(forIdentifier: .activeEnergyBurned) else {
            return 0
        }

        let semaphore = DispatchSemaphore(value: 0)
        var calories = 0

        let query = HKStatisticsQuery(
            quantityType: energyType,
            quantitySamplePredicate: HKQuery.predicateForSamples(withStart: start, end: end, options: .strictStartDate),
            options: .cumulativeSum
        ) { _, statistics, _ in
            if let energy = statistics?.sumQuantity() {
                calories = Int(energy.doubleValue(for: .kilocalorie()).rounded())
            }
            semaphore.signal()
        }

        store.execute(query)
        _ = semaphore.wait(timeout: .now() + 10)

        return calories
    }

    private static func workoutTitle(_ workout: HKWorkout) -> String {
        if let name = workout.workoutActivityType.appleHealthTitle {
            return name
        }

        return "Apple Health workout"
    }

    private static func iso8601(_ date: Date) -> String {
        ISO8601DateFormatter().string(from: date)
    }

    private static func localDate(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.calendar = Calendar.current
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone.current
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter.string(from: date)
    }

    private static func statusName(available: Bool, requested: Bool) -> String {
        if !available {
            return "unavailable"
        }

        if !requested {
            return "permission_required"
        }

        return "connected"
    }

    private static var readTypes: Set<HKObjectType> {
        var types: Set<HKObjectType> = [HKObjectType.workoutType()]

        if let activeEnergy = HKQuantityType.quantityType(forIdentifier: .activeEnergyBurned) {
            types.insert(activeEnergy)
        }

        if let basalEnergy = HKQuantityType.quantityType(forIdentifier: .basalEnergyBurned) {
            types.insert(basalEnergy)
        }

        return types
    }
}

private enum AppleHealthError: LocalizedError {
    case queryTimedOut
    case importFailed(String)

    var errorDescription: String? {
        switch self {
        case .queryTimedOut:
            return "Apple Health query timed out."
        case .importFailed(let output):
            return output.isEmpty ? "Apple Health import did not report success." : output
        }
    }
}

private extension HKWorkoutActivityType {
    var appleHealthTitle: String? {
        switch self {
        case .cycling, .handCycling:
            return "Cycling"
        case .running:
            return "Run"
        case .traditionalStrengthTraining, .functionalStrengthTraining:
            return "Strength training"
        case .walking:
            return "Walk"
        case .swimming:
            return "Swim"
        case .hiking:
            return "Hike"
        case .yoga:
            return "Yoga"
        case .coreTraining:
            return "Core"
        case .elliptical:
            return "Elliptical"
        case .rowing:
            return "Row"
        case .stairClimbing, .stairs:
            return "Stairs"
        case .highIntensityIntervalTraining:
            return "HIIT"
        case .mixedCardio, .cardioDance:
            return "Cardio"
        @unknown default:
            return nil
        }
    }
}
