# Notes Techniques v0.2.70

## Décisions Confirmées

- Le plugin vise une version complète des fonctionnalités du cahier des charges.
- Le site WordPress n'existe pas encore et sera hébergé chez Infomaniak.
- Les seuils de retards par défaut sont 3, 6 et 7.
- Si un import fait passer un apprenti directement à 7 retards ou plus, la procédure reste séquentielle : convocation 3, puis convocation 6, puis notification Doyen.
- Une convocation déjà envoyée à 3 retards n'est pas proposée une seconde fois.
- La communication à 6 retards explique le nombre de retards supplémentaires depuis le seuil précédent et affiche le compteur total atteint.
- La communication à 6 retards reprend la date de la première convocation envoyée pour les 3 premières arrivées tardives.
- Le PDF de 2e convocation indique `Convocation pour 6 arrivées tardives` dans le motif et place la référence à la première convocation dans la remarque.
- Les variables de retards sont explicites : `{lateness_count}` et `{lateness_total}` affichent le total courant, avec un minimum égal au seuil de la tâche ; `{lateness_previous_threshold}` reprend le seuil précédent et `{lateness_additional_count}` calcule les retards supplémentaires depuis ce seuil.
- Les mises à la porte sont gérées comme un simple compteur.
- Les fiches apprentis, classes, actions à traiter et actions exécutées restent modifiables.
- Une vue `Apprentis` centralise les fiches actives pour les corrections rapides des champs simples et du formateur rattaché.
- La vue `Apprentis` permet aussi de sélectionner plusieurs fiches affichées et d'appliquer une action groupée : marquer comme vérifiées, remettre à vérifier ou définir le genre.
- Une vue `Formateurs` en pleine largeur centralise les coordonnées, l'entreprise, l'adresse complète et les rattachements des formateurs responsables. Le formulaire d'ajout est replié par défaut et s'ouvre depuis un bouton. Les données d'un responsable enregistré sont propagées aux fiches élèves liées.
- Dans la liste des formateurs, la colonne d'action garde une largeur fixe pour que le bouton `Ouvrir` reste horizontal.
- Les fiches apprentis et formateurs disposent d'un statut de vérification indépendant, affiché par badge et filtrable dans les listes.
- Les champs modifiés ou vérifiés manuellement dans les fiches apprentis et formateurs sont mémorisés dans `manual_fields_json` et ne sont plus écrasés par les imports suivants.
- Les listes globales `Apprentis` et `Formateurs` peuvent être exportées en CSV UTF-8 séparé par point-virgule, avec les champs complets et les rattachements utiles.
- Les communications aux formateurs utilisent uniquement l'e-mail du responsable de formation issu de `Entreprise formatrice Email`.
- Les apprentis ont un contrat actif par défaut. Une rupture peut être datée dans la fiche, avec suivi école pendant 3 mois et action automatique de contrôle à 3 mois + 1 semaine. Le statut `Candidat libre` est aussi disponible ; en rupture ou candidat libre, aucune adresse formateur n'est utilisée pour les communications, mais les situations restent visibles dans `À traiter`.
- Les alertes d'absence ne sont plus créées uniquement sur le taux : le délai de démarrage après le début des cours et un nombre minimal de périodes d'absence doivent aussi être atteints.
- Les rapports formateurs sont générés individuellement, y compris lors d'un export groupé de classe.
- Le module `Messages types` est séparé de `Communication` : il permet de gérer les modèles d'e-mails libres et les modèles d'envois de masse sans déclencher d'envoi.
- Les e-mails libres disposent de modèles stockés dans l'option `gca_free_message_templates` et peuvent préremplir l'objet et le corps du message depuis une situation à traiter.
- Les fiches apprentis permettent de signaler manuellement un avertissement reçu, plusieurs exclusions avec dates de début et de fin, et une convocation à un conseil de savoir-être.
- Les noms d'apprentis affichés dans les vues du plugin ouvrent la fiche dans une nouvelle page ; les classes affichées ouvrent la liste de leurs apprentis.
- Les modèles de communication aux formateurs remplacent aussi les repères `{{Prénom Nom}}` et `{{Numéro de classe}}`; une liste HTML ou texte contenant ces repères est développée selon les apprentis réellement liés au destinataire, sans conserver une balise `pre`, `code`, `span` ou `font` accidentelle autour des données générées.
- Les notes importées depuis l'interface dédiée aux notes sont stockées dans `gca_grades` et rattachées uniquement lorsqu'une classe active et une fiche apprenti existante correspondent.
- Le shortcode `[gestion_apprentis]` force un affichage pleine largeur sur les pages front-end afin de ne pas être limité par la largeur de contenu du thème WordPress.
- Les compteurs importés depuis Lagap (`absence_count`, `absence_rate`, `lateness_count`, `removal_count`, `comment_count`) restent toujours pilotés par le dernier import et ne sont plus protégés contre l'écrasement par les corrections manuelles ou le statut `Vérifié`.
- Le nombre de périodes d'absence justifiées dans le plugin est stocké séparément dans `absence_justified_count`. Les justificatifs datés sont conservés dans `gca_absence_justifications` avec l’apprenti, le jour et le nombre de périodes excusées. Les imports mettent à jour les absences brutes, mais ne modifient pas ce compteur local ; les alertes d'absence utilisent le solde `absence_count - absence_justified_count` et un taux recalculé proportionnellement. Dans les situations à traiter, le formulaire de justification n'apparaît que lorsqu'il reste des périodes non justifiées et son libellé est adapté au motif de l'alerte.

