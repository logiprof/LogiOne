=== LogiInfo ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.8.1
License: GPLv2 or later

Les formations informatiques LogiProf (Word 365, Excel 365, PowerPoint 365 et le guide « Réussir ses présentations ») dans Sensei LMS, réunies dans une seule extension.

== Menu « LogiInfo » ==
Remises des exercices : copies déposées par les élèves, contrôle automatique, traçabilité, fiche de correction, retours publiés.
Correction par l'IA   : export des copies pour Claude et import de correction.json.
Progression des élèves : étapes cochées « Étape terminée » par chaque élève, leçon par leçon.
Cours et fichiers     : création / mise à jour des cours Sensei, installation des fichiers (uploads/Word2026, Excel2026, PowerPoint2026 et Presentation2026).
Formateurs            : liste des formateurs, e-mail, compte WordPress et mode de réception des exercices.
Données et sauvegarde : état des tables et des fichiers, test de protection, sauvegarde ZIP complète, restauration sans écrasement, journal.
Réglages              : taille des fichiers, échelle de notes, message après dépôt, mot de passe des fichiers d'exercice Word.

== Codes courts ==
[fw_remise lecon="3"]                      Rendre l'exercice Word de la leçon 3 (fichier .docx).
[fw_remise lecon="3" formation="excel"]    Rendre l'exercice Excel de la leçon 3 (fichier .xlsx).
[fw_remise lecon="3" formation="powerpoint"]  Rendre l'exercice PowerPoint de la leçon 3 (fichier .pptx).
[fw_remise lecon="1" formation="presentation"] Rendre l'exercice du guide « Réussir ses présentations » (fichier .pptx, tout logiciel).
[logiinfo_remise titre="Rapport de visite" types="pdf,docx" fichiers="1"]   Rendre un exercice dans n'importe quel cours ou leçon.
[fw_mes_remises] / [fw_mes_remises formation="excel"]   Récapitulatif des exercices rendus par l'élève connecté.
Les leçons des cours contiennent déjà ces codes courts à la fin de l'exercice.

== Contrôle automatique ==
Word  : critères écrits pour chaque leçon (styles, mise en page, tableaux, images…), comparés au résultat attendu.
Excel : critères déduits automatiquement du fichier de départ et du résultat attendu : valeurs et présence de formules
        dans les cellules à remplir, formats de nombres, mises en forme ajoutées, cellules fusionnées, volets figés, filtres,
        tableaux, mises en forme conditionnelles, listes déroulantes, graphiques, tableaux croisés (résultats cherchés
        n'importe où sur la feuille), réglages d'impression demandés dans les consignes.
PowerPoint : critères écrits pour chaque leçon (data/powerpoint-checks.json) : nombre et titres des diapositives, dispositions,
        sections, niveaux et listes numérotées, images, icônes, tableaux, graphiques, SmartArt ou formes, connecteurs, vidéo,
        textes de remplacement, transitions, animations, notes, liens, logo du masque, pied de page, numéros, thème
        (couleurs, polices), format 16:9, propriétés, poids des images, commentaires.
Présentations (guide) : lisibilité du fichier, quel que soit le logiciel d'origine : un titre par diapositive, taille réelle du texte
        (≥ 20 points, réduction automatique comprise), 6 lignes au maximum par diapositive, 2 polices au maximum, images non
        déformées, un graphique pour les chiffres.
Vérification de l'auteur : à chaque remise, LogiInfo compare les noms enregistrés dans le fichier (auteurs des modifications,
        dernier enregistrement, créateur) au compte qui a déposé, et cherche un fichier identique déposé par un autre compte.
        Une incohérence affiche « 🪪 Auteur à vérifier » dans la liste et un encart rouge dans la fiche de correction.
Décision : chaque remise peut être « Validée » ou « Invalidée » avec un motif ; l'apprenti·e les voit sous l'exercice
        une fois le retour publié. Action groupée « Marquer Validé et publier » (les remises invalidées ne sont pas touchées).
