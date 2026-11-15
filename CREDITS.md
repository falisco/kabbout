# Crédits et licences des ressources de Kabbout

Les cartes et les sons sont dans le domaine public (CC0 1.0) : utilisation commerciale permise, aucune attribution obligatoire, les crédits sont une courtoisie. Les polices sont sous licence libre SIL OFL 1.1, qui permet de les embarquer dans l'app.

## Cartes — `app/web/cards/`

- Source : « Bridge-Sized Playing Card Deck (PNG, CC0) » par **Mesmedir**, OpenGameArt — https://opengameart.org/node/186480 (`playingcards_bridgesize_png.zip`).
- Dérivé du jeu « English pattern » de **Dmitry Fomin** sur Wikimedia Commons (CC0 1.0) — https://commons.wikimedia.org/wiki/Category:SVG_English_pattern_playing_cards
- Modifications : 32 cartes de belote (7 à As), index des figures remplacés par les index français **R / D / V** (lettres dessinées par `tools/frcards.py`), redimensionnement à 330 px et conversion en WebP.
- Écran anglais : les douze figures gardent leurs index d'origine **J / Q / K**, dans `app/web/cards/en/`.
- Le dos (`back.webp`) n'est pas tiré du paquet : c'est un dessin du projet, un treillis d'étoiles à huit branches (`tools/brand.mjs`).
- Pour régénérer : télécharger le zip ci-dessus, le décompresser, puis `python3 tools/frcards.py <dossier-png> app/web/cards` et `python3 tools/frcards.py <dossier-png> app/web/cards/en --en` (nécessite Pillow avec WebP).

## Sons — intégrés en base64 dans `app/web/sounds.js`

- « Casino Audio » par **Kenney** (kenney.nl), CC0 1.0 — https://kenney.nl/assets/casino-audio
- 15 sons retenus (pose et glissement de cartes, mélange, jetons), convertis en MP3 mono 64 kb/s.
- La musique d'ambiance est synthétisée dans la page (pas de fichier audio).

## Polices — `app/web/fonts/`

- **Source Serif 4** et **Source Sans 3** (Adobe), licence SIL Open Font License 1.1 — https://github.com/adobe-fonts/source-serif et https://github.com/adobe-fonts/source-sans
- **El Messiri** (Mohamed Gaber, d'après Jovanny Lemonad), licence SIL Open Font License 1.1 — https://github.com/Gue3bara/El-Messiri — pour le nom du jeu, en lettres latines et arabes.
- **Noto Sans Arabic** (Google), licence SIL Open Font License 1.1 — https://github.com/notofonts/arabic — pour les textes en arabe tunisien.
- Fichiers WOFF2 des sous-ensembles latin et arabe, servis par l'app elle-même : la page ne charge rien depuis un autre site.

## Emblème — `app/web/icon.svg`, `app/web/brand/`

- L'étoile à huit branches au pique, les icônes de l'app, l'image de partage et le dos des cartes sont des dessins du projet, tracés par `tools/brand.mjs` à partir de `app/web/src/star.js`.
