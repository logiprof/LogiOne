<?php
/*
 * Statistiques des jeux (menu LogiPlay > Statistiques).
 * - Vue d'ensemble sur une période : élèves actifs, séances, temps de jeu, réussite, activité par jour.
 * - Par jeu : élèves, séances, temps, réussite, note moyenne à l'examen blanc.
 * - Par classe (groupes Sensei Pro) : élèves actifs, XP gagnés, temps, réussite, maîtrise ; détail des élèves.
 * - Classes favorites : chaque enseignant·e coche ses classes (méta utilisateur), affichées en premier.
 * Accès : administrateurs, éditeurs du site et rôles cochés dans « Accès aux statistiques » (capacité jrl_voir_stats).
 * Le temps de jeu compte les séries terminées (entraînement, révision, examen blanc) ; une série abandonnée n'est pas comptée.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- Accès ---------- */
function jrl_stats_roles() {
	$r = get_option( 'jrl_stats_roles', null );
	if ( is_array( $r ) ) { return $r; }
	$def = array();
	foreach ( wp_roles()->roles as $slug => $role ) {
		$n = strtolower( $slug . ' ' . ( $role['name'] ?? '' ) );
		if ( ! empty( $role['capabilities']['edit_posts'] ) || false !== strpos( $n, 'enseign' ) || false !== strpos( $n, 'formateur' ) || false !== strpos( $n, 'teacher' ) || false !== strpos( $n, 'prof' ) ) { $def[] = $slug; }
	}
	return $def;
}
add_filter( 'user_has_cap', function ( $all, $caps, $args, $user ) {
	if ( in_array( 'jrl_voir_stats', (array) $caps, true ) && $user instanceof WP_User ) {
		if ( ! empty( $all['manage_options'] ) || ! empty( $all['edit_posts'] ) || array_intersect( (array) $user->roles, jrl_stats_roles() ) ) { $all['jrl_voir_stats'] = true; }
	}
	return $all;
}, 10, 4 );

/* Menu : sous « Gestion des jeux » ; un·e enseignant·e sans droit d'édition ne voit que Statistiques */
add_action( 'admin_menu', function () {
	if ( current_user_can( 'edit_posts' ) ) {
		add_submenu_page( 'jeux-logistique', 'Statistiques des jeux', 'Statistiques', 'jrl_voir_stats', 'jeux-logistique-stats', 'jrl_stats_page', 2 );
	} elseif ( current_user_can( 'jrl_voir_stats' ) ) {
		add_menu_page( 'Statistiques des jeux', 'LogiPlay', 'jrl_voir_stats', 'jeux-logistique-stats', 'jrl_stats_page', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iYmxhY2siIGZpbGwtcnVsZT0iZXZlbm9kZCIgZD0iTTEwIDEuMiAxNy42IDUuNiAxMCAxMCAyLjQgNS42WiBNOC42IDMuNlY3LjZMMTIgNS42WiIvPjxwYXRoIGZpbGw9ImJsYWNrIiBkPSJNMiA2LjQgOS41IDEwLjhWMTkuMkwyIDE0LjhaIi8+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik0xOCA2LjQgMTAuNSAxMC44VjE5LjJMMTggMTQuOFoiLz48L3N2Zz4=', 26 );
	}
}, 22 );

/* Classes favorites (par utilisateur) */
function jrl_favoris( $uid = 0 ) { $f = get_user_meta( $uid ? $uid : get_current_user_id(), 'jrl_classes_favorites', true ); return is_array( $f ) ? array_map( 'intval', $f ) : array(); }
add_action( 'wp_ajax_jrl_favori', function () {
	check_ajax_referer( 'jrl_favori' );
	if ( ! current_user_can( 'jrl_voir_stats' ) ) { wp_send_json_error( null, 403 ); }
	$g = (int) ( $_POST['groupe'] ?? 0 ); $f = jrl_favoris();
	$f = in_array( $g, $f, true ) ? array_values( array_diff( $f, array( $g ) ) ) : array_merge( $f, array( $g ) );
	update_user_meta( get_current_user_id(), 'jrl_classes_favorites', $f );
	wp_send_json_success( array( 'favori' => in_array( $g, $f, true ) ) );
} );
/* Rôles autorisés (enregistrés depuis la page Statistiques par un administrateur) */
add_action( 'admin_post_jrl_stats_roles', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
	check_admin_referer( 'jrl_stats_roles' );
	$r = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ?? array() ) ), array_keys( wp_roles()->roles ) ) );
	update_option( 'jrl_stats_roles', $r, false );
	wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique-stats&ok=roles#jrl-acces' ) ); exit;
} );

