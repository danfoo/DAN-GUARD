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
     * Cadence fixe (6 h) — suffisante pour un seuil exprimé en jours.
     */
    fun scheduleOfflineGuard(context: Context) {
        val request = PeriodicWorkRequestBuilder<OfflineGuardWorker>(6, TimeUnit.HOURS).build()
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
