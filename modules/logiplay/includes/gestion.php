<?php
/*
 * Gestion des jeux (menu LogiPlay > Gestion des jeux).
 * - activer / masquer un jeu (un jeu masqué n'apparaît plus nulle part pour les élèves et ne s'ouvre plus) ;
 * - statistiques par jeu (élèves, maîtrise moyenne, note moyenne, dernière activité) ;
 * - textes de la carte modifiables (titre, durée, en français et en allemand) et couleur ;
 * - aperçu du jeu et remise à zéro de la progression d'un élève.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function jrl_masques() { $m = get_option( 'jrl_masques', array() ); return is_array( $m ) ? array_values( array_filter( $m, 'is_string' ) ) : array(); }
function jrl_est_masque( $slug ) { return in_array( $slug, jrl_masques(), true ); }
function jrl_cartes() { $c = get_option( 'jrl_cartes', array() ); return is_array( $c ) ? $c : array(); }

/* Textes de carte modifiés et jeux masqués (après l'inscription des jeux, priorité 20) */
add_filter( 'jdr_jeux', function ( $jeux ) {
	$cartes = jrl_cartes(); $masques = jrl_masques();
	/* aperçu : un éditeur peut encore ouvrir un jeu masqué (page du jeu et sauvegarde) ; l'administration voit tout */
	$ed = did_action( 'init' ) && current_user_can( 'edit_posts' );
	$apercu = $ed && ( isset( $_GET['jdr_jeu'] ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) );
	foreach ( jrl_jeux() as $slug => $d ) {
		if ( ! isset( $jeux[ $slug ] ) ) { continue; }
		if ( in_array( $slug, $masques, true ) && ! is_admin() && ! $apercu ) { unset( $jeux[ $slug ] ); continue; }
		$c = isset( $cartes[ $slug ] ) && is_array( $cartes[ $slug ] ) ? $cartes[ $slug ] : array();
		if ( ! empty( $c['titre'] ) ) { $jeux[ $slug ]['titre'] = (string) $c['titre']; }
		if ( ! empty( $c['duree'] ) ) { $jeux[ $slug ]['duree'] = (string) $c['duree']; }
		if ( ! empty( $c['couleur'] ) ) { $jeux[ $slug ]['couleur'] = (string) $c['couleur']; }
		if ( ! empty( $jeux[ $slug ]['i18n']['de'] ) ) {
			if ( ! empty( $c['titre_de'] ) ) { $jeux[ $slug ]['i18n']['de']['titre'] = (string) $c['titre_de']; }
			if ( ! empty( $c['duree_de'] ) ) { $jeux[ $slug ]['i18n']['de']['duree'] = (string) $c['duree_de']; }
		}
	}
	return $jeux;
}, 25 );

/** Statistiques de tous les jeux en une requête : slug => array( eleves, maitrise, note, notes, derniere ). */
function jrl_stats() {
	global $wpdb;
	if ( ! function_exists( 'jdr_table' ) ) { return array(); }
	$t = jdr_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return array(); }
	$rows = $wpdb->get_results( "SELECT jeu, COUNT(*) AS eleves, AVG(maitrise) AS maitrise, AVG(NULLIF(meilleure_note,0)) AS note, SUM(meilleure_note > 0) AS notes, MAX(maj_le) AS derniere FROM $t WHERE xp > 0 GROUP BY jeu" ); // phpcs:ignore
	$s = array();
	foreach ( (array) $rows as $r ) { $s[ $r->jeu ] = $r; }
	return $s;
}

/* Menu : « Gestion des jeux » juste sous « Shortcodes et réglages » */
add_action( 'admin_menu', function () {
	add_submenu_page( 'jeux-logistique', 'Gestion des jeux', 'Gestion des jeux', 'edit_posts', 'jeux-logistique-gestion', 'jrl_gestion_page', 1 );
}, 21 );

function jrl_gestion_url( $args = array() ) { return add_query_arg( $args, admin_url( 'admin.php?page=jeux-logistique-gestion' ) ); }

