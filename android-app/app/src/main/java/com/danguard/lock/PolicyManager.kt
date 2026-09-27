package com.danguard.lock

import android.app.admin.DevicePolicyManager
import android.app.admin.FactoryResetProtectionPolicy
import android.content.Context
import android.content.Intent
import android.os.Build
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
     * Protection contre la réinitialisation d'usine (FRP), Android 11+ (API 30).
     *
     * Une fois activée, tout effacement de l'appareil (y compris via le mode recovery)
     * bloque le téléphone à la configuration initiale : seul un compte Google de la liste
     * (que le vendeur contrôle) peut le débloquer. C'est le vrai rempart anti hard-reset.
     *
     * Sans compte fourni, la politique gérée est désactivée (comportement par défaut) —
     * on n'active jamais un FRP « vide » qui risquerait de bloquer l'appareil.
     *
     * @param accounts Identifiants de compte Google (Gaia ID) autorisés à débloquer.
     */
    fun applyFactoryResetProtection(accounts: List<String>) {
        if (!isDeviceOwner) return
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            Log.i(TAG, "FRP nécessite Android 11+ : ignoré")
            return
        }
        runCatching {
            val policy = if (accounts.isEmpty()) {
                null // désactive la politique FRP gérée
            } else {
                FactoryResetProtectionPolicy.Builder()
                    .setFactoryResetProtectionAccounts(accounts)
                    .setFactoryResetProtectionEnabled(true)
                    .build()
            }
            dpm.setFactoryResetProtectionPolicy(admin, policy)
            Log.i(TAG, "FRP appliqué (${accounts.size} compte(s))")
        }.onFailure { Log.e(TAG, "FRP non supporté / échec", it) }
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
     *
     * @return true si l'appareil n'est plus Device Owner (libération effective), false si
     *         l'opération a échoué — l'appelant doit alors CONSERVER les identifiants et
     *         ne pas accuser réception, pour réessayer plus tard.
     */
    fun release(): Boolean {
        if (!isDeviceOwner) {
            return true // déjà non-Device Owner : rien à faire
        }
        return runCatching {
            dpm.setUninstallBlocked(admin, context.packageName, false)
            dpm.clearDeviceOwnerApp(context.packageName)
            !isDeviceOwner // confirme que le retrait a bien eu lieu
        }.getOrElse {
            Log.e(TAG, "Échec release", it)
            false
        }
    }

    companion object {
        private const val TAG = "DanGuardPolicy"
    }
}
