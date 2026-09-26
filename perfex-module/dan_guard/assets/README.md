# assets/

## qrcode.js (optionnel — installations hors-ligne)

La page « QR de provisioning » génère le QR code **dans le navigateur de l'admin** ; le
jeton d'enrôlement ne quitte jamais votre serveur/navigateur.

Par défaut, la bibliothèque de rendu est chargée depuis un CDN
(`qrcode-generator`). Si votre Perfex est sur un réseau **sans accès Internet**, déposez
simplement le fichier de la bibliothèque ici :

```
modules/dan_guard/assets/qrcode.js
```

Il sera chargé en priorité (le CDN n'est utilisé qu'en repli). Récupérez-le depuis
n'importe quelle machine connectée :

```bash
curl -L https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js -o qrcode.js
```

(Bibliothèque `qrcode-generator` de Kazuhiko Arase, licence MIT.)

En dernier recours, le bouton **Télécharger le JSON** de la page permet de générer le QR
avec l'outil de votre choix — le rendu QR n'est qu'un affichage, la donnée est le JSON.
