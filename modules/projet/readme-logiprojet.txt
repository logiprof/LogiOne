=== LogiProjet ===
Contributors: Fontes Carlos (LogiProf)
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.10.2
License: GPLv2 or later

Travaux notés en duo (présentations PowerPoint) : dépôt par les apprentis, correction assistée par IA, validation par l'enseignant. Style LogiOne.

== Description ==

1. L'enseignant crée un travail : il choisit le cours Sensei, coche les leçons, et la grille d'évaluation se remplit (une ligne par leçon, puis explications, structure, lisibilité, forme). Tout reste modifiable.
2. Les apprentis voient le travail dans la page [logiprojet], choisissent leur partenaire dans leur groupe Sensei et déposent leur PowerPoint (.pptx). Un seul dépôt par duo ; les deux le voient.
3. L'enseignant lance « Corriger avec l'IA » (un duo ou tous). L'IA lit chaque slide (texte, images, tableaux, notes du présentateur), la compare aux leçons du cours et propose 0, 1 ou 2 par critère, avec une justification et un retour aux apprentis.
4. L'enseignant vérifie, corrige, ajoute le bonus oral si le duo a présenté, puis « Valider et publier ». Seulement alors les apprentis voient leur note, la grille commentée et le retour.
5. Import et export d'un travail (.json) : bouton « Importer un travail » sur l'accueil, « Exporter le travail » sur la page d'un travail. Le cours et les leçons sont retrouvés par identifiant, slug ou titre ; un PDF de consigne joint au fichier est versé dans la médiathèque. Un travail importé arrive en brouillon.
6. Grilles imprimables (une page A4 par duo, ou vierge) et export des notes en CSV (Excel).

Note suisse : points ÷ total × 5 + 1, arrondie au demi-point. Bonus oral : +0.5 si 4 / 6 ou plus (note maximale 6).

== Installation ==

1. Extensions → Ajouter → Téléverser une extension → logiprojet-1.1.0.zip (« Remplacer la version actuelle » pour une mise à jour) → Activer.
2. Réglages → LogiProjet : choisir l'IA (Gemini, OpenAI ou Claude), coller la clé API, vérifier les rôles enseignants.
   Plus sûr : définir la clé dans wp-config.php, par exemple define( 'LOGIPROJET_GEMINI_KEY', '...' );
3. Créer une page avec le code court [logiprojet] (par exemple « Mes travaux »), et la relier au portail LogiOne.
   Les enseignants ont aussi le menu « LogiProjet » dans l'administration.

Options du code court : [logiprojet pleine_largeur="non"] pour rester dans la colonne du thème.

== Données et confidentialité ==

* Les PowerPoint sont rangés dans un dossier au nom aléatoire de wp-content/uploads, protégé par .htaccess (Apache), avec des noms de fichiers aléatoires. Ils ne sont servis qu'à l'enseignant du travail et aux deux membres du duo.
  Sur un serveur Nginx, le .htaccess n'est pas lu : les noms aléatoires restent la protection.
* Avant l'envoi à l'IA, les noms et prénoms des deux apprentis sont remplacés par [Apprenti 1] et [Apprenti 2]. Le nom du fichier n'est pas envoyé.
* L'IA ne voit pas les images elles-mêmes, seulement leur présence.
* Intégré à LogiOne (1.51.0) : supprimer l'ancienne extension autonome n'efface plus rien tant que LogiOne est actif.

== Groupes Sensei ==

Les classes sont lues dans les groupes de Sensei Pro (table sensei_pro_groups_students). Sans groupe, tous les utilisateurs connectés voient les travaux ouverts.

== Changelog ==

= 1.10.2 =
* Les leçons numérotées sans point (ex. « 7 Les catégories de déchets en Suisse ») sont reconnues : numéro en vert dans la consigne, et plage « 7 à 7.4 ».
* Quand les apprentis peuvent travailler seuls, le bandeau de la consigne indique « Travail en duo ou seul·e ».

= 1.10.1 =
* Le modèle de consigne d'un travail noté contient maintenant la règle de retard dans « Le rendu ». La compétence Claude « logiprojet-activite » l'ajoute aussi.

= 1.10.0 =
* Page d'un travail : un tableau par classe, avec pour chaque classe le nombre de groupes, de remises et de retards.
* Bandeau « Remise au plus tard » bien visible, en vert, en jaune à 3 jours de l'échéance et en rouge une fois dépassée, avec la règle des pénalités. Il s'affiche aussi pour les apprentis.
* Remises en retard acceptées tant que le travail est ouvert. La pénalité est calculée automatiquement sur la note finale : de 1 à 7 jours de retard −0.5, de 8 à 14 jours −1, au-delà de 14 jours note 1. Tout jour commencé compte, et la note ne descend jamais sous 1.
* L'enseignant peut excuser un retard (certificat, accord) : la pénalité est alors retirée.
* Les apprentis voient le retard, la pénalité avant la remise (avertissement et confirmation) et dans leur note. Pour une activité non notée, le retard est signalé sans pénalité.
* Grille imprimée et export CSV : classe, jours de retard et pénalité.

