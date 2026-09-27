package com.danguard.lock

import android.content.Context
import android.util.Log
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters

/**
 * Contacte le module Perfex : récupère l'état de référence et les ordres en attente,
 * puis applique verrouillage / déverrouillage / libération.
 *
 * Exécuté périodiquement (WorkManager) et à la demande (réveil FCM).
 */
class CheckinWorker(context: Context, params: WorkerParameters) :
    CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        val prefs = Prefs(applicationContext)
        val token = prefs.apiToken ?: return Result.success() // pas enrôlé
        val api = ApiFactory.create(prefs.effectiveBaseUrl)
        val policy = PolicyManager(applicationContext)

        return try {
            val resp = api.checkin(
                "Bearer $token",
                CheckinRequest(status = "ok", fcmToken = prefs.fcmToken)
            )
            resp.checkinInterval?.let { prefs.checkinIntervalHours = it }
            resp.maxOfflineDays?.let { prefs.maxOfflineDays = it }

            // Check-in réussi : réarme le compteur anti-mode-avion.
            prefs.lastCheckinEpoch = System.currentTimeMillis()

            // Applique l'état de référence renvoyé par le serveur.
            if (resp.shouldLock) {
                prefs.lastLockMessage = resp.lockMessage
                policy.lock(resp.lockMessage)
            }

            // Traite les ordres explicites. On n'accuse réception que si l'ordre a été
            // appliqué : sinon il reste en attente côté serveur et sera redélivré.
            resp.commands?.forEach { cmd ->
                if (handleCommand(cmd, policy, prefs)) {
                    runCatching { api.ack("Bearer $token", AckRequest(cmd.id)) }
                }
            }

            // Rien à verrouiller et pas d'ordre lock -> s'assurer que c'est débloqué.
            if (!resp.shouldLock && resp.commands.orEmpty().none { it.command == "lock" }) {
                policy.unlock()
            }

            Result.success()
        } catch (e: Exception) {
            Log.e(TAG, "Check-in échoué", e)
            Result.retry()
        }
    }

    /**
     * @return true si l'ordre a été appliqué et peut être accusé, false s'il doit être
     *         redélivré (ex. libération échouée).
     */
    private fun handleCommand(cmd: Command, policy: PolicyManager, prefs: Prefs): Boolean {
        return when (cmd.command) {
            "lock" -> {
                val msg = cmd.payload?.get("message") as? String
                prefs.lastLockMessage = msg
                policy.lock(msg)
                true
            }
            "unlock" -> {
                policy.unlock()
                true
            }
            "release" -> {
                policy.unlock()
                // N'efface les identifiants et n'arrête les workers QUE si le retrait du
                // rôle Device Owner a réussi ; sinon on garde tout pour réessayer.
                if (policy.release()) {
                    prefs.clear()
                    CheckinScheduler.cancel(applicationContext)
                    true
                } else {
                    Log.w(TAG, "Libération échouée : identifiants conservés pour réessai")
                    false
                }
            }
            else -> {
                Log.w(TAG, "Ordre inconnu: ${cmd.command}")
                true // évite une redélivrance en boucle d'un ordre non géré
            }
        }
    }

    companion object {
        private const val TAG = "DanGuardCheckin"
    }
}