## Identification

L'export ne contient pas d'identifiant unique. La clé fonctionnelle retenue est donc :

```text
classe + nom + prénom
```

Cette hypothèse repose sur le fait qu'il n'y a jamais deux apprentis dans la même classe avec le même nom et le même prénom.

Si cette clé ne retrouve aucune fiche, l'import tente aussi une correspondance par `E-mail principal de l'élève`, uniquement lorsqu'une seule fiche active correspond à cette adresse. Cela permet de conserver les corrections manuelles de nom, prénom ou classe sans créer de doublon quand l'e-mail reste stable.

Pour l'import de notes, la règle est plus stricte : la classe doit exister et le nom/prénom doivent correspondre dans cette classe. Aucune correspondance de secours par e-mail n'est utilisée, afin d'éviter d'attribuer une note à la mauvaise fiche.

## Imports Lagap

Les exports Lagap peuvent fournir notamment :

- `Nom`
- `Prénom`
- `Nb d'absences annuel`, importé comme nombre de périodes d'absence
- `Nb de mises à la porte annuel`
- `Nb de retards annuel`
- `Taux d'absence annuel`
- `Classe(s)`
- `N de téléphone du formateur responsable`
- `Formateur responsable`
- `Entreprise formatrice`
- `Entreprise formatrice Adresse`
- `Entreprise formatrice NPA`
- `Entreprise formatrice Ville`
- `Entreprise formatrice Email`
- `E-mail principal de l’élève`
- `Sexe officiel` / `Genre`

La colonne `E-mail principal de l’élève` alimente l'adresse principale de convocation. `Formateur responsable` alimente le nom du responsable. `Entreprise formatrice`, `Entreprise formatrice Adresse`, `Entreprise formatrice NPA` et `Entreprise formatrice Ville` alimentent l'entreprise et son adresse complète. `Entreprise formatrice Email` alimente l'e-mail du responsable de formation utilisé pour les communications.

Le mapping validé par un prof peut être sauvegardé dans l'option `gca_import_mapping_profiles`. La sauvegarde est structurée par `teacher_id` et par type d'import. Au prochain import, le mapping est réappliqué uniquement si toutes les colonnes sauvegardées existent encore exactement dans le fichier analysé.

## Import Photos PDF

Le PDF de liste de classe fourni par Lagap contient les photos sous forme d'images RGB compressées et les noms d'élèves dans les textes alternatifs `/Alt`.

Le plugin :

