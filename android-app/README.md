# App Android — DAN-GUARD (Device Owner)

Application Kotlin installée sur les téléphones vendus à crédit. En tant que **Device
Owner**, elle applique le verrouillage à distance piloté par le module Perfex.

## Ce que fait l'app

- S'enrôle auprès du module Perfex avec un jeton unique (via provisioning QR code).
- Fait des **check-ins** périodiques (WorkManager) et immédiats (push FCM).
- Applique l'état de référence du serveur : **verrouille** (mode kiosk plein écran),
  **déverrouille**, ou se **libère** définitivement (solde payé).
- Laisse toujours l'**appel d'urgence** accessible depuis l'écran de verrouillage.
- Est **visible et transparente** — pas de collecte de données personnelles.

## Structure

```
app/src/main/
├── AndroidManifest.xml
├── java/com/danguard/lock/
│   ├── DanGuardApp.kt        Application : réapplique les politiques au démarrage
│   ├── MainActivity.kt       Écran d'accueil (état)
│   ├── LockActivity.kt       Écran de verrouillage kiosk + appel d'urgence
│   ├── AdminReceiver.kt      DeviceAdminReceiver (fin de provisioning → enrôlement)
│   ├── PolicyManager.kt      DevicePolicyManager : lock task, restrictions, release
│   ├── EnrollmentManager.kt  Échange jeton d'enrôlement → jeton d'API
│   ├── CheckinWorker.kt      Récupère état + ordres et les applique
│   ├── CheckinScheduler.kt   Planification WorkManager (périodique + immédiat)
│   ├── FcmService.kt         Réveil push (à activer avec Firebase)
│   ├── Api.kt                Retrofit : enroll / checkin / ack
│   └── Prefs.kt              Stockage chiffré du jeton (EncryptedSharedPreferences)
└── res/                      layouts, thèmes, device_admin.xml
```

## Configuration

1. **URL Perfex** : dans `app/build.gradle`, champ `PERFEX_BASE_URL`
   (`https://votre-perfex/dan_guard/api/`).
2. **Firebase (push)** : créez un projet Firebase, ajoutez `app/google-services.json`,
   décommentez le plugin `google-services` et les dépendances `firebase-messaging` dans
   `app/build.gradle`, puis activez la vraie classe dans `FcmService.kt`. Reportez la
   **clé serveur** dans les réglages du module Perfex. Sans Firebase, seuls les check-ins
   périodiques fonctionnent (le verrouillage est simplement moins immédiat).
3. **Signature** : signez l'APK release ; le checksum de signature est requis dans le QR
   de provisioning (voir `PROVISIONING.md`).

## Compilation

```bash
cd android-app
./gradlew :app:assembleRelease
```

> Le wrapper Gradle (`gradlew`, `gradle/wrapper/`) n'est pas versionné ici. Générez-le
> avec `gradle wrapper` (Gradle 8.7+) ou ouvrez le projet dans Android Studio, qui le
> crée automatiquement.

## Déploiement

Voir [`PROVISIONING.md`](PROVISIONING.md) pour le provisioning Device Owner sur un
appareil neuf/réinitialisé.

## Limites & sécurité

- Le rôle Device Owner s'attribue **uniquement** sur un appareil neuf/réinitialisé.
- L'app bloque sa désinstallation et le safe boot tant qu'elle n'est pas libérée.
- La source de vérité reste le serveur : un appareil hors ligne conserve son dernier
  état ; il applique le verrouillage dès qu'il retrouve le réseau si une échéance est en
  retard. (Une évolution possible : verrouillage automatique après N jours sans check-in.)
- Servez impérativement Perfex en **HTTPS**.
