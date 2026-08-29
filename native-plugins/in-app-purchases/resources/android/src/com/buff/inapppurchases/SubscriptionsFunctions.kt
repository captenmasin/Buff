package com.buff.inapppurchases

import android.content.Context
import android.icu.text.MeasureFormat
import android.icu.util.Measure
import android.icu.util.MeasureUnit
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeError
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.utils.NativeActionCoordinator
import com.revenuecat.purchases.CustomerInfo
import com.revenuecat.purchases.Package
import com.revenuecat.purchases.PackageType
import com.revenuecat.purchases.PurchaseParams
import com.revenuecat.purchases.Purchases
import com.revenuecat.purchases.PurchasesConfiguration
import com.revenuecat.purchases.PurchasesErrorCode
import com.revenuecat.purchases.awaitCustomerInfo
import com.revenuecat.purchases.getOfferingsWith
import com.revenuecat.purchases.logInWith
import com.revenuecat.purchases.models.Period
import com.revenuecat.purchases.models.PricingPhase
import com.revenuecat.purchases.purchaseWith
import com.revenuecat.purchases.restorePurchasesWith
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import org.json.JSONObject
import java.util.Locale
import java.util.UUID

private object SubscriptionEvent {
    const val OFFERING_LOADED = "Buff\\InAppPurchases\\Events\\OfferingLoaded"
    const val OFFERING_FAILED = "Buff\\InAppPurchases\\Events\\OfferingFailed"
    const val PURCHASE_COMPLETED = "Buff\\InAppPurchases\\Events\\PurchaseCompleted"
    const val PURCHASE_CANCELLED = "Buff\\InAppPurchases\\Events\\PurchaseCancelled"
    const val PURCHASE_PENDING = "Buff\\InAppPurchases\\Events\\PurchasePending"
    const val PURCHASE_FAILED = "Buff\\InAppPurchases\\Events\\PurchaseFailed"
    const val RESTORE_COMPLETED = "Buff\\InAppPurchases\\Events\\RestoreCompleted"
    const val RESTORE_FAILED = "Buff\\InAppPurchases\\Events\\RestoreFailed"
}

private object SubscriptionState {
    val packages = mutableMapOf<String, Package>()
}

object SubscriptionsFunctions {
    class Configure(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val apiKey = parameters["api_key"] as? String
            if (apiKey == null || !Regex("^(goog|test)_[A-Za-z0-9]+$").matches(apiKey)) {
                throw BridgeError.InvalidParameters("api_key must be an Android or Test Store public SDK key")
            }

            val appUserID = parameters["app_user_id"] as? String
            if (appUserID == null || runCatching { UUID.fromString(appUserID) }.isFailure) {
                throw BridgeError.InvalidParameters("app_user_id must be a UUID")
            }

            if (!Purchases.isConfigured) {
                Purchases.configure(
                    PurchasesConfiguration.Builder(context, apiKey)
                        .appUserID(appUserID)
                        .build(),
                )

                return BridgeResponse.success(mapOf("configured" to true, "switching_account" to false))
            }

            if (Purchases.sharedInstance.appUserID == appUserID) {
                return BridgeResponse.success(mapOf("configured" to true, "switching_account" to false))
            }

            CoroutineScope(Dispatchers.Main).launch {
                Purchases.sharedInstance.logInWith(
                    appUserID = appUserID,
                    onSuccess = { _, _ -> },
                    onError = { },
                )
            }

            return BridgeResponse.success(mapOf("configured" to true, "switching_account" to true))
        }
    }

    class LoadOffering(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (!Purchases.isConfigured) {
                SubscriptionPayload.dispatch(
                    activity,
                    SubscriptionEvent.OFFERING_FAILED,
                    mapOf("category" to "not_configured", "message" to "Subscriptions are not configured."),
                )

                return BridgeResponse.success(mapOf("started" to false))
            }

            CoroutineScope(Dispatchers.Main).launch {
                Purchases.sharedInstance.getOfferingsWith(
                    onSuccess = { offerings ->
                        val packages = offerings.current?.availablePackages
                            ?.filter { it.packageType == PackageType.MONTHLY || it.packageType == PackageType.ANNUAL }
                            .orEmpty()

                        if (packages.isEmpty()) {
                            SubscriptionPayload.dispatch(
                                activity,
                                SubscriptionEvent.OFFERING_FAILED,
                                mapOf("category" to "unavailable", "message" to "No subscription offering is available."),
                            )
                            return@getOfferingsWith
                        }

                        SubscriptionState.packages.clear()
                        SubscriptionState.packages.putAll(packages.associateBy { it.identifier })
                        SubscriptionPayload.dispatch(
                            activity,
                            SubscriptionEvent.OFFERING_LOADED,
                            mapOf("packages" to packages.map(SubscriptionPayload::packagePayload)),
                        )
                    },
                    onError = { error ->
                        SubscriptionPayload.dispatch(
                            activity,
                            SubscriptionEvent.OFFERING_FAILED,
                            mapOf("category" to "offerings", "message" to error.message),
                        )
                    },
                )
            }

            return BridgeResponse.success(mapOf("started" to true))
        }
    }

    class Purchase(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val packageIdentifier = parameters["package_identifier"] as? String
            if (packageIdentifier.isNullOrBlank()) {
                throw BridgeError.InvalidParameters("package_identifier is required")
            }
            if (!Purchases.isConfigured) {
                throw BridgeError.ExecutionFailed("Subscriptions are not configured")
            }

            CoroutineScope(Dispatchers.Main).launch {
                val packageToPurchase = SubscriptionState.packages[packageIdentifier]
                if (packageToPurchase == null) {
                    SubscriptionPayload.dispatch(
                        activity,
                        SubscriptionEvent.PURCHASE_FAILED,
                        mapOf(
                            "category" to "unavailable",
                            "message" to "The selected subscription is unavailable.",
                            "package_identifier" to packageIdentifier,
                        ),
                    )
                    return@launch
                }

                Purchases.sharedInstance.purchaseWith(
                    purchaseParams = PurchaseParams.Builder(activity, packageToPurchase).build(),
                    onSuccess = { _, customerInfo ->
                        SubscriptionPayload.dispatch(
                            activity,
                            SubscriptionEvent.PURCHASE_COMPLETED,
                            mapOf(
                                "package_identifier" to packageIdentifier,
                                "product_identifier" to SubscriptionPayload.productIdentifier(packageToPurchase),
                                "entitled" to SubscriptionPayload.isEntitled(customerInfo),
                            ),
                        )
                    },
                    onError = { error, userCancelled ->
                        when {
                            userCancelled || error.code == PurchasesErrorCode.PurchaseCancelledError -> {
                                SubscriptionPayload.dispatch(
                                    activity,
                                    SubscriptionEvent.PURCHASE_CANCELLED,
                                    mapOf("package_identifier" to packageIdentifier, "category" to "cancelled"),
                                )
                            }
                            error.code == PurchasesErrorCode.PaymentPendingError -> {
                                SubscriptionPayload.dispatch(
                                    activity,
                                    SubscriptionEvent.PURCHASE_PENDING,
                                    mapOf(
                                        "package_identifier" to packageIdentifier,
                                        "product_identifier" to SubscriptionPayload.productIdentifier(packageToPurchase),
                                        "category" to "pending",
                                    ),
                                )
                            }
                            else -> {
                                SubscriptionPayload.dispatch(
                                    activity,
                                    SubscriptionEvent.PURCHASE_FAILED,
                                    mapOf(
                                        "category" to "purchase",
                                        "message" to error.message,
                                        "package_identifier" to packageIdentifier,
                                    ),
                                )
                            }
                        }
                    },
                )
            }

            return BridgeResponse.success(mapOf("started" to true))
        }
    }

    class Restore(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (!Purchases.isConfigured) {
                throw BridgeError.ExecutionFailed("Subscriptions are not configured")
            }

            CoroutineScope(Dispatchers.Main).launch {
                Purchases.sharedInstance.restorePurchasesWith(
                    onSuccess = { customerInfo ->
                        SubscriptionPayload.dispatch(
                            activity,
                            SubscriptionEvent.RESTORE_COMPLETED,
                            mapOf("entitled" to SubscriptionPayload.isEntitled(customerInfo)),
                        )
                    },
                    onError = { error ->
                        SubscriptionPayload.dispatch(
                            activity,
                            SubscriptionEvent.RESTORE_FAILED,
                            mapOf("category" to "restore", "message" to error.message),
                        )
                    },
                )
            }

            return BridgeResponse.success(mapOf("started" to true))
        }
    }

    class CustomerInfo(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (!Purchases.isConfigured) {
                return BridgeResponse.success(mapOf("configured" to false, "entitled" to false))
            }

            return try {
                val customerInfo = runBlocking(Dispatchers.IO) {
                    Purchases.sharedInstance.awaitCustomerInfo()
                }
                BridgeResponse.success(
                    mapOf("configured" to true, "entitled" to SubscriptionPayload.isEntitled(customerInfo)),
                )
            } catch (error: Exception) {
                throw BridgeError.ExecutionFailed(error.message ?: "Customer information is unavailable")
            }
        }
    }
}