/* ---------- Données ---------- */
function jrl_st_periodes() { return array( '7' => '7 derniers jours', '30' => '30 derniers jours', '90' => '3 derniers mois', 'annee' => 'Cette année scolaire', 'tout' => 'Depuis le début' ); }
/** Date de début (Y-m-d, heure locale) de la période. */
function jrl_st_depuis( $p ) {
	$ts = current_time( 'timestamp' );
	if ( 'tout' === $p ) { return '2000-01-01'; }
	if ( 'annee' === $p ) { $y = (int) gmdate( 'Y', $ts ); if ( (int) gmdate( 'n', $ts ) < 8 ) { $y--; } return $y . '-08-01'; }
	return gmdate( 'Y-m-d', $ts - ( (int) $p - 1 ) * DAY_IN_SECONDS );
}
/** Comptes exclus des statistiques : enseignant·e·s et administrateurs (droit d'édition). */
function jrl_st_exclus() {
	static $ids = null;
	if ( null === $ids ) { $ids = array_map( 'intval', get_users( array( 'capability' => 'edit_posts', 'fields' => 'ID' ) ) ); }
	return $ids;
}
function jrl_st_in( array $vals, $fmt = '%d' ) { global $wpdb; return implode( ',', array_map( function ( $v ) use ( $wpdb, $fmt ) { return $wpdb->prepare( $fmt, $v ); }, $vals ) ); }
function jrl_st_ok() { return function_exists( 'jdr_table' ) && function_exists( 'jdr_table_sess' ) && function_exists( 'jdr_table_hist' ); }

/** Agrégats par jeu et totaux pour une liste d'élèves (null = tous les élèves). */
function jrl_st_agregats( $depuis, $uids = null ) {
	global $wpdb;
	$jeux = array_keys( jrl_jeux() );
	$vide = array( 'jeux' => array(), 'eleves' => 0, 'seances' => 0, 'duree' => 0, 'bonnes' => 0, 'total' => 0, 'note' => 0, 'notes' => 0, 'jours' => array(), 'parEleve' => array() );
	if ( ! jrl_st_ok() || ( is_array( $uids ) && ! $uids ) ) { return $vide; }
	$j = jrl_st_in( $jeux, '%s' ); $ex = jrl_st_exclus();
	$wU = is_array( $uids ) ? ' AND user_id IN (' . jrl_st_in( $uids ) . ')' : '';
	$wX = $ex ? ' AND user_id NOT IN (' . jrl_st_in( $ex ) . ')' : '';
	$debut = get_gmt_from_date( $depuis . ' 00:00:00' );
	$se = jdr_table_sess(); $h = jdr_table_hist();
	$r = $vide;
	/* séances terminées */
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, jeu, mode, bonnes, total, note, duree, cree_le FROM $se WHERE cree_le >= %s AND jeu IN ($j) $wU $wX", $debut ) ); // phpcs:ignore
	$eleves = array();
	foreach ( (array) $rows as $s ) {
		$g = &$r['jeux'][ $s->jeu ];
		if ( ! $g ) { $g = array( 'eleves' => array(), 'seances' => 0, 'duree' => 0, 'bonnes' => 0, 'total' => 0, 'note' => 0, 'notes' => 0 ); }
		$g['eleves'][ $s->user_id ] = 1; $g['seances']++; $g['duree'] += (int) $s->duree; $g['bonnes'] += (int) $s->bonnes; $g['total'] += (int) $s->total;
		if ( 'exam' === $s->mode && $s->note > 0 ) { $g['note'] += (float) $s->note; $g['notes']++; $r['note'] += (float) $s->note; $r['notes']++; }
		$r['modes'][ $s->mode ]['n'] = ( $r['modes'][ $s->mode ]['n'] ?? 0 ) + 1; $r['modes'][ $s->mode ]['duree'] = ( $r['modes'][ $s->mode ]['duree'] ?? 0 ) + (int) $s->duree;
		$r['seances']++; $r['duree'] += (int) $s->duree; $r['bonnes'] += (int) $s->bonnes; $r['total'] += (int) $s->total;
		$e = &$r['parEleve'][ (int) $s->user_id ];
		if ( ! $e ) { $e = array( 'seances' => 0, 'duree' => 0, 'bonnes' => 0, 'total' => 0, 'note' => 0, 'notes' => 0, 'jeux' => array() ); }
		$e['seances']++; $e['duree'] += (int) $s->duree; $e['bonnes'] += (int) $s->bonnes; $e['total'] += (int) $s->total; $e['jeux'][ $s->jeu ] = 1;
		if ( 'exam' === $s->mode && $s->note > 0 ) { $e['note'] += (float) $s->note; $e['notes']++; }
		$jour = get_date_from_gmt( $s->cree_le, 'Y-m-d' );
		$r['jours'][ $jour ]['seances'] = ( $r['jours'][ $jour ]['seances'] ?? 0 ) + 1;
		$r['jours'][ $jour ]['duree'] = ( $r['jours'][ $jour ]['duree'] ?? 0 ) + (int) $s->duree;
		unset( $g, $e );
	}
	/* jours d'activité (une ligne par élève, jeu et jour : compte aussi les séries non terminées) */
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, jeu, jour FROM $h WHERE jour >= %s AND jeu IN ($j) $wU $wX", $depuis ) ) as $a ) { // phpcs:ignore
		$eleves[ (int) $a->user_id ] = 1;
		$r['jeux'][ $a->jeu ]['eleves'][ (int) $a->user_id ] = 1;
		foreach ( array( 'seances', 'duree', 'bonnes', 'total', 'note', 'notes' ) as $k ) { $r['jeux'][ $a->jeu ][ $k ] = $r['jeux'][ $a->jeu ][ $k ] ?? 0; }
		$r['jours'][ $a->jour ]['actifs'][ (int) $a->user_id ] = 1;
		$r['parEleve'][ (int) $a->user_id ]['jeux'][ $a->jeu ] = 1;
		$r['parEleve'][ (int) $a->user_id ]['actif'] = 1;
	}
	foreach ( $r['parEleve'] as $id => $e ) { $eleves[ $id ] = 1; }
	$r['eleves'] = count( $eleves );
	return $r;
}