La note proposée reste une proposition : le formateur la valide ou la corrige avant de publier le retour.

== Remplace ==
« LogiProf – Formations Word et Excel » et « Formation Word – Remise des exercices » (désactivées automatiquement ; leurs
réglages, cours et remises sont repris sans rien effacer).

== Données (rien n'est jamais perdu) ==
Tables propres : {préfixe}logiinfo_remises, {préfixe}logiinfo_progression, {préfixe}logiinfo_eleves, {préfixe}logiinfo_journal.
Fichiers des élèves : wp-content/uploads/logiinfo/copies-eleves/ (hors du dossier de l'extension, protégé par .htaccess,
web.config et index.php ; noms de fichiers aléatoires). Sur Nginx : location ^~ /wp-content/uploads/logiinfo/ { deny all; }
Mise à jour : les tables sont seulement complétées (dbDelta), jamais vidées. Désactivation / suppression : rien n'est effacé
(pas de uninstall.php). « Supprimer » une remise = corbeille, restaurable. Reprise automatique des anciennes données
(table fwr_remises, dossier uploads/fw-remises, progression enregistrée dans les profils) sans effacer les originaux.

== Plusieurs formateurs ==
Classes : dans LogiInfo › Formateurs, chaque groupe Sensei (classe) peut être rattaché à un formateur. Les élèves d'une
classe rattachée n'ont rien à choisir : la copie part chez le formateur de la classe. Colonne et filtre « Classe » dans la liste.
Chaque élève choisit son formateur dans « Rendre mon exercice » (choix mémorisé, modifiable). Réception au choix :
les administrateurs corrigent tout dans LogiInfo ; un enseignant (rôle « Enseignant logisticien »…) reçoit ses copies par e-mail
avec le fichier joint (Répondre = écrire à l'élève), ou, si l'administrateur coche « Corrige dans LogiInfo », y corrige uniquement
les copies de ses classes et de ses élèves. Formateurs, Données, Cours et Réglages : droit logiinfo_admin (rôle Administrateur). L'administrateur voit tout (filtre par formateur) et peut
transmettre une remise à un autre formateur depuis la fiche.

== Journal ==
2.5.0 – Comptes « Enseignant logisticien » sélectionnables comme formateurs (nom et e-mail remplis automatiquement) ; case « Corrige dans LogiInfo » par enseignant (ses classes uniquement) ; droit propre logiinfo_admin, car ce rôle a aussi manage_options.
2.4.1 – Lecture des classes identique à LogiProgress / LogiEval : table sensei_pro_groups_students (group_id, student_id), groupes publiés, secours par les métas sensei_group_ids / sensei_group_id.
2.4.0 – Classes (groupes Sensei Pro) rattachées à un formateur : envoi automatique selon la classe de l'élève ; colonne et filtre Classe.
2.3.0 – Code court [logiinfo_remise] pour tout autre cours (types de fichiers au choix, contrôle du contenu réel) ; correction réservée aux administrateurs, collègues par e-mail.
2.2.0 – Plusieurs formateurs : choix du formateur par l'élève, envoi par e-mail avec le fichier joint, remises visibles seulement par leur formateur, transmission à un autre formateur, table logiinfo_eleves.
2.1.1 – Cases « Étape terminée » rétablies (chemin du script corrigé) ; clic sur une image : agrandissement par-dessus la page (zoom, déplacement, image suivante, Échap pour fermer).
2.1.0 – Base de données propre à LogiInfo (remises, progression, journal) avec reprise des anciennes données ; fichiers des élèves dans uploads/logiinfo ; corbeille au lieu de la suppression ; page Données et sauvegarde (contrôle des fichiers, test de protection, sauvegarde et restauration sans écrasement, journal).
2.0.1 – Encadré « Rendre mon exercice » plus visible (bandeau, zone de dépôt, gros bouton).
2.0.0 – Extension unique LogiInfo avec son propre menu ; remise des exercices Excel ; contrôle automatique Excel ; images de mise en avant des cours ; légendes des captures sans leur numéro.
