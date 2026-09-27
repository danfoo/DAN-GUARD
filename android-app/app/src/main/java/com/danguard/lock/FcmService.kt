package com.danguard.lock

/**
 * Réception des pushs Firebase Cloud Messaging.
 *
 * NOTE : cette classe hérite de FirebaseMessagingService. Elle est laissée en commentaire
 * tant que Firebase n'est pas ajouté au projet (google-services.json + dépendances dans
 * app/build.gradle). Une fois Firebase configuré, retirez les commentaires ci-dessous et
 * supprimez la classe stub.
 *
 * Le push ne transporte jamais l'ordre lui-même : il ne fait que demander à l'app de
 * déclencher un check-in authentifié, qui récupère l'ordre réel côté serveur.
 */

// import com.google.firebase.messaging.FirebaseMessagingService
// import com.google.firebase.messaging.RemoteMessage
//
// class FcmService : FirebaseMessagingService() {
//
//     override fun onMessageReceived(message: RemoteMessage) {
//         // Toute notification déclenche un check-in immédiat.
//         CheckinScheduler.checkinNow(applicationContext)
//     }
//
//     override fun onNewToken(token: String) {
//         // Stocke le jeton ; il part au prochain check-in (CheckinRequest.fcmToken) et
//         // à l'enrôlement. On déclenche un check-in immédiat pour le transmettre vite.
//         Prefs(applicationContext).fcmToken = token
//         CheckinScheduler.checkinNow(applicationContext)
//     }
// }

/** Stub temporaire pour que le manifeste reste valide avant l'ajout de Firebase. */
class FcmService : android.app.Service() {
    override fun onBind(intent: android.content.Intent?) = null
}
