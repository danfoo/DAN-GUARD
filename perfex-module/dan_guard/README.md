# Module Perfex CRM — DAN-GUARD

Module de gestion du financement de téléphones avec verrouillage à distance.

## Installation

1. Copiez le dossier `dan_guard/` dans `modules/` de votre installation Perfex :
   ```
   {perfex}/modules/dan_guard/
   ```
2. Dans l'admin Perfex : **Configuration → Modules**, activez **DAN-GUARD**.
   L'activation crée les tables (`tbldan_guard_*`) et les options par défaut.
3. Menu latéral → **DAN-GUARD**. Ouvrez **Réglages** et renseignez :
   - **Compte de service Firebase (JSON)** : Console Firebase → Paramètres du projet →
     Comptes de service → *Générer une nouvelle clé privée*. Collez le JSON obtenu ;
     il sert à l'API **FCM HTTP v1** pour réveiller les appareils. Gardez-le confidentiel.
   - **Jours de grâce** : délai après échéance avant verrouillage automatique.
   - **Intervalle de check-in** : fréquence des contacts de l'app (heures).
   - **Verrouillage auto après N jours hors ligne** : anti-mode-avion. L'app se
     verrouille d'elle-même si le téléphone reste injoignable au-delà de ce seuil (0 =
     désactivé). Le déverrouillage se fait au retour en ligne si tout est en règle.
   - **QR de provisioning** : URL de téléchargement de l'APK (HTTPS), checksum de
     signature de l'APK, et nom du composant Device Admin. Requis pour générer les QR.

## Utilisation

1. **Nouvel appareil** : renseignez le client, le modèle, l'IMEI, le prix, et
   éventuellement un nombre d'échéances + première date → un échéancier mensuel est
   généré automatiquement. Un **jeton d'enrôlement** unique est créé.
2. **Provisioning du téléphone** : sur la fiche appareil (état « en attente
   d'enrôlement »), cliquez sur **QR de provisioning**. Le module génère le QR code
   Device Owner avec le jeton déjà intégré (rendu dans votre navigateur — le jeton ne
   transite par aucun service externe). Boutons télécharger/copier le JSON en repli.
   Voir [`../../android-app/PROVISIONING.md`](../../android-app/PROVISIONING.md). À la
   première connexion, l'app échange ce jeton contre un jeton d'API.
3. **Suivi** : la fiche appareil affiche l'état, les échéances et le journal.
   Marquez une échéance **payée** → si un retard est régularisé, l'appareil est
   automatiquement déverrouillé ; solde entièrement payé → **libération** définitive.
4. **Actions manuelles** : boutons Verrouiller / Déverrouiller / Libérer.
5. **Automatique** : le cron Perfex évalue chaque jour les retards au-delà du délai de
   grâce et met en file les verrouillages.

## API (appelée par l'app Android)

Base : `{perfex_url}/dan_guard/api/`

| Méthode | Endpoint  | Auth | Corps | Réponse |
|---------|-----------|------|-------|---------|
| POST | `enroll`  | jeton d'enrôlement dans le corps | `{ enrollment_token, imei, serial, android_id, model, fcm_token }` | `{ device_id, api_token, checkin_interval }` |
| POST | `checkin` | `Authorization: Bearer <api_token>` | `{ status, fcm_token? }` | `{ status, should_lock, lock_message, commands[] }` |
| POST | `ack`     | `Authorization: Bearer <api_token>` | `{ command_id }` | `{ ok: true }` |

**Sécurité** : le jeton d'API n'est jamais stocké en clair côté serveur (seul son
hash SHA-256 l'est). Le jeton d'enrôlement est à usage unique. Servez Perfex en HTTPS.

## Cron Perfex

Le verrouillage automatique dépend du cron Perfex. Assurez-vous qu'il est configuré :
```
*/5 * * * * php {perfex}/index.php cron
```

## Tables créées

- `tbldan_guard_devices` — appareils, jetons, état, dernier check-in
- `tbldan_guard_installments` — échéances de paiement
- `tbldan_guard_commands` — file d'ordres (lock/unlock/release) et leur statut
- `tbldan_guard_logs` — journal d'événements
