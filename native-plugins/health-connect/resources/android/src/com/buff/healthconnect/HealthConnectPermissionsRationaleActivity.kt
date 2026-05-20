package com.buff.healthconnect

import android.app.Activity
import android.os.Bundle
import android.view.Gravity
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView

open class HealthConnectPermissionsRationaleActivity : Activity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val density = resources.displayMetrics.density
        val padding = (24 * density).toInt()

        val layout = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(padding, padding, padding, padding)
        }

        layout.addView(TextView(this).apply {
            text = "Buff uses Health Connect workout data to add exercise calories to your daily log. Workout data stays on your device and is only used inside Buff."
            textSize = 18f
        })

        layout.addView(Button(this).apply {
            text = "Close"
            setOnClickListener { finish() }
        })

        setContentView(layout)
    }
}
