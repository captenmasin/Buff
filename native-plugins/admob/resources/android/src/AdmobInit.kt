package com.blessedzulu.nativephp.admob

import android.content.Context
import android.os.Bundle
import com.google.ads.mediation.admob.AdMobAdapter
import com.google.android.gms.ads.AdRequest
import com.google.android.gms.ads.MobileAds
import com.google.android.gms.ads.RequestConfiguration

/** Process-wide policy and explicit Mobile Ads initialization gate. */
object AdmobInit {
    @Volatile
    private var policyConfigured = false

    @Volatile
    private var initialized = false

    @Volatile
    private var underAgeOfConsent = true

    @Volatile
    private var nonPersonalized = true

    @JvmStatic
    @Synchronized
    fun configurePolicy(
        underAgeOfConsent: Boolean,
        nonPersonalized: Boolean,
        maxContentRating: String,
    ): Boolean {
        if (maxContentRating != "T") {
            return false
        }

        val requestConfiguration = MobileAds.getRequestConfiguration().toBuilder()
            .setTagForChildDirectedTreatment(RequestConfiguration.TAG_FOR_CHILD_DIRECTED_TREATMENT_FALSE)
            .setTagForUnderAgeOfConsent(
                if (underAgeOfConsent) {
                    RequestConfiguration.TAG_FOR_UNDER_AGE_OF_CONSENT_TRUE
                } else {
                    RequestConfiguration.TAG_FOR_UNDER_AGE_OF_CONSENT_FALSE
                },
            )
            .setMaxAdContentRating(RequestConfiguration.MAX_AD_CONTENT_RATING_T)
            .build()

        MobileAds.setRequestConfiguration(requestConfiguration)
        this.underAgeOfConsent = underAgeOfConsent
        this.nonPersonalized = nonPersonalized
        policyConfigured = true

        return true
    }

    @JvmStatic
    @Synchronized
    fun initialize(context: Context): Boolean {
        if (initialized) {
            return true
        }

        if (!policyConfigured || !ConsentManager.info(context).canRequestAds()) {
            return false
        }

        MobileAds.initialize(context.applicationContext) { }
        BannerLifecycle.register()
        initialized = true

        return true
    }

    fun isPolicyConfigured(): Boolean = policyConfigured

    fun underAgeOfConsent(): Boolean = underAgeOfConsent

    fun canLoadBanner(context: Context): Boolean =
        policyConfigured && initialized && ConsentManager.info(context).canRequestAds()

    /** One request factory keeps the non-personalized flag on every banner refresh. */
    fun bannerRequest(): AdRequest {
        val builder = AdRequest.Builder()

        if (nonPersonalized) {
            builder.addNetworkExtrasBundle(
                AdMobAdapter::class.java,
                Bundle().apply { putString("npa", "1") },
            )
        }

        return builder.build()
    }
}
