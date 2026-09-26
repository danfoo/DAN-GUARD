package com.danguard.lock

import android.os.Bundle
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

/**
 * Écran d'accueil visible de l'app. Volontairement minimal et transparent : il indique
 * l'état d'enrôlement et de paiement. L'app n'est pas cachée — l'acheteur sait qu'elle
 * est présente (exigence légale de transparence).
 */
class MainActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        val prefs = Prefs(this)
        val status = findViewById<TextView>(R.id.statusText)
        status.text = when {
            !prefs.isEnrolled -> getString(R.string.status_pending)
            else -> getString(R.string.status_active)
        }

        // Un appareil enrôlé fait un check-in à l'ouverture de l'app.
        if (prefs.isEnrolled) CheckinScheduler.checkinNow(this)
    }
}
