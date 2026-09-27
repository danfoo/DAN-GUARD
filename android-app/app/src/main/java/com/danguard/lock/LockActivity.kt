package com.danguard.lock

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.widget.Button
import android.widget.TextView
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import java.lang.ref.WeakReference

/**
 * Écran de verrouillage plein écran (mode kiosk). L'utilisateur ne peut pas en sortir ;
 * seul l'appel d'urgence reste accessible. Débloqué à distance via [dismiss].
 */
class LockActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_lock)
        current = WeakReference(this)

        // Empêche la sortie par le bouton retour.
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() { /* bloqué */ }
        })

        val message = intent.getStringExtra(EXTRA_MESSAGE)
            ?: Prefs(this).lastLockMessage
            ?: getString(R.string.default_lock_message)
        findViewById<TextView>(R.id.lockMessage).text = message

        val deviceId = Prefs(this).deviceId
        findViewById<TextView>(R.id.deviceInfo).text = "ID: $deviceId • ${Build.MODEL}"

        findViewById<Button>(R.id.emergencyButton).setOnClickListener { dialEmergency() }
    }

    override fun onResume() {
        super.onResume()
        // Passe en mode épinglé (kiosk). Autorisé sans confirmation car Device Owner.
        runCatching { startLockTask() }
    }

    /**
     * Ouvre le composeur en mode urgence. ACTION_DIAL n'exige pas de permission et
     * reste possible même en verrouillage.
     */
    @SuppressLint("MissingPermission")
    private fun dialEmergency() {
        val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:112"))
        runCatching { startActivity(intent) }
    }

    private fun releaseLock() {
        runCatching { stopLockTask() }
        finish()
    }

    companion object {
        const val EXTRA_MESSAGE = "message"
        private var current: WeakReference<LockActivity>? = null

        /** Appelé lors d'un ordre de déverrouillage. */
        fun dismiss() {
            current?.get()?.runOnUiThread { current?.get()?.releaseLock() }
        }
    }
}