- extrait les noms `/Alt` ;
- relie chaque nom `/Alt` à l'objet image référencé par son identifiant de contenu PDF ;
- extrait les images `FlateDecode` RGB ou les JPEG `DCTDecode` utiles ;
- convertit les images en JPEG ;
- ignore les placeholders `NO IMAGE` afin qu'une photo manquante ne décale jamais les attributions suivantes ;
- stocke les vignettes dans un dossier temporaire privé pendant la validation ;
- prépare une proposition avec la classe de la fiche uniquement si le `Prénom Nom` du PDF correspond exactement à une seule fiche existante ;
- permet une attribution manuelle vers une autre fiche lorsque le système ou le PDF contient une erreur ;
- bloque les doublons afin qu'une fiche ne reçoive pas deux photos lors du même import.

Cet import est historisé dans `gca_imports` avec le type `photos_pdf`.

## Import Notes

L'import de notes accepte CSV et XLSX. Les champs obligatoires sont `Classe`, `Nom`, `Prénom`, `Évaluation` et `Note`.

`Classe`, `Nom` et `Prénom` sont uniquement des clés de rattachement à une fiche apprenti existante. Les seules données de notes récupérées et affichées dans le plugin sont `Évaluation` et `Note`.

Les lignes sont traitées ainsi :

- la classe importée est comparée aux classes actives de l'année scolaire courante, en acceptant le nom complet ou le numéro court de classe ;
- l'apprenti est cherché dans cette classe par nom et prénom ;
- si la fiche n'est pas trouvée, la ligne est ajoutée aux erreurs de l'import ;
- si la note existe déjà pour la même fiche et la même évaluation, elle est mise à jour ;
- si `Ignorer la ligne existante` est choisi, une note déjà connue est sautée ;
- les notes sont affichées dans la fiche apprenti, incluses dans l'export individuel, reprises dans l'export complet des apprentis et ajoutées aux rapports PDF formateurs.

Cet import est historisé dans `gca_imports` avec le type `grades`.

## Profils Profs Et Imports

Chaque utilisateur WordPress qui utilise le plugin est copié dans la table interne `gca_teachers` avec son identifiant WordPress, son nom affiché, son e-mail et ses rôles au moment de l'action.

Les imports stockent `created_by`, `teacher_id`, `source_sha256`, `source_size` et `source_path`. La validation d'un import est limitée au même utilisateur que celui qui a préparé le fichier. Les mappings sauvegardés sont également liés au profil prof interne afin qu'un réglage d'import d'un utilisateur ne s'applique pas au compte d'un autre.

## Audit Et Sauvegarde

Le plugin utilise des tables dédiées `gca_*` et ne stocke pas ses données métier dans `wp_posts`.

Tables principales :

- `gca_teachers` : copie locale des profils profs ;
- `gca_trainers` : fiches formateurs responsables créées ou confirmées manuellement, avec entreprise, adresse complète, e-mail d'envoi, coordonnées, formule d'appel éditable et statut de vérification ;
- `gca_grades` : notes récupérées depuis l'interface dédiée aux notes, liées à l'apprenti, à la classe et à l'année scolaire ;
- `gca_imports` : imports et empreintes de fichiers ;
- `gca_logs` : journal d'audit consultable ;
- `gca_snapshots` : sauvegardes techniques horodatées des actions ;
- `gca_events` : historique visible par fiche apprenti ;
- `gca_absence_justifications` : justificatifs d'absence datés par apprenti, avec nombre de périodes excusées, source et auteur ;
- `gca_disciplinary_exclusions` : exclusions multiples par apprenti, avec période, durée calculable, note, auteur et profil prof ;
- `gca_students.gender` et `gca_students.photo_path` : genre et photo privée utilisés pour les rapports ;
- `gca_students.trainer_id`, `trainer_name`, `trainer_company`, `trainer_company_address`, `trainer_company_zip`, `trainer_company_city`, `trainer_email`, `trainer_phone` : rattachement et copie locale du formateur responsable, de l'entreprise et de l'e-mail du responsable ;
- `gca_students.field_trainer_name`, `field_trainer_email`, `field_trainer_phone` : anciens champs conservés pour compatibilité, non affichés et non utilisés par la logique actuelle ;
- `gca_students.contract_status` et `gca_students.contract_break_date` : statut du contrat de formation, avec `active`, `broken` ou `free_candidate`, et date de rupture éventuelle ;
- `gca_students.disciplinary_warning_received`, `gca_students.expulsion_received` et `gca_students.wellbeing_council_convoked` : résumé des décisions ou convocations disciplinaires renseignées dans la fiche. Les périodes d'exclusion détaillées sont stockées dans `gca_disciplinary_exclusions`.
- `gca_students.verified_at`, `gca_students.verified_by`, `gca_trainers.verified_at` et `gca_trainers.verified_by` : date et utilisateur WordPress ayant marqué la fiche comme vérifiée.
- `gca_students.manual_fields_json` et `gca_trainers.manual_fields_json` : liste des champs modifiés ou validés manuellement. L'import saute ces champs afin de conserver la donnée contrôlée dans le plugin.

