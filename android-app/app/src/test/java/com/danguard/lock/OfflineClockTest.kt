package com.danguard.lock

import com.danguard.lock.OfflineClock.DAY_MS
import com.danguard.lock.OfflineClock.Reading
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class OfflineClockTest {

    private val hour = 3_600_000L
    private val gap = hour // cadence de la garde

    /** Mise en service à J0 : horloge murale 1 000 j, téléphone allumé depuis 1 h, boot n° 5. */
    private val start = Reading(wallMs = 1_000 * DAY_MS, realtimeMs = hour, bootCount = 5)

    /** Avance le temps réel de [ms] sur le même démarrage ; [wallShift] décale la date affichée. */
    private fun Reading.after(ms: Long, wallShift: Long = 0L) =
        copy(wallMs = wallMs + ms + wallShift, realtimeMs = realtimeMs + ms)

    /** Contrôle toutes les heures pendant [hours] heures, comme la garde. */
    private fun run(state: OfflineClock.State, from: Reading, hours: Int, wallShift: Long = 0L):
        Pair<OfflineClock.State, Reading> {
        var s = state
        var now = from
        repeat(hours) {
            now = now.after(hour, if (it == 0) wallShift else 0L)
            s = OfflineClock.tick(s, now, gap)
        }
        return s to now
    }

    @Test
    fun `honest phone locks after 7 days like before`() {
        val (s, now) = run(OfflineClock.onCheckin(start), start, 7 * 24)
        assertEquals(7L, OfflineClock.elapsedDays(s, now))
    }

    @Test
    fun `just under 7 days stays under the threshold`() {
        val (s, now) = run(OfflineClock.onCheckin(start), start, 7 * 24 - 1)
        assertEquals(6L, OfflineClock.elapsedDays(s, now))
    }

    @Test
    fun `turning the date back does not stop the counter`() {
        // Dès la 1re heure hors ligne, l'utilisateur recule la date de 30 jours.
        val (s, now) = run(OfflineClock.onCheckin(start), start, 7 * 24, wallShift = -30 * DAY_MS)
        assertEquals(7L, OfflineClock.elapsedDays(s, now))
    }

    @Test
    fun `turning the date back repeatedly does not stop the counter`() {
        var s = OfflineClock.onCheckin(start)
        var now = start
        repeat(7 * 24) {
            // Chaque heure, la date est remise à celle du dernier check-in.
            now = now.after(hour).copy(wallMs = start.wallMs)
            s = OfflineClock.tick(s, now, gap)
        }
        assertEquals(7L, OfflineClock.elapsedDays(s, now))
    }

    @Test
    fun `reboot counts uptime plus the unknown gap before shutdown`() {
        var s = OfflineClock.onCheckin(start)
        // 3 h sur le premier démarrage (dernier contrôle à +3 h).
        var now = start.after(3 * hour)
        s = OfflineClock.tick(s, now, gap)
        // Redémarrage, date reculée d'un an : 2 h depuis le démarrage au contrôle suivant.
        now = Reading(wallMs = start.wallMs - 365 * DAY_MS, realtimeMs = 2 * hour, bootCount = 6)
        s = OfflineClock.tick(s, now, gap)
        assertEquals(3 * hour + 2 * hour + gap, OfflineClock.elapsedMs(s, now))
    }

    @Test
    fun `frequent reboots cannot stall the counter`() {
        var s = OfflineClock.onCheckin(start)
        var boot = start.bootCount
        // Redémarre toutes les 50 min (juste avant chaque contrôle horaire) en remettant la
        // date : seul le contrôle au démarrage voit l'appareil, 1 min après l'allumage.
        repeat(8 * 24) {
            boot++
            val now = Reading(wallMs = start.wallMs, realtimeMs = 60_000L, bootCount = boot)
            s = OfflineClock.tick(s, now, gap)
        }
        val now = Reading(wallMs = start.wallMs, realtimeMs = 60_000L, bootCount = boot)
        // 8 × 24 cycles de 50 min ≈ 6,7 jours réels, comptés au moins comme tels.
        val realElapsed = 8 * 24 * 50 * 60_000L
        assertTrue(OfflineClock.elapsedMs(s, now) >= realElapsed)
        assertTrue(OfflineClock.elapsedDays(s, now) >= 7L)
    }

    @Test
    fun `without boot count a monotonic clock going back means reboot`() {
        val noBoot = start.copy(bootCount = OfflineClock.UNKNOWN_BOOT)
        var s = OfflineClock.onCheckin(noBoot)
        var now = noBoot.after(5 * hour)
        s = OfflineClock.tick(s, now, gap)
        now = noBoot.copy(wallMs = noBoot.wallMs, realtimeMs = 30 * 60_000L)
        s = OfflineClock.tick(s, now, gap)
        assertEquals(5 * hour + 30 * 60_000L + gap, s.accumulatedMs)
    }

    @Test
    fun `checkin resets the counter`() {
        val (s, now) = run(OfflineClock.onCheckin(start), start, 6 * 24)
        val reset = OfflineClock.onCheckin(now)
        assertEquals(0L, OfflineClock.elapsedMs(reset, now))
        val (s2, later) = run(reset, now, 2)
        assertEquals(2 * hour, OfflineClock.elapsedMs(s2, later))
        assertTrue(s.accumulatedMs > 0)
    }

    @Test
    fun `never checked in stays inert like the server`() {
        val s = OfflineClock.tick(OfflineClock.State(), start.after(30 * DAY_MS), gap)
        assertEquals(0L, OfflineClock.elapsedMs(s, start.after(30 * DAY_MS)))
    }

    @Test
    fun `upgrade from wall-clock only state keeps prior time and only anchors`() {
        // État d'une version précédente : seul le check-in mural est connu.
        val legacy = OfflineClock.State(lastCheckinWallMs = start.wallMs - 3 * DAY_MS)
        val now = start.copy(realtimeMs = 40 * DAY_MS) // allumé depuis longtemps
        val s = OfflineClock.tick(legacy, now, gap)
        assertEquals(0L, s.accumulatedMs) // pas d'uptime compté à tort
        assertEquals(3L, OfflineClock.elapsedDays(s, now))
        val (s2, later) = run(s, now, 24)
        assertEquals(4L, OfflineClock.elapsedDays(s2, later))
    }
}
