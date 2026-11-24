# Format d'import LogiProjet (`logiprojet-travail`, version 1)

Un fichier `.json` en UTF-8, importé dans LogiProjet par **Accueil → Importer un travail**. Le travail arrive en **brouillon** : l'enseignant vérifie, choisit les classes et les dates, puis l'ouvre aux dépôts.
Le même fichier produit le Word avec `scripts/consigne_docx.js` : les apprentis lisent donc dans LogiProjet exactement le texte du Word.

## Champs

| Champ | Type | Obligatoire | Rôle |
|---|---|---|---|
| `format` | `"logiprojet-travail"` | oui | Identifie le fichier. |
| `version` | `1` | oui | Version du format. |
| `title` | texte | oui | Titre du travail (bandeau). |
| `module` | texte | non | Code du module, ex. `"M109"`. |
| `course` | `{id, slug, title}` ou `null` | non | Cours Sensei. L'import le retrouve par id, puis slug, puis titre, puis code du module. |
| `lessons` | liste de `{id, slug, title}` | non | Leçons concernées, **titres sans le préfixe du module** (ex. `"9.1 Autorisation d’accès"`). Retrouvées par id, slug, titre ou numéro. Liste vide = pas de tuiles de leçons. |
| `graded` | booléen | non (`true`) | `true` = noté sur 6 avec grille obligatoire ; `false` = activité formative, sans note. |
| `group_size` | entier 1 à 6 | non (`2`) | 1 = individuel, 2 = duo, 3 = trio… Au dépôt, chaque apprenti choisit `group_size - 1` camarades. |
| `allow_solo` | booléen | non (`false`) | Accepte des groupes incomplets. |
| `formats` | liste | non (`["pptx"]`) | Parmi `pptx`, `pdf`, `docx`, `xlsx`, `odp`, `key`, `img` (.jpg/.png), `mp4`, `zip`. **L'IA de LogiProjet ne corrige que les `.pptx`** ; les autres formats se notent à la main. |
| `date_debut` | `"AAAA-MM-JJTHH:MM"` ou `null` | non | Heure suisse. `null` = zone jaune `jj.mm.aaaa` dans le Word. |
| `due_at` | `"AAAA-MM-JJTHH:MM"` ou `null` | non | Date de fin et de rendu. Ne jamais l'inventer. |
| `oral_bonus` | booléen | non (`true`) | Bonus de +0.5 si le groupe tiré au sort obtient 4 / 6 à l'oral (seulement si `graded`). |
| `grille` | liste de critères | oui si `graded` | Voir plus bas. |
| `consigne` | objet | oui | Le texte, section par section. Voir plus bas. |
| `consignes` | texte | non | Ancien champ texte. LogiProjet le reconstruit lui-même à partir de `consigne`. |
| `groups` | liste de noms de classes | non | Ex. `["LOG 2A – 2026"]`. Retrouvées par nom ; sinon l'enseignant les coche. |
| `auteur`, `formation` | texte | non | Pied de page du Word (ex. `"Fontes Carlos"`, `"CFC Logisticien·ne"`). |
| `consigne_pdf` | `{name, data}` (base64) | non | PDF final de la consigne, versé dans la médiathèque. **Seulement s'il contient déjà les dates.** |
| `consigne_url` | URL | non | Lien vers un PDF déjà en ligne. |

## La grille

```json
{"id": "c1", "bloc": "A · Contenu des 5 leçons du M109", "label": "9.1 Autorisation d’accès : contenu juste et bien expliqué", "lesson_id": 30612}
```

- Chaque critère vaut **0, 1 ou 2** : total T = 2 × nombre de critères. Note = points ÷ T × 5 + 1, arrondie au demi-point (LogiProjet calcule le tableau).
- `id` en minuscules et unique (`c1`, `c2`…). Les critères d'un même `bloc` se suivent. Le `bloc` commence par une lettre : `A · …`, `B · …`.
- Un `label` qui commence par un numéro de leçon (`9.1 Titre : …`) s'affiche avec le titre en gras.
- 40 critères au maximum. Pour une activité non notée, la grille devient « Critères de réussite » (elle peut aussi être vide).

## La consigne

```json
{
  "note": "Encadré du haut, un seul paragraphe",
  "mission": ["paragraphe", "paragraphe"],
  "actions": ["① Expliquer | Dites ce que c’est…", "② Donner un exemple | …"],
  "plan": ["Slide 1 | **Titre** : …", "Dernière | **Sources** : …"],
  "faire": ["Écrire avec **vos propres mots**"],
  "eviter": ["Copier-coller le cours, Internet ou une IA"],
  "etapes": ["une ligne par période (le nombre de lignes = nombre de périodes)"],
  "rendu": ["Un fichier **PowerPoint (.pptx)** par duo", "Rendu au plus tard le {date_fin}"],
  "jourj": ["…"],
  "checklist": ["…"],
  "titres": {"contenu": "Ce que vous devez analyser"},
  "intro_lecons": "Votre présentation couvre **les {n} leçons du module {module}** sur logiprof :",
  "intro_actions": "Pour **chaque leçon**, votre présentation doit faire ces {k} choses :"
}
```

- Chaque liste = une ligne par élément, 20 lignes au maximum, 600 caractères par ligne. Une liste vide ou absente = section masquée.
- `actions` et `plan` : `Titre | texte`.
- `**mot**` = gras. Pas d'autre mise en forme (pas de HTML, pas de Markdown).
- Repères remplacés à l'affichage : `{module}`, `{total}` (points de la grille), `{n}` (nombre de leçons), `{lecons}` (ex. « 9.1 à 9.5 »), `{periodes}`, `{date_debut}`, `{date_fin}`, et `{k}` dans `intro_actions`.
- Les sections sont numérotées automatiquement dans l'ordre : mission, contenu (leçons + actions), plan, règles (`faire` / `eviter`), étapes, rendu, jour J, check-list. Le Word renvoie à « la page 3 » pour la grille ; sur le site, LogiProjet écrit « ci-dessous ».
- `titres` (facultatif) remplace les titres par défaut. Clés : `mission`, `contenu`, `plan`, `page2` (titre de la 2e page du Word), `regles`, `etapes`, `rendu`, `jourj`, `checklist`, `grille`. Indispensable dès que l'activité n'est pas une présentation (ex. `"plan": "Le plan de votre dossier"`, `"jourj": "La mise en commun"`).
- `intro_lecons` / `intro_actions` : chaîne vide `""` = pas de phrase (et pas de tuiles de leçons pour `intro_lecons`).
- `references/modele-consigne.json` contient le modèle exact de cette version de LogiProjet (noté et non noté). Le reprendre puis l'adapter garantit le même ton que les autres travaux.
