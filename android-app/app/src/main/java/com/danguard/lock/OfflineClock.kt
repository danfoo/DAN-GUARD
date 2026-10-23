package com.danguard.lock

/**
 * Mesure du temps passé hors ligne, résistante au changement de date du téléphone.
 *
 * L'horloge murale (`System.currentTimeMillis()`) est réglable par l'utilisateur : la
 * reculer suffirait à bloquer le compteur anti-mode-avion. On cumule donc en plus le
 * temps réel écoulé, mesuré avec l'horloge monotone (`SystemClock.elapsedRealtime()`),
 * insensible aux réglages de date mais remise à zéro à chaque redémarrage :
 *
 * - à chaque contrôle ([tick]), on ajoute le temps monotone écoulé depuis le précédent ;
 * - après un redémarrage, on ajoute le temps depuis le démarrage, plus [rebootGapMs] pour
 *   le temps inconnu entre le dernier contrôle et l'extinction (compté en entier :
 *   logique « fail-closed ») ; la durée où le téléphone est éteint n'est pas comptée ;
 * - le temps hors ligne retenu est le **maximum** du cumul monotone et de l'écart
 *   d'horloge murale : reculer la date ne fait plus rien gagner, et un utilisateur
 *   honnête n'est jamais pénalisé par rapport à l'ancien calcul.
 *
 * Logique pure (sans Android) pour être testable ; la lecture des horloges et la
 * persistance sont dans [Prefs].
 */
object OfflineClock {

    const val DAY_MS = 86_400_000L

    /** Pas d'ancre monotone (état antérieur à cette mesure, ou jamais contrôlé). */
    const val NO_ANCHOR = -1L

    /** Compteur de démarrages inconnu (`Settings.Global.BOOT_COUNT` illisible). */
    const val UNKNOWN_BOOT = -1

    /** Lectures des horloges à un instant donné. */
    data class Reading(val wallMs: Long, val realtimeMs: Long, val bootCount: Int)

    data class State(
        /** Horloge murale au dernier check-in réussi (0 = jamais). */
        val lastCheckinWallMs: Long = 0L,
        /** Temps réel cumulé depuis le dernier check-in, mesuré à l'horloge monotone. */
        val accumulatedMs: Long = 0L,
        /** Horloge monotone au dernier contrôle, ou [NO_ANCHOR]. */
        val anchorRealtimeMs: Long = NO_ANCHOR,
        /** Compteur de démarrages au dernier contrôle, ou [UNKNOWN_BOOT]. */
        val anchorBootCount: Int = UNKNOWN_BOOT,
    )

    /** Check-in réussi : le compteur repart de zéro. */
    fun onCheckin(now: Reading): State =
        State(
            lastCheckinWallMs = now.wallMs,
            accumulatedMs = 0L,
            anchorRealtimeMs = now.realtimeMs,
            anchorBootCount = now.bootCount,
        )

    /** Ajoute le temps réel écoulé depuis le contrôle précédent et réancre le compteur. */
    fun tick(state: State, now: Reading, rebootGapMs: Long): State {
        if (state.lastCheckinWallMs <= 0L) return state // jamais contrôlé : garde inerte

        val added = when {
            // Première mesure monotone (mise à jour de l'app) : l'horloge murale couvre
            // la période précédente, on se contente d'ancrer.
            state.anchorRealtimeMs == NO_ANCHOR -> 0L
            sameBoot(state, now) -> now.realtimeMs - state.anchorRealtimeMs
            else -> now.realtimeMs + rebootGapMs
        }

        return state.copy(
            accumulatedMs = state.accumulatedMs + added.coerceAtLeast(0L),
            anchorRealtimeMs = now.realtimeMs,
            anchorBootCount = now.bootCount,
        )
    }

    /** Temps hors ligne retenu (ms) : le plus grand des deux mesures. */
    fun elapsedMs(state: State, now: Reading): Long {
        if (state.lastCheckinWallMs <= 0L) return 0L
        val wall = (now.wallMs - state.lastCheckinWallMs).coerceAtLeast(0L)
        return maxOf(state.accumulatedMs, wall)
    }

    /** Jours entiers hors ligne (même arrondi que l'ancien calcul). */
    fun elapsedDays(state: State, now: Reading): Long = elapsedMs(state, now) / DAY_MS

    private fun sameBoot(state: State, now: Reading): Boolean =
        if (state.anchorBootCount != UNKNOWN_BOOT && now.bootCount != UNKNOWN_BOOT) {
            state.anchorBootCount == now.bootCount
        } else {
            // Sans compteur de démarrages : l'horloge monotone qui recule trahit un redémarrage.
            now.realtimeMs >= state.anchorRealtimeMs
        }
}
