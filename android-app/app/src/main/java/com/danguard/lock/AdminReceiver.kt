package com.danguard.lock

import android.app.admin.DeviceAdminReceiver
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Récepteur Device Admin. En tant que Device Owner, il reçoit l'événement de fin de
 * provisioning et déclenche l'enrôlement auprès du module Perfex.
 */
class AdminReceiver : DeviceAdminReceiver() {

    override fun onEnabled(context: Context, intent: Intent) {
        Log.i(TAG, "Device admin activé")
    }

    override fun onProfileProvisioningComplete(context: Context, intent: Intent) {
        // Le jeton d'enrôlement est transmis dans le bundle de provisioning du QR code.
        val extras = intent.getBundleExtra(
            android.app.admin.DevicePolicyManager.EXTRA_PROVISIONING_ADMIN_EXTRAS_BUNDLE
        )
        val enrollmentToken = extras?.getString(EXTRA_ENROLLMENT_TOKEN)

        Log.i(TAG, "Provisioning terminé, enrôlement en cours")
        EnrollmentManager(context).startEnrollment(enrollmentToken)
    }

    companion object {
        private const val TAG = "DanGuardAdmin"
        const val EXTRA_ENROLLMENT_TOKEN = "dan_guard_enrollment_token"

        fun componentName(context: Context): ComponentName =
            ComponentName(context, AdminReceiver::class.java)
    }
}
