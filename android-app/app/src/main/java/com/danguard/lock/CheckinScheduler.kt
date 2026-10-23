package com.danguard.lock

import android.content.Context
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

/**
 * Planifie les check-ins périodiques et les check-ins immédiats (déclenchés par FCM).
 */
object CheckinScheduler {

    private const val PERIODIC = "dan_guard_checkin_periodic"
    private const val IMMEDIATE = "dan_guard_checkin_now"
    private const val OFFLINE_GUARD = "dan_guard_offline_guard"

    /**
     * Cadence de la garde anti-mode-avion. Elle borne aussi le temps compté d'office à
     * chaque redémarrage (voir [OfflineClock]) : courte pour limiter ce surplus.
     */
    const val OFFLINE_GUARD_HOURS = 1L

    fun schedule(context: Context, intervalHours: Int) {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val request = PeriodicWorkRequestBuilder<CheckinWorker>(
            intervalHours.coerceAtLeast(1).toLong(), TimeUnit.HOURS
        ).setConstraints(constraints).build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            PERIODIC, ExistingPeriodicWorkPolicy.UPDATE, request
        )

        scheduleOfflineGuard(context)
    }

    /**
     * Garde anti-mode-avion : SANS contrainte réseau, pour pouvoir verrouiller hors ligne.
     * Cadence fixe ([OFFLINE_GUARD_HOURS]) : contrôle local, sans réseau, peu coûteux.
     */
    fun scheduleOfflineGuard(context: Context) {
        val request = PeriodicWorkRequestBuilder<OfflineGuardWorker>(
            OFFLINE_GUARD_HOURS, TimeUnit.HOURS
        ).build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            OFFLINE_GUARD, ExistingPeriodicWorkPolicy.UPDATE, request
        )
    }

    /** Contrôle hors-ligne immédiat (ex. au démarrage de l'app). */
    fun offlineGuardNow(context: Context) {
        val request = OneTimeWorkRequestBuilder<OfflineGuardWorker>().build()
        WorkManager.getInstance(context)
            .enqueueUniqueWork(OFFLINE_GUARD + "_now", ExistingWorkPolicy.KEEP, request)
    }

    /** Check-in immédiat, ex. suite à un push FCM. */
    fun checkinNow(context: Context) {
        val request = OneTimeWorkRequestBuilder<CheckinWorker>()
            .setConstraints(
                Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()
            ).build()
        WorkManager.getInstance(context)
            .enqueueUniqueWork(IMMEDIATE, ExistingWorkPolicy.REPLACE, request)
    }

    fun cancel(context: Context) {
        WorkManager.getInstance(context).apply {
            cancelUniqueWork(PERIODIC)
            cancelUniqueWork(OFFLINE_GUARD)
        }
    }
}