private object SubscriptionPayload {
    fun dispatch(activity: FragmentActivity, event: String, payload: Map<String, Any>) {
        CoroutineScope(Dispatchers.Main).launch {
            NativeActionCoordinator.dispatchEvent(activity, event, JSONObject(payload).toString())
        }
    }

    fun isEntitled(customerInfo: CustomerInfo): Boolean =
        customerInfo.entitlements.active.containsKey("ai_meal_analysis")

    fun productIdentifier(packageToPurchase: Package): String =
        packageToPurchase.product.id.substringBefore(":")

    fun packagePayload(packageToPurchase: Package): Map<String, Any> {
        val product = packageToPurchase.product
        val payload = mutableMapOf<String, Any>(
            "package_identifier" to packageToPurchase.identifier,
            "product_identifier" to productIdentifier(packageToPurchase),
            "localized_price" to product.price.formatted,
            "localized_period" to localizedPeriod(product.period ?: product.defaultOption?.billingPeriod),
        )
        val introductoryPhase = product.defaultOption?.freePhase ?: product.defaultOption?.introPhase

        payload["introductory_offer"] = introductoryPhase?.let(::introductoryPayload) ?: JSONObject.NULL

        return payload
    }

    private fun introductoryPayload(phase: PricingPhase): Map<String, Any> = mapOf(
        "localized_price" to phase.price.formatted,
        "localized_period" to localizedPeriod(phase.billingPeriod),
        "period_count" to (phase.billingCycleCount ?: 1),
        "payment_mode" to (phase.offerPaymentMode?.name?.lowercase(Locale.ROOT) ?: "unknown"),
        "is_free_trial" to (phase.price.amountMicros == 0L),
    )

    private fun localizedPeriod(period: Period?): String {
        if (period == null) {
            return ""
        }

        val unit = when (period.unit) {
            Period.Unit.DAY -> MeasureUnit.DAY
            Period.Unit.WEEK -> MeasureUnit.WEEK
            Period.Unit.MONTH -> MeasureUnit.MONTH
            Period.Unit.YEAR -> MeasureUnit.YEAR
            Period.Unit.UNKNOWN -> return ""
        }

        return MeasureFormat.getInstance(Locale.getDefault(), MeasureFormat.FormatWidth.WIDE)
            .format(Measure(period.value, unit))
    }
}