/* Actions : masquer / activer, textes de carte, remise à zéro */
add_action( 'admin_post_jrl_gestion', function () {
	if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Accès refusé.' ); }
	check_admin_referer( 'jrl_gestion' );
	$tous = jrl_jeux();
	$quoi = isset( $_POST['quoi'] ) ? sanitize_key( wp_unslash( $_POST['quoi'] ) ) : '';
	$jeu  = isset( $_POST['jeu'] ) ? sanitize_key( wp_unslash( $_POST['jeu'] ) ) : '';
	$retour = isset( $_POST['retour'] ) && 'fiche' === $_POST['retour'] && isset( $tous[ $jeu ] ) ? array( 'jeu' => $jeu ) : array();

	if ( 'statut' === $quoi || 'lot' === $quoi ) {
		$liste = 'lot' === $quoi ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['jeux'] ?? array() ) ) : array( $jeu );
		$liste = array_values( array_intersect( $liste, array_keys( $tous ) ) );
		$masquer = isset( $_POST['etat'] ) && 'masquer' === $_POST['etat'];
		$m = jrl_masques();
		$m = $masquer ? array_values( array_unique( array_merge( $m, $liste ) ) ) : array_values( array_diff( $m, $liste ) );
		update_option( 'jrl_masques', $m, false );
		wp_safe_redirect( jrl_gestion_url( $retour + array( 'ok' => $masquer ? 'masque' : 'active', 'n' => count( $liste ) ) ) ); exit;
	}
	if ( 'carte' === $quoi && isset( $tous[ $jeu ] ) ) {
		$c = jrl_cartes();
		if ( ! empty( $_POST['origine'] ) ) {
			unset( $c[ $jeu ] );
		} else {
			$v = array();
			foreach ( array( 'titre', 'titre_de', 'duree', 'duree_de' ) as $k ) { $x = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; if ( '' !== $x ) { $v[ $k ] = $x; } }
			$col = isset( $_POST['couleur'] ) ? sanitize_hex_color( wp_unslash( $_POST['couleur'] ) ) : '';
			if ( $col && strtolower( $col ) !== strtolower( (string) ( $tous[ $jeu ]['couleur'] ?? '#067030' ) ) ) { $v['couleur'] = $col; }
			if ( $v ) { $c[ $jeu ] = $v; } else { unset( $c[ $jeu ] ); }
		}
		update_option( 'jrl_cartes', $c, false );
		$m = jrl_masques();
		$m = empty( $_POST['actif'] ) ? array_values( array_unique( array_merge( $m, array( $jeu ) ) ) ) : array_values( array_diff( $m, array( $jeu ) ) );
		update_option( 'jrl_masques', $m, false );
		wp_safe_redirect( jrl_gestion_url( array( 'jeu' => $jeu, 'ok' => empty( $_POST['origine'] ) ? 'carte' : 'origine' ) ) ); exit;
	}
	if ( 'raz' === $quoi && isset( $tous[ $jeu ] ) && function_exists( 'jdr_write' ) ) {
		if ( ! current_user_can( function_exists( 'jdr_cap' ) ? jdr_cap() : 'edit_others_posts' ) ) { wp_die( 'Accès refusé.' ); }
		global $wpdb;
		$uids = 'tous' === ( $_POST['qui'] ?? '' )
			? array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . jdr_table() . ' WHERE jeu = %s', $jeu ) ) )
			: array( (int) ( $_POST['user'] ?? 0 ) );
		$n = 0;
		foreach ( array_filter( $uids ) as $uid ) {
			if ( ! jdr_row( $uid, $jeu ) ) { continue; }
			/* la progression repart de zéro ; « raz » oblige chaque appareil de l'élève à oublier sa copie locale */
			$wpdb->delete( jdr_table_hist(), array( 'user_id' => $uid, 'jeu' => $jeu ) );
			$d = array( 'state' => array( 'raz' => (string) time() . '-' . wp_generate_password( 6, false ) ) );
			if ( jdr_write( $uid, $jeu, $d ) ) { $n++; }
		}
		wp_safe_redirect( jrl_gestion_url( array( 'jeu' => $jeu, 'ok' => 'raz', 'n' => $n ) ) ); exit;
	}
	wp_safe_redirect( jrl_gestion_url() ); exit;
} );

