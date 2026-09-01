import Foundation
import RevenueCat

private enum SubscriptionEvent {
    static let configurationCompleted = "Buff\\InAppPurchases\\Events\\ConfigurationCompleted"
    static let configurationFailed = "Buff\\InAppPurchases\\Events\\ConfigurationFailed"
    static let offeringLoaded = "Buff\\InAppPurchases\\Events\\OfferingLoaded"
    static let offeringFailed = "Buff\\InAppPurchases\\Events\\OfferingFailed"
    static let purchaseCompleted = "Buff\\InAppPurchases\\Events\\PurchaseCompleted"
    static let purchaseCancelled = "Buff\\InAppPurchases\\Events\\PurchaseCancelled"
    static let purchasePending = "Buff\\InAppPurchases\\Events\\PurchasePending"
    static let purchaseFailed = "Buff\\InAppPurchases\\Events\\PurchaseFailed"
    static let restoreCompleted = "Buff\\InAppPurchases\\Events\\RestoreCompleted"
    static let restoreFailed = "Buff\\InAppPurchases\\Events\\RestoreFailed"
}

@MainActor
private enum SubscriptionState {
    static var packages: [String: Package] = [:]
}

enum SubscriptionsFunctions {
    class Configure: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let apiKey = parameters["api_key"] as? String,
                  apiKey.range(of: #"^(appl|test)_[A-Za-z0-9]+$"#, options: .regularExpression) != nil else {
                throw BridgeError.invalidParameters("api_key must be an iOS or Test Store public SDK key")
            }

            guard let appUserID = parameters["app_user_id"] as? String,
                  UUID(uuidString: appUserID) != nil else {
                throw BridgeError.invalidParameters("app_user_id must be a UUID")
            }

            if !Purchases.isConfigured {
                Purchases.configure(withAPIKey: apiKey, appUserID: appUserID)

                return BridgeResponse.success(data: ["configured": true, "switching_account": false])
            }

            guard Purchases.shared.appUserID != appUserID else {
                return BridgeResponse.success(data: ["configured": true, "switching_account": false])
            }

            Task { @MainActor in
                do {
                    _ = try await Purchases.shared.logIn(appUserID)
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.configurationCompleted,
                        ["app_user_id": appUserID]
                    )
                } catch {
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.configurationFailed,
                        [
                            "app_user_id": appUserID,
                            "category": "identity",
                            "message": error.localizedDescription,
                        ]
                    )
                }
            }

            return BridgeResponse.success(data: ["started": true, "switching_account": true])
        }
    }

    class LoadOffering: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard Purchases.isConfigured else {
                SubscriptionPayload.dispatch(
                    SubscriptionEvent.offeringFailed,
                    ["category": "not_configured", "message": "Subscriptions are not configured."]
                )

                return BridgeResponse.success(data: ["started": false])
            }

            Task { @MainActor in
                do {
                    guard let offering = try await Purchases.shared.offerings().current else {
                        SubscriptionPayload.dispatch(
                            SubscriptionEvent.offeringFailed,
                            ["category": "unavailable", "message": "No subscription offering is available."]
                        )
                        return
                    }

                    let packages = offering.availablePackages.filter {
                        $0.packageType == .monthly || $0.packageType == .annual
                    }
                    SubscriptionState.packages = Dictionary(uniqueKeysWithValues: packages.map { ($0.identifier, $0) })
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.offeringLoaded,
                        ["packages": packages.map(SubscriptionPayload.package)]
                    )
                } catch {
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.offeringFailed,
                        ["category": "offerings", "message": error.localizedDescription]
                    )
                }
            }

            return BridgeResponse.success(data: ["started": true])
        }
    }

    class Purchase: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let packageIdentifier = parameters["package_identifier"] as? String,
                  !packageIdentifier.isEmpty else {
                throw BridgeError.invalidParameters("package_identifier is required")
            }

            guard Purchases.isConfigured else {
                throw BridgeError.executionFailed("Subscriptions are not configured")
            }

            Task { @MainActor in
                guard let package = SubscriptionState.packages[packageIdentifier] else {
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.purchaseFailed,
                        [
                            "category": "unavailable",
                            "message": "The selected subscription is unavailable.",
                            "package_identifier": packageIdentifier,
                        ]
                    )
                    return
                }

                do {
                    let result = try await Purchases.shared.purchase(package: package)

                    if result.userCancelled {
                        SubscriptionPayload.dispatch(
                            SubscriptionEvent.purchaseCancelled,
                            ["package_identifier": packageIdentifier, "category": "cancelled"]
                        )
                        return
                    }

                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.purchaseCompleted,
                        [
                            "package_identifier": packageIdentifier,
                            "product_identifier": package.storeProduct.productIdentifier,
                            "entitled": SubscriptionPayload.isEntitled(result.customerInfo),
                        ]
                    )
                } catch {
                    let code = ErrorCode(rawValue: (error as NSError).code)

                    if code == .purchaseCancelledError {
                        SubscriptionPayload.dispatch(
                            SubscriptionEvent.purchaseCancelled,
                            ["package_identifier": packageIdentifier, "category": "cancelled"]
                        )
                    } else if code == .paymentPendingError {
                        SubscriptionPayload.dispatch(
                            SubscriptionEvent.purchasePending,
                            [
                                "package_identifier": packageIdentifier,
                                "product_identifier": package.storeProduct.productIdentifier,
                                "category": "pending",
                            ]
                        )
                    } else {
                        SubscriptionPayload.dispatch(
                            SubscriptionEvent.purchaseFailed,
                            [
                                "category": "purchase",
                                "message": error.localizedDescription,
                                "package_identifier": packageIdentifier,
                            ]
                        )
                    }
                }
            }

            return BridgeResponse.success(data: ["started": true])
        }
    }

    class Restore: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard Purchases.isConfigured else {
                throw BridgeError.executionFailed("Subscriptions are not configured")
            }

            Task { @MainActor in
                do {
                    let customerInfo = try await Purchases.shared.restorePurchases()
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.restoreCompleted,
                        ["entitled": SubscriptionPayload.isEntitled(customerInfo)]
                    )
                } catch {
                    SubscriptionPayload.dispatch(
                        SubscriptionEvent.restoreFailed,
                        ["category": "restore", "message": error.localizedDescription]
                    )
                }
            }

            return BridgeResponse.success(data: ["started": true])
        }
    }

    class CustomerInfo: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard Purchases.isConfigured else {
                return BridgeResponse.success(data: ["configured": false, "entitled": false])
            }

            return BridgeResponse.success(data: [
                "configured": true,
                "entitled": Purchases.shared.cachedCustomerInfo.map(SubscriptionPayload.isEntitled) ?? false,
            ])
        }
    }
}

