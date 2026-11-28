<?php
/*
 * Export des statistiques (LogiPlay > Statistiques > Exporter).
 * - Rapport à présenter (direction, association) : page autonome, imprimable / enregistrable en PDF,
 *   chaque graphique téléchargeable en PNG (diapositives, courrier). Données agrégées seulement : aucun nom d'élève.
 * - Tableaux CSV (Excel) : par jeu, par classe.
 * Option : noms des classes remplacés par « Classe A, B… » ; périmètre « toutes les classes » ou « mes classes ».
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const JRL_RP_VERT = '#067030';
const JRL_RP_ANTH = '#313a45';
const JRL_RP_JAUNE = '#d9a400';
const JRL_RP_FONT = "Poppins, 'Segoe UI', Roboto, Arial, sans-serif";

/* ---------- Données ---------- */
function jrl_rp_opts() {
	$per = isset( $_GET['periode'] ) && isset( jrl_st_periodes()[ $_GET['periode'] ] ) ? sanitize_key( $_GET['periode'] ) : '30';
	return array(
		'periode' => $per,
		'depuis'  => jrl_st_depuis( $per ),
		'mes'     => ! empty( $_GET['mes'] ),
		'anonyme' => ! empty( $_GET['anonyme'] ),
	);
}
/** Classes du rapport, avec leurs agrégats. */
function jrl_rp_classes( $o ) {
	$classes = jrl_st_classes();
	if ( $o['mes'] ) { $f = jrl_favoris(); $classes = array_intersect_key( $classes, array_flip( $f ) ); }
	$out = array(); $i = 0;
	$par = array(); $tous = array();
	foreach ( $classes as $id => $n ) { $par[ $id ] = jrl_st_eleves_classe( $id ); $tous = array_merge( $tous, $par[ $id ] ); }
	$tous = array_values( array_unique( $tous ) );
	$xp = jrl_st_xp_depuis( $tous, $o['depuis'] ); $prog = jrl_st_progression( $tous );
	foreach ( $classes as $id => $n ) {
		$m = $par[ $id ]; $c = jrl_st_agregats( $o['depuis'], $m );
		$xps = 0; $mt = 0; $mn = 0;
		foreach ( $m as $u ) { $xps += $xp[ $u ] ?? 0; if ( isset( $prog[ $u ] ) ) { $mt += $prog[ $u ]['maitrise']; $mn++; } }
		$out[] = array(
			'id' => $id, 'nom' => $o['anonyme'] ? 'Classe ' . jrl_rp_lettre( $i ) : $n,
			'membres' => count( $m ), 'actifs' => (int) $c['eleves'], 'xp' => $xps, 'duree' => (int) $c['duree'], 'seances' => (int) $c['seances'],
			'bonnes' => (int) $c['bonnes'], 'total' => (int) $c['total'], 'note' => (float) $c['note'], 'notes' => (int) $c['notes'],
			'maitrise' => $mn ? $mt / $mn : null,
		);
		$i++;
	}
	return array( $out, $tous );
}
function jrl_rp_lettre( $i ) { $s = ''; $i++; while ( $i > 0 ) { $i--; $s = chr( 65 + $i % 26 ) . $s; $i = intdiv( $i, 26 ); } return $s; }
function jrl_rp_periode_txt( $o ) {
	$fin = current_time( 'Y-m-d' );
	if ( 'tout' === $o['periode'] ) { return 'depuis le début, au ' . date_i18n( 'j F Y', strtotime( $fin ) ); }
	return 'du ' . date_i18n( 'j F Y', strtotime( $o['depuis'] ) ) . ' au ' . date_i18n( 'j F Y', strtotime( $fin ) );
}

