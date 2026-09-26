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

    val isEnrolled: Boolean get() = !apiToken.isNullOrEmpty()

    fun clear() = prefs.edit().clear().apply()

    companion object {
        private const val KEY_API_TOKEN = "api_token"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_INTERVAL = "checkin_interval"
        private const val KEY_LOCK_MSG = "lock_message"
    }
}