/** XP gagnés depuis une date, par élève (même règle que le classement : écart avec le dernier jour avant la période). */
function jrl_st_xp_depuis( array $uids, $depuis ) {
	global $wpdb;
	$pts = array_fill_keys( $uids, 0 );
	if ( ! $uids || ! jrl_st_ok() ) { return $pts; }
	$jeux = function_exists( 'jrl_cl_jeux' ) ? jrl_cl_jeux() : array_keys( jrl_jeux() );
	$u = jrl_st_in( $uids ); $j = jrl_st_in( $jeux, '%s' ); $p = jdr_table(); $h = jdr_table_hist();
	$base = array(); $mini = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT h.user_id, h.jeu, h.xp FROM $h h WHERE h.user_id IN ($u) AND h.jeu IN ($j) AND h.jour = (SELECT MAX(h2.jour) FROM $h h2 WHERE h2.user_id = h.user_id AND h2.jeu = h.jeu AND h2.jour < %s)", $depuis ) ) as $r ) { $base[ $r->user_id . '|' . $r->jeu ] = (int) $r->xp; } // phpcs:ignore
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, jeu, MIN(xp) AS x FROM $h WHERE user_id IN ($u) AND jeu IN ($j) AND jour >= %s GROUP BY user_id, jeu", $depuis ) ) as $r ) { $mini[ $r->user_id . '|' . $r->jeu ] = (int) $r->x; } // phpcs:ignore
	foreach ( (array) $wpdb->get_results( "SELECT user_id, jeu, xp, cree_le FROM $p WHERE user_id IN ($u) AND jeu IN ($j)" ) as $r ) { // phpcs:ignore
		$k = $r->user_id . '|' . $r->jeu;
		if ( isset( $base[ $k ] ) ) { $b = $base[ $k ]; } elseif ( get_date_from_gmt( $r->cree_le, 'Y-m-d' ) >= $depuis ) { $b = 0; } else { $b = $mini[ $k ] ?? (int) $r->xp; }
		$pts[ (int) $r->user_id ] += max( 0, (int) $r->xp - $b );
	}
	return $pts;
}
/** Progression actuelle par élève : XP total, maîtrise moyenne des jeux commencés, dernière activité. */
function jrl_st_progression( array $uids ) {
	global $wpdb; $o = array();
	if ( ! $uids || ! jrl_st_ok() ) { return $o; }
	$j = jrl_st_in( array_keys( jrl_jeux() ), '%s' ); $p = jdr_table();
	foreach ( (array) $wpdb->get_results( "SELECT user_id, SUM(xp) AS xp, AVG(maitrise) AS m, COUNT(*) AS n, MAX(maj_le) AS d FROM $p WHERE user_id IN (" . jrl_st_in( $uids ) . ") AND jeu IN ($j) AND xp > 0 GROUP BY user_id" ) as $r ) { // phpcs:ignore
		$o[ (int) $r->user_id ] = array( 'xp' => (int) $r->xp, 'maitrise' => (float) $r->m, 'jeux' => (int) $r->n, 'derniere' => $r->d );
	}
	return $o;
}
function jrl_st_classes() {
	$g = array();
	if ( function_exists( 'jrl_cl_table' ) && jrl_cl_table() ) {
		global $wpdb; $t = jrl_cl_table();
		foreach ( array_map( 'absint', (array) $wpdb->get_col( "SELECT DISTINCT group_id FROM $t" ) ) as $id ) { // phpcs:ignore
			if ( $id && 'trash' !== get_post_status( $id ) ) { $n = get_the_title( $id ); $g[ $id ] = $n ? html_entity_decode( $n, ENT_QUOTES ) : 'Groupe ' . $id; }
		}
	}
	$g = (array) apply_filters( 'jrl_stats_classes', $g );
	natcasesort( $g );
	return $g;
}
function jrl_st_eleves_classe( $gid ) {
	$ids = function_exists( 'jrl_cl_membres' ) ? jrl_cl_membres( $gid ) : array();
	return array_values( array_diff( array_map( 'intval', $ids ), jrl_st_exclus() ) );
}

