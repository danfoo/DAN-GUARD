package com.danguard.lock

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Stockage chiffré des identifiants de l'appareil (jeton d'API, id, dernier état).
 */
class Prefs(context: Context) {

    private val prefs = run {
        val key = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        EncryptedSharedPreferences.create(
            context,
            "dan_guard_secure",
            key,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    var apiToken: String?
        get() = prefs.getString(KEY_API_TOKEN, null)
        set(value) = prefs.edit().putString(KEY_API_TOKEN, value).apply()

    var deviceId: Int
        get() = prefs.getInt(KEY_DEVICE_ID, 0)
        set(value) = prefs.edit().putInt(KEY_DEVICE_ID, value).apply()

    var checkinIntervalHours: Int
        get() = prefs.getInt(KEY_INTERVAL, 6)
        set(value) = prefs.edit().putInt(KEY_INTERVAL, value.coerceAtLeast(1)).apply()

    var lastLockMessage: String?
        get() = prefs.getString(KEY_LOCK_MSG, null)
        set(value) = prefs.edit().putString(KEY_LOCK_MSG, value).apply()

    /** Jeton d'enregistrement FCM courant, transmis au serveur pour les pushs. */
    var fcmToken: String?
        get() = prefs.getString(KEY_FCM, null)
        set(value) = prefs.edit().putString(KEY_FCM, value).apply()

    /** Horodatage (epoch ms) du dernier check-in réussi. */
    var lastCheckinEpoch: Long
        get() = prefs.getLong(KEY_LAST_CHECKIN, 0L)
        set(value) = prefs.edit().putLong(KEY_LAST_CHECKIN, value).apply()

    /**
     * Nombre de jours hors ligne toléré avant verrouillage automatique local
     * (anti-mode-avion). 0 = désactivé. Valeur fournie par le serveur.
     */
    var maxOfflineDays: Int
        get() = prefs.getInt(KEY_MAX_OFFLINE, DEFAULT_MAX_OFFLINE_DAYS)
        set(value) = prefs.edit().putInt(KEY_MAX_OFFLINE, value.coerceAtLeast(0)).apply()

    val isEnrolled: Boolean get() = !apiToken.isNullOrEmpty()

    /** Jours écoulés depuis le dernier check-in réussi. */
    fun daysSinceLastCheckin(now: Long = System.currentTimeMillis()): Long {
        if (lastCheckinEpoch <= 0L) return 0L
        return (now - lastCheckinEpoch) / 86_400_000L
    }

    fun clear() = prefs.edit().clear().apply()

    companion object {
        const val DEFAULT_MAX_OFFLINE_DAYS = 7
        private const val KEY_API_TOKEN = "api_token"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_INTERVAL = "checkin_interval"
        private const val KEY_LOCK_MSG = "lock_message"
        private const val KEY_LAST_CHECKIN = "last_checkin_epoch"
        private const val KEY_MAX_OFFLINE = "max_offline_days"
        private const val KEY_FCM = "fcm_token"
    }
}
