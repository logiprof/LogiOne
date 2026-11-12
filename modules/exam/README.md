# LogiExam

Plugin WordPress pour gérer des évaluations sécurisées avec Sensei LMS et Ultimate Member.

## Shortcodes

- `[logiexam_student]` : interface élève avec code unique, consignes, mode sécurisé et soumission.
- `[logiexam_teacher]` : tableau de bord enseignant, suivi des copies et alertes.

Les anciens shortcodes `[logieval_student]` et `[logieval_teacher]` restent disponibles comme alias de compatibilité.

## Version 3.5.0 — Une page par évaluation

- **Menu réduit à 4 entrées** : Mes évaluations · Bibliothèque · Banque de questions · Réglages (wp-admin et shortcode `[logiexam_teacher]`). Les anciennes adresses (codes, monitoring, sécurité, corrections, exports) mènent automatiquement à la bonne étape.
- **Mes évaluations** : une carte par évaluation avec statut (Brouillon, Prête, En cours, À corriger, Corrigée), compteurs, élèves bloqués et bouton « prochaine action ». Les actions secondaires (aperçu, dupliquer, partager, supprimer) sont dans « Autres actions ». Les élèves bloqués de toutes vos évaluations restent affichés en haut, avec le bouton « Débloquer ».
- **Page d’une évaluation en 4 étapes** : Préparer → Passation (codes, suivi en direct, registre de sécurité replié) → Corriger → Résultats (notes par classe, moyenne, CSV, PDF).
- **Démarrage élève simplifié** : code → règles (une seule fois) → contexte d’entreprise sur un écran séparé s’il existe → plein écran.
- **Codes automatiques** : un code unique par élève est créé à l’enregistrement (et pour les élèves ajoutés ensuite à la classe). Le PDF des codes peut être réimprimé à tout moment.
- **Correction** : bascule « Par élève / Par question », liste des copies avec leur état, « Élève précédent / suivant », « Finaliser et élève suivant », barre fixe avec total, note, bonus et malus, détails de sécurité repliés, avertissement si l’on quitte une copie sans enregistrer.
- **Actions groupées** : « Finaliser toutes les copies pré-corrigées » (reprend les propositions IA non modifiées ; les copies avec des questions sans points ne sont pas touchées) et « PDF de la classe (ZIP) » / « Tous les PDF élèves (ZIP) ».
- **Banque de questions** : même formulaire et mêmes types de questions que dans l’éditeur d’évaluation.
- **Code** : la classe principale est découpée en fichiers thématiques (`includes/evaluations/`), anciens écrans supprimés. Tests de bout en bout dans `tests/` (`node tests/suite.js`, 66 vérifications).

## Version 3.4.1

- Sévérité de la pré-correction IA : les 5 niveaux sont affichés explicitement (1/5 Très bienveillante → 5/5 Très exigeante, avec description).
- Équité : un seul niveau par évaluation, rappelé sur chaque copie. Il est verrouillé dès la première pré-correction ; « Changer de niveau… » efface les propositions IA déjà faites (copies non terminées) pour que tous les élèves soient repré-corrigés au même niveau. Les points saisis par l’enseignant ne sont jamais modifiés.
- Le niveau utilisé est enregistré avec chaque proposition IA.

## Version 3.4.0

- Image par question (facultatif) : ajout, remplacement ou retrait dans l’éditeur d’évaluation, les sections d’histoire et la banque de questions.
- Élève : l’image s’affiche sous la question ; bouton « Agrandir » qui ouvre l’image en grand dans la page (zoom +/−, défilement), sans quitter le plein écran ni déclencher de blocage. Sur Chrome/Edge, la touche Échap ferme l’image au lieu de quitter le plein écran ; sur les autres navigateurs, un message invite à fermer avec le bouton.
- L’image apparaît aussi dans l’aperçu enseignant et dans les corrections.
- Pré-correction IA : curseur « Sévérité » (Très bienveillante → Très exigeante) par évaluation, en haut de la page Corrections, pris en compte par toutes les pré-corrections.

## Version 3.3.0 — Critères d’évaluation

