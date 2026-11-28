# LogiOne 1.53.20

La suite pédagogique de LogiProf. LogiNotes, LogiProgress, LogiExam et LogiClass réunis, plus des tuiles de contenu dans un seul plugin WordPress, avec :

- des permissions par rôle, réglées dans un seul écran ;
- des relevés de progression datés et figés ;
- la création automatique d'une fiche LogiNotes pour chaque évaluation LogiExam ;
- la publication contrôlée des résultats numériques vers LogiNotes.

## Ce que contient le paquet

| Dossier | Contenu |
| --- | --- |
| `modules/notes` | LogiNotes 0.1.20, avec correctifs : changement de classe sans perte, enregistrement ciblé des notes, ajustement conservé sans points, aucun recalcul à la mise à jour |
| `modules/progress` | LogiProgress 3.8.9, avec exclusions corrigées (majuscules accentuées, entités HTML) et statut « Rendue, en attente de correction » |
| `modules/exam` | LogiExam 3.5.0, avec droits centraux, bonus conservé à la remise et suppression bloquée des questions déjà répondues |
| `modules/classe` | Gestion des classes d'apprentis 0.2.70, avec droits centraux et sans échange avec les autres modules |
| `modules/tiles` | Tuiles de contenu : rubriques, liens et outils créés par l'administration, affichés dans le portail |
| `core` | Socle : registre des modules, permissions, journal |
| `integration` | Correspondances, relevés de progression, publication Exam → Notes, diagnostic |

Les tables, identifiants, options, fichiers et shortcodes historiques sont repris tels quels (`gne_`, `logieval_secure_`, `gca_`, `[loginotes]`, `[logiprogress]`, `[logiexam_teacher]`, `[logiexam_student]`, `[gestion_apprentis]` et leurs alias). LogiOne ajoute huit tables `logipilot_*` et n'en supprime aucune.

## Bascule (lots 0, 5 et 6)

1. **Sauvegarder** la base, le dossier `wp-content/uploads` et `wp-config.php`. Tester la restauration sur une copie.
2. **Choisir un moment sans examen en cours.** Le diagnostic indique les évaluations LogiExam ouvertes.
3. **Installer LogiOne sans l'activer**, puis l'activer. Les quatre anciens plugins sont désactivés automatiquement ; leurs données ne sont pas touchées. Une photographie de conservation est prise avant toute écriture.
4. **Ouvrir LogiOne → Diagnostic.**
   - Exporter le diagnostic au format JSON.
   - Vérifier le contrôle de conservation : aucune ligne rouge.
   - Lancer le test de protection des fichiers privés.
   - Lire la liste des leçons dont l'exclusion change avec la règle corrigée.
5. **LogiOne → Permissions.** Relire la reprise des droits, cocher les permissions de chaque rôle et choisir le périmètre des classes. Un rôle qui avait à la fois `gne_manage` et `gca_manage` n'a pas reçu LogiNotes automatiquement : à confirmer.
6. **LogiOne → Correspondances.** Relier chaque classe LogiNotes à son groupe Sensei, puis valider les correspondances élève ↔ compte WordPress.

Les versions internes des modules restent celles des anciens plugins : aucune routine de mise à jour (recalcul des notes, recalcul des situations) n'est relancée par la bascule.

## Retour arrière

1. Désactiver LogiOne. Ses tables restent en place, sans purge.
2. Réactiver Gestion des classes d'apprentis 0.2.70, LogiExam 3.5.0 et LogiProgress 3.8.9.
3. Pour LogiNotes, réactiver **la version 0.1.21 corrigée**, jamais la 0.1.20 d'origine : à l'activation, la 0.1.20 recalcule toutes les notes (vérifié en recette, scénario MIG-06).

Les notes saisies ou publiées pendant l'utilisation de LogiOne sont dans les tables historiques : l'ancien LogiNotes les affiche directement. Ce retour arrière a été testé (REC-24).

## Permissions

Les droits sont enregistrés par identifiant de rôle. Ils ne sont jamais déduits du nom du rôle et ne sont pas écrits dans les rôles : une révocation prend effet immédiatement. Le rôle `administrator` garde toujours tous les droits, comme accès de secours. Les anciens droits (`gne_manage`, `gca_manage`, `psgpt_access`, `logieval_secure_teacher`) ne donnent plus accès à rien.

| Famille | Permissions |
| --- | --- |
| Suite | `lp_access`, `lp_admin_modules`, `lp_admin_permissions` |
| LogiNotes | `lp_notes_view`, `lp_notes_edit`, `lp_notes_grade`, `lp_notes_adjust`, `lp_notes_export`, `lp_notes_delete` |
| LogiProgress | `lp_progress_view`, `lp_progress_export`, `lp_progress_use`, `lp_progress_settings` |
| LogiExam | `lp_exam_teach`, `lp_exam_ai`, `lp_exam_publish`, `lp_exam_export` (les élèves n'ont besoin d'aucun droit : code personnel) |
| LogiClass | `lp_classe_view`, `lp_classe_edit`, `lp_classe_import`, `lp_classe_templates`, `lp_classe_send`, `lp_classe_export`, `lp_classe_delete`, `lp_classe_settings` |
| LogiEval | `lp_eval_use`, `lp_eval_admin` |
| LogiPlanning | `lp_planning_view`, `lp_planning_edit` |
| Intégration | `lp_integration_link`, `lp_integration_sync` |

Le périmètre « Classes affectées » limite LogiNotes aux classes attribuées à chaque enseignant (listes, écrans, exports et requêtes directes). LogiExam garde sa règle actuelle : chaque enseignant gère ses propres évaluations.

## Nouveautés 1.56.0

**LogiExam : tests formatifs séparés des évaluations sommatives.**
- Espace enseignant : deux onglets, « Évaluations sommatives » (évaluées et notées) et « Tests formatifs » (non évalués). L’onglet choisi est retenu ; « Créer un test formatif » coche directement l’option.
- « Autres actions » › « Copier en test formatif » / « Copier en évaluation sommative » : copie de l’évaluation (questions comprises) dans l’autre partie, en brouillon.
- Page élève : espace « Tests formatifs » bien distinct de l’évaluation avec code. Les tests publiés pour la classe y sont rangés en tuiles M100, M200… avec les numéros de modules (M101, M102…) d’après le cours choisi. Sans code, sans minuteur ni mode sécurisé, autant de fois que l’élève veut ; corrigé après l’envoi et export PDF de sa copie (réponses et réponses attendues).
- Aucune table modifiée : un nouvel essai réutilise la copie existante de l’élève (remise à zéro) ; le nombre d’essais est gardé dans la méta « logiexam_formative_runs ».

## Nouveautés 1.55.0

- **LogiPlay intégré à LogiOne.** Les 70 jeux de révision (M101 à M809, mathématiques, en français, allemand et italien) font partie de la suite : même menu « LogiPlay » dans l’administration (codes courts et réglages, regroupements, gestion des jeux, statistiques, suivi des élèves), mêmes codes courts (`[jeux_logistique]`, `[jeu_m101]`…) déjà placés dans les pages.
- **Tuile LogiPlay dans le portail** : raccourcis enseignant (statistiques, suivi des élèves, codes courts, gestion des jeux) et le catalogue des jeux tel que le voient les élèves.
- **Données conservées** : tables `jdr_progression`, `jdr_historique`, `jdr_sessions` et options `jrl_*` / `jdr_*` reprises telles quelles. Si l’extension autonome LogiPlay est encore active, le module n’est pas chargé : désactivez-la (sans la supprimer) pour passer au module. Les mises à jour des jeux arrivent avec LogiOne.
- **Choisir mes outils.** Sur l’accueil LogiOne, chaque enseignant coche les outils à afficher (accueil et menu des outils). Les outils masqués restent accessibles et se réaffichent d’un clic (« Tout réafficher »). Choix enregistré dans la méta utilisateur `logione_outils_masques`.

## Nouveautés 1.53.20

- **LogiNotes dans le portail LogiOne :** les boutons « Afficher » (relevé) et « Comparer » ne font plus quitter LogiNotes.
- **LogiExam dans le portail LogiOne :** la page « Créer une évaluation » n’a plus d’erreur de script (boutons flottants, regroupement en problème).

## Nouveautés 1.53.19

- **LogiEval : mode démonstration** pour présenter l’outil aux collègues. À activer dans LogiEval › Réglages › Mode démonstration : la page de connexion propose « Essayer LogiEval sans identifiant ». Chaque collègue choisit son profil (**maître de classe** ou **enseignant·e**) et reçoit sa propre classe fictive (5 élèves, deux collègues fictifs dont un qui a déjà terminé) avec une évaluation courte : 3 objectifs, 4 questions (Oui/Non/Sans avis, un choix unique, un objectif réservé au maître).
- Les essais n’envoient aucun e-mail, n’apparaissent pas dans les listes de l’administration et n’utilisent pas les vrais objectifs (la grille de démonstration est inactive). Un essai est effacé quand le collègue quitte la démonstration, au bout de 3 jours, ou d’un clic (« Effacer toutes les données de démonstration »). Aucune table modifiée (option `logieval_demo`).

## Nouveautés 1.53.18

- **LogiProjet : statut par classe.** Quand un travail vise plusieurs classes, le cadre « Statut par classe » de la fiche du travail permet de le laisser en brouillon, de l’ouvrir ou de le fermer pour une classe sans toucher aux autres (« Comme le travail » par défaut). Les tuiles et l’en-tête du travail affichent le statut de chaque classe quand ils diffèrent.
- Réglage enregistré dans l’option `logiprojet_class_status` : aucune table modifiée, travaux et dépôts existants inchangés.

## Nouveautés 1.53.17

- **LogiInfo intégré à LogiOne.** Les formations Word, Excel, PowerPoint et « Réussir ses présentations » font partie de la suite : même menu « LogiInfo » dans l’administration (remises, correction par l’IA, progression, cours et fichiers, formateurs, données, réglages), mêmes codes courts (`[fw_remise …]`, `[fw_mes_remises]`, `[logiinfo_remise …]`) déjà placés dans les leçons Sensei.
- **Tuile LogiInfo dans le portail** : remises à corriger (avec leur nombre), correction par l’IA, progression des élèves et, pour l’administrateur, cours, formateurs et sauvegarde.
- **Base de données indépendante, conservée** : tables `logiinfo_remises`, `logiinfo_progression`, `logiinfo_eleves`, `logiinfo_exercices`, `logiinfo_journal`, réglages et fichiers des élèves (`uploads/logiinfo/`) repris tels quels. Rien n’est copié, vidé ni supprimé.
- Tant que l’extension autonome LogiInfo est active, le module n’est pas chargé (avis de conflit). Désactivez-la : le module prend le relais avec les mêmes données.

## Nouveautés 1.53.16

- **Logi3D intégré à LogiOne.** Les animations 3D font maintenant partie de la suite : même menu « Logi3D » dans l’administration, mêmes codes courts (`[logi3d …]`, `[logi3d_…]`, anciens `[animation …]`), et une tuile Logi3D dans le portail avec le catalogue des animations (aperçu, plein écran, code court).
- **Données conservées et indépendantes.** Le module reprend telles quelles les options `logi3d_animations` et `logi3d_logo` et le dossier `wp-content/uploads/logi3d/` : les animations ajoutées et le logo restent en place, rien n’est copié ni supprimé.
- Tant que l’extension autonome Logi3D est active, le module n’est pas chargé (avis de conflit). Désactivez-la : le module prend le relais avec les mêmes données.

## Nouveautés 1.53.15

- LogiEval › Réglages : chaque modèle d'e-mail indique ses destinataires. « Un enseignant a terminé » ne part qu'aux enseignants qui n'ont pas encore terminé, et seulement tant que l'évaluation est ouverte.

## Nouveautés 1.53.14

- LogiEval › Enseignants : les accès ne sont envoyés qu'aux enseignants affectés à au moins une classe (non archivée). Nouvelle colonne « Classes » ; les enseignants sans classe sont signalés et ne reçoivent rien, même par l'envoi groupé.

## Nouveautés 1.53.13

- LogiEval : guide de l'enseignant mis à jour (auteur dans le pied de page, couverture aérée).

## Nouveautés 1.53.12

- LogiEval : le guide de l'enseignant (PDF, 12 pages avec sommaire) est intégré.
  - L'e-mail d'accès contient le lien vers le guide (variable {guide}, ajoutée automatiquement si un modèle personnalisé ne l'a pas).
  - Bouton « Aide » dans l'en-tête des enseignants connectés, et lien « Guide de l'enseignant » sur la page de connexion.
  - Réglages : ouvrir le guide actuel, ou le remplacer par votre propre PDF (adresse de la Médiathèque).

## Nouveautés 1.53.11

- LogiEval › Enseignants : « Activer sans e-mail » (préparer sans rien envoyer), puis « Envoyer les accès » quand tout est prêt — par enseignant, par sélection multiple ou d'un clic pour tous les actifs qui ne les ont pas encore reçus. Nouvelle colonne « Accès envoyé » (date d'envoi).
- Actions groupées sur les enseignants cochés : activer sans e-mail, envoyer l'accès, activer et envoyer, désactiver.

## Nouveautés 1.53.10

- LogiEval › Nouvelle évaluation (administration et maître de classe) : les enseignants de la classe dont le compte n'est pas encore activé apparaissent grisés « compte inactif », avec un message indiquant de compléter leur e-mail et de les activer, au lieu d'être invisibles.

## Nouveautés 1.53.9