/* ---------- Affichage ---------- */
function jrl_st_duree( $s ) { $s = (int) $s; if ( $s < 60 ) { return $s ? $s . ' s' : '0 min'; } $m = (int) round( $s / 60 ); return $m < 60 ? $m . ' min' : intdiv( $m, 60 ) . ' h ' . sprintf( '%02d', $m % 60 ); }
function jrl_st_pct( $a, $b ) { return $b ? (int) round( 100 * $a / $b ) . ' %' : '–'; }
function jrl_st_note( $n, $k ) { return $k ? number_format_i18n( $n / $k, 1 ) : '–'; }
function jrl_st_url( $args = array() ) { return add_query_arg( $args, admin_url( 'admin.php?page=jeux-logistique-stats' ) ); }

function jrl_stats_page() {
	if ( ! current_user_can( 'jrl_voir_stats' ) ) { wp_die( 'Accès refusé.' ); }
	$per = isset( $_GET['periode'] ) && isset( jrl_st_periodes()[ $_GET['periode'] ] ) ? sanitize_key( $_GET['periode'] ) : '30';
	$depuis = jrl_st_depuis( $per );
	$classes = jrl_st_classes();
	$gid = isset( $_GET['classe'] ) ? (int) $_GET['classe'] : 0;
	jrl_st_assets();
	echo '<div class="wrap jrl-st">';
	if ( $gid && isset( $classes[ $gid ] ) ) { jrl_st_classe( $gid, $classes[ $gid ], $per, $depuis ); echo '</div>'; return; }
	$a = jrl_st_agregats( $depuis );
	$jeux = jrl_jeux();
	$fav = jrl_favoris();
	?>
	<h1>Statistiques des jeux</h1>
	<?php if ( 'roles' === ( $_GET['ok'] ?? '' ) ) : ?><div class="notice notice-success is-dismissible"><p>Accès enregistré.</p></div><?php endif; ?>
	<?php jrl_st_filtre( $per ); ?>
	<div class="jrl-kpis">
		<div><b><?php echo (int) $a['eleves']; ?></b>élèves actifs</div>
		<div><b><?php echo (int) $a['seances']; ?></b>séries terminées</div>
		<div><b><?php echo esc_html( jrl_st_duree( $a['duree'] ) ); ?></b>temps de jeu</div>
		<div><b><?php echo esc_html( $a['eleves'] ? jrl_st_duree( $a['duree'] / $a['eleves'] ) : '–' ); ?></b>par élève actif</div>
		<div><b><?php echo esc_html( jrl_st_pct( $a['bonnes'], $a['total'] ) ); ?></b>de bonnes réponses</div>
		<div><b><?php echo esc_html( jrl_st_note( $a['note'], $a['notes'] ) ); ?></b>note moyenne aux examens blancs<?php echo $a['notes'] ? ' (' . (int) $a['notes'] . ')' : ''; ?></div>
	</div>
	<?php jrl_st_graphe( $a['jours'], $depuis ); ?>
	<?php if ( function_exists( 'jrl_rp_encadre' ) ) { jrl_rp_encadre( $per ); } ?>

	<h2 id="jrl-classes">Classes</h2>
	<?php if ( ! $classes ) : ?>
		<p>Aucun groupe Sensei trouvé. Créez vos classes dans <b>Sensei LMS › Groupes</b> et inscrivez-y vos élèves.</p>
	<?php else :
		$uids_all = array(); $par = array();
		foreach ( $classes as $id => $n ) { $par[ $id ] = jrl_st_eleves_classe( $id ); $uids_all = array_merge( $uids_all, $par[ $id ] ); }
		$uids_all = array_values( array_unique( $uids_all ) );
		$xp = jrl_st_xp_depuis( $uids_all, $depuis ); $prog = jrl_st_progression( $uids_all );
		uksort( $classes, function ( $x, $y ) use ( $fav, $classes ) { $fx = in_array( $x, $fav, true ); $fy = in_array( $y, $fav, true ); return $fx === $fy ? strnatcasecmp( $classes[ $x ], $classes[ $y ] ) : ( $fx ? -1 : 1 ); } );
		?>
		<p class="description">Cliquez sur ☆ pour marquer vos classes : elles s’affichent en premier, pour vous seulement. Les comptes enseignants ne sont pas comptés.</p>
		<p><label><input type="checkbox" id="jrl-mesclasses" <?php checked( (bool) $fav ); ?>> Afficher seulement mes classes</label></p>
		<table class="widefat striped jrl-tbl" id="jrl-tclasses">
			<thead><tr><th style="width:34px"></th><th>Classe</th><th class="num">Élèves</th><th class="num">Actifs</th><th class="num">XP gagnés</th><th class="num">XP / élève actif</th><th class="num">Temps de jeu</th><th class="num">Réussite</th><th class="num">Note exam.</th><th class="num">Maîtrise moy.</th></tr></thead>
			<tbody>
			<?php foreach ( $classes as $id => $n ) :
				$m = $par[ $id ]; $c = jrl_st_agregats( $depuis, $m );
				$xps = 0; $mt = 0; $mn = 0; foreach ( $m as $u ) { $xps += $xp[ $u ] ?? 0; if ( isset( $prog[ $u ] ) ) { $mt += $prog[ $u ]['maitrise']; $mn++; } }
				$isf = in_array( $id, $fav, true ); ?>
				<tr data-fav="<?php echo $isf ? 1 : 0; ?>">
					<td><button type="button" class="jrl-star<?php echo $isf ? ' on' : ''; ?>" data-g="<?php echo (int) $id; ?>" title="Mes classes" aria-pressed="<?php echo $isf ? 'true' : 'false'; ?>"><?php echo $isf ? '★' : '☆'; ?></button></td>
					<td><a href="<?php echo esc_url( jrl_st_url( array( 'classe' => $id, 'periode' => $per ) ) ); ?>"><strong><?php echo esc_html( $n ); ?></strong></a></td>
					<td class="num"><?php echo count( $m ); ?></td>
					<td class="num"><?php echo (int) $c['eleves']; ?> <small>(<?php echo esc_html( jrl_st_pct( $c['eleves'], count( $m ) ) ); ?>)</small></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $xps ) ); ?></td>
					<td class="num"><?php echo $c['eleves'] ? esc_html( number_format_i18n( round( $xps / $c['eleves'] ) ) ) : '–'; ?></td>
					<td class="num"><?php echo esc_html( jrl_st_duree( $c['duree'] ) ); ?></td>
					<td class="num"><?php echo esc_html( jrl_st_pct( $c['bonnes'], $c['total'] ) ); ?></td>
					<td class="num"><?php echo esc_html( jrl_st_note( $c['note'], $c['notes'] ) ); ?></td>
					<td class="num"><?php echo $mn ? (int) round( $mt / $mn ) . ' %' : '–'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2>Jeux</h2>
	<table class="widefat striped jrl-tbl">
		<thead><tr><th>Jeu</th><th class="num">Élèves</th><th class="num">Séries</th><th class="num">Temps de jeu</th><th class="num">Réussite</th><th class="num">Note exam.</th></tr></thead>
		<tbody>
		<?php
		$liste = $a['jeux'];
		uasort( $liste, function ( $x, $y ) { return count( $y['eleves'] ?? array() ) <=> count( $x['eleves'] ?? array() ) ?: ( $y['duree'] <=> $x['duree'] ); } );
		foreach ( $liste as $slug => $g ) : if ( ! isset( $jeux[ $slug ] ) ) { continue; } ?>
			<tr><td><strong><?php echo esc_html( $jeux[ $slug ]['titre'] ); ?></strong></td>
				<td class="num"><?php echo count( $g['eleves'] ?? array() ); ?></td><td class="num"><?php echo (int) $g['seances']; ?></td>
				<td class="num"><?php echo esc_html( jrl_st_duree( $g['duree'] ) ); ?></td><td class="num"><?php echo esc_html( jrl_st_pct( $g['bonnes'], $g['total'] ) ); ?></td>
				<td class="num"><?php echo esc_html( jrl_st_note( $g['note'], $g['notes'] ) ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $liste ) : ?><tr><td colspan="6">Aucune activité sur cette période.</td></tr><?php endif; ?>
		</tbody>
	</table>
	<p class="description"><?php echo esc_html( sprintf( '%d jeux sans activité sur cette période.', count( $jeux ) - count( array_intersect_key( $liste, $jeux ) ) ) ); ?> Le temps de jeu compte les séries terminées ; une série abandonnée en cours de route n’est pas comptée.</p>

	<?php if ( current_user_can( 'manage_options' ) ) : $roles = jrl_stats_roles(); ?>
	<h2 id="jrl-acces">Accès aux statistiques</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'jrl_stats_roles' ); ?><input type="hidden" name="action" value="jrl_stats_roles">
		<p>Rôles qui voient cette page (les administrateurs et les éditeurs la voient toujours) :</p>
		<p><?php foreach ( wp_roles()->roles as $slug => $r ) : if ( 'administrator' === $slug ) { continue; } ?>
			<label style="display:inline-block;margin:0 16px 6px 0"><input type="checkbox" name="roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $roles, true ) ); ?>> <?php echo esc_html( translate_user_role( $r['name'] ) ); ?></label>
		<?php endforeach; ?></p>
		<p class="description">Un·e enseignant·e qui n’a pas le droit de modifier le site voit seulement un menu « LogiPlay » avec cette page. Chacun·e choisit ses propres classes favorites.</p>
		<p><button class="button" type="submit">Enregistrer l’accès</button></p>
	</form>
	<?php endif; ?>
	</div>
	<?php jrl_st_script();
}