function jrl_gestion_page() {
	if ( ! empty( $_GET['jeu'] ) && isset( jrl_jeux()[ sanitize_key( wp_unslash( $_GET['jeu'] ) ) ] ) ) { jrl_gestion_fiche( sanitize_key( wp_unslash( $_GET['jeu'] ) ) ); return; }
	$jeux = jrl_jeux(); $stats = jrl_stats(); $masques = jrl_masques(); $cartes = jrl_cartes();
	$ok = isset( $_GET['ok'] ) ? sanitize_key( $_GET['ok'] ) : ''; $n = (int) ( $_GET['n'] ?? 0 );
	$msg = array( 'masque' => sprintf( '%d jeu(x) masqué(s).', $n ), 'active' => sprintf( '%d jeu(x) activé(s).', $n ) );
	$eleves = 0; foreach ( $stats as $s ) { $eleves += (int) $s->eleves; }
	$series = array(); foreach ( $jeux as $d ) { $series[ substr( strtoupper( $d['module'] ), 0, 2 ) ] = true; }
	?>
	<div class="wrap jrl-gestion">
		<h1>Gestion des jeux</h1>
		<?php if ( isset( $msg[ $ok ] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg[ $ok ] ); ?></p></div><?php endif; ?>
		<p><?php echo esc_html( sprintf( '%d jeux · %d actifs · %d masqués · %d participations', count( $jeux ), count( $jeux ) - count( array_intersect( $masques, array_keys( $jeux ) ) ), count( array_intersect( $masques, array_keys( $jeux ) ) ), $eleves ) ); ?>.
			Un jeu masqué disparaît du catalogue, des regroupements et de son shortcode, et les élèves ne peuvent plus l’ouvrir ; leurs progressions sont conservées.</p>
		<div class="jrl-filtres">
			<label>Série <select id="jrl-f-serie"><option value="">Toutes</option><?php foreach ( array_keys( $series ) as $s ) : ?><option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $s . '00' ); ?></option><?php endforeach; ?></select></label>
			<label>Statut <select id="jrl-f-statut"><option value="">Tous</option><option value="actif">Actifs</option><option value="masque">Masqués</option></select></label>
			<label><input type="search" id="jrl-f-txt" placeholder="Rechercher un jeu…"></label>
			<span id="jrl-f-n" class="description"></span>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jrl_gestion' ); ?>
			<input type="hidden" name="action" value="jrl_gestion"><input type="hidden" name="quoi" value="lot">
			<p class="jrl-lot">Sélection : <button class="button" name="etat" value="activer">Activer</button> <button class="button" name="etat" value="masquer">Masquer</button></p>
			<table class="widefat striped jrl-table">
				<thead><tr><td class="check-column"><input type="checkbox" id="jrl-tout"></td><th>Module</th><th>Jeu</th><th>Statut</th><th class="num">Élèves</th><th class="num">Maîtrise moy.</th><th class="num">Note moy.</th><th>Dernière activité</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach ( $jeux as $slug => $d ) :
					$s = $stats[ $slug ] ?? null; $cache = in_array( $slug, $masques, true ); $titre = ! empty( $cartes[ $slug ]['titre'] ) ? $cartes[ $slug ]['titre'] : $d['titre'];
					$apercu = function_exists( 'jdr_game_url' ) ? jdr_game_url( $slug ) : ''; ?>
					<tr data-serie="<?php echo esc_attr( substr( strtoupper( $d['module'] ), 0, 2 ) ); ?>" data-statut="<?php echo $cache ? 'masque' : 'actif'; ?>" data-txt="<?php echo esc_attr( strtolower( $d['module'] . ' ' . $titre . ' ' . $d['titre'] ) ); ?>"<?php echo $cache ? ' class="jrl-off"' : ''; ?>>
						<th class="check-column"><input type="checkbox" name="jeux[]" value="<?php echo esc_attr( $slug ); ?>"></th>
						<td><strong><?php echo esc_html( $d['module'] ); ?></strong></td>
						<td><a href="<?php echo esc_url( jrl_gestion_url( array( 'jeu' => $slug ) ) ); ?>"><strong><?php echo esc_html( $titre ); ?></strong></a><?php echo $titre !== $d['titre'] ? '<br><small>Titre d’origine : ' . esc_html( $d['titre'] ) . '</small>' : ''; ?><br><code>[<?php echo esc_html( $d['shortcode'] ); ?>]</code></td>
						<td><button type="submit" class="jrl-pill <?php echo $cache ? 'is-off' : 'is-on'; ?>" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="un" value="<?php echo esc_attr( $slug ); ?>" title="<?php echo $cache ? 'Cliquer pour activer' : 'Cliquer pour masquer'; ?>"><?php echo $cache ? 'Masqué' : 'Actif'; ?></button></td>
						<td class="num"><?php echo $s ? (int) $s->eleves : 0; ?></td>
						<td class="num"><?php echo $s ? (int) round( $s->maitrise ) . ' %' : '–'; ?></td>
						<td class="num"><?php echo $s && $s->note ? esc_html( number_format_i18n( (float) $s->note, 1 ) ) . ' <small>(' . (int) $s->notes . ')</small>' : '–'; ?></td>
						<td><?php echo $s && $s->derniere ? esc_html( get_date_from_gmt( $s->derniere, 'd.m.Y H:i' ) ) : '–'; ?></td>
						<td class="jrl-actions"><a class="button button-small" href="<?php echo esc_url( jrl_gestion_url( array( 'jeu' => $slug ) ) ); ?>">Modifier</a>
							<?php if ( $apercu ) : ?><a class="button button-small" href="<?php echo esc_url( $apercu ); ?>" target="_blank" rel="noopener">Aperçu</a><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>
	</div>
	<?php jrl_gestion_assets(); ?>
	<script>
	(function () {
		var rows = Array.prototype.slice.call(document.querySelectorAll('.jrl-table tbody tr'));
		var fs = document.getElementById('jrl-f-serie'), fst = document.getElementById('jrl-f-statut'), ft = document.getElementById('jrl-f-txt'), fn = document.getElementById('jrl-f-n');
		function f() { var n = 0, t = ft.value.trim().toLowerCase();
			rows.forEach(function (r) { var ok = (!fs.value || r.dataset.serie === fs.value) && (!fst.value || r.dataset.statut === fst.value) && (!t || r.dataset.txt.indexOf(t) >= 0); r.hidden = !ok; if (ok) n++; });
			fn.textContent = n + ' jeu' + (n > 1 ? 'x' : '') + ' affiché' + (n > 1 ? 's' : ''); }
		[fs, fst].forEach(function (e) { e.addEventListener('change', f); }); ft.addEventListener('input', f); f();
		document.getElementById('jrl-tout').addEventListener('change', function () { var c = this.checked; rows.forEach(function (r) { if (!r.hidden) r.querySelector('input[type=checkbox]').checked = c; }); });
		/* bouton Actif / Masqué d'une ligne : bascule ce seul jeu */
		document.querySelectorAll('.jrl-pill[name=un]').forEach(function (b) { b.addEventListener('click', function (e) {
			var form = b.form; e.preventDefault();
			form.querySelector('[name=quoi]').value = 'statut';
			var j = document.createElement('input'); j.type = 'hidden'; j.name = 'jeu'; j.value = b.value; form.appendChild(j);
			var et = document.createElement('input'); et.type = 'hidden'; et.name = 'etat'; et.value = b.classList.contains('is-off') ? 'activer' : 'masquer'; form.appendChild(et);
			form.submit(); }); });
	})();
	</script>
	<?php
}