- LogiEval › Élèves : sélection multiple (cases à cocher, « tout cocher ») et actions groupées « Changer de classe » et « Supprimer », avec confirmation (et avertissement si des élèves cochés ont déjà des réponses d'évaluation).

## Nouveautés 1.53.8

- LogiEval › Import : un fichier qui ne correspond pas au type choisi (par exemple le fichier « Classes ↔ enseignants » importé comme « Élèves ») est refusé avec un message qui dit quoi faire, au lieu de 36 erreurs « champs manquants ».

## Nouveautés 1.53.7

- LogiEval : dans une évaluation clôturée sans attendre tout le monde, les votes et commentaires d'un enseignant resté « en cours » ne s'affichent plus dans les décomptes (ils n'étaient déjà pas comptés dans les notes).

## Nouveautés 1.53.6

- LogiEval › Élèves › « 📈 Suivi » : toutes les évaluations d'un élève, quelle que soit sa classe à l'époque (classe au moment de l'évaluation, points, note, export Word), plus ses changements de classe.
- Import : une classe est reconnue par son nom ET son année scolaire. Une « C1e 2025-2026 » n'est jamais remplie avec les élèves de 2026-2027 : une nouvelle « C1e 2026-2027 » est créée.
- Passage d'année proposé automatiquement (bandeau sur Classes et le tableau de bord, dès juillet) pour les classes créées au moins 60 jours avant la rentrée (fin août) ; les classes plus récentes ne sont pas touchées. Si la classe de destination existe déjà (créée par l'import), l'ancienne est proposée à l'archivage au lieu d'être dupliquée.
- Listes de classes : l'année scolaire est affichée à côté du nom.

## Nouveautés 1.53.5

- LogiEval › Import : nouveau type « Classes ↔ enseignants » (colonne 1 classe, colonne 2 maître de classe, colonnes suivantes autres enseignants). Enseignant connu : lié à la classe. Inconnu : compte créé inactif, e-mail à compléter avant activation. Aucun lien existant n'est retiré.
- Enseignants créés sans e-mail : « ⚠ e-mail à compléter » dans la liste, activation bloquée tant que l'e-mail manque.
- Classes : les enseignants inactifs liés à une classe sont marqués « (inactif) ».
- LogiOne › LogiEval, pour l'administration : barre « Administration » (tableau de bord, évaluations, classes, élèves, enseignants et codes, objectifs, import, réglages) au-dessus de l'accès enseignant ; les écrans s'ouvrent dans la page, sans passer par le menu WordPress.
- Import : deux colonnes au même titre ne s'écrasent plus.

## Nouveautés 1.53.4

- LogiEval › Élèves › Nouvel élève : si l'élève est déjà dans une autre classe, une bulle demande confirmation. « Oui » le déplace dans la nouvelle classe (même fiche, évaluations conservées) ; « Annuler » ne change rien.
- Import élèves : une ligne sans n° d'élève n'efface plus le n° déjà enregistré.

## Nouveautés 1.53.3

- LogiEval › Import élèves : nouvelle option « créer automatiquement les classes manquantes » (avec l'année scolaire, niveau déduit du nom : C1, CR2, A1…).
- Un élève est reconnu d'abord par son n° d'élève ; deux homonymes avec des numéros différents ne sont plus fusionnés.
- Les classes archivées ne reçoivent plus d'élèves importés.
- Passage d'année : les classes CFC réduit (CR1 → CR2, CR2 archivée) et AFP (A1 → A2, A2 archivée) sont désormais reconnues.

## Nouveautés 1.53.2

- Nouveau modèle Excel d'import des élèves LogiEval (LogiEval › Import) : liste déroulante des classes (feuille « Classes »), n° d'élève en texte (les zéros restent), doublons du fichier en orange, lignes incomplètes en rouge, feuille « Mode d'emploi » avec un exemple.

## Nouveautés 1.53.1 (LogiEval : un élève, une classe)

- Un élève ne peut plus être créé deux fois : à l'ajout ou à la modification, un élève de même nom et prénom (ou même n° d'élève) déjà présent dans une autre classe est refusé avec un message indiquant sa classe. À l'import, il n'est pas dupliqué ; avec « mettre à jour les existants », il change de classe.
- Changement de classe (redoublement) : « Modifier » sur l'élève, nouvelle classe. Ses évaluations passées restent dans les résultats, impressions et exports de son ancienne classe ; les nouvelles évaluations le comptent dans sa nouvelle classe. Chaque changement est noté (option `logieval_student_moves`).
- La page Élèves signale les doublons déjà existants (même élève dans plusieurs fiches) pour les corriger.

## Nouveautés 1.53.0 (LogiEval : passage d'année, allègement)

- **Passage d'année des classes** (LogiEval › Classes › « 🎓 Passage d'année ») : chaque classe monte d'une année — nom (CLLOG-C2e → CLLOG-C3e), niveau (2ème → 3ème année) et année scolaire (2025-2026 → 2026-2027) — avec ses élèves, ses enseignants et ses évaluations (la note précédente reste reprise). Les classes de dernière année (C3…) sont **archivées** : masquées des listes, du tableau de bord des enseignants et des nouvelles évaluations, sans rien supprimer, et restaurables. Tout est proposé dans un tableau modifiable (action, nom, niveau, année) avant validation ; les classes au nom non reconnu ne sont pas touchées. Archivage et historique des passages dans des options WordPress : aucune table modifiée.
- **Allègement du site** : la vérification des tables LogiEval (une quinzaine de requêtes) ne se fait plus à chaque page du site (y compris pendant les examens), mais après une mise à jour et au plus une fois par jour.
- **E-mails différés** : les notifications (terminé, dernier enseignant, clôture, affectation) partent juste après la réponse ; « Terminer » s'affiche tout de suite même si l'envoi d'e-mails est lent.
- **Connexion moins rigide** : blocage après 60 identifiants faux en 5 minutes depuis la même adresse internet (au lieu de 20 en 15 minutes), pour une école entière derrière la même adresse.

## Nouveautés 1.52.4 (LogiNotes : « Nouvelle évaluation »)

- « Nouvelle évaluation » et les « + » des colonnes ouvrent toujours le formulaire : il est cherché dans le même bloc LogiNotes que le bouton, et s'il ne peut pas s'afficher, la page se recharge avec le formulaire ouvert au lieu de ne rien faire.
- Les fichiers de LogiNotes (app.js, app.css) changent d'adresse à chaque modification : un cache (navigateur, FlyingPress) ne peut plus servir une ancienne copie après une mise à jour.

## Nouveautés 1.52.3 (LogiExam : questions fermées sans IA)

- « Pré-corriger avec l'IA » (une copie, une question ou toutes) n'envoie plus les questions fermées (Vrai/Faux, QCM, QCM à plusieurs réponses) à l'IA : LogiExam compare la réponse de l'élève à la réponse attendue enregistrée et écrit lui-même le commentaire (« Bonne réponse : … » / « Vous avez répondu …, la réponse attendue était … »). L'IA ne peut plus contredire le corrigé de l'enseignant. Sans réponse attendue enregistrée, l'IA reste utilisée.

## Nouveautés 1.52.2 (LogiExam : QCM corrigés automatiquement)

- Les QCM (une bonne réponse) et les QCM à plusieurs réponses n'étaient jamais notés par LogiExam ; les QCM à plusieurs réponses n'étaient même pas envoyés dans le fichier de correction par IA, d'où des QCM « pas corrigés » après l'import. LogiExam les note maintenant lui-même dès la réponse de l'élève (bonne réponse = tous les points ; plusieurs réponses : au prorata, une case fausse annule une juste).
- Les copies déjà rendues sont complétées automatiquement à l'ouverture de l'étape Corriger, à l'export IA et à la finalisation (seulement les QCM sans points ; les points saisis par l'enseignant et les notes déjà finalisées ne changent pas).
- Les QCM ne sont plus envoyés à l'IA (inutile). Le Vrai/Faux justifié est envoyé : le choix est noté par LogiExam, la justification par l'IA.

## Nouveautés 1.52.1 (test formatif : autocorrection)

- Un test formatif ne se corrige plus : ni IA, ni enseignant. Dès qu'il a envoyé sa copie, l'élève voit sur sa page LogiExam « Mes tests formatifs » › « Voir le corrigé et m'autocorriger » : pour chaque question, sa réponse à côté de la réponse attendue (bonnes réponses des QCM, ce qu'il fallait dire avec les points), et trois boutons « Juste / À moitié / À revoir » pour s'autocorriger ; bouton « Imprimer ». Seule sa propre copie est accessible.
- Côté enseignant, l'étape Corriger l'indique (« aucune correction n'est nécessaire ») ; les copies restent consultables. Si une copie est quand même finalisée, l'élève peut aussi télécharger la copie annotée (PDF).

## Nouveautés 1.52.0 (LogiExam : test formatif)

- Nouvelle case « Test formatif » dans l'étape Préparer d'une évaluation (badge violet « Formatif » dans les listes).
- Un test formatif se passe comme un examen (code, sécurité) et se corrige comme d'habitude, mais ses résultats ne sont **jamais envoyés dans LogiNotes** (pas de fiche créée, publication bloquée) et **aucun bonus / malus de progression** n'est appliqué.
- Dès que la correction d'une copie est finalisée, l'élève voit sur sa page LogiExam « Mes tests formatifs corrigés » avec sa note indicative et le bouton « Télécharger ma copie corrigée (PDF) » (seulement sa propre copie).
- Réglage enregistré dans une option WordPress (`logiexam_formative`) : aucune table modifiée. Une fiche LogiNotes créée avant de cocher la case n'est pas supprimée.

## Nouveautés 1.51.1

- LogiProjet a sa tuile dans le portail LogiOne (orange clair, cube avec une diapositive) et apparaît dans LogiOne › Disposition du portail comme les autres outils : ordre, taille, place. La tuile n'est montrée qu'aux enseignants de LogiProjet (rôles choisis dans ses réglages) ; elle ouvre LogiProjet dans le portail.

## Nouveautés 1.51.0 (LogiProjet intégré)

- **LogiProjet 1.10.2 devient un module de LogiOne** (`modules/projet`). Mêmes tables (`wp_logiprojet_travaux`, `wp_logiprojet_depots`), mêmes réglages (`logiprojet_settings`), même dossier des PowerPoint déposés : tous les travaux, dépôts, corrections et notes existants sont repris tels quels.
- Le menu **LogiProjet** reste dans le menu latéral de WordPress (avec ses réglages), le code court **[logiprojet]** (et `pleine_largeur="non"`) et l'API REST restent identiques : les pages des élèves continuent de fonctionner.
- Tant que l'extension autonome LogiProjet est active, le module n'est pas chargé (pas de doublon) et un avis le signale. Il suffit de désactiver l'ancienne extension.
- **Protection des données** : supprimer un ancien plugin autonome (LogiProjet, LogiNotes, LogiExam…) depuis la page Extensions lançait son `uninstall.php`, qui efface ses tables et ses fichiers. Tant que LogiOne est actif, ce nettoyage est neutralisé : seul le dossier de l'ancien plugin est supprimé, les données restent.
- Module activable / désactivable dans LogiOne › Modules, comme les autres. Les mises à jour de LogiProjet passent désormais par celles de LogiOne (GitHub).

## Nouveautés 1.50.0 (stabilité pendant les examens)

Suite aux erreurs 503 des 12 et 23 novembre 2026 (rapport de transmission du 23 novembre).

- **Codes d'examen** : le correctif appliqué en production le 23 novembre est intégré (un code faux n'est plus comparé aux codes de tous les autres élèves : 401 vérifications coûteuses → seulement celles de l'élève). En plus, seuls les codes de l'élève dont l'évaluation est **ouverte maintenant** sont vérifiés (un élève avec 30 codes d'anciennes évaluations : 30 vérifications → 1). Les contrôles d'accès (ouverture, autorisation, Safe Exam Browser) sont inchangés.
- **Essais simultanés** : une seule vérification de code à la fois par élève (double clic, plusieurs onglets) et l'essai est compté avant la vérification, donc des essais en parallèle ne contournent plus la limite de 8 essais / 10 min. Un refus Safe Exam Browser ne compte pas comme mauvais code.
- **Présence (LogiLive)** : retour du verrou « un seul envoi à la fois » (perdu depuis le 12 novembre) : quand le serveur ralentit, les signaux ne s'empilent plus (test : 4 envois simultanés → 1). Délai maximal de 25 s par envoi, décalage aléatoire de 0 à 5 s par page pour qu'une classe entière n'envoie pas au même instant. Le signal de fermeture part toujours immédiatement. L'adresse du script change à chaque modification (cache navigateur renouvelé).
- Test isolé des codes : `tests/exam-code-entry-test.php` (hors du .zip).

## Nouveautés 1.49.3

- Version papier (PDF et Word) : les points de chaque question (« _____ / 2.00 pts ») sont à droite, sur la même ligne que la question.

## Nouveautés 1.49.2 (LogiExam : points sur la version papier)

- Version papier (PDF et Word) : sous chaque question, « Points : ______ / 2.00 pts » en gras, avec une case pour reporter les points obtenus à la main.
- Correctif : une question dont les points propres à l'évaluation valaient « 0 » prend maintenant les points de la question.

## Nouveautés 1.49.1

- Copie finalisée en mode « Par élève » : les numéros de questions, « ← Précédente » et « Suivante → » restent cliquables (ils étaient grisés avec le reste de la copie verrouillée). On peut relire chaque réponse de l'élève et sa correction sans rouvrir la copie.

## Nouveautés 1.49.0 (LogiExam : copies finalisées visibles par question)

- La correction par question affiche aussi les copies déjà finalisées (badge vert « Finalisée ») : on peut revoir n'importe quelle question de n'importe quel élève, et la modifier directement ; la note de la copie est recalculée à l'enregistrement (elle reste finalisée). Penser ensuite à republier dans LogiNotes. Seules les copies annulées pour tricherie restent exclues.
- Une copie finalisée compte comme corrigée : « une copie à la fois » s'ouvre toujours sur la première réponse pas encore corrigée.

## Nouveautés 1.48.0 (LogiExam : tout finaliser depuis la correction par question)

- En bas de la correction par question, nouveau bouton « ✓ Enregistrer et finaliser toutes les corrections » : enregistre la progression puis finalise d'un coup toutes les copies dont chaque réponse a des points (ceux de l'enseignant, ou à défaut la proposition IA). Les copies encore incomplètes restent à corriger et un message indique combien. Plus besoin de repasser élève par élève.

## Nouveautés 1.47.0 (LogiExam : commentaire régénéré automatiquement)

- Quand l'enseignant met d'autres points que ceux proposés par l'IA (boutons « Tous les points / Moitié / Aucun point », points tapés à la main ou critères cochés), le commentaire pour l'élève est régénéré tout seul par l'IA d'après ces points, sans clic. Pour des points tapés, la régénération part 1 seconde après la dernière touche. Le bouton « Question suivante → » apparaît ensuite et reçoit le focus.
- Pas de régénération si les points redeviennent ceux de l'IA, ni si l'enseignant a déjà écrit son propre commentaire.

## Nouveautés 1.46.0 (LogiExam : suivante après un commentaire régénéré)

- Après « Régénérer le commentaire avec l'IA », un grand bouton « Question suivante → » (« Suivante → » en correction par question) apparaît juste à droite, sur la même ligne, et reçoit le focus : un clic ou la touche Entrée passe à la suite.
- En correction par question, le commentaire régénéré est maintenant enregistré par la sauvegarde automatique.

## Nouveautés 1.45.0 (LogiExam : réponses vides masquées)

- Dans la correction (« Par question » comme « Par élève »), les questions laissées vides ne sont plus affichées : elles valent 0 pt d'office (avec le commentaire « Non répondu. ») à l'enregistrement. Une ligne orange indique lesquelles ont été masquées (numéros de questions, ou noms des élèves en mode « Par question »).

## Nouveautés 1.44.0 (LogiExam : correction par question)

- « Par question » devient le mode de correction par défaut. Le dernier mode utilisé (« Par question » ou « Par élève ») est retenu pour chaque évaluation : en revenant dans sa correction, on reprend dans le même mode, là où l'on s'était arrêté. Un lien « Corriger la copie » d'un élève précis s'ouvre toujours « Par élève ».
- Correction par question, une copie à la fois : une seule réponse à l'écran, « Élève suivant → » / « Question suivante → » tourne la page (animation), « ← Précédente » revient. Flèches ← → du clavier. Lien « Afficher toutes les copies (défilement) » pour revenir à l'ancien affichage (choix retenu).
- Sauvegarde automatique : chaque copie modifiée est enregistrée en passant à la suivante, toutes les 20 secondes, en quittant la page, et AVANT toute pré-correction IA (bouton d'une copie ou d'une question). En cas de plantage, au pire la dernière réponse en cours est perdue. Seules les copies modifiées par l'enseignant sont envoyées (les propositions IA non touchées ne sont pas enregistrées comme corrigées).

## Nouveautés 1.43.0 (bonus / malus corrigeables après publication)

- Nouveau réglage par évaluation LogiExam : « Ne pas appliquer de bonus / malus à cette évaluation » (étape Résultats, et panneau Progression). Utilisable à tout moment, même après la finalisation et l'envoi dans LogiNotes : les copies sont recalculées (seul l'ajustement de progression est retiré, les bonus / malus saisis à la main restent) et les notes déjà envoyées à LogiNotes sont corrigées automatiquement. Le bouton « Appliquer de nouveau les bonus / malus » fait l'inverse.
- Dans LogiNotes, le panneau « Résultats numériques (LogiExam) » a un bouton « Corriger dans LogiExam » qui mène directement à cet endroit.
- Le réglage est enregistré dans une option WordPress : aucune table modifiée.

## Nouveautés 1.42.0 (LogiExam : heure affichée)

- Pendant l'évaluation, l'heure actuelle (HH:MM) s'affiche dans la barre de progression, juste avant le compte à rebours. Elle reste visible même quand l'élève masque le minuteur ou si l'évaluation n'a pas de durée limitée.

## Nouveautés 1.41.2

- « Terminer la copie et l'envoyer en correction » s'affiche aussi quand la date d'envoi de la copie vaut « 0000-00-00 » dans la base (certains serveurs MySQL).

## Nouveautés 1.41.1 (LogiExam : copies restées « en cours »)

- Une copie que l'élève n'a jamais envoyée (parti sans cliquer sur « Envoyer », évaluation sans limite de temps) restait « En cours » et n'apparaissait pas dans les copies à corriger.
- Nouvelle action « ⚙ Gérer » › « Terminer la copie et l'envoyer en correction » : la copie part en correction avec les réponses déjà enregistrées (action notée dans le journal, copie réouvrable ensuite).
- Disponible dans « Suivre la passation » et dans l'onglet « Par élève », qui indique aussi combien de réponses sont enregistrées.

## Nouveautés 1.41.0 (LogiExam : recherche par élève)

- Nouvel onglet « Par élève » dans LogiExam (aussi dans le menu WordPress LogiExam › Par élève) :
  - une recherche instantanée sur tous les élèves des groupes classes, sans tenir compte des accents, avec un filtre par classe ; Entrée ouvre le premier élève trouvé ;
  - pour l’élève choisi : nombre de copies corrigées, à corriger, d’évaluations pas encore passées, et moyenne des notes finalisées ;
  - une carte par évaluation (les plus récentes en haut, celles pas encore passées à la fin) : classe, date de passage, statut, points, note ;
  - boutons « Voir la copie corrigée » (ou « Corriger la copie »), « PDF élève » et « Ouvrir l’évaluation » ;
  - « Détail par question » : points obtenus sur chaque question, réponses vides signalées, commentaire de l’enseignant.
