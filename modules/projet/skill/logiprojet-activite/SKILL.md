---
name: logiprojet-activite
description: Crée une activité pour LogiProjet (présentation, affiche, dossier, étude de cas, vidéo…), notée sur 6 ou formative, en duo, trio ou seul : un fichier .json à importer dans LogiProjet et la consigne Word identique, à la charte LogiOne.
---

# Créer une activité LogiProjet

Compétence téléchargée depuis LogiProjet {{VERSION}} sur {{SITE_NAME}} ({{SITE_URL}}).

LogiProjet est l'extension WordPress où les apprentis déposent leurs travaux et où l'enseignant les corrige.
Cette compétence produit, à partir d'une demande de l'enseignant :
1. `<module>_<activite>_LogiProjet.json` : le fichier d'import (format dans `references/format-logiprojet.md`) ;
2. `<module>_<activite>_Consignes.docx` : la même consigne en Word, générée **à partir du .json** par `scripts/consigne_docx.js`.

Les apprentis lisent dans LogiProjet exactement le texte du Word. Ne jamais écrire le Word à la main : toujours le générer depuis le .json.

Écrire en français de Suisse romande, avec des phrases courtes. Tutoyer l'enseignant s'il tutoie. S'adresser aux apprentis avec « vous ».

## 1. Comprendre la demande (poser seulement les questions qui manquent)

- **Le type d'activité** : présentation, affiche, dossier écrit, étude de cas, vidéo, exercice pratique, recherche…
- **Notée ou non** : notée sur 6 (`graded: true`, grille obligatoire) ou formative (`graded: false`, critères de réussite et retour sans note).
- **Le groupe** : individuel, duo, trio… (`group_size` de 1 à 6), et si les groupes incomplets sont acceptés (`allow_solo`).
- **Les formats de remise** : `pptx`, `pdf`, `docx`, `xlsx`, `odp`, `key`, `img`, `mp4`, `zip`. Rappeler que la correction directe par l'IA dans LogiProjet ne lit que les `.pptx` ; avec « Corriger dans Claude » (compétence logiprojet-correction), tous les formats se corrigent.
- **Le module et les leçons concernés**, le nombre de périodes en classe et, si l'enseignant les donne, les dates de début et de fin.

Par défaut, si l'enseignant ne précise pas : une présentation PowerPoint en duo, notée, de 8 à 12 slides, préparée en classe ; le jour J, 1 ou 2 groupes tirés au sort présentent 10 min + 5 min de questions, avec un bonus de +0.5.

Ne jamais inventer une date, une pénalité ou une règle que l'enseignant n'a pas donnée. Une date inconnue reste `null` : elle apparaît en jaune à compléter dans le Word.

## 2. Lire le module

- **Si un connecteur WordPress.com vers le site est disponible** : chercher le cours avec post_type `course` (search « M1xx »), puis les leçons avec post_type `lesson`.
  - Garder les leçons de contenu numérotées (« 9.1 … »), et écarter Objectifs, Évaluation, Mathématiques, Podcast, etc.
  - Retirer le préfixe du module des titres.
  - Garder l'id et le slug de chaque leçon.
  - Lire le contenu des leçons pour estimer le temps de lecture et pour écrire des exemples justes.
- **Sinon** : demander à l'enseignant les titres des leçons (et leur contenu, ou un export du cours). Sans id, mettre seulement `title` : l'import retrouve les leçons par leur titre.

Estimer les périodes de 45 min d'après la longueur réelle des leçons, leurs vidéos et le produit demandé. Pour une présentation de 5 leçons : 4 périodes, plus 1 pour le jour J. Proposer 3 périodes si le module a déjà été vu, 5 s'il est découvert ou si la classe est plus lente.

## 3. Écrire le fichier d'import

1. Partir de `references/modele-consigne.json` : le modèle exact de cette version de LogiProjet, en version notée et non notée. S'appuyer aussi sur les exemples de `exemples/` :
   - `M109_presentation_duo_notee.json` : présentation notée, en duo ;
   - `M109_affiche_trio_non_notee.json` : affiche formative, en trio.