= 1.9.0 =
* Chaque enseignant ne voit que les travaux qu'il a créés, y compris un administrateur par défaut. Le serveur refuse d'ouvrir, de corriger, d'imprimer ou d'exporter le travail d'un autre enseignant.
* Administrateurs seulement : la case « Afficher aussi les travaux des autres enseignants » affiche les travaux de tous les enseignants, avec la mention « Créé par … ». Le choix est mémorisé dans le navigateur.

= 1.8.0 =
* Page d'un travail : le panneau « Qui n'a pas encore de groupe » liste, classe par classe, les apprentis qui ne sont encore dans aucun groupe (nombre sur l'effectif de la classe, puis les noms). Il indique aussi combien d'apprentis ont choisi de travailler seul·e. Pour un travail individuel, il liste ceux qui n'ont encore rien déposé.
* Si le travail est ouvert à toutes les classes, toutes les classes Sensei qui ont des apprentis sont listées.

= 1.7.0 =
* Bouton « Je travaille seul·e » pour les apprentis, dans un travail de groupe. Un apprenti seul peut ensuite rejoindre des camarades avec « Travailler en groupe » tant que le travail n'est pas remis, et son fichier de travail est alors partagé.
* L'option du travail s'appelle maintenant « Les apprentis peuvent aussi travailler seuls ou en groupe incomplet ». Elle est cochée par défaut pour les nouveaux travaux. Si elle est décochée, le travail se fait obligatoirement en groupe.
* Enseignant : la mention « seul·e » s'affiche à côté du nom dans le tableau des dépôts.

= 1.6.0 =
* Fichier de travail : les apprentis enregistrent leur fichier en cours sans le remettre. Chaque membre du groupe peut le télécharger pour continuer (par exemple si un camarade est absent) et en enregistrer une nouvelle version.
* Bouton « Remettre le travail », avec confirmation : il remet le fichier de travail, ou un nouveau fichier choisi.
* Après la remise, les apprentis ne peuvent plus ouvrir ni remplacer le fichier remis.
* Enseignant : bouton « Rendre au groupe » sur un travail remis non validé. Le fichier redevient un fichier de travail et la correction en cours est effacée. Le fichier de travail d'un groupe en cours est téléchargeable.
* Si un membre quitte un groupe qui a un fichier de travail, le fichier reste aux camarades restants.

= 1.5.0 =
* Les apprentis peuvent d'abord enregistrer leur groupe sans déposer de fichier, puis revenir plus tard déposer le travail. N'importe quel membre dépose pour tout le groupe.
* Tant que rien n'est déposé, un membre peut modifier le groupe ou le quitter. Après le dépôt, le groupe est verrouillé : seul l'enseignant peut le supprimer.
* Un apprenti n'est que dans un seul groupe par travail, mais il peut être dans un groupe différent pour chaque travail.
* Côté enseignant : statut « Groupe formé » sans fichier, liste des apprentis encore sans groupe, grille imprimable par groupe avant la présentation, suppression d'un groupe.

= 1.4.0 =
* Correction dans Claude, sans limite de temps du serveur : sur la page d'un travail, « Corriger dans Claude » en 3 étapes. Exporter les dépôts non validés (.zip anonymisé, avec la consigne, la grille, le texte des leçons et les fichiers PDF/images, PowerPoint en option), les corriger dans Claude, puis importer le fichier .json rendu.
* Les notes importées arrivent comme propositions « IA » : l'enseignant vérifie et valide chaque dépôt. Les dépôts déjà validés ne sont jamais modifiés.
* Nouvelle compétence Claude « logiprojet-correction » téléchargeable (page du travail et Réglages), avec un vérificateur du fichier avant import.
* Fonctionne pour tous les formats de dépôt (PowerPoint, PDF, images), notés ou non.

= 1.3.0 =
* Compétence Claude « logiprojet-activite » téléchargeable par les enseignants et les administrateurs (bouton « Compétence Claude (.zip) » sur l'accueil et dans Réglages → LogiProjet). Elle crée de nouvelles activités, notées ou non, et rend le fichier .json à importer et la consigne Word identique.
* Activités non notées (formatives) : case « Activité notée » dans le formulaire, critères de réussite sans total ni note, tampon « Formatif », bouton « Publier le retour », grille imprimée sans note.
* Titres de sections et phrases d'introduction modifiables, pour d'autres activités qu'une présentation (affiche, dossier, étude de cas…).
* La compétence téléchargée contient le modèle de consigne de votre version et l'adresse de votre site.

= 1.2.0 =
* Consigne structurée section par section, identique au document Word : encadré de note, cartouche (apprentis, classe, dates de début et de fin, durée), mission, leçons et actions, plan, règles, étapes par période, rendu, jour J, check-list.
* Les apprentis voient le travail avec la mise en page du document Word, et la grille d'évaluation de la page 3, cochée et commentée une fois le travail validé.
* Bouton « Aperçu apprenti » pour l'enseignant ; bouton « Remplir avec le modèle » dans le formulaire.
* Nombre de participants par groupe (1 à 6) : duo, trio, groupe… Les textes du modèle s'adaptent.
* Formats de remise au choix par travail : PowerPoint, PDF, Word, Excel, Impress, Keynote, image, vidéo, archive. Contenu vérifié. La correction par IA reste réservée aux .pptx.
* Date de début du travail.

= 1.1.0 =
* Import et export d'un travail au format .json (logiprojet-travail, version 1).
* Barre d'actions : boutons mieux alignés.

= 1.0.0 =
* Première version.
