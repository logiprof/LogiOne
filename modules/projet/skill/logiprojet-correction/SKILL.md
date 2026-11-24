---
name: logiprojet-correction
description: Corrige un export de correction LogiProjet (.zip ou correction.json, format « logiprojet-correction-export ») selon sa grille 0/1/2 et rend un fichier JSON vérifié à réimporter dans LogiProjet.
---

# Corriger un export LogiProjet

Compétence téléchargée depuis LogiProjet {{VERSION}} sur {{SITE_NAME}} ({{SITE_URL}}).

L'enseignant exporte les dépôts d'un travail depuis LogiProjet (**Corriger dans Claude → Exporter pour Claude**). Il envoie ici le `.zip` et attend en retour **un seul fichier** `correction_LogiProjet.json`. Il l'importera dans LogiProjet : tes notes y arrivent comme des **propositions**, et c'est lui qui valide.

## 1. Lire l'export

1. Ouvrir le `.zip` et lire `correction.json`. Si l'enseignant n'a envoyé que `correction.json`, travailler avec, et signaler les dépôts dont le `fichier` manque.
2. Dans `correction.json` :
   - **`travail`** : le titre, le module, `notee` (true/false), la taille du groupe, la `consigne` complète et la `grille` (id, bloc, label).
   - **`lecons`** : le texte de référence de chaque leçon du cours. C'est la base pour juger si le contenu est juste.
   - **`regles`** : les règles de correction. **Les suivre toutes.**
   - **`reponse_attendue`** : le modèle exact du fichier à rendre.
   - **`depots`** : chaque dépôt a une `ref` (D01, D02…) et un numéro `depot` :
     - `extrait` : le contenu d'un PowerPoint, slide par slide (texte, notes du présentateur, nombre d'images, de tableaux et de graphiques) ;
     - `fichier` : le chemin du fichier joint dans le zip (`depots/D01.pdf`…), s'il y en a un ;
     - `remarque` : une précision à lire.

## 2. Corriger chaque dépôt

Corriger **un dépôt à la fois, en entier**, avant de passer au suivant. S'il y a plus de 6 dépôts, écrire la correction au fur et à mesure dans le fichier JSON pour ne rien perdre.

- **PowerPoint** : lire l'`extrait`. Si le `.pptx` est joint, regarder aussi les slides : lisibilité, images utiles, quantité de texte. Utiliser la compétence pptx si elle est disponible.
- **PDF ou image** : lire le fichier joint (compétence pdf si disponible), page par page.
- **Dépôt sans extrait ni fichier** : ne pas l'inventer. Le laisser hors du fichier et le signaler à l'enseignant.

Pour **chaque critère** de la grille :
- 0 = absent ou faux, 1 = partiel ou imprécis, 2 = atteint. Ne pas donner 2 par défaut.
- Comparer au texte des `lecons`. Une erreur de fond fait baisser le critère.
- La justification tient en **une phrase** courte et précise, avec la slide ou la page (« Slide 4 : définition juste, mais aucun exemple. »). Elle s'adresse à l'enseignant et aux apprentis : pas de jargon.
- Mettre l'id dans `a_revoir` quand on hésite entre deux notes, ou qu'on n'a pas pu vérifier (image illisible, par exemple).
- `copie_suspecte: true` si plusieurs lignes semblent recopiées mot pour mot des leçons.

Ensuite, le **retour aux apprentis** :
- `points_forts` : exactement 2 phrases concrètes ;
- `piste` : 1 amélioration précise et faisable ;
- vouvoiement, ton bienveillant, français de Suisse romande ;
- **aucun nom**, même si un nom figure sur le PDF ou sur la première slide. Dans les extraits, les noms sont déjà remplacés par [Apprenti 1], [Apprenti 2]… ;
- activité non notée (`notee: false`) : jamais de note sur 6 dans le retour.

Enfin, le `resume` : 2 phrases pour l'enseignant seulement (niveau général, ce qu'il faut regarder).

Ne pas calculer de note dans le fichier : LogiProjet la calcule à partir des points.

## 3. Écrire le fichier

Suivre exactement `reponse_attendue` :

```json
{
  "format": "logiprojet-correction",
  "version": 1,
  "travail_id": 12,
  "modele": "Claude Opus 5.5",
  "depots": [
    {
      "ref": "D01",
      "depot": 34,
      "criteres": [ { "id": "c1", "score": 2, "justification": "Slide 3 : définition juste, avec un exemple de l’entrepôt." } ],
      "points_forts": ["…", "…"],
      "piste": "…",
      "resume": "…",
      "a_revoir": ["c4"],
      "copie_suspecte": false
    }
  ]
}
```

Règles du fichier :
- `travail_id` est le même que `travail.id` de l'export.
- `ref` et `depot` sont recopiés tels quels de l'export.
- Chaque critère de la grille apparaît une fois par dépôt.
- `modele` : le nom du modèle Claude utilisé.

## 4. Vérifier, puis livrer

```bash
python3 scripts/verifier_correction.py export.zip correction_LogiProjet.json
```

Corriger chaque ERREUR et relire chaque ligne « À RELIRE ». Le script affiche aussi une note indicative par dépôt pour un travail noté : la parcourir pour repérer une correction trop sévère ou trop généreuse par rapport aux autres groupes, et ajuster si besoin.

Livrer :
1. le fichier `correction_LogiProjet.json` ;
2. un tableau court : ref, points, note indicative (seulement si le travail est noté), critères à revoir, copie suspecte ;
3. les dépôts non corrigés et pourquoi ;
4. le rappel : dans LogiProjet, ouvrir le travail → **Corriger dans Claude → Importer la correction (.json)**, puis ouvrir chaque dépôt, vérifier et valider.

L'enseignant ne connaît les groupes que par leur ref dans ce fichier : ne pas essayer de retrouver qui est derrière D01, D02…