/* ---------- Graphiques SVG (couleurs et polices écrites en dur : export PNG fidèle) ---------- */
function jrl_rp_entete( $W, $titre, $sous ) {
	return '<rect width="' . $W . '" height="100%" fill="#ffffff"/>'
		. '<text x="24" y="38" font-family="' . esc_attr( JRL_RP_FONT ) . '" font-size="22" font-weight="700" fill="' . JRL_RP_ANTH . '">' . esc_html( $titre ) . '</text>'
		. '<text x="24" y="62" font-family="' . esc_attr( JRL_RP_FONT ) . '" font-size="14" fill="#5b6672">' . esc_html( $sous ) . '</text>';
}
function jrl_rp_source( $W, $y ) {
	return '<text x="' . ( $W - 24 ) . '" y="' . $y . '" text-anchor="end" font-family="' . esc_attr( JRL_RP_FONT ) . '" font-size="11" fill="#8a96a3">' . esc_html( 'Jeux de révision – Logistique · ' . wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</text>';
}
/** Activité dans le temps : barres = temps de jeu, ligne = élèves actifs. Par semaine au-delà de 62 jours. */
function jrl_rp_svg_activite( $jours, $depuis, $titre, $sous ) {
	$DAY = DAY_IN_SECONDS; $fin = strtotime( current_time( 'Y-m-d' ) ); $d0 = strtotime( $depuis );
	if ( $jours ) { $d0 = max( $d0, strtotime( min( array_keys( $jours ) ) ) ); }
	$hebdo = ( $fin - $d0 ) / $DAY > 62;
	$b = array();
	for ( $t = $d0; $t <= $fin; $t += $DAY ) {
		$k = $hebdo ? gmdate( 'Y-m-d', $t - ( (int) gmdate( 'N', $t ) - 1 ) * $DAY ) : gmdate( 'Y-m-d', $t );
		$d = gmdate( 'Y-m-d', $t );
		if ( ! isset( $b[ $k ] ) ) { $b[ $k ] = array( 'duree' => 0, 'actifs' => array() ); }
		$b[ $k ]['duree'] += (int) ( $jours[ $d ]['duree'] ?? 0 );
		$b[ $k ]['actifs'] += $jours[ $d ]['actifs'] ?? array();
	}
	$W = 1000; $top = 90; $ph = 230; $L = 78; $R = 56; $pw = $W - $L - $R; $H = $top + $ph + 86;
	$n = max( 1, count( $b ) ); $bw = $pw / $n;
	$max = 1; $maxa = 1; foreach ( $b as $v ) { $max = max( $max, $v['duree'] ); $maxa = max( $maxa, count( $v['actifs'] ) ); }
	/* graduation « ronde » en minutes */
	$pas = array( 5, 10, 15, 30, 60, 120, 180, 300, 600, 900, 1200, 1800, 3000, 6000 ); $mm = $max / 60; $st = 6000;
	foreach ( $pas as $p ) { if ( $mm / $p <= 4 ) { $st = $p; break; } }
	$ymax = max( $st, ceil( $mm / $st ) * $st ) * 60; $amax = max( 2, (int) ceil( $maxa / 2 ) * 2 );
	$f = esc_attr( JRL_RP_FONT );
	$s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" role="img" aria-label="' . esc_attr( $titre ) . '">' . jrl_rp_entete( $W, $titre, $sous );
	for ( $g = 0; $g <= $ymax; $g += $st * 60 ) {
		$y = round( $top + $ph - $ph * $g / $ymax, 1 );
		$s .= '<line x1="' . $L . '" y1="' . $y . '" x2="' . ( $W - $R ) . '" y2="' . $y . '" stroke="' . ( $g ? '#e6e9e3' : '#b8beb5' ) . '"/>';
		$s .= '<text x="' . ( $L - 8 ) . '" y="' . ( $y + 4 ) . '" text-anchor="end" font-family="' . $f . '" font-size="12" fill="#5b6672">' . esc_html( jrl_st_duree( $g ) ) . '</text>';
	}
	foreach ( array( 0, $amax / 2, $amax ) as $a ) {
		$y = round( $top + $ph - $ph * $a / $amax, 1 );
		$s .= '<text x="' . ( $W - $R + 8 ) . '" y="' . ( $y + 4 ) . '" font-family="' . $f . '" font-size="12" fill="#a07800">' . (int) $a . '</text>';
	}
	$pts = array(); $i = 0; $mois = '';
	foreach ( $b as $k => $v ) {
		$x = $L + $i * $bw; $h = $v['duree'] ? max( 2, $ph * $v['duree'] / $ymax ) : 0; $a = count( $v['actifs'] );
		$lab = $hebdo ? 'Semaine du ' . date_i18n( 'j M', strtotime( $k ) ) : date_i18n( 'D j M', strtotime( $k ) );
		$s .= '<rect x="' . round( $x + $bw * .14, 1 ) . '" y="' . round( $top + $ph - $h, 1 ) . '" width="' . round( max( 1, $bw * .72 ), 1 ) . '" height="' . round( $h, 1 ) . '" rx="2" fill="' . JRL_RP_VERT . '"><title>' . esc_html( $lab . ' : ' . jrl_st_duree( $v['duree'] ) . ', ' . $a . ' élève(s) actif(s)' ) . '</title></rect>';
		$pts[] = round( $x + $bw / 2, 1 ) . ',' . round( $top + $ph - $ph * $a / $amax, 1 );
		$t = strtotime( $k ); $m = gmdate( 'Y-m', $t );
		$etiq = $hebdo ? ( $m !== $mois ? date_i18n( 'M y', $t ) : '' ) : ( ( 0 === $i || 1 === (int) gmdate( 'j', $t ) ) ? date_i18n( 'j M', $t ) : ( ( 1 === (int) gmdate( 'N', $t ) && $n <= 45 && (int) gmdate( 'j', $t ) > 3 && $i > 3 ) ? date_i18n( 'j', $t ) : '' ) );
		if ( $hebdo ) { $mois = $m; }
		if ( '' !== $etiq ) { $s .= '<text x="' . round( $x + $bw / 2, 1 ) . '" y="' . ( $top + $ph + 20 ) . '" text-anchor="middle" font-family="' . $f . '" font-size="12" fill="#5b6672">' . esc_html( $etiq ) . '</text>'; }
		$i++;
	}
	if ( count( $pts ) > 1 ) { $s .= '<polyline points="' . esc_attr( implode( ' ', $pts ) ) . '" fill="none" stroke="' . JRL_RP_JAUNE . '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>'; }
	$ly = $top + $ph + 52;
	$s .= '<rect x="' . $L . '" y="' . ( $ly - 11 ) . '" width="16" height="12" rx="2" fill="' . JRL_RP_VERT . '"/><text x="' . ( $L + 24 ) . '" y="' . $ly . '" font-family="' . $f . '" font-size="13" fill="' . JRL_RP_ANTH . '">' . ( $hebdo ? 'Temps de jeu par semaine (échelle de gauche)' : 'Temps de jeu par jour (échelle de gauche)' ) . '</text>';
	$s .= '<rect x="' . ( $L + 380 ) . '" y="' . ( $ly - 6 ) . '" width="18" height="3" fill="' . JRL_RP_JAUNE . '"/><text x="' . ( $L + 406 ) . '" y="' . $ly . '" font-family="' . $f . '" font-size="13" fill="' . JRL_RP_ANTH . '">Élèves actifs (échelle de droite)</text>';
	return $s . jrl_rp_source( $W, $H - 10 ) . '</svg>';
}
/** Barres horizontales : $rows = [[libellé, valeur, texte affiché], …]. */
function jrl_rp_svg_barres( $rows, $titre, $sous, $couleur = JRL_RP_VERT, $max = null ) {
	$W = 1000; $top = 86; $rh = 34; $lm = 0; foreach ( $rows as $r ) { $lm = max( $lm, min( 42, mb_strlen( $r[0] ) ) ); } $L = max( 150, min( 340, 34 + $lm * 7.6 ) ); $txt = 150; $H = $top + count( $rows ) * $rh + 36;
	if ( null === $max ) { $max = 0; foreach ( $rows as $r ) { $max = max( $max, $r[1] ); } }
	$max = max( $max, 0.0001 ); $bwmax = $W - $L - $txt - 24; $f = esc_attr( JRL_RP_FONT );
	$s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" role="img" aria-label="' . esc_attr( $titre ) . '">' . jrl_rp_entete( $W, $titre, $sous );
	foreach ( array_values( $rows ) as $i => $r ) {
		$y = $top + $i * $rh; $lab = $r[0]; if ( mb_strlen( $lab ) > 42 ) { $lab = mb_substr( $lab, 0, 40 ) . '…'; }
		$w = $r[1] > 0 ? max( 3, $bwmax * $r[1] / $max ) : 0;
		if ( $i % 2 ) { $s .= '<rect x="16" y="' . ( $y - 2 ) . '" width="' . ( $W - 32 ) . '" height="' . $rh . '" fill="#f6f7f4"/>'; }
		$s .= '<text x="' . ( $L - 12 ) . '" y="' . ( $y + 20 ) . '" text-anchor="end" font-family="' . $f . '" font-size="14" fill="' . JRL_RP_ANTH . '">' . esc_html( $lab ) . '</text>';
		$s .= '<rect x="' . $L . '" y="' . ( $y + 6 ) . '" width="' . round( $w, 1 ) . '" height="' . ( $rh - 14 ) . '" rx="3" fill="' . $couleur . '"/>';
		$s .= '<text x="' . round( $L + $w + 10, 1 ) . '" y="' . ( $y + 20 ) . '" font-family="' . $f . '" font-size="14" font-weight="600" fill="' . JRL_RP_ANTH . '">' . esc_html( $r[2] ) . '</text>';
	}
	return $s . jrl_rp_source( $W, $H - 10 ) . '</svg>';
}

/* ---------- Rapport ---------- */
add_action( 'admin_init', function () {
	if ( 'jeux-logistique-stats' !== ( $_GET['page'] ?? '' ) || empty( $_GET['rapport'] ) ) { return; }
	if ( ! current_user_can( 'jrl_voir_stats' ) ) { wp_die( 'Accès refusé.' ); }
	jrl_rp_rapport(); exit;
} );

function jrl_rp_rapport() {
	$o = jrl_rp_opts(); $per = jrl_rp_periode_txt( $o );
	list( $classes, $uids ) = jrl_rp_classes( $o );
	$a = $o['mes'] ? jrl_st_agregats( $o['depuis'], $uids ) : jrl_st_agregats( $o['depuis'] );
	$jeux = jrl_jeux(); $site = get_bloginfo( 'name' );
	$portee = $o['mes'] ? ( count( $classes ) > 1 ? count( $classes ) . ' classes' : ( $classes ? '1 classe' : 'aucune classe' ) ) : 'tous les élèves du site';
	$sous = ucfirst( $per ) . ' · ' . $portee;
	$G = array(); /* [id, titre, svg] */
	$G[] = array( 'activite', 'Activité', jrl_rp_svg_activite( $a['jours'], $o['depuis'], 'Activité sur les jeux de révision', $sous ) );
	$lj = array();
	foreach ( $a['jeux'] as $slug => $g ) { if ( isset( $jeux[ $slug ] ) ) { $lj[] = array( $jeux[ $slug ]['titre'], count( $g['eleves'] ?? array() ), (int) $g['duree'] ); } }
	usort( $lj, function ( $x, $y ) { return $y[1] <=> $x[1] ?: $y[2] <=> $x[2]; } );
	$top = array_slice( $lj, 0, 12 );
	if ( $top ) {
		$G[] = array( 'jeux-eleves', 'Jeux les plus joués', jrl_rp_svg_barres( array_map( function ( $r ) { return array( $r[0], $r[1], $r[1] . ' élève' . ( $r[1] > 1 ? 's' : '' ) . ' · ' . jrl_st_duree( $r[2] ) ); }, $top ), 'Les jeux les plus joués', $sous . ' · nombre d’élèves et temps de jeu' . ( count( $lj ) > 12 ? ' · 12 premiers sur ' . count( $lj ) : '' ) ) );
	}
	$modes = array( 'train' => array( 'Entraînement', 0, 0 ), 'review' => array( 'Révision ciblée', 0, 0 ), 'exam' => array( 'Examen blanc', 0, 0 ) );
	foreach ( $a['modes'] ?? array() as $m => $v ) { if ( isset( $modes[ $m ] ) ) { $modes[ $m ][1] = $v['n']; $modes[ $m ][2] = $v['duree']; } }
	if ( $a['seances'] ) {
		$G[] = array( 'modes', 'Façons de jouer', jrl_rp_svg_barres( array_map( function ( $r ) use ( $a ) { return array( $r[0], $r[1], $r[1] . ' séries (' . jrl_st_pct( $r[1], $a['seances'] ) . ') · ' . jrl_st_duree( $r[2] ) ); }, array_values( $modes ) ), 'Comment les élèves jouent', $sous . ' · séries terminées par mode', JRL_RP_ANTH ) );
	}
	if ( $classes ) {
		$G[] = array( 'classes-participation', 'Participation par classe', jrl_rp_svg_barres( array_map( function ( $c ) { return array( $c['nom'], $c['membres'] ? 100 * $c['actifs'] / $c['membres'] : 0, $c['actifs'] . ' / ' . $c['membres'] . ' élèves (' . jrl_st_pct( $c['actifs'], $c['membres'] ) . ')' ); }, $classes ), 'Participation par classe', $sous . ' · part des élèves qui ont joué', JRL_RP_VERT, 100 ) );
		$G[] = array( 'classes-temps', 'Temps par élève', jrl_rp_svg_barres( array_map( function ( $c ) { $v = $c['actifs'] ? $c['duree'] / $c['actifs'] : 0; return array( $c['nom'], $v, $c['actifs'] ? jrl_st_duree( $v ) . ' par élève actif' : '–' ); }, $classes ), 'Temps de jeu moyen par élève actif', $sous, JRL_RP_ANTH ) );
		$G[] = array( 'classes-reussite', 'Réussite par classe', jrl_rp_svg_barres( array_map( function ( $c ) { return array( $c['nom'], $c['total'] ? 100 * $c['bonnes'] / $c['total'] : 0, $c['total'] ? jrl_st_pct( $c['bonnes'], $c['total'] ) . ( $c['notes'] ? ' · note exam. ' . jrl_st_note( $c['note'], $c['notes'] ) : '' ) : '–' ); }, $classes ), 'Taux de bonnes réponses par classe', $sous . ' · note moyenne aux examens blancs sur 6', JRL_RP_VERT, 100 ) );
	}
	$nom_fichier = 'statistiques-jeux-' . $o['periode'] . '-' . current_time( 'Y-m-d' );
	?><!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( 'Statistiques des jeux de révision – ' . $site ); ?></title>
<style>
@page{size:A4;margin:14mm 12mm}
*{box-sizing:border-box}
body{margin:0;background:#eef0eb;font-family:Poppins,'Segoe UI',Roboto,Arial,sans-serif;color:#313a45}
.bar{position:sticky;top:0;z-index:2;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 20px;background:#313a45;color:#fff}
.bar b{margin-right:auto;font-weight:600}
.bar button,.bar a{font:inherit;font-size:14px;border:0;border-radius:99px;padding:9px 16px;cursor:pointer;text-decoration:none}
.bar .p{background:#f5c400;color:#313a45;font-weight:700}.bar .s{background:#fff;color:#313a45}.bar a{color:#fff;background:transparent;border:1px solid #6b7480}
main{max-width:1060px;margin:0 auto;padding:24px 20px 60px}
.page{background:#fff;border-radius:8px;padding:28px 30px;margin-bottom:22px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
h1{font-size:28px;margin:0 0 4px;line-height:1.2}.eyebrow{color:#067030;font-weight:600;margin:0 0 6px}
.per{color:#5b6672;margin:0 0 20px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:0 0 8px}
.kpis div{border:1px solid #d9ddd6;border-top:6px solid #067030;border-radius:6px;padding:12px 14px;color:#5b6672;font-size:14px}
.kpis b{display:block;font-size:28px;color:#313a45;line-height:1.2}
.fig{position:relative;margin:0 0 22px;break-inside:avoid;page-break-inside:avoid;border:1px solid #e3e6e0;border-radius:8px;overflow:hidden}
.fig svg{display:block}
.fig button{position:absolute;top:12px;right:12px;font:inherit;font-size:13px;border:1px solid #d9ddd6;background:#fff;border-radius:99px;padding:6px 12px;cursor:pointer;color:#313a45}
.fig button:hover{border-color:#067030;color:#067030}
table{width:100%;border-collapse:collapse;font-size:13px;break-inside:avoid}
th,td{padding:7px 8px;border-bottom:1px solid #e3e6e0;text-align:right;font-variant-numeric:tabular-nums}
th:first-child,td:first-child{text-align:left}th{background:#f6f7f4;font-weight:600}
.note{color:#5b6672;font-size:12.5px;line-height:1.5;margin-top:16px}
h2{font-size:18px;margin:4px 0 12px}
@media print{body{background:#fff}.bar,.fig button{display:none!important}main{padding:0;max-width:none}.page{box-shadow:none;padding:0;margin:0;border-radius:0}.fig{border:0}}
</style></head><body>
<div class="bar"><b>Rapport à présenter · <?php echo esc_html( $per ); ?></b>
	<button class="p" onclick="window.print()">Imprimer / enregistrer en PDF</button>
	<button class="s" id="tout">Télécharger tous les graphiques (PNG)</button>
	<a href="<?php echo esc_url( jrl_st_url( array( 'periode' => $o['periode'] ) ) ); ?>">Retour</a>
</div>
<main><div class="page">
	<p class="eyebrow"><?php echo esc_html( $site ); ?> · LogiPlay</p>
	<h1>Utilisation des jeux de révision</h1>
	<p class="per"><?php echo esc_html( ucfirst( $per ) . ' · ' . $portee . ' · ' . count( $jeux ) . ' jeux disponibles (FR/DE)' ); ?></p>
	<div class="kpis">
		<div><b><?php echo (int) $a['eleves']; ?></b>élèves ont joué</div>
		<div><b><?php echo esc_html( jrl_st_duree( $a['duree'] ) ); ?></b>de jeu (séries terminées)</div>
		<div><b><?php echo (int) $a['seances']; ?></b>séries terminées</div>
		<div><b><?php echo esc_html( $a['eleves'] ? jrl_st_duree( $a['duree'] / $a['eleves'] ) : '–' ); ?></b>par élève actif</div>
		<div><b><?php echo esc_html( number_format_i18n( $a['total'] ) ); ?></b>questions répondues · <?php echo esc_html( jrl_st_pct( $a['bonnes'], $a['total'] ) ); ?> justes</div>
		<div><b><?php echo esc_html( jrl_st_note( $a['note'], $a['notes'] ) ); ?></b>note moyenne aux examens blancs<?php echo $a['notes'] ? ' (' . (int) $a['notes'] . ')' : ''; ?></div>
	</div>
	<?php foreach ( $G as $g ) : ?>
		<div class="fig" data-nom="<?php echo esc_attr( $nom_fichier . '-' . $g[0] ); ?>"><button type="button" class="png">PNG</button><?php echo $g[2]; // phpcs:ignore -- SVG construit et échappé ci-dessus ?></div>
	<?php endforeach; ?>
	<?php if ( $classes ) : ?>
	<h2>Résumé par classe</h2>
	<table><thead><tr><th>Classe</th><th>Élèves</th><th>Actifs</th><th>Temps de jeu</th><th>Séries</th><th>Réussite</th><th>Note exam.</th><th>Maîtrise moy.</th></tr></thead><tbody>
	<?php foreach ( $classes as $c ) : ?>
		<tr><td><?php echo esc_html( $c['nom'] ); ?></td><td><?php echo (int) $c['membres']; ?></td><td><?php echo (int) $c['actifs']; ?> (<?php echo esc_html( jrl_st_pct( $c['actifs'], $c['membres'] ) ); ?>)</td><td><?php echo esc_html( jrl_st_duree( $c['duree'] ) ); ?></td><td><?php echo (int) $c['seances']; ?></td><td><?php echo esc_html( jrl_st_pct( $c['bonnes'], $c['total'] ) ); ?></td><td><?php echo esc_html( jrl_st_note( $c['note'], $c['notes'] ) ); ?></td><td><?php echo null === $c['maitrise'] ? '–' : (int) round( $c['maitrise'] ) . ' %'; ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php endif; ?>
	<p class="note">Données agrégées, sans nom d’élève. Le temps de jeu compte les séries terminées (entraînement, révision ciblée, examen blanc) ; une série abandonnée n’est pas comptée. Un élève est « actif » s’il a joué au moins une fois sur la période. Note des examens blancs sur 6 (5 × taux de réussite + 1). Les comptes des enseignant·e·s ne sont pas comptés. Rapport établi le <?php echo esc_html( date_i18n( 'j F Y à H:i' ) ); ?>.</p>
</div></main>
<script>
(function () {
	function png(fig, done) {
		var svg = fig.querySelector('svg'), vb = svg.viewBox.baseVal, k = 2;
		var c = document.createElement('canvas'); c.width = vb.width * k; c.height = vb.height * k;
		var x = c.getContext('2d'), img = new Image();
		img.onload = function () {
			x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0, c.width, c.height);
			c.toBlob(function (b) { var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = fig.dataset.nom + '.png'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); if (done) done(); }, 400); }, 'image/png');
		};
		img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
	}
	document.querySelectorAll('.fig .png').forEach(function (b) { b.addEventListener('click', function () { png(b.closest('.fig')); }); });
	document.getElementById('tout').addEventListener('click', function () {
		var f = [].slice.call(document.querySelectorAll('.fig')); (function n() { var x = f.shift(); if (x) png(x, n); })();
	});
})();
</script>
</body></html><?php
}

/* ---------- CSV (Excel : séparateur « ; », BOM UTF-8) ---------- */
add_action( 'admin_post_jrl_stats_csv', function () {
	if ( ! current_user_can( 'jrl_voir_stats' ) ) { wp_die( 'Accès refusé.' ); }
	check_admin_referer( 'jrl_stats_csv' );
	$o = jrl_rp_opts(); $quoi = 'classes' === ( $_GET['quoi'] ?? '' ) ? 'classes' : 'jeux';
	$h = array(); $rows = array();
	if ( 'classes' === $quoi ) {
		list( $classes ) = jrl_rp_classes( $o );
		$h = array( 'Classe', 'Élèves', 'Élèves actifs', 'Part active (%)', 'XP gagnés', 'Séries terminées', 'Temps de jeu (min)', 'Temps par élève actif (min)', 'Bonnes réponses', 'Questions', 'Réussite (%)', 'Examens blancs', 'Note moyenne exam.', 'Maîtrise moyenne (%)' );
		foreach ( $classes as $c ) {
			$rows[] = array( $c['nom'], $c['membres'], $c['actifs'], $c['membres'] ? round( 100 * $c['actifs'] / $c['membres'] ) : '', $c['xp'], $c['seances'], round( $c['duree'] / 60 ), $c['actifs'] ? round( $c['duree'] / 60 / $c['actifs'], 1 ) : '', $c['bonnes'], $c['total'], $c['total'] ? round( 100 * $c['bonnes'] / $c['total'] ) : '', $c['notes'], $c['notes'] ? round( $c['note'] / $c['notes'], 2 ) : '', null === $c['maitrise'] ? '' : round( $c['maitrise'] ) );
		}
	} else {
		list( , $uids ) = $o['mes'] ? jrl_rp_classes( $o ) : array( null, null );
		$a = $o['mes'] ? jrl_st_agregats( $o['depuis'], $uids ) : jrl_st_agregats( $o['depuis'] );
		$h = array( 'Module', 'Jeu', 'Élèves', 'Séries terminées', 'Temps de jeu (min)', 'Bonnes réponses', 'Questions', 'Réussite (%)', 'Examens blancs', 'Note moyenne exam.' );
		foreach ( jrl_jeux() as $slug => $j ) {
			$g = $a['jeux'][ $slug ] ?? array();
			$rows[] = array( strtoupper( strtok( $slug, '-' ) ), $j['titre'], count( $g['eleves'] ?? array() ), (int) ( $g['seances'] ?? 0 ), round( ( $g['duree'] ?? 0 ) / 60 ), (int) ( $g['bonnes'] ?? 0 ), (int) ( $g['total'] ?? 0 ), ! empty( $g['total'] ) ? round( 100 * $g['bonnes'] / $g['total'] ) : '', (int) ( $g['notes'] ?? 0 ), ! empty( $g['notes'] ) ? round( $g['note'] / $g['notes'], 2 ) : '' );
		}
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="statistiques-' . $quoi . '-' . $o['periode'] . '-' . current_time( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Période : ' . jrl_rp_periode_txt( $o ) ), ';' );
	fputcsv( $out, $h, ';' );
	foreach ( $rows as $r ) { fputcsv( $out, array_map( function ( $v ) { return is_float( $v ) ? str_replace( '.', ',', (string) $v ) : $v; }, $r ), ';' ); }
	fclose( $out ); exit;
} );

/** Encadré « Exporter » de la page Statistiques. */
function jrl_rp_encadre( $per ) {
	$csv = function ( $q ) use ( $per ) { return wp_nonce_url( admin_url( 'admin-post.php?action=jrl_stats_csv&quoi=' . $q . '&periode=' . $per ), 'jrl_stats_csv' ); };
	?>
	<div class="jrl-box" id="jrl-export">
		<h2 style="margin-top:0">Exporter pour une présentation</h2>
		<p>Un rapport avec graphiques, sans nom d’élève, pour la direction ou l’association : à imprimer ou enregistrer en PDF, chaque graphique en image PNG pour vos diapositives.</p>
		<form method="get" target="_blank" class="jrl-exp">
			<input type="hidden" name="page" value="jeux-logistique-stats"><input type="hidden" name="rapport" value="1"><input type="hidden" name="periode" value="<?php echo esc_attr( $per ); ?>">
			<label><input type="checkbox" name="mes" value="1" <?php checked( (bool) jrl_favoris() ); ?> <?php disabled( ! jrl_favoris() ); ?>> Seulement mes classes (★)</label>
			<label><input type="checkbox" name="anonyme" value="1"> Remplacer les noms des classes par « Classe A, B… »</label>
			<button class="button button-primary" type="submit">Ouvrir le rapport (<?php echo esc_html( strtolower( jrl_st_periodes()[ $per ] ) ); ?>)</button>
			<span class="jrl-csv">Tableaux pour Excel : <a href="<?php echo esc_url( $csv( 'jeux' ) ); ?>" data-csv>par jeu (CSV)</a> · <a href="<?php echo esc_url( $csv( 'classes' ) ); ?>" data-csv>par classe (CSV)</a></span>
		</form>
	</div>
	<script>
	(function () { var f = document.querySelector('.jrl-exp'); if (!f) return;
		function maj() { f.querySelectorAll('[data-csv]').forEach(function (a) { var u = new URL(a.href); ['mes', 'anonyme'].forEach(function (k) { u.searchParams.delete(k); if (f.elements[k].checked) u.searchParams.set(k, 1); }); a.href = u.toString(); }); }
		f.addEventListener('change', maj); maj(); })();
	</script>
	<?php
}
