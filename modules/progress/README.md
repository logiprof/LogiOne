# LogiProgress

Suivi de progression Sensei par groupe et par cours. Cette version affiche les lecons utiles au suivi, ajoute le pourcentage de reussite au quiz quand Sensei fournit une note exploitable et permet de personnaliser les couleurs de l'interface.

## Shortcodes

Recherche libre avec filtres visibles :

```text
[logiprogress show_filters="yes"]
```

Tableau fixe pour un groupe et un cours :

```text
[logiprogress group_id="123" course_id="456"]
```

Tableau fixe pour un groupe et plusieurs cours :

```text
[logiprogress group_id="123" course_ids="456,789"]
```

Masquer le bouton d'export :

```text
[logiprogress show_filters="yes" show_export="no"]
```

Alertes d'eleves inactifs selon le seuil configure dans le back-office :

```text
[logiprogress_inactive_students show_filters="yes"]
```

Changer le seuil d'inactivite :

```text
[logiprogress_inactive_students show_filters="yes" days="10"]
```

Acces plus large, a utiliser seulement sur une page protegee :

```text
[logiprogress show_filters="yes" capability="read"]
```

Par defaut, le shortcode demande la capacite WordPress `psgpt_access`, afin d'eviter d'exposer les progressions des eleves a tous les utilisateurs connectes.

Les shortcodes historiques `pickles_sensei_group_progress` et `pickles_sensei_inactive_students` sont conserves pour ne pas casser les pages ou ils sont deja utilises.

Le plugin ajoute automatiquement cette capacite aux administrateurs et au role Ultimate Member dont la cle ou le libelle correspond a `Enseignant logistique`.

Le shortcode d'inactivite est independant du tableau de progression. Il affiche, pour le groupe selectionne, les eleves qui ont au moins un module incomplet sans activite depuis le nombre de jours configure dans le back-office. Les modules termines a 100% ne sont pas signales.

## Parametres

Dans le menu WordPress "LogiProgress", une zone "Parametres LogiProgress" permet de choisir le nombre de jours sans activite avant signalement. La valeur par defaut est 7 jours.

La meme zone permet de personnaliser les couleurs principales de l'interface : couleur principale, variante foncee, fond, texte principal, texte secondaire, et couleurs des statuts en cours/a reprendre.

L'attribut `days="10"` dans le shortcode reste prioritaire si tu veux un seuil different sur une page precise.

## Export PDF

Quand un groupe et au moins un cours sont selectionnes, un bouton "Exporter en PDF" ouvre une page d'impression optimisee en format A4 paysage et lance automatiquement la fenetre d'enregistrement PDF du navigateur.

L'entete du tableau est repetee sur chaque page du PDF. En export multi-cours, chaque cours commence sur une nouvelle page.

## Tableau de suivi

Le filtre de cours accepte plusieurs selections. Les resultats sont affiches separement par cours, puis un resume final regroupe les lecons par eleve.

Sur une page publique, l'interface du shortcode s'etend automatiquement sur presque toute la largeur disponible du navigateur, meme si le theme WordPress limite la largeur du contenu principal.

Le design LogiProgress utilise une interface compacte et lisible : filtres en bandeau, cartes de resume, tableau a lignes alternees, entete fixe, colonne eleve fixe et progression plus visible.

L'entete de chaque tableau reste visible pendant le defilement. La liste des eleves est limitee en hauteur et devient deroulante quand il y a beaucoup d'inscrits.

Les titres de lecons dans l'entete ouvrent la lecon dans un nouvel onglet.

Les lecons sont triees pour afficher d'abord les titres contenant un point suivi d'un numero, comme `M101.2`, puis l'evaluation globale, puis les mathematiques, puis le reste des lecons.

Les lecons objectifs, mathematiques solutions, podcast et evaluation formative & mathematiques telechargeables sont masquees dans LogiProgress. Le pourcentage de progression est calcule uniquement sur les autres lecons.

Quand une lecon possede un quiz et qu'un resultat Sensei est disponible pour l'eleve, la cellule affiche aussi une ligne discrete `Quiz : X%`. Cette information est reprise dans le resume par eleve et dans l'export PDF.

Si une table de resultats compatible du plugin de revisions Logiprof existe, l'historique recent des revisions multimodules faites par les eleves de la classe est affiche en bas du suivi, independamment des cours selectionnes.

## Menu WordPress

Le menu "LogiProgress" est place tout en haut de l'administration, juste sous "Tableau de bord".

Le menu est accessible aux administrateurs et au role `Enseignant logistique`.

## Dates affichees

Chaque cellule de lecon affiche :

- le statut ;
- la date et l'heure de validation au format 24h quand la lecon est terminee ;
- la date de reprise si une lecon terminee est rouverte ensuite.

Le plugin conserve la premiere date de fin dans une meta de commentaire Sensei a partir de son activation. Il sait aussi relire les metas historiques de l'ancien plugin Logiprof pour ne pas perdre les dates deja enregistrees. Pour les reprises faites avant l'installation, il affiche ce que Sensei a encore en base, mais ne peut pas recreer une date historique que Sensei aurait deja remplacee.
