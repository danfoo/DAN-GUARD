package com.danguard.lock

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Encapsule les API Device Owner (DevicePolicyManager) pour verrouiller/déverrouiller
 * l'appareil via le mode « lock task » (kiosk).
 *
 * Le verrouillage repose sur :
 *  - setLockTaskPackages : n'autorise que notre app en mode kiosk ;
 *  - LockActivity lancée en startLockTask() : plein écran, sortie impossible ;
 *  - restrictions (désactivation de la désinstallation, du safe mode, du factory reset
 *    partiel) pour empêcher le contournement.
 */
class PolicyManager(private val context: Context) {

    private val dpm =
        context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
    private val admin = AdminReceiver.componentName(context)

    val isDeviceOwner: Boolean
        get() = dpm.isDeviceOwnerApp(context.packageName)

    /**
     * Configuration appliquée une fois au premier démarrage en tant que Device Owner.
     */
    fun applyBaselinePolicies() {
        if (!isDeviceOwner) {
            Log.w(TAG, "Pas Device Owner : politiques ignorées")
            return
        }
        // Seule notre app peut passer en mode kiosk.
        dpm.setLockTaskPackages(admin, arrayOf(context.packageName))

        // Empêche la désinstallation et le contournement basique.
        dpm.addUserRestriction(admin, android.os.UserManager.DISALLOW_SAFE_BOOT)
        dpm.addUserRestriction(admin, android.os.UserManager.DISALLOW_FACTORY_RESET)
        dpm.addUserRestriction(admin, android.os.UserManager.DISALLOW_ADD_USER)
        dpm.setUninstallBlocked(admin, context.packageName, true)
    }

    /**
     * Verrouille : lance l'écran de blocage en mode kiosk.
     */
    fun lock(message: String?) {
        if (!isDeviceOwner) return
        val intent = Intent(context, LockActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
            if (message != null) putExtra(LockActivity.EXTRA_MESSAGE, message)
        }
        context.startActivity(intent)
    }

    /**
     * Déverrouille : sort du mode kiosk. LockActivity appelle stopLockTask() et se ferme.
     */
    fun unlock() {
        LockActivity.dismiss()
    }

    /**
     * Libération définitive : retire l'app du rôle Device Owner. L'appareil est libre.
     */
    fun release() {
        if (!isDeviceOwner) return
        runCatching {
            dpm.setUninstallBlocked(admin, context.packageName, false)
            dpm.clearDeviceOwnerApp(context.packageName)
        }.onFailure { Log.e(TAG, "Échec release", it) }
    }

    companion object {
        private const val TAG = "DanGuardPolicy"
    }
}