- Chaque question peut avoir un barème : une liste de critères avec leurs points (éditeur d’évaluation, sections d’histoire et banque de questions). Le total des critères est comparé aux points de la question.
- Correction d’un élève : les critères s’affichent en cases à cocher ; cocher additionne automatiquement les points (modifiables ensuite).
- Correction question par question : barème rappelé sous la réponse attendue et cases à cocher pour chaque élève.
- Pré-correction IA : le barème est envoyé à l’IA, qui évalue critère par critère et indique lesquels sont remplis.
- Les critères ne sont jamais envoyés aux élèves pendant l’évaluation. Ils sont copiés avec l’évaluation lors d’une duplication.
- Correction terminée : bandeau « Correction terminée le … — note … » et formulaire verrouillé. Bouton « Rouvrir la correction » pour la modifier, puis la finaliser à nouveau.
- Les copies terminées ne sont plus modifiées par la correction question par question ni par la pré-correction IA de toutes les copies.

## Version 3.2.5

- Correction d’un élève : encadré « Bonus / Malus » placé en haut de la copie, boutons rapides (+0.5, +1, −0.5, remise à zéro) et récapitulatif en direct (points des questions, bonus, malus, total, note).
- Correction question par question : nouveau tableau « Bonus / Malus par élève », enregistré avec toute la progression.
- Les valeurs négatives sont refusées (un malus se saisit dans le champ malus).

## Version 3.2.4

- PDF élève : les encadrés verts « Contexte / Section » (et les autres encadrés) s’agrandissent selon la longueur du texte, avec une marge après le texte.
- PDF élève : les questions longues passent à la ligne avant les points, qui ne se chevauchent plus avec le titre.

## Version 3.2.3

- Correction d’un élève : bouton « Pré-corriger toutes les questions de cet élève » en haut de sa copie (seule cette copie est envoyée à l’IA).

## Version 3.2.2

- Exports : pour chaque évaluation, un bouton « Exporter tout » et un bouton CSV par classe ayant des copies (classe par classe).
- CSV : nouvelle colonne « Classe », tri par classe puis par élève, statuts en français, accents corrects dans Excel.

## Version 3.2.1

- Groupe classe Sensei : sélection par boutons radio (un groupe par évaluation), avec l’option « Aucun groupe (élèves spécifiques uniquement) ».
- Dupliquer une évaluation (la sienne ou depuis la bibliothèque) ne reprend jamais le groupe classe, les élèves spécifiques ni les dates d’ouverture / fermeture. Un message le rappelle sur la copie.

## Version 3.2.0 — Multi-enseignants

- Chaque enseignant (rôle Enseignant Logistique) ne voit que ses évaluations, ses questions, ses corrections, son monitoring et ses élèves bloqués.
- L’administrateur voit tout, avec une bascule « Mes évaluations / Tous les enseignants » (choix mémorisé).
- Bibliothèque partagée : une évaluation peut être partagée (case dans l’évaluation ou bouton « Partager » de la liste). Les collègues la prévisualisent et la dupliquent dans leur espace : copie indépendante, questions comprises, sans les classes, élèves ni dates de l’auteur.
- Dupliquer une évaluation copie maintenant aussi ses questions (avant, la copie et l’original partageaient les mêmes questions).
- Contrôles serveur : impossible de modifier, supprimer, corriger, exporter ou débloquer dans l’évaluation d’un collègue.
- Pré-correction IA : chaque enseignant enregistre sa propre clé API (chiffrée) dans Réglages › Ma pré-correction IA. Fournisseurs : OpenAI, Anthropic (Claude), Mistral AI, Google Gemini, ou toute API compatible OpenAI (URL libre). Bouton « Tester la connexion ».
- L’administrateur garde un interrupteur global « Autoriser la pré-correction IA ».
- Migration automatique : les évaluations et questions existantes sont attribuées au premier administrateur, et l’ancienne clé OpenAI globale devient sa clé personnelle.

## Version 3.1.15

- Copie annulée pour tricherie = note 1 (enregistrée en base, affichée dans Corrections et dans l’export CSV).
- PDF élève exportable comme pour les autres copies : en-tête « Copie annulée - tricherie », encadré rouge « Évaluation annulée pour tricherie - Note : 1 », note finale 1, questions marquées « Non corrigée ».
- PDF : les titres en majuscules gardent leurs accents (ÉLÈVE, DÉBUT…).

## Version 3.1.14

