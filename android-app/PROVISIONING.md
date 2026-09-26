# Provisioning Device Owner (QR code)

Pour que DAN-GUARD puisse verrouiller le téléphone, l'app doit être **Device Owner**.
Ce rôle ne peut être attribué que sur un appareil **neuf ou réinitialisé** (retour usine),
avant toute configuration de compte Google.

## Étapes

1. Réinitialisez le téléphone (Factory reset).
2. Sur le tout premier écran de bienvenue, **tapez 6 fois** à un endroit vide : Android
   ouvre le scanner de QR code de provisioning.
3. Scannez un QR code contenant le JSON ci-dessous.
4. Android télécharge l'APK, l'installe comme Device Owner, puis déclenche
   `PROFILE_PROVISIONING_COMPLETE` → l'app s'enrôle automatiquement auprès de Perfex
   avec le jeton d'enrôlement.

## Contenu du QR code

```json
{
  "android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME": "com.danguard.lock/.AdminReceiver",
  "android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION": "https://votre-serveur.example.com/dan-guard.apk",
  "android.app.extra.PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM": "<checksum_base64url_de_la_signature_APK>",
  "android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED": true,
  "android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE": {
    "dan_guard_enrollment_token": "<JETON_D_ENROLEMENT_DEPUIS_PERFEX>"
  }
}
```

- `...DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION` : URL publique HTTPS de votre APK signé.
- `...SIGNATURE_CHECKSUM` : checksum de la signature de l'APK. Générez-le avec :
  ```bash
  apksigner verify --print-certs dan-guard.apk   # récupère le certificat
  # puis calcul du checksum base64url du certificat (voir doc Android EMM)
  ```
- `dan_guard_enrollment_token` : le jeton affiché sur la fiche appareil dans Perfex
  (état « en attente d'enrôlement »). **Un jeton par appareil, à usage unique.**

## Générer le QR code

N'importe quel générateur de QR acceptant du texte brut convient : collez le JSON
(minifié) comme contenu. Pour un déploiement à l'échelle, générez-le dynamiquement
depuis un petit écran d'admin (évolution possible du module Perfex).

## Rappels

- Le rôle Device Owner ne peut PAS être ajouté après coup sur un téléphone déjà
  configuré — d'où la réinitialisation préalable, à faire au moment de la vente.
- Testez d'abord sur un appareil de test : une fois Device Owner, la désinstallation
  est bloquée jusqu'à la libération (`release`).
