<?php
/**
 * Aide de LogiProjet (LogiOne 1.54.0) : tutoriels vidéo et manuels d'utilisation.
 *
 * - Vidéos : liens réglés dans Réglages → LogiProjet (YouTube ou fichier MP4). Une vidéo YouTube est lue
 *   dans une fenêtre du site avec le lecteur sans cookies (youtube-nocookie.com).
 * - Manuels : pages imprimables servies par admin-post.php?action=logiprojet_manuel&qui=eleve|enseignant.
 *   Le manuel des enseignants est réservé aux enseignants.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Help {

	/** Analyse un lien YouTube (watch, youtu.be, shorts, embed, live) ou un fichier vidéo. Rend null si non reconnu. */
	public static function video( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return null;
		}
		if ( preg_match( '~\.(mp4|webm|m4v)(\?.*)?$~i', $url ) ) {
			return array(
				'type'  => 'file',
				'src'   => esc_url_raw( $url ),
				'watch' => esc_url_raw( $url ),
			);
		}
		$id = '';
		if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/))([A-Za-z0-9_-]{11})~', $url, $m ) ) {
			$id = $m[1];
		}
		if ( '' === $id ) {
			return null;
		}
		$start = 0;
		if ( preg_match( '~[?&](?:t|start)=(\d+)~', $url, $m ) ) {
			$start = (int) $m[1];
		}
		return array(
			'type'  => 'youtube',
			'id'    => $id,
			'src'   => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1&playsinline=1&autoplay=1' . ( $start ? '&start=' . $start : '' ),
			'watch' => 'https://www.youtube.com/watch?v=' . $id . ( $start ? '&t=' . $start . 's' : '' ),
		);
	}

	public static function manual_url( $qui ) {
		return add_query_arg(
			array(
				'action' => 'logiprojet_manuel',
				'qui'    => 'enseignant' === $qui ? 'enseignant' : 'eleve',
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Ce que l'interface affiche dans « Aide » pour l'utilisateur connecté. */
	public static function for_user() {
		$out = array(
			'eleve' => array(
				'video'  => self::video( LogiProjet_Settings::get( 'video_eleve' ) ),
				'manuel' => self::manual_url( 'eleve' ),
			),
		);
		if ( LogiProjet_Access::is_teacher() ) {
			$out['enseignant'] = array(
				'video'  => self::video( LogiProjet_Settings::get( 'video_enseignant' ) ),
				'manuel' => self::manual_url( 'enseignant' ),
			);
		}
		return $out;
	}

	/* ================= Manuels ================= */

	public static function render() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$qui = isset( $_GET['qui'] ) && 'enseignant' === $_GET['qui'] ? 'enseignant' : 'eleve'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'enseignant' === $qui && ! LogiProjet_Access::is_teacher() ) {
			wp_die( esc_html__( 'Ce manuel est réservé aux enseignants.', 'logiprojet' ), '', array( 'response' => 403 ) );
		}
		$help  = self::for_user();
		$video = $help[ $qui ]['video'] ?? null;
		$img   = LOGIPROJET_URL . 'assets/aide/';
		$title = 'enseignant' === $qui ? 'Manuel des enseignants' : 'Manuel des apprentis';
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>LogiProjet · <?php echo esc_html( $title ); ?></title>
<style>
@font-face{font-family:Poppins;font-weight:400;src:url(<?php echo esc_url( LOGIPROJET_URL . 'assets/fonts/poppins-latin-400-normal.woff2' ); ?>) format('woff2')}
@font-face{font-family:Poppins;font-weight:600;src:url(<?php echo esc_url( LOGIPROJET_URL . 'assets/fonts/poppins-latin-600-normal.woff2' ); ?>) format('woff2')}
@font-face{font-family:Poppins;font-weight:700;src:url(<?php echo esc_url( LOGIPROJET_URL . 'assets/fonts/poppins-latin-700-normal.woff2' ); ?>) format('woff2')}
:root{--ink:#252c35;--dark:#313a45;--green:#067030;--green2:#2fae5f;--mint:#dcefe3;--yellow:#f5c400;--paper:#f4f5f0;--muted:#5b6672;--line:#dfe3e0}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.6 Poppins,system-ui,-apple-system,"Segoe UI",sans-serif}
header{background:var(--dark);color:#fff;padding:28px 24px 30px;border-bottom:6px solid var(--yellow)}
header .in,main{max-width:940px;margin:0 auto}
header img{height:44px;display:block;margin-bottom:14px}
header .k{display:inline-block;background:var(--yellow);color:var(--ink);font-weight:700;font-size:12px;letter-spacing:.06em;padding:3px 10px;border-radius:99px;text-transform:uppercase}
header h1{margin:8px 0 4px;font-size:32px;line-height:1.2}
header p{margin:0;color:#c9d1d9}
.bar{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.bar a,.bar button{font:inherit;font-weight:600;font-size:14px;border:0;border-radius:8px;padding:9px 16px;cursor:pointer;text-decoration:none;background:#fff;color:var(--ink)}
.bar .y{background:var(--yellow)}
main{padding:26px 24px 60px}
nav.toc{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 22px;margin-bottom:26px}
nav.toc b{display:block;margin-bottom:4px}
nav.toc ol{margin:0;padding-left:22px;columns:2;column-gap:32px}
nav.toc a{color:var(--green);text-decoration:none}
section{background:#fff;border:1px solid var(--line);border-radius:12px;padding:22px 26px;margin-bottom:20px;break-inside:avoid-page}
h2{display:flex;align-items:center;gap:12px;margin:0 0 10px;font-size:22px;line-height:1.3}
h2 .n{flex:none;display:inline-grid;place-items:center;width:34px;height:34px;border-radius:50%;background:var(--green);color:#fff;font-size:16px}
h3{font-size:17px;margin:18px 0 6px}
p{margin:0 0 10px}
ol.steps{margin:0 0 12px;padding-left:22px}
ol.steps li{margin-bottom:6px}
figure{margin:14px 0 6px;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fafbf8}
figure img{display:block;width:100%;height:auto}
figcaption{font-size:13px;color:var(--muted);padding:7px 12px;border-top:1px solid var(--line);background:#fff}
.tip,.warn,.new{border-radius:10px;padding:12px 16px;margin:12px 0;font-size:15px}
.tip{background:var(--mint);border-left:5px solid var(--green2)}
.warn{background:#fff6d6;border-left:5px solid var(--yellow)}
.new{background:#e8f0fb;border-left:5px solid #2f6fd0}
.btn{display:inline-block;font-weight:600;font-size:14px;padding:1px 9px;border-radius:6px;border:1px solid #c9d1d9;background:#f6f7f4;white-space:nowrap}
.btn.y{background:var(--yellow);border-color:#d9ae00}
table{border-collapse:collapse;width:100%;font-size:15px;margin:8px 0 12px}
td,th{border:1px solid var(--line);padding:7px 10px;text-align:left;vertical-align:top}
th{background:#f6f7f4}
footer{color:var(--muted);font-size:13px;text-align:center;margin-top:30px}
dialog{border:0;border-radius:14px;padding:0;width:min(960px,94vw);background:#111}
dialog::backdrop{background:rgba(10,14,20,.72)}
dialog .v{position:relative;padding-top:56.25%}
dialog iframe,dialog video{position:absolute;inset:0;width:100%;height:100%;border:0}
dialog .f{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;background:var(--dark)}
dialog .f a,dialog .f button{font:inherit;font-weight:600;font-size:14px;border:0;border-radius:8px;padding:8px 14px;cursor:pointer;text-decoration:none;background:#fff;color:var(--ink)}
@media (max-width:640px){nav.toc ol{columns:1}header h1{font-size:26px}section{padding:18px 16px}}
@media print{
  body{background:#fff;font-size:12pt}
  header{background:#fff;color:var(--ink);border-bottom:3px solid var(--yellow);padding:0 0 12px}
  header img{filter:invert(1) hue-rotate(180deg)}
  header p{color:var(--muted)}
  .bar,dialog{display:none!important}
  main{padding:14px 0}
  section,nav.toc{border:0;padding:0 0 8px;margin-bottom:14px}
  section{break-inside:auto}
  figure{break-inside:avoid}
  a{color:inherit}
}
</style>
</head>
<body>
<header><div class="in">
	<img src="<?php echo esc_url( LOGIPROJET_URL . 'assets/logiprojet-logo-white.svg' ); ?>" alt="LogiProjet by LogiProf">
	<span class="k"><?php echo 'enseignant' === $qui ? 'Enseignants' : 'Apprentis'; ?></span>
	<h1><?php echo esc_html( $title ); ?></h1>
	<p><?php echo 'enseignant' === $qui ? 'Créer un travail, le réutiliser, suivre les groupes, corriger avec l’aide de l’IA et publier les notes.' : 'Former votre groupe, enregistrer votre travail en cours et le remettre à votre enseignant.'; ?></p>
	<div class="bar">
		<button type="button" class="y" onclick="window.print()">Imprimer / enregistrer en PDF</button>
		<?php if ( $video ) : ?>
			<button type="button" data-video>▶ Voir le tutoriel vidéo</button>
		<?php endif; ?>
		<?php if ( 'enseignant' === $qui ) : ?>
			<a href="<?php echo esc_url( self::manual_url( 'eleve' ) ); ?>">Manuel des apprentis</a>
		<?php endif; ?>
	</div>
</div></header>
<main>
<?php
if ( 'enseignant' === $qui ) {
	self::teacher_body( $img );
} else {
	self::student_body( $img );
}
?>
<footer>LogiProjet · LogiOne by LogiProf · <?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></footer>
</main>
<?php if ( $video ) : ?>
<dialog id="lpj-v" aria-label="Tutoriel vidéo">
	<div class="v" data-slot></div>
	<div class="f"><a href="<?php echo esc_url( $video['watch'] ); ?>" target="_blank" rel="noopener"><?php echo 'youtube' === $video['type'] ? 'Ouvrir sur YouTube ↗' : 'Ouvrir la vidéo ↗'; ?></a><button type="button" data-close autofocus>Fermer ✕</button></div>
</dialog>
<script>
(function () {
	var d = document.getElementById('lpj-v'), slot = d.querySelector('[data-slot]');
	var v = <?php echo wp_json_encode( $video ); ?>;
	document.querySelector('[data-video]').addEventListener('click', function () {
		slot.innerHTML = v.type === 'youtube'
			? '<iframe src="' + v.src + '" title="Tutoriel vidéo" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>'
			: '<video src="' + v.src + '" controls autoplay playsinline></video>';
		d.showModal();
	});
	d.addEventListener('close', function () { slot.innerHTML = ''; });
	d.querySelector('[data-close]').addEventListener('click', function () { d.close(); });
	d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
})();
</script>
<?php endif; ?>
</body>
</html>
		<?php
		exit;
	}

	private static function fig( $src, $cap ) {
		echo '<figure><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $cap ) . '" loading="lazy"><figcaption>' . esc_html( $cap ) . '</figcaption></figure>';
	}

	private static function student_body( $img ) {
		?>
<nav class="toc"><b>Sommaire</b><ol>
	<li><a href="#e1">Ouvrir LogiProjet</a></li>
	<li><a href="#e2">Vos travaux</a></li>
	<li><a href="#e3">Former votre groupe</a></li>
	<li><a href="#e4">Enregistrer votre travail en cours</a></li>
	<li><a href="#e5">Remettre le travail</a></li>
	<li><a href="#e6">Voir votre note</a></li>
	<li><a href="#e7">Questions fréquentes</a></li>
</ol></nav>

<section id="e1"><h2><span class="n">1</span>Ouvrir LogiProjet</h2>
	<ol class="steps">
		<li>Allez sur <b>logiprof.fontes-oliveira.ch</b> et connectez-vous avec votre compte.</li>
		<li>Dans le portail, cliquez sur la tuile <b>LogiProjet</b>.</li>
	</ol>
	<p>Les boutons <span class="btn">▶ Tutoriel vidéo</span> et <span class="btn">Manuel</span> en haut de LogiProjet ouvrent l’aide à tout moment.</p>
</section>

<section id="e2"><h2><span class="n">2</span>Vos travaux</h2>
	<p>La page <b>« Vos travaux »</b> montre tous les travaux que votre enseignant a ouverts pour votre classe. Le badge de chaque carte vous dit où vous en êtes :</p>
	<table>
		<tr><th>Badge</th><th>Ce que cela veut dire</th></tr>
		<tr><td><b>🔒 S’ouvre le …</b></td><td>Le travail est annoncé, mais il n’a pas encore commencé. Vous ne pouvez pas l’ouvrir avant la date et l’heure indiquées.</td></tr>
		<tr><td><b>À rendre</b></td><td>Le travail est ouvert : formez votre groupe et déposez votre fichier.</td></tr>
		<tr><td><b>En cours · fichier de travail enregistré</b></td><td>Votre groupe a enregistré un fichier, mais il n’est pas encore remis.</td></tr>
		<tr><td><b>Remis · en attente de correction</b></td><td>Votre enseignant a reçu le travail.</td></tr>
		<tr><td><b>Corrigé · note …</b></td><td>La note, la grille commentée et le retour de l’enseignant sont disponibles.</td></tr>
		<tr><td><b>En retard</b> / <b>Remises fermées</b></td><td>La date de remise est passée. Un retard fait perdre des points.</td></tr>
	</table>
	<?php self::fig( $img . 'e-mes-travaux.jpg', 'Une carte de travail : module, date de remise et badge « À rendre ».' ); ?>
	<p>Cliquez sur la carte pour ouvrir le travail. En haut : la date de remise. Plus bas : toute la consigne et la grille d’évaluation.</p>
</section>

<section id="e3"><h2><span class="n">3</span>Former votre groupe</h2>
	<ol class="steps">
		<li>Dans <b>Étape 1</b>, choisissez votre ou vos camarades dans la liste. Seuls les camarades de votre classe qui n’ont pas encore de groupe y apparaissent.</li>
		<li>Cliquez sur <span class="btn">Enregistrer le groupe</span>. Vos camarades voient maintenant le même travail que vous.</li>
	</ol>
	<?php self::fig( $img . 'e-groupe.jpg', 'Étape 1 : choisir son partenaire, puis « Enregistrer le groupe ».' ); ?>
	<p>Vous vous êtes trompé·e ? Cliquez sur <span class="btn">Modifier le groupe</span>, tant que le travail n’est pas remis.</p>
	<div class="tip"><b>Travailler seul·e.</b> Si votre enseignant l’autorise, cliquez sur <span class="btn">Je travaille seul·e</span> et confirmez. Vous pourrez encore rejoindre un camarade plus tard, tant que le travail n’est pas remis.</div>
</section>

<section id="e4"><h2><span class="n">4</span>Enregistrer votre travail en cours</h2>
	<p>La période se termine et votre travail n’est pas fini ? Enregistrez-le pour le reprendre au cours suivant.</p>
	<ol class="steps">
		<li>Dans <b>Étape 2</b>, glissez votre fichier dans la zone de dépôt, ou cliquez dessus pour le choisir.</li>
		<li>Cliquez sur <span class="btn">Enregistrer comme fichier de travail</span>.</li>
	</ol>
	<?php self::fig( $img . 'e-depot.jpg', 'Étape 2 : déposer le fichier, puis « Enregistrer comme fichier de travail ».' ); ?>
	<p>Votre fichier est enregistré, <b>mais il n’est pas remis</b> : votre enseignant ne le corrige pas encore. C’est votre sauvegarde entre deux cours.</p>
	<h3>Au cours suivant</h3>
	<ol class="steps">
		<li>Cliquez sur <span class="btn">Télécharger pour continuer</span> pour récupérer le fichier. En groupe, vos camarades peuvent aussi le récupérer (par exemple si vous êtes absent·e).</li>
		<li>Quand vous avez avancé, déposez la nouvelle version et cliquez sur <span class="btn">Mettre à jour le fichier de travail</span>.</li>
	</ol>
	<?php self::fig( $img . 'e-brouillon.jpg', 'Le fichier de travail enregistré, à télécharger au cours suivant.' ); ?>
</section>

<section id="e5"><h2><span class="n">5</span>Remettre le travail</h2>
	<ol class="steps">
		<li>Votre travail est terminé ? Cliquez sur le bouton jaune <span class="btn y">Remettre le travail</span>. Sans nouveau fichier, c’est votre fichier de travail qui est remis.</li>
		<li>Vérifiez le nom du fichier dans la confirmation, puis confirmez.</li>
	</ol>
	<?php self::fig( $img . 'e-remettre.jpg', '« Remettre le travail » : le fichier de travail est remis à l’enseignant.' ); ?>
	<?php self::fig( $img . 'e-remis.jpg', 'Le travail est remis : le fichier est verrouillé.' ); ?>
	<div class="warn"><b>Après la remise, le fichier est verrouillé</b> : plus personne du groupe ne peut l’ouvrir ni le remplacer. Pour le corriger, demandez à votre enseignant de vous le rendre.</div>
	<p>Remettez avant la date indiquée : <b>chaque jour de retard fait perdre des points</b>.</p>
</section>

<section id="e6"><h2><span class="n">6</span>Voir votre note</h2>
	<p>Quand votre enseignant a corrigé, la carte du travail affiche <b>« Corrigé »</b>. Ouvrez-la pour voir votre note, la grille commentée critère par critère, vos points forts et une piste d’amélioration.</p>
</section>

<section id="e7"><h2><span class="n">7</span>Questions fréquentes</h2>
	<h3>Je vois le travail, mais je ne peux pas l’ouvrir.</h3>
	<p>Il n’a pas encore commencé : la carte indique la date et l’heure d’ouverture. Revenez à ce moment-là (rechargez la page).</p>
	<h3>Mon camarade n’apparaît pas dans la liste.</h3>
	<p>Il est déjà dans un autre groupe, ou il n’est pas dans votre classe. Parlez-en à votre enseignant.</p>
	<h3>J’ai remis le mauvais fichier.</h3>
	<p>Demandez à votre enseignant de vous rendre le travail : il redevient un fichier de travail que vous pouvez remplacer, puis remettre.</p>
	<h3>Mon fichier est refusé.</h3>
	<p>Vérifiez le format demandé (indiqué sous la zone de dépôt) et la taille maximale.</p>
</section>
		<?php
	}

	private static function teacher_body( $img ) {
		?>
<nav class="toc"><b>Sommaire</b><ol>
	<li><a href="#p1">Ouvrir LogiProjet</a></li>
	<li><a href="#p2">Créer un travail</a></li>
	<li><a href="#p3">Dates, statut et classes</a></li>
	<li><a href="#p4">La bibliothèque de modèles</a></li>
	<li><a href="#p5">Ouvrir le travail aux apprentis</a></li>
	<li><a href="#p6">Suivre les groupes</a></li>
	<li><a href="#p7">Corriger et publier</a></li>
	<li><a href="#p8">Rendre un travail, imprimer, exporter</a></li>
	<li><a href="#p9">Aide pour les apprentis</a></li>
</ol></nav>

<section id="p1"><h2><span class="n">1</span>Ouvrir LogiProjet</h2>
	<ol class="steps">
		<li>Connectez-vous sur <b>logiprof.fontes-oliveira.ch</b> et ouvrez le portail LogiOne.</li>
		<li>Cliquez sur la tuile <b>LogiProjet</b>. Vous arrivez dans votre espace enseignant : chaque carte est un travail, avec son statut et le nombre de dépôts. Vous ne voyez que les travaux que vous avez créés.</li>
	</ol>
	<?php self::fig( $img . 'p-accueil.jpg', 'L’espace enseignant : vos travaux, « Importer un travail » et « + Nouveau travail ».' ); ?>
	<p>Le bouton <span class="btn">Aide</span> en haut ouvre les tutoriels vidéo et les manuels, pour vous <b>et pour vos apprentis</b>.</p>
</section>

<section id="p2"><h2><span class="n">2</span>Créer un travail</h2>
	<ol class="steps">
		<li>Cliquez sur <span class="btn y">+ Nouveau travail</span> (ou partez d’un modèle, voir <a href="#p4">section 4</a>).</li>
		<li><b>Le cours :</b> choisissez le cours Sensei du module. Les leçons de contenu sont cochées ; le titre et le module se remplissent.</li>
		<li>Cliquez sur <span class="btn">Créer la grille à partir de ces leçons</span>.</li>
	</ol>
	<?php self::fig( $img . 'p-cours.jpg', 'Choisir le cours, vérifier les leçons, puis créer la grille.' ); ?>
	<p><b>La grille :</b> un critère par leçon, puis l’explication, la structure, la lisibilité et la forme. Chaque critère vaut 0, 1 ou 2 points. Modifiez les textes, ajoutez ou supprimez des critères et des blocs.</p>
	<?php self::fig( $img . 'p-grille.jpg', 'La grille d’évaluation, modifiable directement.' ); ?>
	<p><b>La consigne</b> est remplie avec le modèle, section par section, comme le document Word. Adaptez-la, puis cliquez sur <span class="btn y">Enregistrer</span>.</p>
	<p>Choisissez aussi le <b>nombre d’apprentis par groupe</b>, les <b>formats acceptés</b> (la correction par l’IA ne lit que les PowerPoint), si l’activité est <b>notée</b> et si les apprentis peuvent <b>travailler seuls</b>.</p>
</section>

<section id="p3"><h2><span class="n">3</span>Dates, statut et classes</h2>
	<?php self::fig( $img . 'p-reglages.jpg', 'Dates, statut et classes concernées.' ); ?>
	<table>
		<tr><th>Réglage</th><th>Effet pour les apprentis</th></tr>
		<tr><td><b>Statut « Brouillon »</b></td><td>Le travail est invisible.</td></tr>
		<tr><td><b>Statut « Ouvert aux dépôts »</b></td><td>Le travail est visible. À partir de la date de début, ils peuvent l’ouvrir, former leur groupe et déposer.</td></tr>
		<tr><td><b>Statut « Fermé »</b></td><td>Ils voient le travail et leur note, mais ne peuvent plus déposer.</td></tr>
		<tr><td><b>Date de début</b></td><td>Avant cette date et cette heure, le travail apparaît chez l’apprenti avec <b>🔒 S’ouvre le …</b>, mais il ne peut pas l’ouvrir (ni la consigne, ni la grille, ni le dépôt). À l’heure dite, il s’ouvre tout seul. Laissez vide pour un accès immédiat.</td></tr>
		<tr><td><b>Date de fin · rendu du fichier</b></td><td>Après cette date, la remise reste possible tant que le travail est ouvert, mais la pénalité de retard s’applique automatiquement.</td></tr>
	</table>
	<div class="new"><b>Plusieurs classes ?</b> Chaque classe peut avoir <b>sa propre date de début</b> et <b>son propre statut</b>. Dans le formulaire, une ligne par classe cochée apparaît sous « Date de début » ; sur la page du travail, le cadre <b>« Statut et début par classe »</b> permet aussi de les changer à tout moment. Une classe sans date suit la date de début du travail.</div>
	<div class="tip">Vous pouvez donc préparer le travail à l’avance, le mettre sur « Ouvert aux dépôts » et laisser LogiProjet l’ouvrir à chaque classe au bon moment, sans repasser par le brouillon.</div>
</section>

<section id="p4"><h2><span class="n">4</span>La bibliothèque de modèles</h2>
	<p>Un modèle garde tout ce qui se réutilise : le cours, les leçons, la grille, la consigne, les formats, la taille des groupes et le lien vers le PDF de consigne. <b>Il ne garde ni les classes, ni les dates, ni les dépôts des apprentis.</b></p>
	<h3>Enregistrer un travail comme modèle</h3>
	<ol class="steps">
		<li>Ouvrez le travail et cliquez sur <span class="btn">Enregistrer comme modèle</span>.</li>
		<li>Donnez-lui un nom (par exemple « M107 · Présentation en duo »). Cochez <b>« Partager avec les autres enseignants »</b> si vos collègues peuvent l’utiliser.</li>
	</ol>
	<h3>Utiliser un modèle</h3>
	<ol class="steps">
		<li>Sur la page d’accueil, cliquez sur <span class="btn">Bibliothèque de modèles</span>.</li>
		<li>Sur la carte du modèle, cliquez sur <span class="btn y">Utiliser ce modèle</span>.</li>
		<li>Un nouveau travail est créé <b>en brouillon</b> et s’ouvre dans le formulaire : cochez les classes, indiquez les dates, vérifiez le PDF de consigne, puis enregistrez.</li>
	</ol>
	<p>Dans la bibliothèque, vous pouvez aussi renommer, partager ou supprimer vos modèles. Supprimer un modèle ne touche à aucun travail déjà créé.</p>
	<div class="tip">Pour un collègue d’un autre site, utilisez plutôt <span class="btn">Exporter le travail</span> (fichier .json) : il l’importe avec <span class="btn">Importer un travail</span>.</div>
</section>

<section id="p5"><h2><span class="n">5</span>Ouvrir le travail aux apprentis</h2>
	<ol class="steps">
		<li>Avec <span class="btn">Aperçu apprenti</span>, vous voyez exactement ce que verront les apprentis.</li>
		<li>Quand tout est prêt, cliquez sur <span class="btn y">Ouvrir aux dépôts</span> (ou choisissez le statut dans le formulaire).</li>
	</ol>
	<?php self::fig( $img . 'p-statut-classe.jpg', 'Statut et début par classe : ouvrir, verrouiller ou décaler une seule classe.' ); ?>
</section>

<section id="p6"><h2><span class="n">6</span>Suivre les groupes</h2>
	<p>La page du travail montre l’avancement de chaque classe : un cadre liste les apprentis <b>sans groupe</b>, et le tableau montre chaque groupe, travail remis ou encore en cours avec un fichier de travail enregistré.</p>
	<?php self::fig( $img . 'p-suivi.jpg', 'Le suivi : apprentis sans groupe, groupes en cours et travaux remis.' ); ?>
</section>

<section id="p7"><h2><span class="n">7</span>Corriger et publier</h2>
	<ol class="steps">
		<li>Cliquez sur <span class="btn y">Corriger avec l’IA</span> pour obtenir une proposition de note pour tous les PowerPoint remis (ou utilisez « Corriger dans Claude »).</li>
		<li>Ouvrez un dépôt. À gauche, le contenu du fichier, slide par slide ; à droite, la grille avec la proposition de l’IA et ses commentaires.</li>
		<li><b>C’est vous qui décidez :</b> vérifiez chaque critère et corrigez si besoin.</li>
		<li>Ajoutez vos points forts et une piste d’amélioration. En bas : points, note et note finale (retard et bonus oral compris).</li>
		<li>Cliquez sur <span class="btn y">Valider et publier</span>, puis confirmez. Les apprentis voient leur note, la grille commentée et votre retour.</li>
	</ol>
	<?php self::fig( $img . 'p-correction.jpg', 'La correction : le contenu du PowerPoint à gauche, la grille à droite.' ); ?>
	<?php self::fig( $img . 'p-publier.jpg', 'Le retour de l’enseignant et le résumé de la note, avant « Valider et publier ».' ); ?>
</section>

<section id="p8"><h2><span class="n">8</span>Rendre un travail, imprimer, exporter</h2>
	<p><b>Rendre au groupe :</b> un travail remis est incomplet ? Ouvrez-le et cliquez sur <span class="btn">Rendre au groupe</span>. Le fichier redevient un fichier de travail : les apprentis peuvent le modifier, puis le remettre.</p>
	<p><b>Imprimer et exporter :</b> <span class="btn">Imprimer les grilles</span>, <span class="btn">Grille vierge</span>, <span class="btn">Exporter les notes (CSV)</span> pour votre bulletin.</p>
	<?php self::fig( $img . 'p-export.jpg', 'Aperçu apprenti, impression des grilles et export des notes.' ); ?>
</section>

<section id="p9"><h2><span class="n">9</span>Aide pour les apprentis</h2>
	<p>Les apprentis ont leur propre bouton d’aide dans LogiProjet : le tutoriel vidéo « Rendre son travail » et le <a href="<?php echo esc_url( self::manual_url( 'eleve' ) ); ?>">manuel des apprentis</a>. Vous les retrouvez aussi dans votre menu <span class="btn">Aide</span>, pour les projeter en classe.</p>
</section>
		<?php
	}
}
