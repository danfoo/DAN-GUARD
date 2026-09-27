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
    "dan_guard_enrollment_token": "<JETON_D_ENROLEMENT_DEPUIS_PERFEX>",
    "dan_guard_base_url": "https://votre-perfex/dan_guard/api/"
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
- `dan_guard_base_url` : l'URL de l'API de **cette** installation Perfex
  (`https://.../dan_guard/api/`). Elle est ajoutée automatiquement par le module dans le
  QR généré → l'app la mémorise et l'utilise pour tous ses appels. Grâce à ça, **un APK
  unique fonctionne avec plusieurs installations Perfex** (rien n'est codé en dur).

## Générer le QR code depuis Perfex (recommandé)

Le module DAN-GUARD génère le QR pour vous, jeton déjà intégré :

1. Réglages du module → renseignez une fois :
   - **URL de téléchargement de l'APK** (HTTPS) ;
   - **Checksum de signature de l'APK** (voir ci-dessous) ;
   - **Nom du composant** (par défaut `com.danguard.lock/.AdminReceiver`).
2. Ouvrez la fiche d'un appareil **en attente d'enrôlement** → bouton **QR de
   provisioning**. Le QR s'affiche, prêt à scanner.
3. Le QR est rendu **dans votre navigateur** (le jeton ne transite par aucun service
   externe). Boutons **Télécharger le JSON** / **Copier le JSON** en repli, et
   **Régénérer le jeton** si besoin.

### Calculer le checksum de signature

Le champ `PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM` est le SHA-256 **du certificat
de signature** de l'APK, encodé en base64url sans padding :

```bash
keytool -exportcert -alias <votre_alias> -keystore <votre_keystore.jks> \
  | openssl dgst -sha256 -binary \
  | openssl base64 | tr -d '=' | tr '+/' '-_'
```

Collez le résultat dans les réglages du module.

> Alternative : n'importe quel générateur de QR acceptant du texte brut convient — collez
> le JSON minifié téléchargé depuis Perfex.

## Rappels

- Le rôle Device Owner ne peut PAS être ajouté après coup sur un téléphone déjà
  configuré — d'où la réinitialisation préalable, à faire au moment de la vente.
- Testez d'abord sur un appareil de test : une fois Device Owner, la désinstallation
  est bloquée jusqu'à la libération (`release`).