function jrl_gestion_assets() { ?>
	<style>
	.jrl-gestion .jrl-filtres{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin:14px 0 6px}
	.jrl-gestion .jrl-table td.num,.jrl-gestion .jrl-table th.num{text-align:right;font-variant-numeric:tabular-nums}
	.jrl-gestion .jrl-table tr.jrl-off td{opacity:.6}
	.jrl-gestion .jrl-table tr.jrl-off td:nth-child(4){opacity:1}
	.jrl-gestion .jrl-pill{cursor:pointer;border-radius:99px;padding:3px 12px;font-weight:600;border:1px solid;background:#fff}
	.jrl-gestion .jrl-pill.is-on{color:#067030;border-color:#067030;background:#e9f5ed}
	.jrl-gestion .jrl-pill.is-off{color:#8a2424;border-color:#c9a3a3;background:#fbeeee}
	.jrl-gestion .jrl-actions{white-space:nowrap}
	.jrl-fiche{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start;max-width:1200px}
	@media (max-width:1100px){.jrl-fiche{grid-template-columns:minmax(0,1fr)}}
	.jrl-box{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px 18px;margin-bottom:18px}
	.jrl-box h2{margin-top:0}
	.jrl-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
	.jrl-kpis div{background:#f6f7f7;border-radius:6px;padding:10px 12px}
	.jrl-kpis b{display:block;font-size:1.5em;line-height:1.2}
	</style>
<?php }

/* Fiche d'un jeu : carte, statut, statistiques, élèves */
function jrl_gestion_fiche( $slug ) {
	$tous = jrl_jeux(); $d = $tous[ $slug ]; $c = jrl_cartes()[ $slug ] ?? array(); $cache = jrl_est_masque( $slug );
	$de = is_array( $d['de'] ?? null ) ? $d['de'] : array();
	$s = jrl_stats()[ $slug ] ?? null;
	$ok = isset( $_GET['ok'] ) ? sanitize_key( $_GET['ok'] ) : ''; $n = (int) ( $_GET['n'] ?? 0 );
	$msg = array( 'carte' => 'Carte enregistrée.', 'origine' => 'Textes d’origine rétablis.', 'masque' => 'Jeu masqué.', 'active' => 'Jeu activé.', 'raz' => sprintf( 'Progression remise à zéro pour %d élève(s).', $n ) );
	$rows = function_exists( 'jdr_rows_for' ) ? (array) jdr_rows_for( $slug ) : array();
	$tot = count( (array) ( $d['contenu'] ?? array() ) );
	$peut_raz = current_user_can( function_exists( 'jdr_cap' ) ? jdr_cap() : 'edit_others_posts' );
	?>
	<div class="wrap jrl-gestion">
		<p><a href="<?php echo esc_url( jrl_gestion_url() ); ?>">← Tous les jeux</a></p>
		<h1><?php echo esc_html( $c['titre'] ?? $d['titre'] ); ?> <?php echo $cache ? '<span class="jrl-pill is-off" style="font-size:13px;vertical-align:middle">Masqué</span>' : ''; ?></h1>
		<?php if ( isset( $msg[ $ok ] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg[ $ok ] ); ?></p></div><?php endif; ?>
		<div class="jrl-fiche">
			<div>
				<form class="jrl-box" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'jrl_gestion' ); ?>
					<input type="hidden" name="action" value="jrl_gestion"><input type="hidden" name="quoi" value="carte"><input type="hidden" name="jeu" value="<?php echo esc_attr( $slug ); ?>">
					<h2>Carte du jeu</h2>
					<p class="description">Ces textes ne changent que la carte affichée sur le site, pas le jeu lui-même. Laissez un champ vide pour garder le texte d’origine.</p>
					<table class="form-table" role="presentation"><tbody>
						<tr><th scope="row">Statut</th><td><label><input type="checkbox" name="actif" value="1" <?php checked( ! $cache ); ?>> Jeu actif (visible par les élèves)</label></td></tr>
						<tr><th scope="row"><label for="jrl-t">Titre</label></th><td><input class="large-text" id="jrl-t" name="titre" value="<?php echo esc_attr( $c['titre'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $d['titre'] ); ?>"></td></tr>
						<tr><th scope="row"><label for="jrl-td">Titre en allemand</label></th><td><input class="large-text" id="jrl-td" name="titre_de" value="<?php echo esc_attr( $c['titre_de'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $de['titre'] ?? '' ); ?>"></td></tr>
						<tr><th scope="row"><label for="jrl-d">Durée</label></th><td><input class="regular-text" id="jrl-d" name="duree" value="<?php echo esc_attr( $c['duree'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $d['duree'] ?? '' ); ?>"></td></tr>
						<tr><th scope="row"><label for="jrl-dd">Durée en allemand</label></th><td><input class="regular-text" id="jrl-dd" name="duree_de" value="<?php echo esc_attr( $c['duree_de'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $de['duree'] ?? '' ); ?>"></td></tr>
						<tr><th scope="row"><label for="jrl-c">Couleur</label></th><td><input type="color" id="jrl-c" name="couleur" value="<?php echo esc_attr( $c['couleur'] ?? ( $d['couleur'] ?? '#067030' ) ); ?>"> <span class="description">boutons et titre de module de la carte</span></td></tr>
					</tbody></table>
					<p><button class="button button-primary" type="submit">Enregistrer</button>
						<?php if ( $c ) : ?> <button class="button" type="submit" name="origine" value="1">Rétablir les textes d’origine</button><?php endif; ?></p>
				</form>

				<div class="jrl-box">
					<h2>Élèves (<?php echo (int) count( $rows ); ?>)</h2>
					<?php if ( ! $rows ) : ?><p>Aucun élève n’a encore joué à ce jeu.</p><?php else : ?>
					<table class="widefat striped">
						<thead><tr><th>Élève</th><th class="num">XP</th><th class="num">Maîtrise</th><th class="num">Objectifs</th><th class="num">Meilleure note</th><th>Dernière activité</th><?php echo $peut_raz ? '<th></th>' : ''; ?></tr></thead>
						<tbody>
						<?php foreach ( $rows as $p ) : ?>
							<tr><td><?php echo esc_html( $p->display_name ? $p->display_name : '#' . $p->user_id ); ?><br><small><?php echo esc_html( (string) $p->user_login ); ?></small></td>
								<td class="num"><?php echo esc_html( number_format_i18n( (int) $p->xp ) ); ?></td><td class="num"><?php echo (int) $p->maitrise; ?> %</td>
								<td class="num"><?php echo (int) $p->objectifs . ' / ' . (int) $tot; ?></td>
								<td class="num"><?php echo $p->meilleure_note > 0 ? esc_html( number_format_i18n( (float) $p->meilleure_note, 1 ) ) : '–'; ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $p->maj_le, 'd.m.Y H:i' ) ); ?></td>
								<?php if ( $peut_raz ) : ?><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remettre à zéro la progression de <?php echo esc_js( $p->display_name ? $p->display_name : $p->user_login ); ?> sur ce jeu ? XP, maîtrise, badges et meilleure note seront effacés. Cette action est définitive.');">
									<?php wp_nonce_field( 'jrl_gestion' ); ?><input type="hidden" name="action" value="jrl_gestion"><input type="hidden" name="quoi" value="raz"><input type="hidden" name="jeu" value="<?php echo esc_attr( $slug ); ?>"><input type="hidden" name="user" value="<?php echo (int) $p->user_id; ?>">
									<button class="button button-small button-link-delete" type="submit"<?php disabled( (int) $p->xp <= 0 ); ?>>Remettre à zéro</button></form></td><?php endif; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $peut_raz ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px" onsubmit="return confirm('Remettre à zéro la progression de TOUS les élèves sur ce jeu ? Cette action est définitive.');">
						<?php wp_nonce_field( 'jrl_gestion' ); ?><input type="hidden" name="action" value="jrl_gestion"><input type="hidden" name="quoi" value="raz"><input type="hidden" name="qui" value="tous"><input type="hidden" name="jeu" value="<?php echo esc_attr( $slug ); ?>">
						<button class="button button-link-delete" type="submit">Remettre à zéro tous les élèves</button>
						<span class="description">Les bilans des sessions déjà jouées restent dans le suivi.</span>
					</form>
					<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
			<div>
				<div class="jrl-box">
					<h2>Statistiques</h2>
					<div class="jrl-kpis">
						<div><b><?php echo $s ? (int) $s->eleves : 0; ?></b>élèves</div>
						<div><b><?php echo $s ? (int) round( $s->maitrise ) . ' %' : '–'; ?></b>maîtrise moyenne</div>
						<div><b><?php echo $s && $s->note ? esc_html( number_format_i18n( (float) $s->note, 1 ) ) : '–'; ?></b>note moyenne à l’examen blanc<?php echo $s && $s->notes ? ' (' . (int) $s->notes . ')' : ''; ?></div>
						<div><b style="font-size:1.05em"><?php echo $s && $s->derniere ? esc_html( get_date_from_gmt( $s->derniere, 'd.m.Y H:i' ) ) : '–'; ?></b>dernière activité</div>
					</div>
					<p><a href="<?php echo esc_url( admin_url( 'tools.php?page=jeux-revision&jeu=' . $slug . '#suivi' ) ); ?>">Suivi détaillé et export CSV</a></p>
				</div>
				<div class="jrl-box">
					<h2>Aperçu</h2>
					<p>Ouvre le jeu dans un nouvel onglet, avec votre compte (votre propre progression), même s’il est masqué.</p>
					<?php if ( function_exists( 'jdr_game_url' ) ) : ?>
					<p><a class="button" href="<?php echo esc_url( jdr_game_url( $slug ) ); ?>" target="_blank" rel="noopener">Ouvrir en français</a>
						<?php if ( file_exists( JRL_DIR . '/jeux/' . $slug . '/index.de.html' ) ) : ?><a class="button" href="<?php echo esc_url( jdr_game_url( $slug, 'de' ) ); ?>" target="_blank" rel="noopener">Auf Deutsch öffnen</a><?php endif; ?>
						<?php if ( file_exists( JRL_DIR . '/jeux/' . $slug . '/index.it.html' ) ) : ?><a class="button" href="<?php echo esc_url( jdr_game_url( $slug, 'it' ) ); ?>" target="_blank" rel="noopener">Aprire in italiano</a><?php endif; ?></p>
					<?php endif; ?>
					<p>Shortcodes : <code>[<?php echo esc_html( $d['shortcode'] ); ?>]</code> · <code>[<?php echo esc_html( $d['shortcode'] ); ?> langue="de"]</code> · <code>[<?php echo esc_html( $d['shortcode'] ); ?> langue="it"]</code></p>
				</div>
			</div>
		</div>
	</div>
	<?php jrl_gestion_assets();
}