- Copies annulées pour tricherie (nombre maximal de sorties atteint) : clairement signalées et exclues de la correction.
  - Bandeau rouge en haut de l’évaluation avec le nom des élèves concernés.
  - Ligne rouge, badge « ⛔ Annulée – tricherie », points et note remplacés par « Annulée ».
  - À la place du formulaire de correction : encadré « Évaluation annulée pour tricherie — ne pas corriger » avec l’heure de chaque sortie et le journal de sécurité.
  - Exclues de la correction question par question, de la pré-correction IA et du filtre « En attente de correction » ; nouveau filtre « Annulées pour tricherie ».
  - Protection serveur : impossible d’enregistrer une correction sur une copie annulée.
  - Monitoring : statut « Annulée – tricherie (3 sorties) ».

## Version 3.1.13

- Correctif critique : à chaque sortie du plein écran, le serveur plantait (erreur PHP 8 « Unknown format specifier » dans le message « Tentative de sortie détectée (x/3) »). Le blocage n’était donc jamais enregistré, la demande de déblocage échouait, et après un premier déblocage l’élève pouvait se débloquer seul.
- Un déblocage enseignant ne sert qu’une fois : après la reprise, toute nouvelle sortie rebloque l’élève.
- Règle : 2 déblocages possibles au maximum ; la 3e sortie termine l’évaluation (résultat « Triche suspectée »).
- Le temps de pause ajouté à l’échéance utilise le fuseau horaire WordPress.
- Thèmes en blocs : les scripts sont chargés correctement même quand le shortcode est rendu avant l’en-tête.

## Version 3.1.12

- Rechargement de page / onglet fermé pendant l’évaluation : l’élève ressaisit son code et retrouve directement son état (le code n’est plus refusé comme « déjà utilisé »).
  - Élève bloqué : il revoit l’écran de blocage ; si sa demande était déjà envoyée, elle reste visible chez l’enseignant.
  - Élève débloqué mais pas encore reparti : bouton « Reprendre l’évaluation », sans nouvelle sanction.
  - Évaluation commencée puis page rechargée : compte comme une sortie (blocage, ou arrêt à la 3e sortie).
- Correction d’une faille : ressaisir son code remettait le chrono à zéro et effaçait le blocage. L’heure de début, l’échéance et le statut sont maintenant conservés.
- Écran élève : le bouton est piloté par un mode explicite (security / post_submit / finalized). Un élève bloqué ne peut plus déclencher la « réouverture après envoi » (message « Le délai de réouverture est terminé »).
- Onglet Sécurité : même tableau des élèves bloqués que dans Monitoring (demande + bouton Débloquer), libellés lisibles dans le registre.

## Version 3.1.11

- Correctif blocage / déblocage en cas de sortie du plein écran.
- La demande de déblocage de l’élève ne compte plus comme une sortie supplémentaire (avant : l’élève était terminé automatiquement dès la 2e sortie).
- Monitoring : encadré « Élèves bloqués » avec l’heure du blocage, le motif et l’heure de la demande de déblocage ; actualisation toutes les 10 s ; compteur dans l’onglet du navigateur.
- Bouton « Débloquer l’élève » sans rechargement de page.
- Côté élève : vérification du déblocage toutes les 3 s, puis bouton « Reprendre l’évaluation » qui relance le plein écran et réaffiche les réponses.
- Plein écran demandé directement au clic (compatibilité Safari / Mac / iPad, préfixes webkit).
- Le bouton de l’écran « Plein écran requis » réactive le plein écran au lieu de bloquer l’élève.

## Version 1.0.43

- Refonte visuelle de l’export PDF individuel des copies.
- Mise en page professionnelle avec en-tête, blocs d’information, synthèse de note, sections de contexte et cartes de questions.
- Le contexte est affiché une seule fois par section, puis les questions liées sont listées dessous.
- La réponse attendue n’est plus affichée dans le PDF remis à l’élève.

## Version 1.0.42

- Ajout d’un bouton `PDF élève` dans les corrections pour exporter une copie individuelle.
- Le PDF contient l’évaluation, l’élève, la classe, le temps disponible, le temps effectif, les questions, réponses élève, réponses attendues, points et commentaires.
- Le PDF affiche aussi les alertes de sécurité, les points sans bonus/malus, le bonus, le malus, la note sans bonus/malus et la note finale.
- Les questions non répondues apparaissent dans le PDF avec la mention `Non répondu`.

## Version 1.0.41

- La zone `Corriger` s’affiche maintenant sur toute la largeur disponible sous chaque copie.
- Les questions, réponses, alertes de sécurité et champs de correction ne sont plus enfermés dans une colonne étroite.
- Amélioration de la lisibilité des corrections longues.

