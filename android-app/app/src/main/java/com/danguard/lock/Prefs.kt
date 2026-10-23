package com.danguard.lock

import android.content.Context
import android.os.SystemClock
import android.provider.Settings
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Stockage chiffré des identifiants de l'appareil (jeton d'API, id, dernier état).
 */
class Prefs(context: Context) {

    private val appContext = context.applicationContext

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

    /**
     * URL de base de l'API Perfex, fournie au provisioning (ex.
     * https://mon-perfex/dan_guard/api/). Permet un APK unique pour plusieurs
     * installations Perfex. Toujours normalisée avec un « / » final.
     */
    var baseUrl: String?
        get() = prefs.getString(KEY_BASE_URL, null)
        set(value) {
            val v = value?.trim()
            val norm = when {
                v.isNullOrEmpty() -> v
                v.endsWith("/")   -> v
                else              -> "$v/"
            }
            prefs.edit().putString(KEY_BASE_URL, norm).apply()
        }

    /** URL effective : celle du provisioning, sinon la valeur de compilation (repli). */
    val effectiveBaseUrl: String
        get() = baseUrl?.takeIf { it.isNotBlank() } ?: BuildConfig.PERFEX_BASE_URL

    /**
     * Comptes Google (Gaia ID) autorisés à débloquer l'appareil après réinitialisation
     * (Factory Reset Protection). Stockés en CSV.
     */
    var frpAccounts: List<String>
        get() = prefs.getString(KEY_FRP, "")
            ?.split(',')?.map { it.trim() }?.filter { it.isNotEmpty() } ?: emptyList()
        set(value) = prefs.edit().putString(KEY_FRP, value.joinToString(",")).apply()

    /**
     * Nombre de jours hors ligne toléré avant verrouillage automatique local
     * (anti-mode-avion). 0 = désactivé. Valeur fournie par le serveur.
     */
    var maxOfflineDays: Int
        get() = prefs.getInt(KEY_MAX_OFFLINE, DEFAULT_MAX_OFFLINE_DAYS)
        set(value) = prefs.edit().putInt(KEY_MAX_OFFLINE, value.coerceAtLeast(0)).apply()

    val isEnrolled: Boolean get() = !apiToken.isNullOrEmpty()

    /** Compteur anti-mode-avion (voir [OfflineClock]). */
    private var offlineClock: OfflineClock.State
        get() = OfflineClock.State(
            lastCheckinWallMs = prefs.getLong(KEY_LAST_CHECKIN, 0L),
            accumulatedMs = prefs.getLong(KEY_OFFLINE_ACCUMULATED, 0L),
            anchorRealtimeMs = prefs.getLong(KEY_OFFLINE_ANCHOR, OfflineClock.NO_ANCHOR),
            anchorBootCount = prefs.getInt(KEY_OFFLINE_BOOT, OfflineClock.UNKNOWN_BOOT),
        )
        set(value) = prefs.edit()
            .putLong(KEY_LAST_CHECKIN, value.lastCheckinWallMs)
            .putLong(KEY_OFFLINE_ACCUMULATED, value.accumulatedMs)
            .putLong(KEY_OFFLINE_ANCHOR, value.anchorRealtimeMs)
            .putInt(KEY_OFFLINE_BOOT, value.anchorBootCount)
            .apply()

    /** Check-in réussi (ou enrôlement) : réarme le compteur anti-mode-avion. */
    fun markCheckin() = synchronized(CLOCK_LOCK) {
        offlineClock = OfflineClock.onCheckin(readClocks())
    }

    /**
     * Jours entiers écoulés depuis le dernier check-in réussi, en résistant au
     * changement de date du téléphone. Met à jour le cumul : à appeler régulièrement.
     */
    fun offlineDays(): Long = synchronized(CLOCK_LOCK) {
        val now = readClocks()
        val state = OfflineClock.tick(
            offlineClock, now, CheckinScheduler.OFFLINE_GUARD_HOURS * 3_600_000L
        )
        offlineClock = state
        OfflineClock.elapsedDays(state, now)
    }

    private fun readClocks() = OfflineClock.Reading(
        wallMs = System.currentTimeMillis(),
        realtimeMs = SystemClock.elapsedRealtime(),
        bootCount = Settings.Global.getInt(
            appContext.contentResolver, Settings.Global.BOOT_COUNT, OfflineClock.UNKNOWN_BOOT
        ),
    )

    fun clear() = prefs.edit().clear().apply()

    companion object {
        const val DEFAULT_MAX_OFFLINE_DAYS = 7
        /** Les workers tournent en parallèle : lecture-modification-écriture atomique. */
        private val CLOCK_LOCK = Any()
        private const val KEY_API_TOKEN = "api_token"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_INTERVAL = "checkin_interval"
        private const val KEY_LOCK_MSG = "lock_message"
        private const val KEY_LAST_CHECKIN = "last_checkin_epoch"
        private const val KEY_OFFLINE_ACCUMULATED = "offline_accumulated_ms"
        private const val KEY_OFFLINE_ANCHOR = "offline_anchor_realtime"
        private const val KEY_OFFLINE_BOOT = "offline_anchor_boot"
        private const val KEY_MAX_OFFLINE = "max_offline_days"
        private const val KEY_FCM = "fcm_token"
        private const val KEY_BASE_URL = "base_url"
        private const val KEY_FRP = "frp_accounts"
    }
}