- Chaque enseignant voit les évaluations qu’il gère (l’administrateur : selon « Mes évaluations / Tous les enseignants »). Lecture seule : aucune donnée n’est modifiée.

## Nouveautés 1.40.11 (LogiExam : corrections non terminées mises en évidence)

- « Mes évaluations » : une correction commencée mais pas terminée apparaît en orange. Elle est considérée commencée dès qu’une copie est finalisée, qu’une copie a des points saisis ou que des questions ont déjà été revues. La carte porte :
  - le bandeau « ✏️ Correction commencée · X / Y corrigées · N à terminer » ;
  - le bouton « Reprendre la correction (N copies) ».
- Les évaluations encore en passation mais avec des copies déjà rendues à corriger sont aussi mises en avant, avec un bouton « Corriger » à côté de « Suivre la passation ».
- Le bandeau du haut liste toutes ces évaluations (« dont N commencées », pastilles « à terminer » en orange).

## Nouveautés 1.40.10 (LogiExam)

- Correction par question : chaque question est un bloc encadré avec un bandeau de titre coloré (numéro, titre, points, avancement). Les couleurs alternent d’une question à l’autre (bleu, puis vert d’eau), avec un grand espace entre les blocs.

## Nouveautés 1.40.9 (LogiExam : correction des calculs, le résultat d’abord)

- Quand LogiExam vérifie que le résultat final est juste (arrondi correct compris), le critère « résultat » est accordé, même si l’IA l’avait refusé.
- Résultat juste mais démarche refusée par l’IA : la réponse est marquée « à vérifier » (« vérifiez si la démarche est équivalente »).
- Pré-correction intégrée et fichier pour l’IA externe : lire d’abord le résultat final ; s’il est juste, accepter une démarche équivalente écrite autrement (autre ordre, étapes regroupées, conversion faite autrement, signes = enchaînés) ; s’il est faux, chercher l’étape fausse.

## Nouveautés 1.40.8 (LogiExam)

- Correction par question : le rappel de la question dans chaque carte montre aussi le contexte (repliable), y compris l’énoncé commun d’un problème, et l’image de la question.

## Nouveautés 1.40.7 (LogiExam : évaluations à corriger bien visibles)

- « Mes évaluations » :
  - un bandeau rouge en haut liste les évaluations à corriger, avec le nombre de copies ; un clic ouvre directement la correction ;
  - chaque classe affiche un compteur rouge 📝 des évaluations à corriger ;
  - les cartes à corriger sont mises en avant : fond rosé, cadre rouge, bandeau « 📝 N copies à corriger » et bouton « Corriger » en rouge. L’animation discrète est désactivée si l’ordinateur demande moins d’animations.

## Nouveautés 1.40.6 (LogiExam : reprendre la correction par question)

- Après « Enregistrer toute la progression », on revient en mode « Par question », directement sur la prochaine réponse à corriger, curseur dans « Points manuels ».
- Un bandeau en haut indique « X / Y réponses corrigées » et propose « ▶ Reprendre : Question N · Élève » quand on revient plus tard.
- Chaque question affiche « X / Y corrigées » (vert quand tout est fait). Les cartes déjà enregistrées ont un bord vert.

## Nouveautés 1.40.5 (LogiExam : résultats de calcul)

- Le contrôle automatique du résultat final accepte un arrondi correct quand aucune précision n’est imposée : 233,33 pour 233,33333 (au moins une décimale si le résultat attendu n’est pas entier). Avant, il fallait le nombre exact, et la correction (IA intégrée ou externe) refusait un résultat juste.
- Le résultat peut être écrit dans une phrase ou avec une unité (« 30 cartons », « 233.33 fois plus »), avec virgule ou point, espaces ou apostrophes de milliers.
- Le fichier pour l’IA externe rappelle ces règles, et que le commentaire ne doit jamais contredire les verdicts.

## Nouveautés 1.40.4 (LogiExam)

- Correction par question : chaque carte d’élève rappelle la question (numéro, titre, points et énoncé), pour l’avoir sous les yeux en faisant défiler les élèves.

## Nouveautés 1.40.3 (LogiExam : codes après changement de classe)

- Quand la classe (ou la liste d’élèves) d’une évaluation change, les élèves qui ne sont plus visés perdent leur code dès l’enregistrement : ils ne peuvent plus entrer, et ils disparaissent des fiches de codes (PDF) et du compteur.
- Exception : un élève qui a déjà commencé l’évaluation garde son accès, pour ne pas perdre sa copie.
- Si un élève est remis dans la cible, il reçoit un nouveau code actif.

## Nouveautés 1.40.3 (LogiExam : correction par question)

- La correction « Par question » affiche une carte par élève, comme la correction élève par élève :
  - réponse de l’élève à côté de la réponse attendue ;
  - critères et analyse IA ;
  - ligne de points (« Tous les points », « Moitié », « Aucun point », « Points de l’IA », « Points manuels ») ;
  - commentaire pleine largeur.
- Fini le tableau à 5 colonnes étroites. Enregistrement, passage automatique à l’élève suivant et « Attribuer les points proposés par l’IA » fonctionnent comme avant.

## Nouveautés 1.40.2 (accueil LogiOne)

- Le bandeau d’accueil est aligné sur la mosaïque de tuiles, y compris en largeur « Toute la largeur de l’écran ».
- Le texte d’accueil s’étale sur moins de lignes et le bandeau est moins haut.

## Nouveautés 1.40.2 (LogiExam : points partiels et calculs plus justes)

- Questions sans barème détaillé : les points obtenus pour une idée « partielle » sont donnés dans un champ à part (« awarded »). Avant, une idée partielle valait presque toutes ses points. Concerne la pré-correction intégrée et l’import d’une IA externe.
- Questions de calcul sans barème : quand la démarche est jugée sans rapport avec la question, le résultat juste garde ses points (seules les idées de démarche passent à 0).
- Le fichier pour l’IA externe explique ce champ « awarded ».

## Nouveautés 1.40.1 (LogiExam : import du compte rendu IA plus tolérant)

- L’import accepte les écarts courants des IA :
  - clés en français ou avec accents (élève, critères, statut, commentaire, à vérifier) ;
  - codes « E1 », « Élève 3 », « q2 », « Question 1 » ;
  - statuts « rempli / partiel / manquant » et points « 0,5 » ;
  - virgules en trop, commentaires, True/False, guillemets typographiques ;
  - réponse regroupée par élève ou indexée par code.
- Plusieurs parties collées d’un coup sont lues ensemble. Une réponse coupée est récupérée jusqu’à la dernière correction complète.
- L’aperçu liste les réponses envoyées à l’IA mais absentes du compte rendu, et signale les doublons (la dernière version est gardée).

## Nouveautés 1.40.0 (LogiExam : correction avec une IA externe)

- Nouveau panneau « 🔁 Correction avec une IA externe (Claude, ChatGPT…) » dans l’étape Correction, sans clé API :
  1. Télécharger un fichier avec les consignes de correction, le barème et les réponses. Les élèves y sont anonymes (E01, E02…).
  2. Le confier à l’IA de son choix, qui répond par un bloc JSON au format demandé.
  3. Coller ce compte rendu (ou joindre le fichier) : LogiExam le vérifie, montre un aperçu par élève (noms réels, points proposés, réponses à vérifier), puis l’importe.
- Les propositions arrivent dans la colonne IA, exactement comme une pré-correction intégrée (analyse par critère, « Points de l’IA », commentaire). Les points calculés par LogiExam à partir des critères, avec les mêmes garde-fous. Les points et commentaires de l’enseignant ne sont jamais remplacés ; les copies finalisées ne sont pas touchées.
- Les grandes classes peuvent être importées en plusieurs parties (l’IA indique où elle s’est arrêtée).

## Nouveautés 1.39.8 (LogiExam)

- « Mes évaluations » : dans chaque classe, les dernières évaluations créées sont tout en haut.

## Nouveautés 1.39.7 (LogiProgress)

- Une leçon validée une fois reste acquise : si l’élève la reprend sans la terminer, elle compte toujours comme « Terminée » dans la progression, les pourcentages, les compteurs et le PDF. La cellule indique « Reprise en cours depuis le … ».

## Nouveautés 1.39.6 (LogiExam)

- Les cases à cocher (« Mode sécurisé actif », « Ordre aléatoire des questions », « Ajouter à ma bibliothèque »…) ne s’affichent plus comme de grandes barres : toutes les cases et boutons radio de LogiExam ont leur taille normale.

## Nouveautés 1.39.5 (mises à jour)

- Le lien « Activer les mises à jour auto » apparaît dans Extensions même avant la première version publiée sur GitHub, ou si la clé GitHub manque.

## Nouveautés 1.39.4 (LogiExam)

- Regrouper des questions existantes en problème : cochez « Sélectionner » sur au moins deux questions, puis « Regrouper les questions sélectionnées en problème ». Elles deviennent les sous-questions a), b)… dans leur ordre actuel ; il reste à écrire l’énoncé commun.
- « Sortir du problème » sur une sous-question la remet comme question indépendante, juste après le problème.
- LogiExam empêche d’enregistrer un problème sans énoncé (sinon ses sous-questions redeviendraient des questions séparées à la réouverture).

## Nouveautés 1.39.3 (LogiExam)

- Bouton « Dupliquer » sur chaque question de l’éditeur d’évaluation : la copie apparaît juste en dessous avec tous les réglages (type, points, réponse attendue, critères, image, calculatrice), y compris dans un problème ou une section d’histoire. Elle est enregistrée comme une nouvelle question : modifier la copie ne change pas l’original.

## Nouveautés 1.39.2 (LogiExam)

- Chaque carte de « Mes évaluations » indique sa date de création (« Créée le 13 novembre 2026 »).

## Nouveautés 1.39.1 (LogiProgress sur iPhone)

- Le champ de recherche « Cours à afficher » ne déborde plus de sa carte.
- Le tableau de progression ne fait plus défiler toute la page de côté : il défile seul, à l’intérieur de sa carte.
- Sur iPhone, les colonnes Élève et Progression sont compactes, ce qui laisse voir les leçons à côté des noms.
- Les boutons « Mes favoris » et « Tous les groupes » tiennent sur une ligne.

## Nouveautés 1.39.0 (version web adaptée à l’iPhone)

- **Bandeau LogiOne sur une seule ligne** sur téléphone : bouton ‹ (retour à l’accueil), nom de l’outil ouvert, et un menu **« ☰ Outils »** qui regroupe les autres outils (avec leurs icônes) au lieu de 8 liens sur 3 lignes. Le bandeau reste en haut de l’écran.
- **Accueil** : tuiles compactes sur deux colonnes (icône, nom, une ligne de description) ; texte d’accueil et illustration masqués sur téléphone — tous les outils sont visibles d’un coup d’œil.
- **Menus des outils** (LogiExam, LogiNotes, LogiClass, LogiProgress, LogiEval, étapes d’une évaluation…) sur **une seule ligne qui défile au doigt** ; l’onglet actif est amené à l’écran.
- **Compteurs sur deux colonnes**, cadres imbriqués allégés, titres répétés masqués (« Espace enseignant », « Interface autonome LogiNotes »…), champs en 16 px (Safari ne zoome plus à chaque saisie), boutons plus faciles à toucher, sélecteur « Données affichées » compact.
- **Corrections de débordement** : LogiNotes et LogiClass (pages hors portail) dépassaient l’écran à droite avec les thèmes blocs ; LogiProgress était coupé à droite. Aucune page ne déborde plus à 390 px de large.
- Rien ne change sur ordinateur ni sur tablette (règles limitées aux écrans de 640 px et moins).

## Nouveautés 1.38.5 (LogiExam : problèmes à plusieurs sous-questions)

- **Nouveau bouton « 🧮 Ajouter un problème (énoncé + sous-questions) »** dans la création d’une évaluation : un **énoncé commun** (les données), puis des **sous-questions a), b), c)…** — de type « Calcul » par défaut (espace de développement, résultat final, calculatrice), mais tout autre type reste possible. « + Ajouter une sous-question », « Supprimer le problème ».
- **Côté élève** : l’énoncé est affiché une seule fois en tête du problème et **reste visible en haut de l’écran** pendant qu’il répond aux sous-questions (« Problème 1 · questions 2 à 4 », puis « 2. a) … », « 3. b) … »).
- **Ordre aléatoire** : les sous-questions d’un problème restent ensemble et dans l’ordre ; seuls les blocs sont mélangés.
- Chaque sous-question est une question normale (points, critères, correction, IA, résultats, LogiNotes inchangés) ; elles partagent le même énoncé. Rien n’est modifié dans les tables. À la réouverture, les questions qui se suivent avec le même énoncé sont regroupées en problème.

## Nouveautés 1.38.4 (LogiExam : questions créées avec l’IA = questions faites à la main)

- **Mêmes réglages que l’éditeur de questions** pour chaque question proposée par l’IA, à la relecture comme à l’enregistrement :
  - **Calculatrice** : case « 🧮 Afficher la calculatrice à l’élève » sur chaque question ; cochée d’office quand la question demande un calcul (l’IA l’indique, et un contrôle repère « calcule », « combien », des unités, des opérations…). Toujours présente sur une question « Calcul ».
  - **Relier** : une ligne par paire (gauche ↔ droite), **intrus** (élément de droite qui ne va avec rien — l’IA en propose 1 ou 2), boutons « + Ajouter une paire » / « + Ajouter un intrus », et mode de points (au prorata ou tout juste).
  - **Texte à trous** : liste des mots à placer et tolérance d’une petite faute (cochées d’office), nombre de trous, mode de points.
  - **Remettre dans l’ordre** : mode de points.
- Les questions sont enregistrées exactement dans le même format que celles créées à la main : l’élève les voit de la même façon.

## Nouveautés 1.38.3 (LogiExam : analyse IA cohérente avec les points)

- **La conclusion de l’analyse dit la même chose que les critères.** « L’élève a identifié un élément pertinent… » avec 0 point (ou « il manque… » avec tous les points) est remplacé par une conclusion tirée des verdicts : « Aucun critère rempli : il manque … ». L’IA reçoit aussi la consigne d’une conclusion cohérente.
- **Plus de « bonne idée » dans un commentaire à 0 point.** Si l’élève n’a aucun point, le commentaire ne le félicite pas pour ce qui ne rapporte rien ; une idée vraie mais hors sujet est signalée ainsi : « c’est vrai, mais ça ne répond pas à la question », puis l’idée attendue. Si l’IA félicite quand même, le commentaire est réécrit (ou construit à partir des critères).

## Nouveautés 1.38.2 (LogiExam : reprendre une correction partielle)

- **Questions déjà corrigées bien visibles** quand on reprend une copie enregistrée en partie : pastille **verte avec ✓** dans la barre de progression, badge « ✓ Corrigée » sur la question ; les questions pas encore corrigées ont une pastille blanche en pointillés. Compteur « Question 5 / 11 · 7 corrigées ».
- **Reprise au bon endroit** : la copie s’ouvre sur la première question pas encore corrigée.
- Une question compte comme corrigée quand vous y changez quelque chose (points, boutons de points, « Points de l’IA », pré-correction, critères, commentaire) ou quand vous passez à la suivante avec « Question suivante ». La pré-correction IA de toute la classe ne les marque pas. Les questions vides le sont d’office.
- **Liste des élèves** : une copie en cours de correction affiche « ◐ 7/11 ».
- Enregistré à chaque sauvegarde (même partielle), sans modifier les tables.

## Nouveautés 1.38.1 (LogiLive allégé, temps ajouté exact dans LogiExam)

- **LogiLive envoie beaucoup moins de requêtes au serveur** (cause probable des erreurs 50x) :
  - signal de présence toutes les **30 s** au lieu de 10 s (2 min si l’onglet est caché, plus rien après 10 min cachées) ;
  - défilement et clics regroupés : au plus un envoi toutes les 15 s, joint au signal de présence ;
  - les enseignants et administrateurs ne sont plus suivis (aucun signal de leur part) ;
  - un signal reçu moins de 12 s après le précédent n’écrit plus rien en base ;
  - quand le serveur répond mal (erreur 5xx, 429, coupure), les envois s’espacent automatiquement (jusqu’à 5 min) ;
  - tableau du professeur actualisé toutes les **15 s** (au lieu de 5 s), et moins souvent si le serveur peine.
  Un élève reste « en ligne en ce moment » jusqu’à 90 s après son dernier signal.
