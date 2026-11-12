# Gestion des classes d'apprentis

Plugin WordPress natif pour centraliser le suivi des classes d'apprentis : imports CSV/XLSX, compteurs de retards/absences/mises à la porte, tableau des situations à traiter, e-mails, PDF de convocation et historique.

## Shortcode

Placer le shortcode suivant sur une page WordPress protégée :

```text
[gestion_apprentis]
```

Le plugin ajoute aussi un menu `LogiClass` dans l'administration WordPress.

Sur une page du site, le shortcode affiche l'interface en pleine largeur de fenêtre afin d'éviter que le thème WordPress coupe les grands tableaux.

## Fonctionnalités incluses

- Tables dédiées pour profs, formateurs, classes, apprentis, notes, imports, communications, événements, journal d'audit et snapshots.
- Permission dédiée `gca_manage`, ajoutée au rôle administrateur lors de l'activation.
- Menu WordPress admin `LogiClass`, avec les sous-menus `À traiter`, `Classes`, `Apprentis`, `Formateurs`, `Actions exécutées`, `Communication`, `Messages types`, `Imports` et `Paramètres`.
- Noms d'apprentis cliquables vers la fiche dans une nouvelle page, et classes cliquables vers la liste de leurs élèves.
- Vue `Apprentis` pour voir toutes les fiches actives et mettre à jour rapidement les fiches élèves : genre, e-mail élève, téléphone élève, formateur responsable via une liste des formateurs enregistrés, entreprise formatrice et e-mail du responsable. Les apprentis peuvent aussi être sélectionnés en groupe pour appliquer rapidement un statut `Vérifié`, `À vérifier` ou un genre.
- Vue `Formateurs` en pleine largeur pour centraliser les responsables, l'entreprise formatrice, son adresse complète, l'e-mail du responsable issu de `Entreprise formatrice Email`, la formule d'appel et les apprentis rattachés. Le formulaire d'ajout s'ouvre uniquement depuis un bouton dédié. Une mise à jour du responsable est recopiée dans les fiches élèves liées.
- Export complet des listes `Apprentis` et `Formateurs` en CSV UTF-8, avec les données de contact, de suivi, de vérification, de contrat et les champs protégés contre les imports.
- Badges `Vérifié` / `À vérifier` sur les fiches apprentis et formateurs, avec filtres dédiés pour contrôler les données déjà relues.
- Protection des champs corrigés ou vérifiés manuellement : les imports suivants ne les écrasent pas.
- Les compteurs de suivi restent actualisés à chaque import Lagap : périodes d’absence, taux d’absence, retards, mises à la porte et commentaires ne sont pas verrouillés par les corrections manuelles.
- Statut de contrat dans la fiche apprenti : contrat actif par défaut, rupture de contrat avec date, candidat libre, suivi école pendant 3 mois pour les ruptures et alerte automatique à 3 mois + 1 semaine.
- Création et édition des classes : nom, année scolaire, statut et liste des apprentis.
- Suppression possible des classes, apprentis, actions à traiter et actions exécutées.
- Import CSV natif et import XLSX si l'extension PHP `ZipArchive` est disponible.
- Préréglage `Import Lagap-EP` pour les exports `Recherche d'élèves` exportés en CSV.
- Imports reliés au compte prof qui les prépare et les valide.
- Fichier source d'import conservé dans un dossier privé avec empreinte SHA-256.
- Mapping Lagap des colonnes `Formateur responsable`, `Entreprise formatrice`, `Entreprise formatrice Adresse`, `Entreprise formatrice NPA`, `Entreprise formatrice Ville`, `Entreprise formatrice Email`, `E-mail principal de l’élève`, `Nb de retards annuel`, `Nb d'absences annuel`, `Taux d'absence annuel`, `Nb de mises à la porte annuel` et `Nb de commentaires annuel`.
- Mapping de colonnes avec aperçu avant validation.
- Mapping d'import sauvegardé par profil prof et réappliqué automatiquement lorsque les colonnes du prochain fichier correspondent.
- Import de notes depuis l'interface dédiée aux notes, avec mapping sauvegardable. La classe, le nom et le prénom servent uniquement au rattachement ; seules l'évaluation et la note obtenue sont récupérées.
- Import des photos depuis un PDF de liste de classe, avec prévisualisation, proposition automatique par prénom/nom exacts, attribution manuelle possible et sélection des élèves par classe. Les emplacements `NO IMAGE` sont ignorés afin d'éviter tout décalage des photos suivantes.
- Détection des seuils de retards 3, 6, 7 selon l'ordre de procédure demandé.
- Alertes d'absence séparées : validation sans e-mail possible, action à traiter au-delà de 21 jours/périodes d'absences injustifiées, et action critique dès 30 % d'absences injustifiées.
- Justification locale des absences : depuis une action à traiter ou directement depuis l’onglet `À traiter`, le jour et le nombre de périodes justifiées sont mémorisés dans la fiche élève, conservés après les imports, et les nouvelles alertes se basent sur le solde non justifié.
- Gestion des e-mails via `wp_mail`.
- Expéditeur configurable : le plugin force le nom et l'e-mail définis dans `Paramètres` via WordPress et PHPMailer. La configuration SMTP doit autoriser cette adresse.
- Envois externes manuels : les convocations, rapports et messages aux formateurs partent uniquement après une action manuelle de l'utilisateur.
- Rappels internes automatiques pour les actions en attente, les ruptures de contrat arrivées à échéance et les imports Lagap à actualiser.
- Génération du PDF de retenue à partir du formulaire CPNV, sans dépendance externe obligatoire, avec motif et remarque adaptés pour la 2e convocation.
- Date de convocation affichée uniquement pour les situations qui génèrent une convocation.
- Génération de rapports formateurs individuels et téléchargement groupé par classe en ZIP, avec les notes récupérées lorsqu'elles existent.
- Formules d'appel et coordonnées des formateurs responsables éditables une seule fois par fiche formateur.
- Module `Messages types` séparé pour créer et modifier les modèles libres et les modèles d'envois de masse, sans envoyer d'e-mail depuis cet écran.
- Modèles de messages libres réutilisables depuis les situations à traiter, avec objet, texte formaté et variables de l'apprenti.
- Envois de masse aux formateurs : messages préformatés avec texte formaté, choix de plusieurs classes, un e-mail individuel par e-mail du responsable, élèves rattachés en copie optionnelle, liste automatique des apprentis avec leur numéro court de classe et envoi de test vers une ou plusieurs adresses définies. Les apprentis en rupture de contrat ou candidats libres sont signalés et exclus des envois formateur.
- Les modèles de communication acceptent aussi les repères lisibles `{{Prénom Nom}}` et `{{Numéro de classe}}` dans les listes à puces ; ils sont remplacés par les apprentis réellement rattachés au destinataire, avec la même police que le reste du message.
- Compteurs du tableau de bord incluant les actions à traiter et les actions déjà traitées.
- Compteurs du tableau de bord cliquables vers les classes, les situations à traiter, les actions exécutées et les exclusions en cours.
- Filtre par type de problème dans les situations à traiter.
- Enregistrement des retenues manquées depuis l'onglet `Actions exécutées`.
- Signature de message paramétrable avec texte formaté et image.
- Stockage privé des PDF et photos sous `wp-content/uploads/gestion-apprentis/private`.
- Historique visible des actions déjà entreprises, regroupé par classe : convocations, e-mails au Doyen, e-mails individuels aux formateurs, mises à la porte traitées, avertissements et exclusions datées. Les noms d'apprentis et de formateurs affichent l'entreprise formatrice lorsqu'elle est renseignée.
- Séparation des événements à traiter : plusieurs actions pour un même apprenti apparaissent en lignes distinctes.
- Journal d'audit interne pour conserver les actions historisées.
- Copie cachée automatique vers `carlos.fontes@eduvaud.ch` pour recevoir le formulaire à imprimer.
- Date de convocation obligatoire avec calendrier uniquement pour les e-mails de convocation.
- Adresse d'expédition configurable dans les paramètres.
- Signalement d'une retenue manquée, repris automatiquement dans les convocations suivantes.
- Édition manuelle des fiches, compteurs, statut de contrat, date de rupture, genre, photo, données de contact, avertissement reçu, exclusions avec périodes multiples et convocation à un conseil de savoir-être.
- Affichage des notes récupérées dans la fiche apprenti et dans l'export CSV individuel.
- Export CSV individuel d'une fiche apprenti, en plus des exports complets des listes d'apprentis et de formateurs.

## Notes techniques

Le plugin évite les dépendances Composer afin de rester compatible avec un hébergement mutualisé. Le générateur PDF reproduit le formulaire `3 Retenue.docx` fourni dans `docs/reference/3-retenue.docx` et intègre le logo CPNV depuis `assets/templates/cpnv-logo.jpeg`.

Les données métier ne sont pas stockées dans `wp_posts` ou dans les contenus du site. Elles utilisent des tables WordPress dédiées avec le préfixe `gca_`. Sur un hébergement standard, ces tables vivent dans la même base MySQL que WordPress, mais elles restent logiquement séparées du reste du site.