private enum SubscriptionPayload {
    static func dispatch(_ event: String, _ payload: [String: Any]) {
        DispatchQueue.main.async {
            LaravelBridge.shared.send?(event, payload)
        }
    }

    static func isEntitled(_ customerInfo: RevenueCat.CustomerInfo) -> Bool {
        customerInfo.entitlements.active["buff_plus"] != nil
    }

    static func package(_ package: Package) -> [String: Any] {
        let product = package.storeProduct
        var payload: [String: Any] = [
            "package_identifier": package.identifier,
            "product_identifier": product.productIdentifier,
            "localized_price": product.localizedPriceString,
            "localized_period": localizedPeriod(product.subscriptionPeriod) ?? "",
        ]

        if let discount = product.introductoryDiscount {
            payload["introductory_offer"] = [
                "localized_price": discount.localizedPriceString,
                "localized_period": localizedPeriod(discount.subscriptionPeriod) ?? "",
                "period_count": discount.numberOfPeriods,
                "payment_mode": paymentMode(discount.paymentMode),
                "is_free_trial": discount.paymentMode == .freeTrial,
            ]
        } else {
            payload["introductory_offer"] = NSNull()
        }

        return payload
    }

    private static func localizedPeriod(_ period: SubscriptionPeriod?) -> String? {
        guard let period else {
            return nil
        }

        var components = DateComponents()
        let unit: NSCalendar.Unit

        switch period.unit {
        case .day:
            components.day = period.value
            unit = .day
        case .week:
            components.weekOfMonth = period.value
            unit = .weekOfMonth
        case .month:
            components.month = period.value
            unit = .month
        case .year:
            components.year = period.value
            unit = .year
        @unknown default:
            return nil
        }

        let formatter = DateComponentsFormatter()
        formatter.allowedUnits = unit
        formatter.unitsStyle = .full

        return formatter.string(from: components)
    }

    private static func paymentMode(_ mode: StoreProductDiscount.PaymentMode) -> String {
        switch mode {
        case .payAsYouGo:
            return "pay_as_you_go"
        case .payUpFront:
            return "pay_up_front"
        case .freeTrial:
            return "free_trial"
        @unknown default:
            return "unknown"
        }
    }
}
