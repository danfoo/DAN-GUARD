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
│   ├── OfflineGuardWorker.kt Verrouillage auto anti-mode-avion (sans réseau)
│   ├── CheckinScheduler.kt   Planification WorkManager (périodique + immédiat + garde)
│   ├── FcmService.kt         Réveil push (à activer avec Firebase)
│   ├── Api.kt                Retrofit : enroll / checkin / ack
│   └── Prefs.kt              Stockage chiffré du jeton (EncryptedSharedPreferences)
└── res/                      layouts, thèmes, device_admin.xml
```

## Configuration

1. **URL Perfex** : en production, **rien à coder** — l'URL est transmise dans le QR de
   provisioning et mémorisée par l'app, donc **un seul APK sert plusieurs Perfex**. Le
   champ `PERFEX_BASE_URL` dans `app/build.gradle` n'est qu'un **repli** (builds de test
   sans URL provisionnée).
2. **Firebase (push)** : créez un projet Firebase, ajoutez `app/google-services.json`,
   décommentez le plugin `google-services` et les dépendances `firebase-messaging` dans
   `app/build.gradle`, puis activez la vraie classe dans `FcmService.kt`. Côté serveur,
   collez le **JSON du compte de service** dans les réglages du module Perfex (API FCM
   HTTP v1). Sans Firebase, seuls les check-ins périodiques fonctionnent (le verrouillage
   est simplement moins immédiat).
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
- **Anti-mode-avion** : l'app se verrouille d'elle-même si elle n'a pas réussi de
  check-in depuis plus de `max_offline_days` jours (valeur fournie par le serveur, 7 par
  défaut, 0 = désactivé). Ce contrôle tourne **sans contrainte réseau**
  (`OfflineGuardWorker`), donc il fonctionne SIM retirée / mode avion. Le déverrouillage
  n'a lieu qu'au retour en ligne, quand le serveur confirme que tout est en règle. Le
  compteur se réarme à chaque check-in réussi et démarre à l'enrôlement.
- Servez impérativement Perfex en **HTTPS**.