- **LogiExam : « Ajouter du temps » ajoute exactement le temps choisi.** Chaque clic n’est appliqué qu’une fois : un double clic, ou le navigateur qui renvoie le formulaire quand le serveur est lent, ajoutait les minutes deux fois (+5 → 10 min). Le bouton se désactive dès le premier clic.
- **Surveillance : temps restant exact pendant un blocage.** L’affichage « ⏸ arrêté » laissait croire que le temps était rendu au déblocage ; il continue (depuis 1.33.7). Le vrai temps restant et l’heure de fin sont affichés.

## Nouveautés 1.38.0 (mises à jour automatiques depuis GitHub)

- **LogiOne se met à jour comme une extension WordPress ordinaire** : quand une nouvelle version est publiée sur GitHub (dépôt `logiprof/LogiOne`), WordPress affiche « Une nouvelle version de LogiOne est disponible » dans Extensions et Tableau de bord › Mises à jour ; un clic sur « Mettre à jour » l’installe. Les mises à jour automatiques de WordPress fonctionnent aussi.
- **Seuls les fichiers de l’extension sont remplacés** : les tables et réglages de LogiNotes, LogiExam, LogiProgress et LogiClass ne sont pas touchés.
- **Nouvel écran LogiOne › Mises à jour** (administrateurs) : version installée, dernière version publiée, notes de version, bouton « Vérifier maintenant » et clé GitHub en lecture seule (le dépôt est privé). La clé peut aussi être mise dans `wp-config.php` : `define( 'LOGIONE_GITHUB_TOKEN', 'github_pat_…' );`.
- Le dossier installé reste `logipilot` ; l’en-tête `Update URI` empêche toute confusion avec une extension du répertoire WordPress.

## Nouveautés 1.37.2 (LogiProgress : nouvel onglet garanti)

- Les liens vers un cours ou une leçon s’ouvrent **toujours dans un nouvel onglet**, même quand le thème ou une extension intercepte les clics sur les liens (transitions de page, chargement instantané) et ignorait l’ouverture en nouvel onglet. Le suivi de la classe reste ouvert.

## Nouveautés 1.37.1 (LogiProgress : ouvrir le cours)

- **Le nom du module (cours) est cliquable** et ouvre le cours dans un nouvel onglet ↗ : titre au-dessus du tableau, pastille sous le nom de la classe et titre du cours dans le détail d’un élève.

## Nouveautés 1.37.0 (LogiProgress : quiz des leçons)

- **Chaque leçon indique si elle a un quiz et combien de questions** : « 📝 Quiz · 8 questions » ou « Sans quiz » (gris), sous le titre de la leçon dans le tableau de la classe et à côté de la leçon dans le détail d’un élève.
- Quand le quiz ne pose qu’une partie de ses questions à chaque élève : « 📝 Quiz · 5 / 12 questions ». Une « question multiple » de Sensei (tirage dans une catégorie) compte pour le nombre de questions tirées. Un quiz sans question compte comme « Sans quiz ».

## Nouveautés 1.36.6 (LogiExam : points sous chaque question)

- Dans la barre de progression de la correction, **sous chaque numéro de question : les points obtenus** (par ex. « 1.5/2 »). Vert = tous les points, orange = une partie, rouge = 0, gris « –/2 » = pas encore notée. Mis à jour aussitôt (saisie, boutons de points, « Points de l’IA », critères cochés, pré-correction). Un clic sur la note ouvre la question.

## Nouveautés 1.36.5 (LogiExam : pré-correction IA moins sévère, plus fiable)

- **Les consignes du barème ne sont plus notées comme des critères.** Une ligne comme « Accepter « pour savoir si l’incinérateur pollue » pour le premier critère », « Tolérer… », « Ne pas pénaliser… », « NB : … » est une consigne : l’IA l’applique au critère concerné au lieu d’en faire un critère de plus (presque toujours « non rempli »). Dans la correction, elle apparaît en « ℹ️ consigne de correction », sans case à cocher.
- **Relecture des critères refusés** : la double vérification ne contrôlait que les critères accordés (elle ne pouvait que retirer des points). Désormais, chaque critère refusé ou partiel est relu « du côté de l’élève » : s’il exprime l’idée avec ses propres mots, le critère est accordé (« Accordé à la relecture »), uniquement avec une citation exacte de sa réponse. Jamais de baisse à cette étape ; le niveau d’exigence de l’évaluation s’applique.
- **Règle d’équité dans la consigne de l’IA** : l’élève n’a pas à reprendre les mots de la réponse attendue ni tous ses détails ; une idée juste dite simplement remplit le critère.

## Nouveautés 1.36.4 (LogiExam : correction plus rapide)

- **« Points manuels » sur la même ligne** que « Tous les points / Moitié / Aucun point / Points de l’IA ». **Encadré bleu « ✎ saisi à la main »** quand les points ne sont ni ceux de l’IA ni la proposition automatique.
- **Questions restées vides** : pastille **orange** dans la barre de progression, badge « Non répondu : 0 pt », **0 pt et « Non répondu. » d’office**. « Question suivante » et « Précédente » les **sautent** (un clic sur la pastille orange les montre quand même).
- **Correction d’un bug ancien** : une question jamais ouverte par l’élève (aucune réponse enregistrée) ne recevait pas son 0 pt à l’enregistrement (l’insertion échouait). C’est corrigé, sans changer les tables.
- « Relier » / « Texte à trous » laissés sans rien remplir comptent maintenant comme vides.
- **Menu « Autres actions »** des cartes d’évaluation : il n’est plus coupé par le bord de la colonne de classe ; il s’affiche par-dessus la page, entier dans l’écran (back office et page web), et le cadre ajouté par le thème autour du bouton a disparu.

## Nouveautés 1.36.3 (LogiExam : relancer la pré-correction refait le commentaire)

- **« Pré-corriger avec l’IA » relancé sur une question réécrit aussi le commentaire pour l’élève**, d’après la nouvelle correction, même si le commentaire avait été modifié ou enregistré avant (avant, il n’était remplacé que s’il était vide ou inchangé). Le message indique « commentaire réécrit ». Idem quand on garde une correction après « Comparer deux IA ».
- **« ↶ Remettre l’ancien commentaire »** apparaît sous le commentaire : il rend le texte d’avant la première réécriture (par ex. celui écrit à la main). Rien n’est enregistré tant que vous ne cliquez pas sur « Enregistrer ».
- La pré-correction de toutes les copies (bouton du panneau) garde le comportement prudent : elle ne remplace pas un commentaire déjà modifié.

## Nouveautés 1.36.2 (LogiExam sur iPad / iPhone)

- **Mode tablette automatique** sur iPad et iPhone (Safari ou autre navigateur ; iPadOS est reconnu même s’il se présente comme un Mac). Le plein écran du navigateur y est peu fiable : il se fermait quand le clavier apparaissait ou au moindre glissement, et chaque fermeture bloquait l’élève. Sur tablette, l’évaluation n’utilise plus le plein écran du navigateur ; elle doit occuper tout l’écran.
- **La protection reste active** : changer d’app ou d’onglet, revenir à l’écran d’accueil, ouvrir une app par-dessus, l’écran partagé (Split View), Stage Manager ou la barre latérale de Safari comptent comme une sortie (même délai de grâce, mêmes blocages, même registre qu’à l’ordinateur). Commencer en écran partagé est refusé avec une explication.
- **Pas de faux blocage** : une perte de focus de moins d’une seconde sans quitter la page (liste déroulante, dictée, menu du système) est notée « non comptée » ; tourner la tablette et écrire avec le clavier de l’écran sont permis.
- Consigne « 📱 Évaluation sur tablette » affichée à l’élève avant de commencer.
- Conseil : pour verrouiller complètement un iPad, l’application Safe Exam Browser (gratuite sur l’App Store, déjà prise en charge) ou l’Accès guidé d’iPadOS.

## Nouveautés 1.36.1 (LogiExam → LogiNotes : rapprochement des élèves depuis la publication)

- **Pourquoi « non rapprochée » ?** LogiNotes doit savoir quelle fiche élève correspond à chaque compte élève. Tant que cette correspondance n’a pas été validée (écran LogiOne · Correspondances), les copies restent « non rapprochées » et le bouton de publication est grisé.
- **Rapprochement directement dans « Publier les résultats dans LogiNotes »** (back office et page web) : une colonne « Fiche LogiNotes » propose la fiche de même nom (ou même e-mail) ; on peut en choisir une autre ou « Créer sa fiche dans LogiNotes ». Le bouton « Rapprocher et publier » enregistre les correspondances (une fois pour toutes) puis publie. Rien n’est relié sans ce clic, rien n’est modifié ni supprimé dans LogiNotes.
- **Colonnes plus claires** : « Points obtenus » (points des questions), « Bonus / malus », « Total retenu » (ce qui part dans LogiNotes, plafonné au maximum de l’évaluation).
- **Page web : l’application LogiExam était décalée de moitié hors de l’écran** avec les thèmes blocs (le thème annulait son centrage) — d’où les éléments « hors cadre ». Centrage corrigé ; les grands tableaux défilent dans leur cadre au lieu de déborder.
- **Audit de la page web [logiexam_teacher]** : tous les écrans et formulaires de LogiExam testés depuis la page web comme au back office (mêmes formulaires, aucun renvoi vers l’administration, aucune erreur PHP ou JavaScript).

## Nouveautés 1.36.0 (LogiExam : nouveaux types de questions, calculatrice partout, boutons flottants)

- **Nouveau type « Relier (gauche ↔ droite) »**
  - L'enseignant saisit les paires justes et peut ajouter des intrus (droite seule).
  - L'élève clique à gauche puis à droite, ou choisit la lettre ; les liaisons sont dessinées en couleur.
  - Correction automatique au prorata des liaisons justes, ou tout juste ou rien.
  - Les identifiants de droite ne trahissent pas les paires.
- **Nouveau type « Texte à trous »**
  - Le texte s'écrit avec les mots à trouver entre crochets, par exemple « Le principe [FIFO]… premier [sorti|sortie] », avec des variantes acceptées.
  - Une liste de mots mélangés peut être affichée.
  - Une petite faute de frappe peut être acceptée, dans les mots de 5 lettres et plus.
  - Les majuscules, les accents et les espaces sont ignorés.
  - Correction automatique au prorata, ou tout juste ou rien.
- **Pour les deux types**
  - Aperçu enseignant, correction avec ✓/✗ et la bonne réponse, exports PDF.
  - Évaluation papier PDF et Word.
  - « Créer avec l'IA ».
- **🧮 Calculatrice sur toute question** : une case dans l'éditeur l'affiche à droite de la réponse. Les questions de calcul l'ont toujours.
- **Boutons flottants dans une page avec le shortcode** : « Ajouter une question », « Enregistrer comme brouillon » et « Enregistrer / Mettre à jour » étaient hors de l'écran quand le thème (section, colonne, animation…) utilise une transformation. Ils sont placés directement sous la page et restent reliés au formulaire. Même correction pour « Question suivante » en correction.

## Nouveautés 1.35.1 (LogiExam : réglages en sous-menus)

- Réglages séparés en sous-menus :
  - **🔌 IA : connexion et modèles**
  - **✅ Correction IA** : fiabilité, consignes de correction, vocabulaire du métier, commentaires, apprentissage.
  - **✨ Création IA** : consignes de création.
  - **🔒 Sécurité et sorties** (administrateur)
  - **⚙ Site et élèves** (administrateur) : visibilité des résultats, autorisation de l'IA, shortcodes.
- Le sous-menu ouvert est retenu, aussi après « Enregistrer ».
- Tous les champs restent dans la page : enregistrer une partie ne fait rien perdre des autres.
- Sans JavaScript, tout reste affiché comme avant.

## Nouveautés 1.35.0 (LogiExam : la pré-correction comprend mieux l'élève)

- **Lecture de l'élève d'abord** : avant de juger, l'IA remet la réponse en français correct, idée par idée. Elle déchiffre les fautes, l'écriture phonétique, les abréviations, le langage SMS et les mots d'atelier, sans rien ajouter ni corriger sur le fond.
- **Le sens, pas les mots** : la même idée exprimée avec d'autres mots, un synonyme ou un terme du métier, une idée répartie sur plusieurs phrases, la cause ou la conséquence, ou un exemple qui montre que la notion est comprise : le critère est rempli.
- **« Dans le tir »** : une réponse juste mais incomplète ou imprécise est comptée en partie, pas à zéro. Une notion différente ou fausse, ou trop vague, reste à zéro. Un mot-clé du cours placé dans une phrase fausse ne suffit pas.
- **Double vérification** : elle juge aussi le sens et reçoit la réponse remise au propre.
- **Vocabulaire du métier et équivalences**, nouveau bloc modifiable dans Réglages › Mes consignes pour l'IA. Par exemple « BL = bulletin de livraison », « transpal = transpalette », FIFO/LIFO/FEFO, EPI…
- **« 🧠 Compris par l'IA : « … » »** en tête de l'analyse sur l'écran de correction : on voit d'un coup d'œil si l'IA a bien lu l'élève.

## Nouveautés 1.34.0 (LogiExam : aménagements par élève)

- Nouvel écran **Aménagements** (menu LogiExam). On choisit un groupe, puis on règle chaque élève :
  - **Temps supplémentaire** : +25 %, +⅓, +50 % ou +100 %. Pour 30 min : 37,5 / 40 / 45 / 60 min.
  - **Lecture audio** des questions : seuls les élèves cochés ont le bouton « 🔊 Écouter ».
  - **Zoom / gros caractères** : 125 % ou 150 %.
- Les réglages s'appliquent automatiquement dans toutes les évaluations de l'élève.
- Le temps en plus est calculé au début de l'évaluation : son chronomètre affiche par exemple 40:00 au lieu de 30:00.
- L'élève voit « Vos aménagements » avant de commencer.
- Dans le suivi, une pastille « ⏱ +⅓ · 🔊 · 🔍 150 % » apparaît à côté de son nom.
- Enregistré dans les métadonnées de l'utilisateur : aucune table modifiée.
- **Changement** : la lecture audio n'est plus proposée à tous les élèves, seulement à ceux qui ont cet aménagement.

## Nouveautés 1.33.7 (LogiExam : temps ajouté exact)

- « ⚙ Gérer » → « +5 / +10 / +15 min » donne exactement le temps choisi.
- Avant, au déblocage, le temps passé bloqué était aussi rajouté en cachette. Par exemple, +5 min ajoutées à un élève bloqué depuis 5 min lui donnaient 10 min.
- Le temps de l'élève ne change plus que par « Ajouter du temps ».
- Le menu « Gérer » d'un élève bloqué indique « Bloqué depuis X min. » et précise que ce temps n'est pas rendu, pour décider combien en ajouter.

## Nouveautés 1.33.6 (LogiExam : questions de calcul)

- La calculatrice est de nouveau tout à droite, sur la même ligne que les champs « Développement » et « Résultat final ».
- La mention de précision (« Résultat exact demandé… ») occupait la première colonne et repoussait la calculatrice sous les champs. Elle est maintenant en tête des champs.
- Sur petit écran, la calculatrice reste sous les champs.

## Nouveautés 1.33.5 (LogiExam : « Conséquences automatiques » lisibles)

- L'étiquette, par exemple « Plein écran ou fenêtre quittés », est maintenant placée au-dessus de son texte : plus de chevauchement.
- Le texte suit les règles actuelles :
  - délai de retour ;
  - blocage avec compte à rebours pour demander le déblocage ;
  - reprise décidée par l'enseignant ;
  - sortie qui clôture (note 1).

## Nouveautés 1.33.4 (LogiExam : barre de progression toujours entière, minuteur dedans)

- Le minuteur et le bouton « Masquer le minuteur » sont dans la barre de progression, affichés une seule fois.
- La barre de progression se place sous **tous** les bandeaux fixés en haut du site : la barre d'administration, puis l'en-tête du thème (logo LogiProf…). Elle reste visible en entier pendant le défilement. Avant, l'en-tête du thème en recouvrait le haut.

## Nouveautés 1.33.3 (LogiExam : un blocage = toujours une demande de déblocage possible)