Sur un hébergement WordPress standard, ces tables restent dans la même base MySQL physique que WordPress, mais elles sont logiquement indépendantes du contenu du site.

Les handlers `gca_export_students` et `gca_export_trainers` passent par `admin-post.php`, vérifient le droit `gca_manage`, contrôlent un nonce dédié et journalisent l'export dans `gca_logs`. Le CSV contient un BOM UTF-8 et utilise `;` comme séparateur pour faciliter l'ouverture dans Excel en environnement francophone.

## Formateurs Et Modifications Rapides

L'onglet `Apprentis` lit les fiches actives avec leur classe et permet de mettre à jour `gender`, `email`, `phone`, `trainer_id` et le statut de vérification. Les noms d'apprentis affichent l'entreprise formatrice lorsqu'elle est renseignée et lorsque l'apprenti a un employeur actif.

La table `gca_students` contient un champ `trainer_id` afin de relier une fiche apprenti à une fiche de formateur responsable dans `gca_trainers`. Les champs dénormalisés `trainer_name`, `trainer_company`, `trainer_company_address`, `trainer_company_zip`, `trainer_company_city`, `trainer_email` et `trainer_phone` gardent une copie locale du responsable, de l'entreprise et de l'e-mail d'envoi pour les exports, rapports et e-mails. Lorsqu'un responsable est sélectionné ou mis à jour, ces champs sont recopiés dans la fiche élève.

Les anciens champs `field_trainer_name`, `field_trainer_email` et `field_trainer_phone` ne sont plus alimentés par les imports. Les communications utilisent uniquement le responsable de formation.

L'onglet `Formateurs` permet de modifier `name`, `company`, `company_address`, `company_zip`, `company_city`, `email`, `phone`, `salutation` et le statut de vérification. À l'enregistrement, le plugin met à jour toutes les fiches apprentis dont `trainer_id` correspond au formateur. Les rattachements ajoutés ou retirés depuis la fiche formateur créent aussi un événement dans l'historique de chaque élève concerné.

La sauvegarde compare les valeurs ligne par ligne. Si une fiche n'a pas changé, aucune écriture n'est faite et le retour utilisateur indique qu'aucune modification n'a été détectée. Chaque fiche modifiée reçoit un événement `student_quick_updated` avec la liste des champs modifiés, et une entrée d'audit globale `students_quick_updated` conserve le nombre de fiches concernées.

## Contrats Et Absences

Une rupture de contrat est stockée dans `gca_students.contract_status = broken` avec `contract_break_date`. La date de fin de suivi école est calculée à la volée à `contract_break_date + 3 months`. La tâche `contract_break_review` est créée quand la date `contract_break_date + 3 months + 7 days` est atteinte. Si la fiche repasse en contrat actif ou si la date de rupture change, les tâches de rupture en attente qui ne correspondent plus sont supprimées. Le statut `free_candidate` indique un candidat libre : il n'a pas de date de rupture ni d'e-mail formateur, mais ses autres situations restent recalculées dans `À traiter`.

Les alertes d'absence ne créent plus de tâche générique au seuil de pourcentage libre. Deux tâches dédiées sont utilisées :

- `absence_unjustified_21` lorsque `absence_count` dépasse `absence_unjustified_days_threshold`, par défaut 21 jours/périodes ;
- `absence_rate_30` lorsque `absence_rate` atteint ou dépasse `absence_critical_rate_threshold`, par défaut 30 %.

Ces deux tâches restent soumises au garde-fou de début d'année : `absence_count` doit atteindre `absence_min_count_before_alert` et la date courante doit être après `school_start_date + absence_grace_days`. Les anciennes tâches génériques `absence` en attente sont supprimées au recalcul. Après mise à jour du plugin, un recalcul unique est déclenché au premier affichage de l'interface.