function jrl_st_filtre( $per, $extra = array() ) { ?>
	<form method="get" class="jrl-filtre"><input type="hidden" name="page" value="jeux-logistique-stats">
		<?php foreach ( $extra as $k => $v ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>"><?php endforeach; ?>
		<label>Période <select name="periode" onchange="this.form.submit()"><?php foreach ( jrl_st_periodes() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $per, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
	</form>
<?php }

/** Activité par jour : barres (temps de jeu) et nombre d'élèves actifs. */
function jrl_st_graphe( $jours, $depuis ) {
	$fin = current_time( 'Y-m-d' );
	$d0 = max( strtotime( $depuis ), strtotime( $fin ) - 89 * DAY_IN_SECONDS ); /* au plus 90 jours affichés */
	if ( $jours ) { $premier = min( array_keys( $jours ) ); $d0 = max( $d0, strtotime( $premier ) - 3 * DAY_IN_SECONDS ); }
	$liste = array(); for ( $t = $d0; $t <= strtotime( $fin ); $t += DAY_IN_SECONDS ) { $liste[] = gmdate( 'Y-m-d', $t ); }
	if ( count( $liste ) < 2 ) { return; }
	$max = 1; $maxa = 1;
	foreach ( $liste as $d ) { $max = max( $max, (int) ( $jours[ $d ]['duree'] ?? 0 ) ); $maxa = max( $maxa, count( $jours[ $d ]['actifs'] ?? array() ) ); }
	$W = 900; $H = 170; $n = count( $liste ); $bw = $W / $n;
	echo '<div class="jrl-box"><h2 style="margin-top:0">Activité par jour</h2><svg viewBox="0 0 ' . (int) $W . ' ' . (int) ( $H + 24 ) . '" width="100%" role="img" aria-label="Temps de jeu et élèves actifs par jour">';
	$pts = array();
	foreach ( $liste as $i => $d ) {
		$v = (int) ( $jours[ $d ]['duree'] ?? 0 ); $h = $v ? max( 2, $H * $v / $max ) : 0; $x = $i * $bw;
		$act = count( $jours[ $d ]['actifs'] ?? array() );
		echo '<rect x="' . round( $x + $bw * .15, 1 ) . '" y="' . round( $H - $h, 1 ) . '" width="' . round( $bw * .7, 1 ) . '" height="' . round( $h, 1 ) . '" rx="2" fill="#067030"><title>' . esc_html( date_i18n( 'D j M', strtotime( $d ) ) . ' : ' . jrl_st_duree( $v ) . ' de jeu, ' . $act . ' élève(s) actif(s)' ) . '</title></rect>';
		$pts[] = round( $x + $bw / 2, 1 ) . ',' . round( $H - ( $H - 8 ) * $act / $maxa, 1 );
		if ( 1 === (int) gmdate( 'j', strtotime( $d ) ) || 0 === $i ) { echo '<text x="' . round( $x, 1 ) . '" y="' . ( $H + 18 ) . '" font-size="12" fill="#5b6672">' . esc_html( date_i18n( 'j M', strtotime( $d ) ) ) . '</text>'; }
		elseif ( $n <= 31 && 1 === (int) gmdate( 'N', strtotime( $d ) ) && (int) gmdate( 'j', strtotime( $d ) ) > 3 && $i > 3 ) { echo '<text x="' . round( $x, 1 ) . '" y="' . ( $H + 18 ) . '" font-size="12" fill="#8a96a3">' . esc_html( date_i18n( 'j', strtotime( $d ) ) ) . '</text>'; }
	}
	echo '<polyline points="' . esc_attr( implode( ' ', $pts ) ) . '" fill="none" stroke="#d9a400" stroke-width="2.5" stroke-linejoin="round"/>';
	echo '<line x1="0" y1="' . $H . '" x2="' . $W . '" y2="' . $H . '" stroke="#d9ddd6"/></svg>';
	echo '<p class="jrl-leg"><span style="background:#067030"></span>Temps de jeu (séries terminées) <span style="background:#d9a400;height:3px"></span>Élèves actifs</p></div>';
}

function jrl_st_classe( $gid, $nom, $per, $depuis ) {
	$m = jrl_st_eleves_classe( $gid );
	$c = jrl_st_agregats( $depuis, $m ); $xp = jrl_st_xp_depuis( $m, $depuis ); $prog = jrl_st_progression( $m );
	$fav = in_array( $gid, jrl_favoris(), true );
	$rows = array();
	foreach ( $m as $u ) {
		$us = get_userdata( $u ); if ( ! $us ) { continue; }
		$e = $c['parEleve'][ $u ] ?? array();
		$rows[] = array( 'n' => trim( $us->first_name . ' ' . $us->last_name ) ?: $us->display_name, 'xp' => $xp[ $u ] ?? 0, 'p' => $prog[ $u ] ?? null, 'e' => $e );
	}
	usort( $rows, function ( $a, $b ) { return $b['xp'] <=> $a['xp'] ?: strnatcasecmp( $a['n'], $b['n'] ); } );
	?>
	<p><a href="<?php echo esc_url( jrl_st_url( array( 'periode' => $per ) ) . '#jrl-classes' ); ?>">← Toutes les classes</a></p>
	<h1><button type="button" class="jrl-star<?php echo $fav ? ' on' : ''; ?>" data-g="<?php echo (int) $gid; ?>" title="Mes classes" aria-pressed="<?php echo $fav ? 'true' : 'false'; ?>"><?php echo $fav ? '★' : '☆'; ?></button> <?php echo esc_html( $nom ); ?></h1>
	<?php jrl_st_filtre( $per, array( 'classe' => $gid ) ); ?>
	<div class="jrl-kpis">
		<div><b><?php echo (int) $c['eleves']; ?> / <?php echo count( $m ); ?></b>élèves actifs</div>
		<div><b><?php echo esc_html( number_format_i18n( array_sum( $xp ) ) ); ?></b>XP gagnés</div>
		<div><b><?php echo esc_html( jrl_st_duree( $c['duree'] ) ); ?></b>temps de jeu</div>
		<div><b><?php echo esc_html( jrl_st_pct( $c['bonnes'], $c['total'] ) ); ?></b>de bonnes réponses</div>
		<div><b><?php echo esc_html( jrl_st_note( $c['note'], $c['notes'] ) ); ?></b>note moyenne aux examens blancs</div>
	</div>
	<?php jrl_st_graphe( $c['jours'], $depuis ); ?>
	<table class="widefat striped jrl-tbl">
		<thead><tr><th>Élève</th><th class="num">XP gagnés</th><th class="num">XP total</th><th class="num">Jeux joués</th><th class="num">Séries</th><th class="num">Temps de jeu</th><th class="num">Réussite</th><th class="num">Note exam.</th><th class="num">Maîtrise moy.</th><th>Dernière activité</th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : $e = $r['e']; $p = $r['p']; ?>
			<tr<?php echo empty( $e ) ? ' class="jrl-inactif"' : ''; ?>><td><strong><?php echo esc_html( $r['n'] ); ?></strong></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $r['xp'] ) ); ?></td>
				<td class="num"><?php echo $p ? esc_html( number_format_i18n( $p['xp'] ) ) : '–'; ?></td>
				<td class="num"><?php echo count( $e['jeux'] ?? array() ); ?></td>
				<td class="num"><?php echo (int) ( $e['seances'] ?? 0 ); ?></td>
				<td class="num"><?php echo esc_html( jrl_st_duree( $e['duree'] ?? 0 ) ); ?></td>
				<td class="num"><?php echo esc_html( jrl_st_pct( $e['bonnes'] ?? 0, $e['total'] ?? 0 ) ); ?></td>
				<td class="num"><?php echo esc_html( jrl_st_note( $e['note'] ?? 0, $e['notes'] ?? 0 ) ); ?></td>
				<td class="num"><?php echo $p ? (int) round( $p['maitrise'] ) . ' %' : '–'; ?></td>
				<td><?php echo $p && $p['derniere'] ? esc_html( get_date_from_gmt( $p['derniere'], 'd.m.Y H:i' ) ) : '–'; ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $rows ) : ?><tr><td colspan="10">Aucun élève dans cette classe.</td></tr><?php endif; ?>
		</tbody>
	</table>
	<p class="description">Colonnes « XP gagnés », séries, temps, réussite et note : sur la période choisie. « XP total » et « Maîtrise moy. » : état actuel, tous jeux commencés.</p>
	<?php jrl_st_script();
}