- Le réglage « Sorties avant terminaison automatique » devient **« Blocages autorisés (avec demande de déblocage) »**.
- Avec N, chaque sortie jusqu'à la N-ième bloque l'évaluation, avec le compte à rebours rouge pour demander le déblocage, et l'enseignant peut débloquer. La sortie N+1 clôture (note 1).
- Avant, avec 1, la première sortie clôturait directement l'évaluation, sans blocage ni déblocage possible.
- 0 = la première sortie clôture.
- La valeur enregistrée est gardée telle quelle : avec 3, il y a maintenant 3 blocages possibles et la 4e sortie clôture.

## Nouveautés 1.33.2 (LogiExam : compte à rebours de déblocage fiable, minuteur toujours visible)

- Le délai pour demander un déblocage est calculé avec le fuseau horaire du site. Avant, si une autre extension changeait le fuseau de PHP, ce délai paraissait dépassé d'une heure : l'élève était clôturé (note 1) en rechargeant la page ou à la vérification.
- Quand le serveur bloque l'évaluation lui-même, le compte à rebours rouge « Demander un déblocage » s'affiche aussi.
- Le compte à rebours est placé dans la zone du plein écran quand le navigateur y est encore.
- LogiProgress, « Rythme suspect » :
  - « avec quiz » seulement pour un vrai quiz avec des questions. Sensei crée un quiz vide pour presque chaque leçon.
  - Leçon validée d'un clic (début noté au moment de la validation) : plus de « 1 s » d'office. La durée est prise depuis la leçon précédente, avec « ≈ » et « (depuis la leçon précédente) ».
- **⏱ Minuteur à côté de la progression** : dans la barre « Progression », collée en haut, il reste toujours visible pendant le défilement. Il reprend le même temps que l'en-tête, y compris « Minuteur masqué » et le rouge des 5 dernières minutes.

## Nouveautés 1.33.1 (LogiProgress : changer de classe et de modules sans quitter les résultats)

- Au-dessus des résultats, une barre **Classe / Modules / Afficher** :
  - classe : menu déroulant avec les favoris en tête ;
  - modules : liste à cocher avec recherche, les modules actuels en premier.
- On change de suivi directement, sans repasser par l'écran de choix.
- « Modifier la sélection » reste disponible pour le choix détaillé.

## Nouveautés 1.33.0 (LogiExam : créer avec l'IA à partir des objectifs)

- Nouvelle zone **🎯 Les objectifs que les élèves doivent atteindre** dans « Créer avec l'IA ». On peut coller les objectifs, déposer un fichier (PDF, Word, texte) ou reprendre une leçon Sensei avec le bouton « 🎯 Objectifs » à côté de chaque leçon.
- Une leçon dont le titre contient « Objectif » est reprise automatiquement quand on choisit le cours.
- Chaque question vérifie un objectif, et pas de question hors objectifs.
- Les questions sont réparties à tour de rôle sur tous les objectifs.
- L'objectif visé s'affiche sur chaque carte de relecture.
- La théorie reste la source des réponses attendues.
- Sans objectifs, rien ne change.

## Nouveautés 1.32.1 (LogiExam : médiathèque et images anonymes)

- **📁 Choisir dans la médiathèque** à côté de chaque image : image de la question (évaluations, questions de l'histoire, banque de questions), images des éléments « Remettre dans l'ordre » et logo de l'entreprise. On choisit une image déjà présente sur le site ou on en téléverse une nouvelle.
- Les enseignants LogiExam ont le droit de téléverser des fichiers dans la médiathèque.
- Seules de vraies images sont acceptées.
- **Noms de fichiers cachés aux élèves** : les images des questions et le logo sont servis par une adresse anonyme (`?les_img=…`), sans nom de fichier ni texte alternatif. Le nom n'apparaît ni au survol, ni dans l'adresse, ni en enregistrant l'image (« image.png »). Clic droit et glisser-déposer désactivés sur ces images.

## Nouveautés 1.32.0 (LogiExam : vos IA et vos consignes)

- **Quelle IA pour quoi ?** (Réglages › Ma pré-correction IA) : une IA par défaut pour corriger, une pour créer des questions (la même ou deux différentes).
- **Choix de l'IA sur chaque question** pendant la correction : menu « IA : » à côté de « Pré-corriger avec l'IA » (tous les menus se suivent et le choix est retenu).
- **⚖️ Comparer deux IA** :
  - en correction, la même réponse est corrigée par deux IA (par ex. OpenAI et Claude), côte à côte : points, analyse critère par critère, commentaire, modèle, durée. Rien n'est enregistré tant que vous ne gardez pas l'une des deux (« Garder la correction A / B ») ;
  - en création, chaque IA rédige un lot de questions à partir de la même théorie ; vous ajoutez les questions de l'une, de l'autre ou des deux ;
  - « Vos comparaisons » (réglages) indique quelle IA vous gardez le plus souvent, en correction et en création.
- **Mes consignes pour l'IA** : les consignes envoyées à l'IA sont affichées et modifiables à tout moment (règles de correction, questions de calcul, commentaire pour l'élève, règles de création, QCM). Elles reprennent le texte utilisé jusqu'ici par LogiExam ; la consigne QCM reprend votre méthode « étude de cas → tableau QCM » (distracteurs de même longueur, même structure, une seule variation…). « Rétablir le texte d'origine » à tout moment. La partie technique (format JSON, méthode critère par critère, double vérification) reste gérée par LogiExam pour que tout continue de fonctionner.
- Création : les critères proposés reprennent l'élément précis attendu (« 1 pt pays cité : Panama, Libéria ou îles Marshall »), pour une correction plus fiable.

## Nouveautés 1.31.5 (LogiExam : vos points décident du commentaire)

- « Tous les points », « Moitié », « Aucun point » ne passent plus à la question suivante : le commentaire s'ouvre avec une invitation à le revoir (« ✨ Rédiger avec l'IA d'après mes points » ou « Je l'écris moi-même »), puis « Question suivante ». « Points de l'IA » passe toujours directement à la suite.
- Tous les points donnés = réponse acceptée : le commentaire régénéré confirme que c'est juste et ne signale plus rien comme faux, manquant ou « pas ce qui était attendu ». S'il le fait quand même, il est refait automatiquement, puis remplacé par « C'est juste, tu as tous les points » en dernier recours. Points partiels : le commentaire reflète la proportion ; aucun point : il explique ce qui était attendu.

## Nouveautés 1.31.4 (LogiExam : rapport PDF et commentaire de secours)

- Rapport PDF : le commentaire est espacé de la réponse de l'élève, en italique, et aligné sur le numéro de la question.
- Commentaire de secours : quand l'élève a tous les points, plus jamais « Il te manque… » (« C'est juste, tu as tous les points ») ; « Régénérer le commentaire » avec des points mais aucun critère coché ne considère plus tous les critères comme manqués ; libellés sans « 1 pt pour le ».

## Nouveautés 1.31.3 (LogiExam : commentaire à revoir quand vos points diffèrent de l'IA)

- Quand vous mettez d'autres points que ceux proposés par l'IA alors que le commentaire est encore celui de l'IA (ou vide), LogiExam ne passe plus à la question suivante : un encadré vous invite à reformuler le commentaire pour l'élève, avec trois choix : « ✨ Régénérer avec l'IA d'après mes points », « Je l'écris moi-même » ou « Garder ce commentaire et continuer ».
- Si vous avez déjà écrit ou régénéré le commentaire pour ces points, le passage à la suite se fait normalement.
- **Fiabilité de la pré-correction** : chaque critère est ancré sur l'élément précis de la réponse attendue (« pays cité » = un pays de la réponse attendue ; un autre pays, même réel, ne compte pas). L'IA indique pour chaque critère l'élément visé et s'il correspond ; sinon, 0 sur ce critère.
- **Double vérification** (activée par défaut, réglable dans « Ma pré-correction IA ») : un second passage de l'IA, en contrôleur exigeant, reprend uniquement les points accordés et retire ceux qui ne correspondent pas à l'attendu. Il ne peut jamais ajouter de points. Coût environ doublé.
- **Commentaire rédigé à part** : comme « Régénérer le commentaire », le commentaire de la pré-correction est maintenant écrit dans un passage dédié, d'après les points définitifs (après les contrôles) : plus clair, et toujours cohérent avec les points.

## Nouveautés 1.31.2 (LogiExam : calculs et commentaires IA)

- **Calculs** : plus de points partiels « parce qu'un calcul a été écrit ». L'IA indique si la démarche est cohérente avec ce qui est demandé (bonne opération, bonnes données) ; sinon, les critères de démarche valent 0. Le résultat final est vérifié par le programme : faux = 0 sur le résultat. Calcul sans rapport et résultat faux = 0 point. Une bonne méthode avec une erreur de calcul garde ses points partiels.
- **Points partiels** : un critère « en partie » à 0 point devient « non rempli » (plus de moitié des points d'office).
- **Commentaire pour l'élève** : il dit ce qui était attendu pour chaque critère manqué (« Ce qu'il fallait dire : … ») ; l'IA fournit l'idée attendue en mots simples et LogiExam l'ajoute si le commentaire ne l'explique pas déjà. « Réponse incorrecte. » seul n'est plus accepté.
- **« ✨ Régénérer le commentaire avec l'IA »** (sous chaque commentaire) : l'IA réécrit le commentaire pour l'élève d'après VOS points et les critères cochés (et votre brouillon s'il y en a un). Les points ne sont jamais modifiés ; pensez à enregistrer.
- **Captures d'écran** (LogiExam) : un site ne peut pas bloquer la capture du système, mais les questions se masquent dès que la fenêtre perd la main ou qu'un raccourci de capture commence (touche Windows/Cmd, outil Capture, Cmd+Maj+4/5) : la capture ne montre qu'un écran masqué. La touche Impr. écran et les raccourcis de capture sont notés au journal de sécurité (« Tentative de capture d'écran ») et l'élève voit un avertissement. Filigrane discret au nom de l'élève et à l'heure sur les questions. Dans Safe Exam Browser, la capture est coupée (enablePrintScreen). Une photo avec un téléphone reste impossible à empêcher.
- **Réponse vide** : commentaire simplement « Non répondu. » (aussi mis d'office quand la copie est finalisée) ; les anciens textes automatiques (« Réponse vide : 0 point proposé automatiquement. », « Tu n'as pas répondu… ») s'affichent eux aussi « Non répondu. », sans rien réécrire en base.

## Nouveautés 1.31.1 (LogiExam : commentaire qui parle à l'élève)

