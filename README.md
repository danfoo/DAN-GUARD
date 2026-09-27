# DAN-GUARD

[![CI](https://github.com/danfoo/DAN-GUARD/actions/workflows/ci.yml/badge.svg)](https://github.com/danfoo/DAN-GUARD/actions/workflows/ci.yml)

Solution de **financement de téléphones avec verrouillage à distance** (lock-to-own /
device financing), composée de deux parties :

| Composant | Dossier | Rôle |
|-----------|---------|------|
| **Module Perfex CRM** | [`perfex-module/dan_guard/`](perfex-module/dan_guard/) | Gestion des clients, appareils, échéances de paiement. Envoie les ordres **verrouiller / déverrouiller**. |
| **App Android** | [`android-app/`](android-app/) | Application **Device Owner** installée sur les téléphones vendus. Applique le verrouillage (mode kiosk), autorise les appels d'urgence, se débloque au paiement. |

Les deux communiquent via une **API REST** (le téléphone appelle le module Perfex) et via
**push Firebase Cloud Messaging** (le module réveille le téléphone pour un ordre immédiat).

---

## ⚖️ Cadre légal — À LIRE AVANT DÉPLOIEMENT

Ce type de dispositif est **légal** uniquement s'il respecte des conditions strictes. Ce
n'est PAS un logiciel espion : c'est un outil de garantie financière consenti.

1. **Consentement écrit.** Le verrouillage doit figurer explicitement dans le contrat de
   vente signé par l'acheteur, avec les conditions de déclenchement.
2. **Transparence.** L'application est visible sur l'appareil ; l'acheteur sait qu'elle
   est présente et ce qu'elle fait.
3. **Aucune captation de données personnelles.** L'app ne lit pas les messages, contacts,
   photos, ni ne géolocalise l'acheteur à son insu. Elle rapporte uniquement l'état de
   verrouillage et l'identité de l'appareil.
4. **Appels d'urgence toujours possibles**, même verrouillé (obligation dans la plupart
   des juridictions).
5. **Préavis.** Un délai de grâce et un avertissement doivent précéder le verrouillage.
6. **Déverrouillage définitif** garanti une fois le paiement soldé — l'appareil appartient
   alors pleinement à l'acheteur.
7. **Conformité RGPD / loi locale** sur les données (l'IMEI et l'identité sont des données
   personnelles).

> ⚠️ Faites valider votre contrat de vente et votre politique de confidentialité par un
> juriste de votre pays avant toute mise en production.

---

## Architecture

```
                    ┌───────────────────────────┐
                    │      Perfex CRM            │
                    │  ┌─────────────────────┐   │
   Admin (vous) ───▶│  │  Module DAN-GUARD   │   │
                    │  │  clients/appareils/ │   │
                    │  │  échéances/ordres   │   │
                    │  └──────────┬──────────┘   │
                    └─────────────┼──────────────┘
                       API REST   │   Push FCM
                     (check-in)   │   (ordre immédiat)
                    ┌─────────────▼──────────────┐
                    │   App Android (Device Owner)│
                    │   verrouillage kiosk /      │
                    │   écran de blocage          │
                    └─────────────────────────────┘
```

## Flux principal

1. **Enrôlement** : à la vente, le téléphone (neuf/réinitialisé) est provisionné comme
   Device Owner via QR code. L'app s'installe et s'enregistre auprès du module Perfex avec
   un jeton d'enrôlement, récupérant son jeton d'API.
2. **Fonctionnement normal** : l'app fait un *check-in* périodique auprès du module.
3. **Échéance impayée** : à l'issue du délai de grâce, le module (via son cron) crée un
   ordre `lock`. Il est poussé par FCM et/ou récupéré au prochain check-in.
4. **Verrouillage** : l'app passe en mode kiosk plein écran (appels d'urgence autorisés).
5. **Paiement** : l'admin marque l'échéance payée → ordre `unlock` → l'appareil se
   débloque.
6. **Solde payé** : ordre `release` → l'app se retire du mode Device Owner, l'appareil est
   totalement libre.

> **Anti-mode-avion** : si le téléphone reste injoignable (mode avion, SIM retirée) plus
> de N jours, l'app se verrouille **d'elle-même**, sans dépendre du serveur (logique
> *fail-closed*). Elle se débloque au retour en ligne si les paiements sont à jour. Seuil
> configurable dans les réglages du module (7 jours par défaut).

Voir [`perfex-module/dan_guard/README.md`](perfex-module/dan_guard/README.md) et
[`android-app/README.md`](android-app/README.md) pour l'installation détaillée.
