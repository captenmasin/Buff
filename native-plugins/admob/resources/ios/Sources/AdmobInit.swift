import Foundation
import GoogleMobileAds
import UserMessagingPlatform

/// Process-wide policy and explicit Mobile Ads initialization gate.
enum AdmobInit {
    private(set) static var isPolicyConfigured = false
    private static var initialized = false
    private(set) static var underAgeOfConsent = true
    private static var nonPersonalized = true

    static func configurePolicy(
        underAgeOfConsent: Bool,
        nonPersonalized: Bool,
        maxContentRating: String
    ) -> Bool {
        guard maxContentRating == "T" else { return false }

        let configuration = MobileAds.shared.requestConfiguration
        configuration.tagForChildDirectedTreatment = false
        configuration.tagForUnderAgeOfConsent = NSNumber(value: underAgeOfConsent)
        configuration.maxAdContentRating = GADMaxAdContentRating.teen

        self.underAgeOfConsent = underAgeOfConsent
        self.nonPersonalized = nonPersonalized
        isPolicyConfigured = true

        return true
    }

    static func initialize() -> Bool {
        if initialized { return true }
        guard isPolicyConfigured, ConsentManager.info.canRequestAds else { return false }

        initialized = true
        Task {
            _ = await MobileAds.shared.start()
        }
        BannerLifecycle.register()

        return true
    }

    static func canLoadBanner() -> Bool {
        return isPolicyConfigured && initialized && ConsentManager.info.canRequestAds
    }

    /// One request factory keeps the non-personalized flag on every banner refresh.
    static func bannerRequest() -> Request {
        let request = Request()

        if nonPersonalized {
            let extras = Extras()
            extras.additionalParameters = ["npa": "1"]
            request.register(extras)
        }

        return request
    }
}
