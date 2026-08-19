import Foundation
import Security

enum AppleHealthPHP {
    private static let lock = NSLock()

    static func artisan(_ command: String) -> String {
        lock.lock()
        defer { lock.unlock() }

        if PersistentPHPRuntime.shared.isBooted {
            return PersistentPHPRuntime.shared.artisan(command: command)
        }

        return runEphemeral(command)
    }

    private static func runEphemeral(_ command: String) -> String {
        prepareEnvironment()

        let appPath = AppUpdateManager.shared.getAppPath()
        let bootstrapPath = appPath + "/vendor/nativephp/mobile/bootstrap/ios/persistent.php"

        if _ephemeral_php_boot(bootstrapPath) != 0 {
            return "Could not boot NativePHP ephemeral runtime."
        }

        defer { _ephemeral_php_shutdown() }

        guard let resultPtr = _ephemeral_php_artisan(command) else {
            return ""
        }

        let result = String(cString: resultPtr)
        free(UnsafeMutableRawPointer(mutating: resultPtr))
        return result
    }

    private static func prepareEnvironment() {
        let appPath = AppUpdateManager.shared.getAppPath()
        let storageDir = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first!
        let tempDir = FileManager.default.temporaryDirectory.path
        let databaseDir = storageDir.appendingPathComponent("database").path

        setenv("LARAVEL_STORAGE_PATH", storageDir.appendingPathComponent("storage").path, 1)
        setenv("VIEW_COMPILED_PATH", storageDir.appendingPathComponent("storage/framework/views").path, 1)
        setenv("DB_DATABASE", "\(databaseDir)/database.sqlite", 1)
        setenv("NATIVEPHP_TEMPDIR", tempDir, 1)
        setenv("NATIVEPHP_PLATFORM", "ios", 1)
        setenv("REMOTE_ADDR", "0.0.0.0", 1)
        setenv("COMPOSER_AUTOLOADER_PATH", appPath + "/vendor/autoload.php", 1)
        setenv("LARAVEL_BOOTSTRAP_PATH", appPath + "/bootstrap", 1)

        if let appKey = appKey() {
            setenv("APP_KEY", appKey, 1)
        }
    }

    private static func appKey() -> String? {
        let service = Bundle.main.bundleIdentifier ?? "com.nativephp.app"
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: "APP_KEY",
            kSecAttrService as String: service,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne
        ]
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)

        if status == errSecSuccess, let data = result as? Data {
            return String(data: data, encoding: .utf8)
        }

        return nil
    }
}

@_silgen_name("ephemeral_php_boot")
private func _ephemeral_php_boot(_ bootstrapPath: UnsafePointer<CChar>?) -> Int32

@_silgen_name("ephemeral_php_artisan")
private func _ephemeral_php_artisan(_ command: UnsafePointer<CChar>?) -> UnsafePointer<CChar>?

@_silgen_name("ephemeral_php_shutdown")
private func _ephemeral_php_shutdown()