Les tâches d'absence peuvent être résolues par une justification sans e-mail. Dans ce cas, aucun message n'est envoyé, un enregistrement est ajouté dans `gca_absence_justifications` et un événement `absence_justified` est ajouté à l'historique de la fiche apprenti. Le même enregistrement peut être créé directement depuis l’onglet `À traiter`, même si aucune tâche d’absence n’est encore ouverte pour l’apprenti.

La clé technique de chaque tâche d'absence inclut la valeur observée (`absence_count` ou `absence_rate`). Une absence justifiée, une information envoyée, ou une alerte supprimée manuellement ne bloque donc que la valeur courante. Si le nombre d'absences ou le taux augmente lors d'un import ultérieur, une nouvelle action à traiter est créée.

## PDF Et Rapports

Le générateur `GCA_PDF` reste sans dépendance Composer afin de fonctionner sur un hébergement mutualisé. Pour les convocations de retards, il reproduit le formulaire CPNV `3 Retenue.docx` fourni en référence, intègre le logo JPEG extrait du modèle et remplit les champs nom/prénom, classe, date de convocation et motif.

Il génère aussi les rapports formateurs avec photo, identité, statut de contrat, coordonnées, compteurs, notes récupérées, décisions disciplinaires manuelles, exclusions détaillées, situations à traiter et historique des actions déjà entreprises.

## E-mails

Pour les convocations, le destinataire principal vient de `E-mail principal de l’élève`. La copie utilise uniquement l'e-mail du responsable de formation. La copie cachée ajoute toujours `carlos.fontes@eduvaud.ch` au moment de l'envoi. Si l'apprenti est en rupture de contrat ou candidat libre, les adresses formateurs sont retirées de tous les destinataires, même si elles ont été saisies manuellement, et l'interface affiche une alerte jusque dans la liste `À traiter`.

Pour les rapports, l'adresse par défaut est l'e-mail du responsable de formation. Si l'apprenti est en rupture de contrat ou candidat libre, l'envoi du rapport au formateur est bloqué et une alerte est affichée. La signature configurée dans `Paramètres` est ajoutée aux messages envoyés.

Pour les envois de masse aux formateurs, l'e-mail est envoyé individuellement à chaque e-mail de responsable. À défaut d'e-mail valide, l'apprenti est ignoré et signalé dans les contacts manquants. Si l'option est choisie, les élèves rattachés au responsable sont ajoutés en copie. Les apprentis en rupture de contrat ou candidats libres sont exclus des groupes formateurs et affichés dans une alerte dédiée. Une ligne de communication est enregistrée dans la fiche de chaque apprenti concerné.

Le plugin force `From`, `FromName` et `Sender` avec l'expéditeur défini dans `Paramètres`, y compris lorsqu'un plugin SMTP est actif. Si le fournisseur SMTP interdit cette adresse, l'envoi peut être refusé ; l'erreur est alors conservée temporairement pour être affichée dans l'interface.

## E-mails Et Rappels

Les e-mails externes sont manuels : convocations, rapports et e-mails individuels aux formateurs sont envoyés uniquement depuis les formulaires correspondants. Les rappels internes utilisent WP-Cron :

- `gca_stale_task_reminder` : rappel interne des actions en attente depuis au moins le délai configuré, avec inclusion immédiate des ruptures de contrat arrivées à échéance ;
- `gca_import_freshness_reminder` : rappel interne si le dernier import Lagap date de plus de 8 jours, ou du délai configuré.

L'ancien hook général `gca_weekly_followup_reminder` est effacé au chargement du plugin.

## Format Des Dates

L'interface affiche et accepte les dates au format `JJ.MM.AAAA`. Les valeurs sont normalisées en `YYYY-MM-DD` uniquement pour le stockage SQL.

## Points Restant À Confirmer

- Ajustement éventuel du délai de démarrage et du nombre minimal de périodes d'absence avant alerte.
- Texte officiel définitif des e-mails.
- Adresse(s) du Doyen.
- Politique de conservation des données en fin de cursus.
