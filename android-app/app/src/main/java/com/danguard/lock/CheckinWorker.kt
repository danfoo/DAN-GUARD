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
        val api = ApiFactory.create()
        val policy = PolicyManager(applicationContext)

        return try {
            val resp = api.checkin("Bearer $token", CheckinRequest(status = "ok"))
            resp.checkinInterval?.let { prefs.checkinIntervalHours = it }

            // Applique l'état de référence renvoyé par le serveur.
            if (resp.shouldLock) {
                prefs.lastLockMessage = resp.lockMessage
                policy.lock(resp.lockMessage)
            }

            // Traite les ordres explicites.
            resp.commands?.forEach { cmd ->
                handleCommand(cmd, policy, prefs)
                runCatching { api.ack("Bearer $token", AckRequest(cmd.id)) }
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

    private fun handleCommand(cmd: Command, policy: PolicyManager, prefs: Prefs) {
        when (cmd.command) {
            "lock" -> {
                val msg = cmd.payload?.get("message") as? String
                prefs.lastLockMessage = msg
                policy.lock(msg)
            }
            "unlock" -> policy.unlock()
            "release" -> {
                policy.unlock()
                policy.release()
                prefs.clear()
                CheckinScheduler.cancel(applicationContext)
            }
            else -> Log.w(TAG, "Ordre inconnu: ${cmd.command}")
        }
    }

    companion object {
        private const val TAG = "DanGuardCheckin"
    }
}