## Version 1.0.40

- L’onglet Corrections affiche directement toutes les copies en attente quand aucune évaluation n’est sélectionnée.
- Les copies à corriger sont regroupées par évaluation puis par groupe classe Sensei.
- Chaque correction contient maintenant un récapitulatif sécurité avec nombre d’alertes, tentatives de sortie et statut.
- Les alertes de sécurité sont détaillées dans la copie avec des libellés lisibles, la date, le niveau et les informations utiles.

## Version 1.0.39

- La calculatrice des questions mathématiques affiche maintenant l’opération saisie au-dessus du résultat.
- Le bouton `=` conserve le calcul visible pour que l’élève puisse relire son raisonnement.
- Le bouton `C` remet à zéro l’opération et le résultat.

## Version 1.0.38

- Blocage automatique de l’évaluation dès une tentative de sortie détectée : l’élève doit attendre l’autorisation de l’enseignant.
- Ajout d’un bouton enseignant `Autoriser la reprise` pour débloquer une tentative suspendue.
- À la 3e tentative de sortie, l’évaluation est terminée automatiquement et signalée comme triche suspectée.
- Conservation du compteur de tentatives après reprise : les sorties successives restent cumulées.
- Protection contre les doublons techniques d’événements envoyés à quelques secondes d’intervalle.
- L’élève peut reprendre depuis le même écran après validation enseignant.

## Version 1.0.37

- En mode histoire, le bouton `Passer à la section suivante` amène maintenant l’élève au début de la section suivante, sans remonter tout en haut de la page.
- À la soumission, si des questions sont vides, un avertissement indique le nombre de questions non complétées.
- L’élève peut alors confirmer l’envoi ou revenir à l’évaluation ; en cas de retour, la première question vide est affichée.
- Nettoyage du message d’erreur d’accès après une validation réussie du code.
- La soumission vérifie maintenant explicitement la réponse serveur avant d’afficher le verrouillage post-envoi.

## Version 1.0.36

- Surveillance élève renforcée : changement d’onglet, perte de focus et sortie du plein écran déclenchent un avertissement visible et une journalisation serveur.
- Les événements de sécurité critiques ne sont plus limités par un délai anti-doublon afin de ne pas perdre une tentative de sortie.
- Les copies avec sortie suspecte continuent d’accepter les événements de sécurité pour conserver la chronologie complète.
- Écran Corrections enrichi avec filtres par statut, filtres par groupe classe Sensei et regroupement des copies par classe.
- Ajout d’une colonne `Alertes` dans les corrections avec statut aucune alerte, attention ou critique.
- Les champs de correction affichent le nombre de points possibles et sont préremplis avec le score automatique ou le barème maximum.
- Saisie des points, bonus et malus compatible avec la virgule décimale française.

## Version 1.0.35

- La calculatrice élève s’affiche uniquement dans les questions de type `Mathématiques / calcul`.
- Chaque question mathématique possède sa propre calculatrice affichée à côté du champ de réponse.
- Ajout des touches parenthèses, `π`, `%` et racine carrée.
- Adaptation responsive : la calculatrice passe sous la réponse sur petit écran.

## Version 1.0.34

- Mode histoire côté élève transformé en parcours par sections : histoire de la partie, bouton d’accès aux questions, puis passage à la section suivante.
- Aperçu enseignant en mode étudiant aligné sur ce parcours par étapes.
- Affichage des classes attribuées dans la liste des évaluations.
- Bouton `Aperçu étudiant / tester` ajouté dans les actions d’évaluation.
- Réponses correctes visibles dans l’aperçu enseignant pour les QCM, QCM multi-réponses et Vrai/Faux.
- Texte d’accès élève clarifié : connexion WordPress obligatoire et code personnel unique lié au compte, à l’évaluation et au groupe classe.

## Version 1.0.33

- Les champs de réponse s’adaptent automatiquement au type de question choisi.
- QCM : saisie directe des propositions avec sélection de la bonne réponse.
- Vrai/Faux : sélection directe de la réponse correcte.
- Réponse courte/longue : champ de réponse attendue uniquement.
- Mathématiques/calcul : champ dédié au résultat attendu ou à la méthode, avec calculatrice côté élève.
- La sauvegarde transforme automatiquement ces champs adaptés dans le format interne du plugin.

## Version 1.0.32

