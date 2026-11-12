# LogiNotes

Plugin WordPress autonome pour saisir les groupes classes, élèves, branches, semestres, évaluations et notes.

## Installation

1. Copier le dossier `loginotes` dans `wp-content/plugins/`.
2. Activer le plugin `LogiNotes` depuis l'administration WordPress.
3. Utiliser le menu `LogiNotes`.

Le shortcode suivant peut aussi être placé sur une page protégée :

```text
[loginotes]
```

L'ancien shortcode `[gestion_notes]` reste disponible pour compatibilité.

Sur une page, l'interface rendue par shortcode occupe toute la largeur de la fenêtre, même si le thème limite normalement la largeur du contenu.

## Fonctionnalités

- Tables dédiées préfixées `gne_`, indépendantes du plugin de gestion de classe existant.
- Création de groupes classes avec uniquement le numéro de classe.
- Import CSV, TXT ou Excel `.xlsx`, ou collage direct d’élèves.
- Création et mise à jour des élèves avec uniquement nom et prénom.
- Création de branches dans une interface dédiée, puis sélection de la branche lors de la création ou modification d'une évaluation.
- Saisie des semestres avec année scolaire, dates, ordre et statut.
- Saisie d'une évaluation avec module, branche, date, points totaux, groupe classe puis points obtenus par élève.
- Rattrapage par élève, avec date obligatoire lorsque la case rattrapage est cochée.
- Bonus/malus par élève : aucun ajustement, bonus `+0,5`, `+1`, `+1,5`, ou malus `-0,5`, `-1`, `-1,5`, `-2`, `-2,5`.
- Justificatif PDF bonus/malus attaché à l'évaluation et téléchargé uniquement après contrôle des droits.
- Calcul automatique côté navigateur et côté serveur : `((points obtenus + bonus/malus) x 5 / points totaux) + 1`, arrondi au 0.5.
- Statut par élève et par évaluation : notée, en attente ou `Ne sera pas faite`.
- Relevé de notes par groupe classe et semestre avec moyenne simple des notes saisies, affichée au dixième.
- Export PDF d'une évaluation avec groupe classe, branche, semestre, date, points totaux, bonus/malus, total des points ajustés par élève, rattrapages, moyenne au dixième, lien protégé vers le justificatif PDF et toutes les notes des élèves.
- Export PDF d'un semestre entier pour un groupe classe, avec tous les tests du semestre, les notes par élève et la moyenne automatiquement calculée au dixième.
- Alerte automatique pour les notes manquantes : si une évaluation date d'au moins 15 jours, qu'au moins un camarade a déjà une note et qu'un élève reste sans note, un e-mail de rappel est envoyé à l'adresse définie dans `Paramètres`. Pour une ligne en rattrapage, le délai part de la date de rattrapage.

## Import des élèves

Le fichier peut être en `.csv`, `.txt` ou `.xlsx`, séparé par point-virgule, virgule ou tabulation pour les formats texte.

```csv
classe;nom;prenom
CLLOG-C2b;Dupont;Marie
CLLOG-C2b;Martin;Lucas
```

Depuis la fiche d’un groupe classe, le numéro de classe est déjà connu. Il suffit alors d’importer ou de coller :

```csv
nom;prenom
Dupont;Marie
Martin;Lucas
```

## Sécurité et séparation des données

- Les données sont stockées dans des tables WordPress dédiées préfixées `gne_` : `gne_classes`, `gne_students`, `gne_branches`, `gne_semesters`, `gne_evaluations`, `gne_grades` et `gne_settings`.
- Le plugin ne stocke pas les notes dans `wp_posts`, `wp_postmeta` ou les contenus du site.
- L'accès aux écrans et aux exports est limité aux utilisateurs connectés disposant du droit `gne_manage`, `gca_manage` ou `manage_options`.
- Les formulaires et l'export PDF utilisent les nonces WordPress.
- L'import d'élèves lit le fichier temporaire envoyé par WordPress et ne conserve pas le fichier après traitement.
- Les justificatifs PDF sont stockés dans un dossier dédié `loginotes-documents`, protégé contre l'accès direct lorsque le serveur le permet, et servis par une action WordPress qui exige une connexion avec un compte autorisé.
- Les requêtes SQL variables passent par les requêtes préparées WordPress, et le helper de tables refuse les noms de tables non prévus.
- L'e-mail de rappel est conservé dans `gne_settings`, pas dans les contenus publics du site.

La désactivation du plugin conserve les données.
