package com.danguard.lock

import android.content.Context
import android.util.Log
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters

/**
 * Verrouillage automatique anti-mode-avion.
 *
 * S'exécute périodiquement SANS contrainte réseau (contrairement à [CheckinWorker]).
 * Si l'appareil n'a pas réussi de check-in depuis plus de `maxOfflineDays` jours, il se
 * verrouille de lui-même — même hors ligne, SIM retirée ou mode avion activé.
 *
 * Logique « fail-closed » : rester injoignable revient à ne pas payer. Le déverrouillage
 * n'intervient qu'au retour en ligne, quand le serveur confirme que tout est en règle
 * (géré par [CheckinWorker]).
 *
 * Ce worker tente aussi, au passage, de déclencher un check-in : s'il y a du réseau, le
 * compteur se réarme et un éventuel verrouillage abusif est immédiatement levé.
 */
class OfflineGuardWorker(context: Context, params: WorkerParameters) :
    CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        val prefs = Prefs(applicationContext)
        if (!prefs.isEnrolled) return Result.success()

        // Opportuniste : si du réseau est dispo, ce check-in remettra le compteur à zéro.
        CheckinScheduler.checkinNow(applicationContext)

        val max = prefs.maxOfflineDays
        if (max <= 0) return Result.success() // garde désactivée côté serveur

        val days = prefs.daysSinceLastCheckin()
        if (days >= max) {
            Log.w(TAG, "Hors ligne depuis $days jours (seuil $max) : verrouillage")
            val msg = applicationContext.getString(R.string.offline_lock_message, max)
            prefs.lastLockMessage = msg
            PolicyManager(applicationContext).lock(msg)
        }

        return Result.success()
    }

    companion object {
        private const val TAG = "DanGuardOffline"
    }
}