- Refonte du mode histoire : l’enseignant définit d’abord plusieurs sections d’histoire.
- Chaque section contient son propre texte narratif et une liste de questions associées.
- Ajout des actions `Ajouter une section d’histoire` et `Ajouter une question dans cette section`.
- L’éditeur de questions classique et l’éditeur par sections s’affichent automatiquement selon le type d’évaluation choisi.
- Les sections d’histoire sont sauvegardées avec les questions afin de créer plusieurs parties distinctes dans l’évaluation élève.

## Version 1.0.31

- Renommage du mode classique en `MES Entreprise fictive`.
- Ajout du mode `Test simple questions / réponses`, sans entreprise fictive ni histoire.
- Le mode histoire n’affiche plus l’entreprise fictive et s’organise par sections narratives liées aux questions.
- Suppression de la saisie du titre des questions : le titre interne est généré automatiquement depuis la question posée.
- Ajout d’un champ `Détails nécessaires / section d’histoire` pour chaque question.
- Ajout du type de question `Mathématiques / calcul`.
- Affichage automatique d’une calculatrice intégrée côté élève lorsqu’une évaluation contient une question de calcul.
- Les nouvelles questions reprennent automatiquement le cours Sensei de la question précédente, tout en restant modifiables manuellement.

## Version 1.0.30

- Ajout du choix de structure lors de la création d’une évaluation : classique ou mode histoire.
- Ajout d’un champ `Histoire / scénario d’introduction` affiché seul avant les questions en mode histoire.
- Ajout d’un message de sécurité affiché au démarrage pour rappeler l’interdiction de quitter l’écran et les conséquences.
- Les mini-contextes de questions sont maintenant explicitement utilisables comme parties de l’histoire entre les questions.
- L’aperçu enseignant et l’interface élève utilisent le même sas d’introduction avant l’accès aux questions.

## Version 1.0.29

- Le filtre `Cours Sensei` s’applique maintenant aussi à la banque de questions LogiExam.
- Ajout d’un libellé de filtre actif dans la liste des questions.
- Renommage du bouton de filtre en `Filtrer les questions` pour couvrir les questions LogiExam et Sensei.

## Version 1.0.28

- Ajout de la suppression individuelle des questions depuis la banque LogiExam.
- Ajout de la suppression groupée avec cases à cocher.
- Nettoyage automatique des liens avec les évaluations et des réponses associées aux questions supprimées.
- Recalcul automatique du total de points des évaluations impactées.

## Version 1.0.27

- Ajout de l’édition des questions déjà présentes dans la banque LogiExam.
- Bouton `Modifier` dans le tableau des questions.
- Formulaire prérempli pour mettre à jour le cours Sensei, le contexte, l’énoncé, le type, les points, la réponse attendue, les options QCM et les tags.
- Sauvegarde serveur en mise à jour lorsque la question existe déjà.

## Version 1.0.26

- Ajout d’un sas d’introduction côté élève après validation du code.
- Les consignes, le contexte d’entreprise et le rôle de l’apprenti s’affichent seuls dans un premier temps.
- Les questions ne s’affichent qu’après le clic sur `Commencer l’évaluation`.
- Le chronomètre serveur démarre au lancement des questions, afin de ne pas pénaliser le temps de lecture du contexte.
- L’aperçu enseignant reproduit le même parcours avec un bouton `Commencer l’évaluation`.

## Version 1.0.25

- Correction de l’affichage des fiches PDF de codes : les textes longs sont maintenant renvoyés à la ligne dans la largeur utile de la page.
- Protection contre les débordements sur les lignes Module, Groupe classe et consignes de sécurité du code.

## Version 1.0.24

- Ajout d’un bouton `Visualiser / tester` sur chaque évaluation.
- Ajout d’un mode aperçu enseignant sans code, sans tentative élève et sans enregistrement de réponses.
- Aperçu complet du rendu élève : consignes, entreprise fictive, rôle / mission, contextes, questions et champs de réponse.
- Référence enseignant repliable par question avec type, barème et réponse attendue.

## Version 1.0.23

- Export PDF des codes élèves juste après génération, avec une fiche A4 par élève.
- Fiche PDF enrichie : module, nombre de questions, dates d’ouverture et de fin, prénom/nom, groupe classe et code personnel en grands caractères.
- Message élève plus précis lorsqu’un code valide appartient à un autre compte utilisateur.
- Normalisation du code saisi côté élève : espaces retirés et casse harmonisée avant vérification.

