package com.danguard.lock

import android.annotation.SuppressLint
import android.content.Context
import android.os.Build
import android.provider.Settings
import android.telephony.TelephonyManager
import android.util.Log
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * Échange le jeton d'enrôlement (issu du QR de provisioning) contre un jeton d'API,
 * puis programme les check-ins.
 */
class EnrollmentManager(private val context: Context) {

    private val prefs = Prefs(context)

    fun startEnrollment(enrollmentToken: String?, baseUrl: String? = null, frpAccounts: String? = null) {
        if (enrollmentToken.isNullOrEmpty()) {
            Log.e(TAG, "Jeton d'enrôlement manquant")
            return
        }
        if (prefs.isEnrolled) {
            Log.i(TAG, "Déjà enrôlé")
            return
        }

        // URL du serveur fournie au provisioning (APK unique multi-Perfex).
        if (!baseUrl.isNullOrBlank()) {
            prefs.baseUrl = baseUrl
        }
        // Comptes FRP fournis au provisioning : appliqués dès l'enrôlement (le check-in
        // les tiendra ensuite à jour depuis le serveur).
        if (!frpAccounts.isNullOrBlank()) {
            prefs.frpAccounts = frpAccounts.split(',').map { it.trim() }.filter { it.isNotEmpty() }
        }
        val api = ApiFactory.create(prefs.effectiveBaseUrl)

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val resp = api.enroll(
                    EnrollRequest(
                        enrollmentToken = enrollmentToken,
                        imei = readImei(),
                        serial = readSerial(),
                        androidId = androidId(),
                        model = "${Build.MANUFACTURER} ${Build.MODEL}",
                        fcmToken = prefs.fcmToken // renseigné par FcmService dès que dispo
                    )
                )
                prefs.apiToken = resp.apiToken
                prefs.deviceId = resp.deviceId
                resp.checkinInterval?.let { prefs.checkinIntervalHours = it }
                // Démarre le compteur anti-mode-avion à l'enrôlement.
                prefs.lastCheckinEpoch = System.currentTimeMillis()

                val policy = PolicyManager(context)
                policy.applyBaselinePolicies()
                policy.applyFactoryResetProtection(prefs.frpAccounts)
                CheckinScheduler.schedule(context, prefs.checkinIntervalHours)
                // Premier check-in immédiat : synchronise l'état et la liste FRP du serveur.
                CheckinScheduler.checkinNow(context)
                Log.i(TAG, "Enrôlement réussi, device=${resp.deviceId}")
            } catch (e: Exception) {
                Log.e(TAG, "Échec enrôlement", e)
            }
        }
    }

    private fun androidId(): String? =
        Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)

    @SuppressLint("MissingPermission", "HardwareIds")
    private fun readImei(): String? = runCatching {
        val tm = context.getSystemService(Context.TELEPHONY_SERVICE) as TelephonyManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) tm.imei else null
    }.getOrNull()

    @SuppressLint("MissingPermission", "HardwareIds")
    private fun readSerial(): String? = runCatching {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) Build.getSerial() else null
    }.getOrNull()

    companion object {
        private const val TAG = "DanGuardEnroll"
    }
}