2. Adapter **chaque section** à l'activité. Tout est décrit dans `references/format-logiprojet.md` :
   - `note` : l'encadré du haut. Pour une activité notée, dire qu'elle compte pour la moyenne, sur combien de points, et que tous les membres ont la même note. Pour une activité formative, dire qu'elle n'est pas notée et à quoi elle prépare.
   - `mission` : 1 ou 2 paragraphes, avec la durée et le produit attendu.
   - `actions` : 3 actions pour chaque leçon, au format `Titre | texte`.
   - `plan` : la structure du produit (slides, parties du dossier, zones de l'affiche…), au format `Repère | texte`.
   - `faire` / `eviter` : 3 à 6 règles chacun.
   - `etapes` : **une ligne par période**.
   - `rendu` : le fichier, son nom, `{date_fin}` et le lieu de dépôt (LogiProjet).
     Pour une activité notée, garder juste après la date la ligne des pénalités de retard, que LogiProjet applique automatiquement : « En retard : **−0.5** de 1 à 7 jours, **−1** de 8 à 14 jours, **note 1** au-delà (chaque jour commencé compte) ». Ne pas la mettre pour une activité non notée.
   - `jourj` : le moment collectif (présentation, galerie, mise en commun…), ou une liste vide.
   - `checklist` : 4 à 8 cases vérifiables.
3. **Titres** : dès que l'activité n'est pas une présentation, remplir `consigne.titres` (ex. `contenu`, `plan`, `page2`, `jourj`), ainsi que `intro_lecons` et `intro_actions`.
4. **Mots du groupe** : adapter aussi le vocabulaire : « En trio », « par trio », « les trois », « Nom1_Nom2_Nom3 ».
5. **La grille** (2 points par critère) :
   - un critère par leçon pour le contenu, puis les explications et exemples, la structure, la lisibilité ou la forme, selon le produit ;
   - libellés courts et observables ;
   - pour une activité notée, 8 à 16 critères suffisent ;
   - pour une activité formative, 3 à 8 critères de réussite.
6. Exemples toujours tirés du monde de l'apprenti (pour la logistique : entrepôt, poste, transport). Ne pas recopier le texte des leçons.
7. Mettre `"auteur"` (nom de l'enseignant) et `"formation"` (ex. `"CFC Logisticien·ne"`) pour le pied de page du Word.
8. Vérifier le fichier :

   ```bash
   python3 scripts/verifier.py activite.json
   ```

   Corriger toutes les erreurs, puis relire chaque ligne « À RELIRE ».

## 4. Générer et contrôler le Word

```bash
node scripts/consigne_docx.js activite.json Consignes.docx --auteur "Nom Prénom" --formation "CFC Logisticien·ne"
```

- Si `require('docx')` échoue, lancer `npm install docx`.
- Si une compétence docx est disponible, s'en servir pour valider le fichier et en faire un rendu.
- Convertir en PDF et regarder chaque page :
  - en principe 3 pages : consigne, méthode et grille ;
  - 2 pages si l'activité n'a pas de grille ;
  - aucun débordement ;
  - les zones jaunes sont seulement celles prévues (dates inconnues).

Le Word reprend la charte LogiOne :
- bandeau anthracite #313a45 avec un tampon jaune #f5c400 (« TRAVAIL NOTÉ / note sur 6 », ou « ACTIVITÉ / non notée ») ;
- titres de section à pastille verte #067030 ;
- encadré jaune pour une activité notée, vert menthe #dcefe3 pour une activité formative ;
- tuiles à liseré, police Arial, A4.

## 5. Livrer

1. Envoyer les deux fichiers : le `.json` et le `.docx`.
2. Résumer en 3 à 4 lignes : le type d'activité, noté ou non, la taille des groupes, les formats, le nombre de périodes, le nombre de critères et de points.
3. Donner les étapes dans LogiProjet :
   - **Accueil → Importer un travail** et choisir le `.json` ; le travail arrive en brouillon ;
   - vérifier, cocher les **classes**, mettre les **dates de début et de fin** ;
   - contrôler avec **Aperçu apprenti** ;
   - passer le statut à **Ouvert aux dépôts**.
4. Préciser que la date saisie dans LogiProjet s'affiche automatiquement dans la consigne en ligne. Dans le Word, il faut remplacer les zones jaunes.
5. Lister ce qui reste à compléter, et les choix faits sans confirmation de l'enseignant.