## Version 1.0.22

- Création des questions directement dans le formulaire de création/modification d’évaluation.
- Retrait des questions Sensei de l’écran de création d’évaluation.
- Bouton `Ajouter une question` avec mini mise en contexte, réponse courte, réponse longue, vrai/faux et QCM.
- Rôle / mission de l’apprenti transformé en champ multi-lignes.
- Entrée principale WordPress indépendante `LogiExam`, sans rattachement au dossier `Outils d’évaluation`.
- Sous-menus WordPress visibles sous `LogiExam` : Évaluations, Création, Questions, Codes, Corrections, Sécurité, Exports, Shortcodes et Réglages.
- Boutons `Modifier` et `Supprimer` ajoutés dans la liste des évaluations.
- Suppression sécurisée avec nonce, confirmation et nettoyage des données associées à l’évaluation.
- Questions LogiExam liées à un cours Sensei via une liste déroulante.
- Mini-contexte facultatif ajouté avant l’énoncé d’une question.
- Informations d’entreprise fictive facultatives dans une évaluation : nom, rôle de l’apprenti, contexte et logo.
- Affichage automatique de l’entreprise fictive avant les questions côté élève lorsque ces champs sont renseignés.
- Accès frontend toujours disponible avec `[logiexam_teacher]` et `[logiexam_student]`.
- Interface shortcode enseignant retravaillée avec un rendu plus épuré, plus professionnel et plus lisible.
- Navigation frontend clarifiée, panneaux allégés, tableaux plus propres et actions mieux hiérarchisées.

- Création d'évaluations.
- Application enseignant frontend complète via `[logiexam_teacher]`.
- Création, édition, codes, corrections, sécurité, questions et exports accessibles sans quitter la page frontend.
- QCM multi-réponses avec cases à cocher et barème automatique partiel.
- Barre de progression élève avec navigation par question.
- Sauvegarde locale automatique des réponses en cours et restauration proposée après interruption.
- Compte à rebours élève synchronisé avec l'expiration serveur de la tentative.
- Banque de questions LogiExam.
- Sauvegarde des nouvelles questions dans la base de données du plugin.
- Import de questions Sensei par copie indépendante.
- Sélection directe de questions Sensei lors de la création d'une évaluation.
- Récupération des modules et catégories de questions depuis le paramétrage de Logiprof Révision Modalités.
- Filtrage des questions Sensei par cours et par catégorie liée au cours.
- Attribution à des groupes Sensei et/ou élèves précis.
- Groupes classe Sensei récupérés depuis la table Sensei Pro et triés par ordre alphabétique A-Z.
- Élèves de groupes récupérés depuis la table Sensei Pro pour fiabiliser la génération des codes.
- Récupération et duplication d'examens existants par les enseignants autorisés.
- Codes uniques liés à un utilisateur et à une évaluation.
- Bouton de génération des codes corrigé avec retour vers l'écran Codes élèves.
- Soumission verrouillante.
- Garde de sortie renforcé pendant toute l’évaluation et pendant le verrouillage de 5 minutes.
- Journalisation fiable des fermetures/navigation via `sendBeacon`.
- Correction manuelle avec bonus et malus.
- Calcul automatique selon la formule `(points obtenus / points totaux * 5) + 1`.
- Journal de sécurité.
- Blocage obligatoire de 5 minutes après soumission avec réouverture possible.
- Avertissement et consignation des tentatives de sortie après soumission.
- Données sensibles de correction retirées du flux élève.
- Protection contre les réponses hors évaluation et les injections CSV.
- Shortcodes disponibles depuis une page WordPress, sans entrée dédiée dans le menu d'administration.
- Accès enseignant limité aux administrateurs et au rôle Enseignant Logistique.
- Interface front-end retravaillée pour les shortcodes élève et enseignant.
- Bouton Ouvrir corrigé : il affiche maintenant l'évaluation en mode consultation/édition.
- Interfaces administrateur séparées par tâche pour chaque onglet du menu LogiExam.
- Cours Sensei recommandé mais facultatif lors de la création d'une évaluation.
- Corrections orthographiques et accents sur les libellés visibles.
- Export CSV global.
- Tables dédiées afin d'éviter tout conflit avec les anciennes versions LogiEval.

## Installation

Copier le dossier du plugin dans `wp-content/plugins/`, puis activer **LogiExam** depuis WordPress.
