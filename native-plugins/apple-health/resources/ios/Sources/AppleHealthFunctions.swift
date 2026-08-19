import Foundation

enum AppleHealthFunctions {
    class Status: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return BridgeResponse.success(data: AppleHealthPlugin.status())
        }
    }

    class RequestPermissions: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if !AppleHealthPlugin.isAvailable() {
                return BridgeResponse.success(data: [
                    "supported": true,
                    "available": false,
                    "status": "unavailable",
                    "message": "Apple Health is not available on this device."
                ])
            }

            if AppleHealthPlugin.hasRequestedAuthorization() {
                AppleHealthPlugin.enqueueImmediateSync()

                return BridgeResponse.success(data: AppleHealthPlugin.status() + [
                    "status": "connected",
                    "message": "Apple Health is connected."
                ])
            }

            AppleHealthPlugin.requestAuthorization()

            return BridgeResponse.success(data: [
                "supported": true,
                "available": true,
                "status": "permission_requested"
            ])
        }
    }

    class SyncNow: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if !AppleHealthPlugin.hasRequestedAuthorization() {
                return BridgeResponse.success(data: [
                    "supported": true,
                    "available": AppleHealthPlugin.isAvailable(),
                    "has_permissions": false,
                    "status": "permission_required"
                ])
            }

            AppleHealthPlugin.enqueueImmediateSync()

            return BridgeResponse.success(data: [
                "supported": true,
                "available": true,
                "has_permissions": true,
                "status": "sync_queued"
            ])
        }
    }
}

private func + (lhs: [String: Any], rhs: [String: Any]) -> [String: Any] {
    var merged = lhs
    for (key, value) in rhs {
        merged[key] = value
    }
    return merged
}