function jrl_st_assets() { ?>
	<style>
	.jrl-st .jrl-filtre{margin:10px 0 16px}
	.jrl-st .jrl-filtre select{min-width:200px}
	.jrl-st .jrl-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;max-width:1200px;margin:0 0 18px}
	.jrl-st .jrl-kpis div{background:#fff;border:1px solid #dcdcde;border-top:5px solid #067030;border-radius:6px;padding:12px 14px;color:#50575e}
	.jrl-st .jrl-kpis b{display:block;font-size:1.7em;line-height:1.2;color:#1d2327;font-variant-numeric:tabular-nums}
	.jrl-st .jrl-box{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px 18px;max-width:1200px;margin-bottom:18px;box-sizing:border-box}
	.jrl-st .jrl-leg{color:#50575e;margin:6px 0 0}
	.jrl-st .jrl-leg span{display:inline-block;width:14px;height:10px;border-radius:2px;margin:0 6px 0 12px;vertical-align:middle}
	.jrl-st .jrl-leg span:first-child{margin-left:0}
	.jrl-st .jrl-tbl{max-width:1200px}
	.jrl-st .jrl-tbl .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
	.jrl-st .jrl-tbl tr.jrl-inactif td{color:#8c8f94}
	.jrl-st .jrl-star{border:0;background:none;cursor:pointer;font-size:20px;line-height:1;color:#8c8f94;padding:2px}
	.jrl-st .jrl-star.on{color:#d9a400}
	.jrl-st h1 .jrl-star{font-size:26px;vertical-align:-2px}
	.jrl-st .jrl-exp{display:flex;flex-wrap:wrap;gap:10px 22px;align-items:center}
	.jrl-st .jrl-csv{color:#50575e}
	</style>
<?php }

function jrl_st_script() { ?>
	<script>
	(function () {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'jrl_favori' ) ); ?>, url = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		document.querySelectorAll('.jrl-star').forEach(function (b) { b.addEventListener('click', function () {
			var f = new FormData(); f.append('action', 'jrl_favori'); f.append('_ajax_nonce', nonce); f.append('groupe', b.dataset.g);
			fetch(url, { method: 'POST', credentials: 'same-origin', body: f }).then(function (r) { return r.json(); }).then(function (j) {
				if (!j.success) return; var on = j.data.favori; b.classList.toggle('on', on); b.textContent = on ? '★' : '☆'; b.setAttribute('aria-pressed', on);
				var tr = b.closest('tr'); if (tr) { tr.dataset.fav = on ? 1 : 0; filtre(); }
			}); }); });
		var cb = document.getElementById('jrl-mesclasses');
		function filtre() { if (!cb) return; var rows = document.querySelectorAll('#jrl-tclasses tbody tr'), any = false;
			rows.forEach(function (r) { if (r.dataset.fav === '1') any = true; });
			rows.forEach(function (r) { r.hidden = cb.checked && any && r.dataset.fav !== '1'; }); }
		if (cb) { cb.addEventListener('change', filtre); filtre(); }
	})();
	</script>
<?php }