- Le commentaire proposé par l'IA s'adresse directement à l'élève, simplement (« Tu as bien vu… Il te manque… », ou au vouvoiement selon votre réglage) ; il ne parle plus « de l'élève » à la 3e personne.
- Contrôle automatique : un commentaire du type « Réponse partiellement correcte : l'élève montre… » est réécrit par l'IA ; si elle n'y arrive pas, LogiExam écrit une phrase simple d'après les critères (« C'est juste pour… Il te manque… Tu as 1 point sur 3. »).

## Nouveautés 1.31.0 (LogiExam : pré-correction IA critère par critère)

- **Plus d'incohérence entre l'analyse et les points** : l'IA ne propose plus un total. Elle rend, pour chaque critère, un verdict (rempli / en partie / non rempli), une citation mot pour mot de la réponse de l'élève et une explication. LogiExam calcule lui-même les points : rempli = tous les points du critère, non rempli = 0, en partie = arrondi au quart de point.
- **Garde-fous** : un critère « rempli » sans citation de la réponse n'est pas accordé ; une citation introuvable dans la réponse est signalée « à vérifier » (confiance plafonnée à 60 %). Une réponse hors sujet ne remplit aucun critère, même longue.
- **Sans barème**, l'IA découpe la réponse attendue en 1 à 4 idées dont les points sont ramenés exactement au total de la question. **Vrai/Faux argumenté** : le choix Vrai/Faux est vérifié par le programme, l'IA ne juge que la justification.
- **Affichage** : une ligne par critère (« ✓ Critère 1 rempli — … (1 / 1 pt) : « citation » — explication »), puis « → Total ». Les pastilles ✓ / ◐ / ✗ IA à côté des critères et « Points de l'IA » cochent les bons critères.
- Réponse au format JSON garanti chez OpenAI ; les anciennes analyses déjà enregistrées s'affichent comme avant.

## Nouveautés 1.30.0 (LogiExam : délai pour demander le déblocage)

- **Second compte à rebours rouge après un blocage** : l'élève a un délai (30 s par défaut, réglable dans Réglages → « Délai pour demander un déblocage ») pour cliquer sur « Demander un déblocage ». Sinon, l'évaluation est clôturée définitivement avec la note de 1 (comme une annulation pour tricherie, que l'enseignant peut toujours annuler). 0 = pas de délai.
- **Contrôle par le serveur** : la clôture n'a lieu que si le délai est réellement écoulé ; un élève qui ferme la page pour échapper au compte à rebours est clôturé dès qu'il revient. Une demande envoyée à temps (ou un déblocage de l'enseignant) arrête le compte à rebours.
- **Nouvel écran à part avant les règles** : « À lire avant de commencer : sortir de l'évaluation », avec les délais réels du site et la case obligatoire « J'ai compris : … j'aurai la note de 1 ».
- Registre : « Pas de demande de déblocage dans le délai : évaluation clôturée (note 1) ».

## Nouveautés 1.29.0 (LogiExam : Safe Exam Browser)

- **Nouveau réglage par évaluation « Safe Exam Browser »** : Non / Obligatoire / Obligatoire + clé de configuration vérifiée. Safe Exam Browser (gratuit) verrouille tout l'ordinateur : autres programmes, extensions, assistants IA, raccourcis système.
- **Fichier de configuration généré automatiquement** (.seb) et **lien élève** `sebs://…` qui ouvre Safe Exam Browser directement sur la page de l'évaluation. Seul le site est autorisé ; correcteur et dictée coupés ; mot de passe de sortie facultatif (enregistré haché) ; bouton « Quitter Safe Exam Browser » à la fin de l'évaluation.
- **Contrôle côté serveur à chaque demande** (démarrage, réponses, envoi) : hors de Safe Exam Browser, refus avec un bouton « Ouvrir dans Safe Exam Browser » et un lien de téléchargement ; la tentative est notée dans le registre (pas comptée comme un code faux).
- **Clé de configuration** (mode le plus sûr) : la « Config Key » copiée depuis l'outil de configuration SEB est vérifiée (en-tête envoyé par SEB ou API JavaScript de SEB). Un navigateur ordinaire qui se ferait passer pour SEB est refusé. Si la configuration change (mot de passe de sortie, adresse du site), il faut recopier la nouvelle clé.
- **Dans Safe Exam Browser**, le plein écran du navigateur n'est plus exigé et un clic sur la barre de SEB (Wi-Fi, batterie) est noté sans bloquer.
- **Page de test** : le lien de test ouvre la page élève avec « ✓ Safe Exam Browser détecté » (et « ✓ Configuration vérifiée »).
- Aucune table modifiée : réglages stockés dans l'option `logiexam_seb`.

## Nouveautés 1.28.2 (LogiExam : « Créer avec l'IA » plus fiable)

- Correction de « La réponse de l'IA n'a pas pu être lue » avec les modèles qui réfléchissent (gpt-5.4-mini, gpt-5…, o3/o4, Gemini 2.5) : la réflexion consommait toute la place et la réponse arrivait coupée. Réflexion réglée sur « faible », réponse en JSON garanti, marge de longueur ajoutée.
- Réponse malgré tout coupée : les questions complètes sont gardées, et un nouvel essai part automatiquement avec deux fois plus de place.
- Un lot qui échoue est réessayé une fois, puis la génération continue avec les lots suivants (avant : tout s'arrêtait). Le message final dit combien de questions ont été créées sur le total demandé.

## Nouveautés 1.28.1 (LogiExam : IA sans quitter le plein écran)

- **Panneau latéral du navigateur** (Gemini dans Chrome, Copilot dans Edge…) : repéré même si le plein écran reste actif. Grand compte à rebours rouge « Fermez-le », puis blocage ; noté « Panneau latéral ouvert » dans le registre. Le zoom du navigateur n'est pas confondu avec un panneau.
- **Application ouverte par-dessus l'évaluation** (touche Copilot, ChatGPT de bureau…) : traitée comme une sortie de fenêtre, avec la mention « sans sortir du plein écran » dans le registre.
- **IA intégrée aux champs de réponse** (« Aide-moi à écrire », suggestions d'écriture, correction automatique) : coupée.
- **Texte arrivé sans être tapé** : collage et glisser-déposer bloqués ; un long passage qui apparaît d'un coup (extension, dictée, outil d'IA) est signalé « Texte inséré sans frappe au clavier (à vérifier) », avec la question concernée. Pas de blocage automatique, pour éviter les sanctions injustes.
- **Information des élèves** : l'écran des règles indique ce qui est enregistré ; le contenu des frappes ne l'est jamais.

## Nouveautés 1.28.0 (LogiEval : accès sans compte et corrections)

- **Accès par lien + identifiant, sans compte LogiProf** : la connexion fonctionne aussi avec les thèmes classiques (Divi). Session de 60 jours, ou session limitée à la fermeture du navigateur (12 h max) en cochant « Ordinateur partagé ». Page expirée : nouveau jeton automatique, rien n'est perdu.
- **Notes** : un élève sans aucune réponse affiche « — » (au lieu de la note 1). Un objectif « non évaluable » ou sans aucun avis sort du maximum. La grille est figée à la clôture ; les évaluations déjà clôturées gardent leur ancien calcul.
- **Saisie fiable** : un échec d'enregistrement reste affiché et la case revient à son état ; re-cliquer une réponse l'efface ; choix unique garanti sur les objectifs « un seul choix » ; « non évaluable » garde les réponses de côté.
- **Droits** : export Word et impression de la classe réservés au maître de classe ; mode entraînement sans action réelle ; un maître non attribué voit l'évaluation en lecture seule ; seuls les enseignants encore attribués comptent dans les décomptes.
- **Administration** : plus de données orphelines (classe avec élèves, enseignant ayant répondu, objectif utilisé : suppression refusée avec explication ; un élève supprimé emporte ses réponses) ; e-mail en double refusé ; plus de double évaluation sur double-clic ; modification validée avant enregistrement ; identifiants ≤ 20 caractères ; messages d'erreur du serveur affichés ; e-mail d'activation : échec signalé ; e-mail de clôture de nouveau envoyé.
- **Import** : fichiers CSV Excel Windows (accents), séparateur « ; » ou « , », en-têtes accentués (« Prénom », « E-mail », « N° élève »), cellules vides dans les .xlsx, classe reconnue exactement, message clair pour les .xls.
- **Rappels** : rappel J-7 envoyé même si la tâche planifiée a manqué le jour exact, une seule fois par évaluation.
- Les tables LogiEval existantes ne sont pas modifiées ; les nouvelles informations sont stockées dans des options WordPress.

## Nouveautés 1.27.1 (LogiExam : outils d'IA dans le navigateur)

- Extensions d'IA (assistants comme Monica, Sider, MaxAI, ChatGPT…, correcteurs Grammarly / LanguageTool, aides aux devoirs) : elles peuvent lire les questions et répondre dans la page sans quitter le plein écran. Leurs traces sont repérées (éléments, attributs, adresses chrome-extension://). Avant de commencer : accès refusé avec un message (désactiver l'extension ou ouvrir une fenêtre de navigation privée), noté dans le registre. Pendant l'évaluation : blocage immédiat, compté comme une sortie, « Outil d'IA ou extension détecté » dans le registre.
- Champs de réponse protégés (Grammarly, LanguageTool et remplissage automatique désactivés) ; texte des questions non sélectionnable.
- Délai de grâce du plein écran : 3 fois au maximum par évaluation, ensuite chaque sortie bloque. Règles affichées à l'élève mises à jour.

## Nouveautés 1.27.0 (LogiExam : « ✨ Créer avec l'IA »)

- Nouvel onglet dans LogiExam. 1) La théorie : texte collé, fichiers PDF (lu dans le navigateur), Word .docx, texte .txt, ou leçons Sensei cochées. 2) Les questions voulues : nombre, niveau (CFC 1re–3e, AFP, adultes), tutoiement ou vouvoiement, types (QCM, Vrai/Faux, Vrai/Faux argumenté, réponse courte, réponse longue, calcul, remettre dans l'ordre), consignes pour l'IA. 3) Relecture : chaque question est modifiable (consigne, mise en situation, propositions, bonne réponse, réponse attendue, critères et points), à garder ou non.
- L'IA écrit uniquement à partir de la théorie fournie, avec réponse attendue, points (par 0,5) et critères observables dont la somme donne les points, et cite la source dans la théorie.
- Génération par paquets de 3 questions (aucun délai de l'hébergement dépassé), questions déjà créées évitées.
- « Ajouter à la banque de questions » ou « Ajouter et créer une évaluation (brouillon) » : les questions sont enregistrées au format LogiExam (même chemin que l'éditeur) ; l'évaluation s'ouvre à l'étape Préparer pour choisir la classe et publier.
- Utilise la clé IA de l'enseignant (Réglages → Pré-correction IA) et la permission « Précorriger avec l'IA ».
- Plusieurs fournisseurs d'IA connectés en même temps (OpenAI, Anthropic, Mistral, Gemini, compatible) : une clé et un modèle par fournisseur, une IA par défaut pour corriger et une pour créer des questions. Menu « IA utilisée » dans le panneau de pré-correction et dans « Créer avec l'IA » (choix retenu). Les anciens réglages (une seule clé) sont repris tels quels.
- « Enregistrer et tester » teste chaque IA connectée. « Ma consommation ce mois-ci » : demandes et jetons par fournisseur et modèle, pour comparer les coûts.

## Nouveautés 1.26.9 (LogiExam : « Question suivante » flottant)

- Correction élève par élève, une question à la fois : bouton « Question suivante (2/8) → » (et « ← ») dans la barre du bas, toujours à l'écran. Après une saisie manuelle des points, plus besoin de défiler. À la dernière question : « Commentaire général ↓ ». En bas de page, le bouton reste flottant en bas à droite.

## Nouveautés 1.26.8 (LogiExam : compte à rebours rouge)

- Plein écran ou fenêtre quittés : grand écran rouge sur toute la page, gros chiffre du compte à rebours et gros bouton « Revenir à l'évaluation en plein écran ». Il s'affiche toujours (accroché directement à la page, aucun bloc du thème ne peut le cacher), même si l'élève était en bas de la page.
- Fenêtre quittée un instant (touche Windows, notification…) : même délai, plus de blocage si l'élève revient à temps. Changer d'onglet bloque toujours.
- Correction : quand une question n'a pas de réponse attendue, un encadré le dit clairement (« Aucune réponse attendue n'est enregistrée ») avec « ✎ Ajouter la réponse attendue » : enregistrée dans la question sans quitter la correction (questions à réponse écrite, seulement par l'auteur de la question ; une réponse existante n'est jamais écrasée).
- LogiProgress : seule la leçon « Objectifs » (titre qui commence par « Objectifs », après le code du module) est masquée. Les leçons qui parlent d'objectifs (« 3 Tâches et objectifs de la logistique », « 3.2 … des objectifs … ») sont de nouveau affichées et comptées. La leçon de chapitre « 3 … » se place avant 3.1, 3.2. Les décisions de progression déjà validées gardent leur ancienne règle (version de règle notée).

## Nouveautés 1.26.7 (LogiExam : pastilles des questions, liens, code de la page)

- La barre des questions (pastilles) se place sous l'en-tête fixe du site (logo LogiProf) : plus aucune pastille recouverte. Avant, le clic tombait sur le logo (un lien) et l'élève était bloqué.
- Clic sur un lien hors de l'évaluation : le lien ne s'ouvre pas, simple avertissement noté dans le registre, plus de blocage (l'élève n'a pas quitté l'évaluation).
- Ctrl+U (code de la page), F12 et Ctrl+Maj+I/J/C désactivés pendant l'évaluation, notés dans le registre.

## Nouveautés 1.26.6 (LogiExam : délai pour revenir en plein écran)

- L'élève qui quitte le plein écran voit un compte à rebours (5 s par défaut) : s'il revient en plein écran à temps, pas de blocage (noté « rétabli à temps » dans le registre). Sinon, le blocage est prononcé et l'enseignant doit débloquer.
- Quitter la fenêtre ou l'onglet pendant ce délai reste une vraie sortie.
- Réglage « Délai pour revenir en plein écran (secondes) » dans Réglages (0 = blocage immédiat). Le délai est rappelé à l'élève dans les règles avant de commencer.

## Nouveautés 1.26.5 (LogiExam : main totale sur les blocages)

- Menu « ⚙ Gérer » pour chaque élève, dans le suivi en direct et dans la correction élève par élève.
- Débloquer (le compteur de sorties est conservé) ou « Débloquer et effacer les blocages » (compteur remis à 0 : l'élève retrouve toutes ses sorties).
- Ajouter du temps (+5, +10, +15 min) : le chrono de l'élève se met à jour tout seul (contrôle toutes les 30 s et avant la clôture).
- Copie annulée pour tricherie : « Annuler la sanction et rouvrir la copie » (note 1 retirée, réponses conservées, temps restant rendu, au moins 5 min, plus du temps au choix). L'élève reprend en entrant à nouveau son code.
- Copie envoyée mais pas encore corrigée : « Rouvrir la copie ».
- Tout est inscrit au journal de sécurité, avec une sauvegarde de ce qui est remplacé. La structure des tables LogiExam ne change pas.
- Barre de progression : un clic sur un numéro de question amène toujours à la question (le clic était perdu quand l'élève venait d'écrire une réponse).
- Suivi en direct : colonne « Temps restant » pour chaque élève (décompte en direct, heure de fin, rouge sous 5 min, ⏸ arrêté pendant un blocage).
- Mise en veille de l'ordinateur : n'est plus comptée comme une sortie ni bloquée. Elle est notée « Ordinateur en veille » dans le registre ; au réveil, l'élève remet simplement le plein écran. Une vraie sortie (onglet, fenêtre, plein écran quitté) reste bloquée, quelques secondes après.

## Nouveautés 1.26.4 (LogiExam : fiche des codes)

- Fiche « Code personnel d'évaluation » : dans « Comment entrer dans l'évaluation », chaque point est sur sa propre ligne (1., 2., 3., puis les règles), et « ATTENTION AUX TOUCHES » est en gras rouge.

## Nouveautés 1.26.3 (LogiProgress : rythme suspect)

- Détail par élève : pastille ⚠ « Rythme suspect » à côté du nom quand au moins 3 leçons (ou 2 dont une avec quiz) ont été validées en moins de 3 minutes chacune. Durée = de son début noté par Sensei à sa validation (sinon temps depuis la leçon validée juste avant). Dans le détail : encadré avec les leçons et leur durée, et « ⚡ 2 min » sur chaque leçon concernée. Simple alerte, rien n'est bloqué. Seuil modifiable (filtre logiprogress_suspect_seconds).

## Nouveautés 1.26.2 (LogiProgress : réinitialiser un module)

- Cours organisés en modules Sensei : dans le détail d'un élève, les leçons sont regroupées par module, avec « ↺ Réinitialiser le module » (seules les leçons de ce module sont remises à zéro ; le reste du cours n'est pas touché). Confirmation, e-mail à l'élève (« Ton module « … » a été réinitialisé ») et journal LogiOne, comme pour une leçon ou un cours.

## Nouveautés 1.26.1 (LogiProgress : l'élève est informé)

- Après une réinitialisation (cours ou leçon), l'élève reçoit un e-mail à l'adresse de son profil : qui a réinitialisé quoi, ce qui est à refaire et le lien pour le reprendre. Il peut répondre directement à l'enseignant. Le message dans LogiProgress confirme l'envoi (ou signale l'absence d'adresse) et le journal LogiOne le note.

## Nouveautés 1.26.0 (LogiProgress : réinitialiser l'avancement d'un élève)

- Détail par élève : « ↺ Réinitialiser le cours » (toutes les leçons du cours, l'élève reste inscrit) et « ↺ Réinitialiser » sur chaque leçon commencée. Confirmation claire avant d'effacer ; retour sur le même élève avec un message.
- Utilise les fonctions de Sensei quand elles sont présentes (Sensei_Utils::reset_course_for_user, sensei_remove_user_from_lesson), comme depuis les réglages de Sensei ; progression et réponses aux quiz effacées ; un cours « terminé » repasse « en cours ».
- Nouvelle permission « Réinitialiser l'avancement d'un élève » (LogiProgress, matrice des permissions) : réservée aux administrateurs tant qu'elle n'est pas donnée à un rôle.
- Chaque réinitialisation est inscrite dans le journal LogiOne (qui, quel élève, quel cours ou quelle leçon), avec une copie de ce qui a été effacé.

## Nouveautés 1.25.1 (LogiExam : panneau de pré-correction réductible)

- Correction : une fois des copies pré-corrigées, le panneau « Pré-correction IA » se réduit à une ligne (« Niveau d'exigence : Bienveillante · 13 copies pré-corrigées · Afficher »). Un clic le rouvre (niveau d'exigence, « Changer de niveau… », « Pré-corriger toutes les questions et toutes les copies ») ; « Réduire » le referme. Avant toute pré-correction, il reste ouvert.

## Nouveautés 1.25.0 (LogiExam : correction avec l'IA)

- Correction par élève : critères d'évaluation (1/3) et analyse de l'IA (2/3) côte à côte (l'un sous l'autre sur petit écran).
- Analyse de l'IA séparée : « ✓ Réussi » d'un côté, « ✗ Pas réussi / à revoir » de l'autre (◐ pour ce qui est en partie juste), puis les remarques et la conclusion. L'IA écrit désormais chaque point avec ✓ / ◐ / ✗ ; les anciennes analyses sont classées d'après leurs mots.
- Commentaire pour l'élève mis d'office : celui de l'IA, qui explique en 2 à 4 phrases courtes, en français usuel de Suisse romande, pourquoi les points sont donnés ou non (sans liste ni formules toutes faites). La partie commentaire est dépliée dès qu'elle contient un texte, avec la mention « Commentaire proposé par l'IA : relisez-le » ; il est enregistré avec la correction et reste modifiable.

## Nouveautés 1.24.9 (LogiPlanning : plan favori)

- Côté enseignant (LogiPlanning dans LogiOne) : bouton « ☆ Définir comme plan favori » à côté du choix du plan. Le plan favori s'affiche d'office à l'ouverture de LogiPlanning et se trouve en tête de liste (★). On peut toujours afficher un autre plan ; « ★ Mon plan favori » recliqué retire le favori. Chaque enseignant a son propre favori (enregistré dans son profil, aucune table modifiée).

## Nouveautés 1.24.8 (tuiles : toute la largeur de l'écran)

- Le contenu ouvert sous une tuile (portail LogiOne et tuiles de contenu) occupe toute la largeur de l'écran. À côté d'une grande tuile, il se place sous la rangée, et la page défile pour le montrer.
- LogiPlanning placé dans une tuile (LogiOne ou LogiHub, même ancienne version) reste dans le cadre du contenu ouvert : plus de partie coupée à gauche ni à droite.
- À installer avec LogiHub 0.10.2 (même comportement pleine largeur dans LogiHub).

## Nouveautés 1.24.7 (LogiExam : lecture audio des questions)

- Pendant l'évaluation, chaque question a une barre « 🔊 Écouter » pour tous les élèves : lecture à voix haute du contexte, de la question et des choix de réponse (QCM, Vrai/Faux, éléments à remettre dans l'ordre). « ⏸ Pause », « ▶ Reprendre », « ⏹ Arrêter », vitesse lente / normale / rapide (mémorisée). Le passage lu est surligné.
- Synthèse vocale du navigateur, voix française de l'ordinateur (fr-CH si disponible) : rien n'est envoyé sur internet, aucun coût. La réponse attendue n'est jamais lue.
- Aucun effet sur le mode sécurisé (pas de signalement). Les consignes de début d'examen annoncent la lecture audio et conseillent les écouteurs.

## Nouveautés 1.24.6 (LogiExam : correction)

- Correction par élève : la réponse de l'élève est clairement identifiée (cadre bleu « Réponse de l'élève · nom », texte plus grand) et placée à côté de la réponse attendue (l'une sous l'autre sur petit écran).
- Commentaire par question toujours replié, même rempli : la ligne repliée affiche « 💬 Commentaire : début du texte… modifier » ; un clic l'ouvre pour le modifier.
- Critères et IA : à côté de chaque critère, l'avis de l'IA (« ✓ IA », « ◐ IA », « ✗ IA »). « Points de l'IA » (ou une pré-correction en direct) coche les critères jugés remplis, garde les points de l'IA, et écrit dans le commentaire pour l'élève la liste des critères (✓ rempli, ◐ partiellement, ✗ non rempli) suivie du commentaire de l'IA. Vous pouvez compléter ce commentaire : il n'est plus écrasé ensuite.
- Commentaires pour les élèves simples et humains : l'IA écrit directement à l'élève (tutoiement par défaut, vouvoiement possible dans les réglages IA), une ligne par critère (« ✓ Tu as bien vu que… », « ✗ Il manque… : pense à… »), puis un encouragement ou un conseil ; sans jargon, sans points. Réponse vide : message bienveillant au lieu de « 0 point proposé automatiquement ».

## Nouveautés 1.24.5 (LogiExam : correction plus rapide, IA qui apprend)

- Correction par élève, une question à la fois : barre « Question 2 / 8 » avec pastilles (vertes quand les points sont mis) pour revenir à une question. Un raccourci (Tous les points / Moitié / Aucun point / Points de l'IA) affiche directement la question suivante. Après une saisie manuelle : bouton « Question suivante → » (ou touche Entrée). « Afficher toutes les questions » revient à l'affichage complet ; la case « passer directement à la question suivante » permet de couper le passage automatique (choix mémorisés).
- Commentaire par question replié : « + Ajouter un commentaire » l'ouvre ; il reste ouvert s'il contient un texte.
- Analyse de l'IA présentée en liste : un point par critère (✓ rempli, ◐ partiel, ✗ non rempli), puis la conclusion. L'IA est désormais priée de répondre sous cette forme.
- L'IA apprend de vos corrections : à chaque enregistrement, la proposition de l'IA et vos points sont mémorisés (table LogiOne `logipilot_ai_feedback`, tables LogiExam inchangées). Pour la même question, l'IA reçoit ensuite quelques réponses déjà notées par vous (sans nom d'élève) avec vos points et commentaires, et votre tendance générale (plus ou moins généreux). Réglages IA : activer/désactiver, compteur, « Effacer tout ce que l'IA a appris ».

## Nouveautés 1.24.4 (LogiExam : correction et corbeille)

- Correction : la question est toujours affichée en entier, en gras et lisible (« Question 1 », points à droite). Le titre automatique coupé après quelques mots n'est plus affiché ; un titre choisi par l'enseignant reste visible.
- Bouton « Points de l'IA (x) » à côté de « Tous les points / Moitié / Aucun point » : il attribue les points proposés par l'IA (et reprend son commentaire si le vôtre est vide). Bouton « Attribuer les points proposés par l'IA à toutes les questions » pour une copie, et « … à tous les élèves pour cette question » en correction par question. Il demande avant de remplacer des points déjà différents. Rien n'est enregistré avant « Enregistrer ».
- Corbeille : bouton « Corbeille (n) » en haut de Mes évaluations. Une évaluation supprimée n'est jamais effacée (questions, codes, copies et corrections conservés) ; « Restaurer » la remet avec son statut d'avant. La liste indique la classe, le nombre de copies et la date de suppression.
- Aucune modification des tables de données.

## Nouveautés 1.24.3 (LogiExam : pré-correction IA groupée)

- La pré-correction groupée n'envoie plus toutes les copies en une seule demande (que l'hébergement coupait, d'où l'erreur « Unexpected token '<' … is not valid JSON ») : le navigateur traite une copie à la fois, et chaque demande s'arrête d'elle-même après ~20 secondes puis reprend aussitôt là où elle s'était arrêtée.
- Progression affichée : « Copie 2 sur 18 · nom de l'élève · 12 proposition(s) enregistrée(s)… », puis un résumé final.
- Par défaut, les réponses qui ont déjà une proposition IA sont conservées (pas de double coût). La case « Refaire aussi les réponses déjà pré-corrigées » permet de tout refaire.
- Si le serveur coupe malgré tout, un message clair en français s'affiche ; les propositions déjà faites restent enregistrées et il suffit de relancer.
- Aucune modification des tables de données.

## Nouveautés 1.24.2 (tuiles de contenu)

- Tuiles de contenu (ex. page « Ressources ») : comme dans le portail, le contenu d'une tuile s'ouvre juste sous la tuile choisie et la page se place pour le montrer ; les grandes tuiles voisines gardent leur hauteur. Même amélioration dans LogiHub 0.10.1.

## Nouveautés 1.24.1 (portail)

- Portail LogiOne : le contenu d'une tuile (ex. « Plans de formation », « Ressources ») s'ouvre juste sous la tuile choisie, même à côté d'une grande tuile, et la page se place pour montrer la tuile et le début de son contenu. Les grandes tuiles voisines gardent leur hauteur. Le placement suit la largeur de l'écran (ordinateur, tablette, téléphone).

## Nouveautés 1.24.0 (LogiExam)

- « Mes évaluations » en colonnes, une par classe (comme LogiNotes) : d'abord « Non affectées » (aucune classe choisie), puis chaque classe. Dans une classe, les évaluations sans date en premier, puis dans l'ordre de leur date de planification. Chaque carte montre la date en haut.
- Boutons flottants de l'éditeur : visibles dès l'arrivée sur la page ; masqués seulement une fois le formulaire dépassé (ex. panneau de progression).
- Deux boutons d'enregistrement flottants : « Enregistrer comme brouillon » (aucun code élève, pas de fiche LogiNotes, les élèves n'y ont pas accès) et « Enregistrer la version définitive » / « Publier la version définitive » / « Mettre à jour » (codes créés, évaluation ouverte aux dates prévues). Une évaluation déjà commencée par des élèves ne peut plus repasser en brouillon.
- Correction : dans le portail LogiOne, WordPress transformait « && » dans les scripts de la page de création (classes favorites, ajout de question, boutons flottants), ce qui les empêchait de fonctionner. Les scripts n'utilisent plus ces caractères ; un test vérifie désormais tous les scripts des pages du portail.

