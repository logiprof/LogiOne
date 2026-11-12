# Publier une nouvelle version de LogiOne

Les sites WordPress qui ont LogiOne 1.38.0 ou plus récent vérifient ce dépôt environ deux fois par jour.
Dès qu'une **release** plus récente existe, WordPress propose la mise à jour (Extensions › « Mettre à jour »).

## Étapes

1. Changer le numéro de version dans `logipilot.php` (deux endroits : `Version:` et `LOGIPILOT_VERSION`).
2. Ajouter une section `## Nouveautés X.Y.Z` en haut du `README.md` : elle devient la note de version affichée dans WordPress.
3. Valider (commit) et pousser sur `main`.
4. Créer l'étiquette et la pousser :
   ```
   git tag v1.38.1
   git push origin v1.38.1
   ```
5. L'action GitHub « Publier une version » vérifie le numéro, construit `logione-X.Y.Z.zip` (dossier `logipilot` à
   l'intérieur) et crée la release. C'est tout.

## Clé d'accès des sites (dépôt privé)

Chaque site a besoin d'une clé GitHub **en lecture seule** : écran **LogiOne › Mises à jour**, ou dans `wp-config.php` :

```php
define( 'LOGIONE_GITHUB_TOKEN', 'github_pat_…' );
```

Création : GitHub › Settings › Developer settings › Personal access tokens › **Fine-grained tokens** ›
*Repository access* : `logiprof/LogiOne` seulement › *Permissions* : **Contents : Read-only**.
Si le dépôt devient public, la clé n'est plus nécessaire.

## Sécurité des données

Une mise à jour remplace uniquement les fichiers de l'extension. Les tables et réglages (LogiNotes, LogiExam,
LogiProgress, LogiClass) ne sont pas modifiés. Une sauvegarde du site avant une mise à jour importante reste conseillée.
