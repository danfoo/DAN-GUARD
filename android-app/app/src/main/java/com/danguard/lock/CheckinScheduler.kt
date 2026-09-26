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
        WorkManager.getInstance(context).cancelUniqueWork(PERIODIC)
    }
}