## Nouveautés 1.23.1

- Relevé de progression : la « date limite pédagogique » compte maintenant dans le calcul. Seules les leçons terminées au plus tard ce jour-là donnent droit au bonus ; une leçon terminée (ou un quiz rendu) après compte comme non faite, avec le motif « terminée après la date limite ». Sans date, c'est la progression au moment de la validation qui compte. Nouveau bouton « Appliquer la date et recalculer » (enregistre la date et met à jour la lecture provisoire, sans rien valider). Dans LogiExam, la date est rangée dans la table LogiOne des options (les tables historiques ne sont pas modifiées).
- Éditeur LogiExam : le bouton « Mettre à jour » / « Créer l'évaluation » est flottant, sous « Ajouter une question ». Les deux boutons flottants s'affichent tant que le formulaire de l'évaluation est à l'écran.

## Nouveautés 1.23.0 (LogiExam)

- Ordre aléatoire des questions (option de l'évaluation) : chaque élève reçoit les questions dans un ordre différent, toujours le même pour lui (rechargement, reprise) ; ses réponses restent liées à chaque question ; la correction et les exports gardent l'ordre de l'évaluation.
- « Ma bibliothèque » (non partagée) : un enseignant y range ses évaluations pour les réutiliser (« Autres actions » > « Ajouter à ma bibliothèque », ou la case dans l'évaluation). Classement par cours Sensei avec un champ pour choisir le cours ; « Réutiliser » crée une nouvelle copie en brouillon ; une évaluation supprimée de « Mes évaluations » reste récupérable. La « Bibliothèque partagée » reste dans le 2e onglet.
- Classes favorites : dans le choix du groupe classe, seules les classes marquées d'une étoile s'affichent ; « Toutes les classes » (avec recherche) pour les autres. Ce sont les mêmes favorites que dans LogiProgress.
- Élève : la barre de progression (questions répondues, accès aux questions) reste toujours visible en haut pendant le défilement.
- Élève : bouton « Masquer le minuteur » pour les élèves stressés ; alertes à mi-parcours et quand il reste un tiers du temps ; pendant les 5 dernières minutes, le minuteur est toujours affiché (en rouge).
- Création d'une évaluation : bouton flottant « Ajouter une question », toujours accessible ; la nouvelle question est amenée à l'écran.
- Données : l'ordre aléatoire et « Ma bibliothèque » sont rangés dans une nouvelle table propre à LogiOne (wp_logipilot_exam_options). Les tables historiques de LogiExam ne sont pas modifiées (aucune colonne ajoutée, version inchangée).

## Nouveautés 1.22.1 (LogiExam)

- Grand avertissement « Attention aux touches du clavier ! » en haut des règles que l'élève lit avant d'entrer : ne jamais toucher la touche Windows (elle ouvre le menu Démarrer et fait sortir de l'écran), ni Échap, Alt + Tab, F11, Ctrl + W / T, Cmd + Tab sur Mac ; regarder la touche avant de taper ; en cas de doute, lever la main.
- « Appuyer sur la touche Windows » ajouté aux actions interdites.
- Rappel sur la page où l'élève saisit son code et sur la fiche « Code personnel d'évaluation » (PDF).

## Nouveautés 1.22.0 (LogiProgress)

- Nouvelle mise en page, au même visuel que les autres modules : en-tête, deux vues « Progression » et « Élèves inactifs ».
- Choix en deux étapes avec des boutons ronds : le groupe classe (un seul) puis les cours (plusieurs). La recherche filtre en direct pendant la frappe (« m106 », « M 106 » ou « securite » trouvent le même cours) et un cours trouvé se sélectionne d'un clic. Les cours cochés restent sélectionnés même s'ils sont masqués par la recherche. Une barre en bas résume le choix.
- Groupes favoris : chaque enseignant marque ses groupes d'une étoile. Par défaut seuls ses favoris s'affichent ; « Tous les groupes » (avec recherche) permet d'en choisir un autre. Les favoris sont personnels (enregistrés dans le compte de l'enseignant).
- Résultats : rappel du groupe et des cours avec « Modifier la sélection », export PDF, chiffres clés (progression moyenne, élèves ayant tout terminé, élèves à relancer, rendus à corriger), un onglet par cours, filtres Tous / À relancer / En cours / Terminé, pastilles compactes avec date courte (date et heure exactes au survol), colonne « Dernière activité » signalée en jaune au-delà du seuil d'inactivité.
- Détail par élève : liste des élèves à gauche, fiche de l'élève choisi à droite (chaque cours, chaque leçon avec son état et sa date, révisions récentes). Remplace la grille de cartes « Résumé par élève ».
- Onglet « Élèves inactifs » : choix du groupe (favoris), élèves sans activité depuis le seuil réglé, avec un lien vers leur détail.
- Fonctionne aussi sans JavaScript (formulaire classique) ; adapté au téléphone. Lecture seule des données Sensei.

## Nouveautés 1.21.1 (LogiExam)

- La fiche « Code personnel d'évaluation » (PDF des codes) reprend le visuel de la version élève à imprimer : bandeau LogiExam avec logo, élève et groupe, titre de l'évaluation, informations (questions, durée, ouverture, fin de session, cours), grand cadre du code et marche à suivre. Toujours une page par élève.
- Les titres des PDF (dont le nom de l'évaluation dans la version élève à imprimer) passent à la ligne au lieu de déborder de la page quand ils sont longs.

## Nouveautés 1.21.0 (LogiExam)

- Nouveau : « Version élève à imprimer » (PDF ou Word), disponible dès l'étape « Préparer », même pour un brouillon, et rappelée à l'étape « Passation ». Plan B en cas de panne : les élèves font l'évaluation sur papier.
  - Page 1 : cadre Nom, Prénom, Classe, Date, Points / total et Note ; titre, cours, durée, nombre de questions et points ; consignes, entreprise, rôle, contexte.
  - Dès la page 2 : chaque question avec ses points (et la précision demandée pour un calcul), son image et la zone de réponse : cases à cocher (QCM, Vrai / Faux), lignes d'écriture (réponse courte, longue, justification, développement), « Résultat : ____ », éléments à remettre dans l'ordre mélangés et repérés A, B, C… avec des cases numérotées, lettres mélangées pour un mot.
  - Aucune bonne réponse, réponse attendue, critère ni barème. Le document « Évaluation avec le corrigé » reste disponible à part.
- LogiClass, onglet Paramètres : les exemples d'adresses dans les champs vides sont génériques (prenom.nom@…) au lieu d'adresses personnelles.

## Nouveautés 1.20.0 (LogiExam)

- Export PDF d'une copie aux couleurs de LogiExam : bandeau anthracite avec le logo LogiExam et filet jaune sur chaque page, titres soulignés de jaune, encadré « Note finale » lisible (note en grand sur fond anthracite, points, bonus, malus et total à côté).
- Informations de la copie : « Module » n'apparaît plus quand le cours Sensei est indiqué (doublon) ; « Publication LogiNotes » et « Note sans bonus/malus » supprimés.
- Heure de début, heure limite, temps effectif et temps restant à la remise toujours renseignés : le début est enregistré dès la première réponse (ou la reprise), et pour les copies passées avec une version précédente, il est retrouvé d'après la saisie du code et le premier évènement enregistré. Les heures sont lues dans le fuseau du site puis affichées en heure de Zurich.
- Sécurité dans l'export : uniquement les blocages et alertes (sorties, perte de focus, raccourcis…) ; ni le déblocage par l'enseignant ni la reprise de l'élève.
- Un contexte et sa première question sont toujours sur la même page.
- Barème de l'évaluation (notes à la demi, de 6 à 1, avec les points correspondants) affiché sous les questions dans l'éditeur, recalculé en direct quand on change les points.
- Nouveau : exporter une évaluation en PDF ou en Word (.docx) avec toutes ses informations, les consignes, le barème, et chaque question avec ses points, ses images, ses propositions et son corrigé (bonne réponse, réponse attendue, critères, ordre correct, précision demandée). Bouton « Exporter l’évaluation avec le corrigé » (PDF ou Word) dans l'étape « Préparer ».

## Nouveautés 1.19.2 (LogiExam)

- Questions de mathématiques / calcul : choix du résultat accepté (non précisé, exact, arrondi à l'entier, à 1, 2, 3 ou 4 décimales). L'élève voit la consigne bien en évidence sur la question ; elle figure dans la correction (« Précision demandée ») et dans l'export PDF de la copie. La correction automatique du résultat en tient compte (ex. arrondi à 2 décimales : 233,33 juste, 233,3 faux ; virgule ou point acceptés).
- Alertes de sécurité : les évènements enregistrés à la même seconde (ex. « Perte de focus » et « Évaluation bloquée ») ne forment plus qu'une alerte, à l'écran de correction et dans l'export.
- Page élève : l'encadré « J'ai lu les règles » a la même largeur que les règles, bouton centré ; les deux encadrés de règles passent l'un sous l'autre quand la place manque ; l'écran « Évaluation envoyée / clôturée » est plus large et centré.

- Fiche « Code personnel d'évaluation » : module, nom de l'élève et groupe passent à la ligne et poussent la suite (plus de texte superposé) ; si la fiche est très chargée, les textes du haut se réduisent pour que tout tienne au-dessus du cadre du code. Toujours une seule page par élève.

## Nouveautés 1.19.1 (LogiExam)

- Export PDF d'une copie : les textes longs (titre de l'évaluation, module) passent à la ligne sans chevaucher les rubriques suivantes ; les questions, réponses et corrections commencent toujours en page 2.
- Export PDF, question « Remettre dans l'ordre » avec images : les images apparaissent dans l'ordre choisi par l'élève, réduites, trois par ligne, avec leur texte juste dessous et « Bien placée » / « Mal placée (place correcte : n) ».
- Pré-correction IA d'un calcul : si l'élève n'a écrit aucun développement, l'IA le voit (« aucun développement écrit ») et les critères « développement / démarche / raisonnement / justification / étapes » ne sont jamais accordés, même si l'IA les propose (l'analyse l'explique). Relancez la pré-correction sur les copies déjà proposées.
- Page élève, « Conséquences automatiques » : suit le réglage du nombre de sorties (avec 3 : « 1re et 2e sortie » bloquent, la 3e termine), étiquettes alignées.
- Copie rendue : si l'élève quitte ensuite le plein écran, l'onglet ou la fenêtre, plus aucun incident n'est enregistré (le travail est terminé) ; la copie est simplement clôturée. Les traces « après la remise » enregistrées par les versions précédentes ne sont plus affichées ni comptées dans les alertes.

## Nouveautés 1.19.0 (LogiClass : un seul maître de classe)

- Une classe LogiClass (nom + année scolaire) n'a qu'un maître de classe : l'enseignant qui l'a. Lui seul la voit, la modifie et lance les actions.
- Un autre enseignant ne peut ni la créer, ni renommer une de ses classes en celle-ci, ni l'importer (les lignes de cette classe sont ignorées dans son import, avec une alerte). Le nom est comparé sans tenir compte des majuscules, accents et espaces. Le message ne dit pas qui est le maître de classe.
- « Donner une classe à un collègue » ne concerne plus que LogiNotes (une classe LogiClass ne peut pas être copiée chez un autre enseignant).
- Écran « Espaces enseignants » : nouveau tableau « Maîtres de classe » (classe, année, maître) ; une classe créée en double avant cette règle est signalée.

## Nouveautés 1.18.1 (confidentialité entre enseignants)

- Vérification complète : un enseignant ne voit ni ne modifie jamais les données LogiNotes / LogiClass d'un collègue (écrans, adresses avec les numéros des fiches d'un autre, exports, justificatifs PDF, suppression, changement d'espace, pages publiques, visiteur non connecté).
- Corrigé : dans LogiClass, chaque e-mail envoyé par n'importe quel enseignant partait en copie cachée à l'adresse personnelle de l'enseignant d'origine (Carlos). Désormais, dans l'espace d'un autre enseignant, la copie cachée part à cet enseignant lui-même. Ses rappels et l'adresse de suivi interne, s'il n'a rien réglé, sont aussi les siens, et non plus l'adresse de l'administrateur du site. L'espace d'origine garde son fonctionnement habituel.
- Justificatifs PDF de LogiNotes : nom de fichier impossible à deviner pour les nouveaux envois.
- LogiClass : contrôle plus strict des chemins de fichiers privés (un dossier « private-t1 » ne peut plus être confondu avec « private-t12 »).
- Diagnostic : le test « accès direct aux dossiers privés » vérifie aussi les dossiers de chaque enseignant.

## Nouveautés 1.18.0

- LogiExam, nouveau type « Vrai / Faux argumenté » : l'élève choisit Vrai ou Faux puis justifie. Le bon choix est noté automatiquement (par défaut la moitié des points, réglable) ; l'argumentation se corrige à la main ou avec la pré-correction IA, qui reçoit le barème choix / argumentation et l'argumentation attendue.
- LogiExam, nouveau type « Remettre dans l'ordre » : des éléments (texte, image, ou image avec légende) ou les lettres d'un mot. L'élève reçoit les éléments mélangés (toujours le même mélange pour lui, jamais le bon ordre) et les déplace en glissant (souris, doigt) ou avec les flèches (clavier : Alt + flèches). Correction automatique « au prorata des éléments bien placés » (au quart de point) ou « tout juste ou rien » ; deux lettres identiques sont interchangeables. Correction : ✓ / ✗ par élément, vignettes, « n éléments bien placés sur N ».
- Éditeur : liste des éléments dans le bon ordre, ajout, suppression, flèches ↑ ↓, une image par élément.
- Les navigateurs rechargent les fichiers de LogiExam à chaque nouvelle version de LogiOne.
- LogiLive : le message d'information aux élèves ne s'affiche jamais par-dessus une évaluation LogiExam.

## Nouveautés 1.17.0

- LogiLive plus direct : l'élève envoie un signal toutes les 10 s (60 s si l'onglet est caché) et le tableau de l'enseignant s'actualise toutes les 5 s. Fermer la page fait passer l'élève « Page fermée » (hors ligne) aussitôt ; passer à une autre page du site ne compte pas comme une fermeture.
- Nouvelle colonne « Dernière action » : défilement (avec la part de la page), clic, vidéo (lecture, pause, fin), contenu intégré (vidéo YouTube, exercice…), copie, onglet quitté puis revenu, inactivité (aucun geste depuis 1 min). La liste « Avant » montre les actions précédentes. États « Onglet quitté », « Inactif », « Sans signal ».
- Protection des données : seul le type d'action est noté, jamais le texte copié ni ce que l'élève tape. Actions effacées après 24 h (nettoyage automatique). Réglage « Suivi des actions » (écran LogiLive) : le couper efface tout de suite les actions notées. Les élèves sont informés une fois par un petit message (réglable).
- Version test : LogiLive reste en lecture seule.

## Nouveautés 1.16.0

- Nouveau module LogiEval (issu de LogiEval 2.2.0) : tuile dans le portail LogiOne, toute la largeur. L'enseignant connecté est reconnu par son compte (même adresse e-mail que dans LogiEval) : plus besoin d'identifiant. Si l'adresse diffère, l'identifiant est demandé une seule fois puis mémorisé. Les liens d'évaluation restent dans le portail.
- La page LogiEval indépendante ([logieval_login], connexion par identifiant reçu par e-mail) fonctionne comme avant.
- Nouveau module LogiPlanning (issu de LogiPlanning LMS 1.2.5) : page des élèves (même shortcode [logiplanning_lms]) aux couleurs LogiOne (prochain cours mis en avant, journées passées repliées, liste ou calendrier, export agenda) ; dans LogiOne, l'enseignant choisit un plan et le voit comme ses élèves ([logiplanning_enseignant]).
- LogiPlanning, administration : écran Journées revu (une ligne compacte par journée, détails dans « Plus », ajout replié, barre « Enregistrer » qui reste visible, alerte si on quitte sans enregistrer) ; plans : shortcode à copier en un clic.
- Reprise des données : LogiEval et LogiPlanning gardent leurs tables et réglages (aucune table recréée, vidée ni renommée). À l'activation de LogiOne, les plugins autonomes sont désactivés. Les rôles d'enseignement (droits LogiNotes, LogiProgress ou LogiExam, ou rôle « enseignant logistique ») reçoivent une fois LogiEval et la consultation des plans ; l'administration LogiEval et la gestion des plans restent à l'administrateur.
- Permissions : onglets LogiEval et LogiPlanning.
- Version test : LogiEval et LogiPlanning y travaillent sur une copie (tables lpt_), les plugins d'origine restent actifs et intacts.

## Nouveautés 1.15.0

- Nouveau module LogiLive (issu de LogiLive 1.0.0) : suivi en direct de la classe (élèves connectés, leçon ouverte, dernière activité), au même titre que LogiProgress. Tuile dans le portail, menu LogiLive, permission « Voir l'activité de la classe en direct » (onglet LogiLive de l'écran Permissions), module désactivable. Toute la largeur de l'écran.
- Reprise automatique : les rôles qui avaient accès à LogiLive (droit lslca_access ou rôle « enseignant logistique ») le gardent. Si le plugin LogiLive autonome est actif, il est désactivé à l'activation de LogiOne (mêmes données, mêmes shortcodes [logilive] et [logiprof_sensei_live_activity]).
- Version test : LogiLive y est en lecture seule (aucune activité enregistrée).
- LogiProgress : « Résumé par élève » retravaillé (une carte par élève, barre de progression par cours, même pastille d'état que le tableau pour chaque leçon) ; libellés avec accents (Terminée, Élève, Non commencée…).

## Nouveautés 1.14.0

- Permissions : un onglet par module (Accès, LogiNotes, LogiProgress, LogiExam, LogiClass, Liaisons, Tuiles), libellés clairs sans codes techniques, choix des rôles affichés (les droits des rôles masqués sont conservés), case « Tout le module » par rôle, droit indispensable signalé et coché automatiquement. Les droits individuels sont aussi regroupés par module.

## Nouveautés 1.13.0

- LogiNotes, onglet Évaluations : un gros bouton « Nouvelle évaluation » ouvre le formulaire sur place ; les évaluations sont rangées en colonnes par groupe classe, de la plus récente à la plus ancienne (6 visibles, les plus anciennes repliées), avec un « + » par colonne pour créer une évaluation déjà réglée sur ce groupe classe. Filtres par groupe classe et par évaluation ; dates écrites « 9 octobre 2026 ».

## Nouveautés 1.12.1

- Disposition du portail : dans une tuile « Haute », le texte est juste sous la grande icône (bloc centré dans la tuile).

## Nouveautés 1.12.0

- Tuiles de contenu : nouvel élément « Bloc de texte », mis en forme comme un texte normal (paragraphes, titres, gras, italique, souligné, surligné, liens, listes). Une tuile peut ne contenir qu'un texte : un clic le déplie sur place.
- Disposition du portail : la page suit exactement l'aperçu (une tuile « Petite » reste petite à côté d'une tuile normale ; « Haute » vaut deux tuiles normales).
- Listes des tuiles : une seule puce, même si le thème du site ajoute les siennes.

## Nouveautés 1.11.1

- Texte en haut d'une tuile ouverte : choix du type de texte (Paragraphe, Titre 1 à Titre 4).
- Nouvel écran « Disposition du portail » (administration) : ordre des tuiles par glisser-déposer ou flèches, taille de chaque tuile (petite, normale, haute, large, pleine largeur), 2 à 4 colonnes, largeur de la mosaïque, aperçu en direct. Tant que rien n'est enregistré, l'accueil garde sa présentation d'origine.
- Outils ouverts dans le portail (LogiClass, LogiProgress, LogiNotes, LogiExam) : toute la largeur de l'écran, jusqu'à 1760 px. LogiProgress n'est plus coupé à gauche.

## Nouveautés 1.11.0

- Tuile ouverte présentée comme l'espace élèves : sous-rubriques en onglets (ex. CFC / AFP), liens en lignes compactes, outils dépliés sur place.
- Texte mis en forme en haut d'une tuile ouverte (éditeur visuel : gras, italique, souligné, surligné, liens, listes).
- Icônes importées : pour la tuile elle-même (remplace les cubes) et pour chaque élément (PNG, JPG, WebP ou SVG nettoyé).
- Une rubrique peut reprendre le contenu d'une autre tuile (éléments communs gérés une seule fois) ; une tuile réservée reste cachée à ceux qui n'y ont pas accès.

## Correctifs 1.10.1

- Tuiles de contenu : fermées tant qu'on ne clique pas, même si le thème du site force l'affichage des éléments masqués.
- Rubriques à l'intérieur d'une tuile : s'ouvrent de nouveau au clic (le script des tuiles était vide en 1.9.x et 1.10.0).
- Script du portail chargé comme fichier (compatible avec les extensions d'optimisation).

## Nouveautés 1.10.0

- Bandeau du portail allégé : ni nom, ni lien d'administration, ni déconnexion.
- LogiExam occupe toute la largeur disponible dans le portail.
- Tuiles de contenu : palette complète de couleurs (pastilles + couleur libre) ; le contenu se déplie sous la tuile, sur la page des tuiles ; une tuile qui ne contient qu'un lien mène directement à ce lien.
- Chaque lien s'ouvre dans le même onglet ou dans un nouvel onglet, au choix (« Ouverture du lien »).

## Tuiles de contenu (1.9.0)

- L'administration crée autant de tuiles qu'elle veut. Chaque tuile a un nom, une phrase, une couleur (vert clair, vert, anthracite, jaune, blanc) et un contenu : rubriques, sous-rubriques, liens vers des pages ou des sites, outils intégrés par shortcode.
- Les tuiles s'affichent dans le portail `[logione]`, à la suite des outils, et s'ouvrent dans la même page.
- **Par groupe** : onglet « Accès et comportement », « Réserver cette tuile à certains rôles ». Chaque élément peut aussi être limité par rôle. Un enseignant ne voit que les tuiles de son rôle qui ont du contenu.
- **Créer et modifier** : tuile « Gérer les tuiles » du portail (administration seulement), bouton « Modifier cette tuile » dans une tuile ouverte, ou LogiOne → Tuiles de contenu dans WordPress. Droit `lp_tiles_edit`, réservé à l'administrateur par défaut.
- Données propres à LogiOne (`logipilot_tiles_*`) : aucun autre plugin n'est lu, modifié ni désactivé.

## Page pour les enseignants : [logione] (1.2.0)

Placez `[logione]` dans une page du site (par exemple « Mes outils »). Chaque enseignant connecté y voit uniquement les outils auxquels il a droit et les utilise directement dans la page. Les responsables y ont un lien vers l'administration LogiOne, où se règlent les droits.
Variantes : `[logione module="notes"]` (classe, progress, notes, exam) ouvre directement un outil ; `[logione modules="notes,exam"]` limite les outils proposés.

## Ordre des outils, enseignants seulement (1.5.0)

- Le portail affiche les outils dans cet ordre : LogiClass, LogiProgress, LogiNotes, LogiExam.
- Un outil n'apparaît que si le rôle (ou l'utilisateur) en a le droit dans Permissions ; sinon il est invisible.
- LogiOne est réservé aux enseignants : un élève qui ouvre la page du portail voit « LogiOne est réservé aux enseignants » et aucun outil.
- Les élèves passent leurs évaluations comme avant, sur la page qui contient le shortcode LogiExam d'origine `[logiexam_student]` (version test : `[lpt_logiexam_student]`), sans aucun droit LogiOne.

## LogiClass et présentation du portail (1.3.0)

- « Gestion de classe » s'appelle désormais **LogiClass** (menus, portail, back-office). Les données et les réglages ne changent pas.
- Le portail présente chaque outil avec une courte description. Un clic ouvre l'outil dans la même page (jamais dans un nouvel onglet) ; le bouton **Retour à LogiOne** ramène à l'accueil des outils, et des raccourcis permettent de passer directement d'un outil à l'autre.

## Espaces enseignants (1.1.0)

- LogiNotes et LogiClass sont séparés par enseignant : tables `wp_gne_*` / `wp_gca_*` pour l'administrateur qui active LogiOne (données existantes, sans copie), `wp_gne_t{ID}_*` / `wp_gca_t{ID}_*` pour les autres, créées vides à la première utilisation. Réglages, modèles de messages, rappels et dossiers privés suivent le même découpage.
- Les liaisons LogiOne (correspondances, fiches liées, relevés, publications) sont rangées dans l'espace de l'enseignant concerné ; le journal est commun.
- LogiExam reste organisé par auteur ; les résultats d'une évaluation sont publiés dans l'espace LogiNotes de son auteur.
- Seuls les administrateurs peuvent afficher l'espace d'un autre enseignant (sélecteur « Données affichées »).
- LogiOne → Espaces enseignants : vue d'ensemble, copie d'une classe vers un collègue (sans notes), transfert des données d'origine vers un enseignant dont l'espace est vide.
- Les tâches planifiées (rappels, reprise des publications) s'exécutent une fois par espace.
- Droits individuels : LogiOne → Permissions → « Droits pour un utilisateur précis » (en plus du rôle).

## Échanges entre modules

| Événement | Effet |
| --- | --- |
| Enregistrement d'une évaluation LogiExam | Une fiche LogiNotes est créée (classe reliée, semestre de la date, maximum de points). Il n'y a jamais de doublon. Si une donnée manque, la fiche est mise « en attente de complément » avec le motif. |
| « Actualiser et valider la progression » | Un relevé daté est enregistré : leçons exigées et exclues, statuts, nombres exacts, règle, auteur. Il est figé ensuite. |
| Première note enregistrée sans relevé | Le relevé est figé automatiquement, sans appliquer les propositions. L'enseignant les valide ensuite. |
| Validation d'un relevé (papier) | Le +1 ou le −2 est écrit une seule fois dans l'ajustement LogiNotes, même sans points. |
| Validation d'un relevé (numérique) | Le bonus ou le malus est appliqué aux copies non finalisées, puis à chaque remise de copie. |
| « Publier les résultats dans LogiNotes » | Seules les copies finalisées sont publiées : points bruts, ajustements, note LogiExam, révision. La note n'est jamais recalculée. Une copie arrêtée pour sécurité est transmise comme triche, avec la note 1. |
| Réouverture d'une correction | L'ancienne publication reste affichée, avec la mention « en cours de révision ». La nouvelle publication crée une révision suivante. |
| LogiNotes indisponible | La demande reste en attente. Elle est reprise toutes les heures ou à la main (LogiOne → Synchronisation). |
| Note modifiée directement dans LogiNotes | La publication passe en conflit, sans écrasement. La personne autorisée choisit la version à garder. |

LogiClass n'échange aucune donnée avec les autres modules dans cette version.

## Recette

La recette (207 scénarios pour LogiOne, 150 pour la version test) se trouve dans la suite d'essais livrée à part. Elle couvre les usages historiques et les nouveaux échanges, sur WordPress 6.8.3, PHP 8.3 et MariaDB 10.11. Sensei LMS y est simulé : la lecture réelle de Sensei est à confirmer sur une copie du site.
