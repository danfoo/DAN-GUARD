package com.danguard.lock

import android.app.Application

/**
 * Au démarrage : si l'appareil est déjà enrôlé, réapplique les politiques et relance
 * un check-in (utile après un redémarrage).
 */
class DanGuardApp : Application() {

    override fun onCreate() {
        super.onCreate()
        val prefs = Prefs(this)
        if (prefs.isEnrolled) {
            PolicyManager(this).applyBaselinePolicies()
            CheckinScheduler.schedule(this, prefs.checkinIntervalHours)
            CheckinScheduler.checkinNow(this)
            // Vérifie immédiatement le seuil hors-ligne (ex. après un redémarrage).
            CheckinScheduler.offlineGuardNow(this)
        }
    }
}
