<?php
/* Menu Jeux logistique : liste des jeux et de leurs shortcodes, mises à jour */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Menu « LogiPlay » dans la barre latérale de l'administration (sous Commentaires) */
add_action( 'admin_menu', function () {
	add_menu_page( 'LogiPlay', 'LogiPlay', 'edit_posts', 'jeux-logistique', 'jrl_admin_page', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iYmxhY2siIGZpbGwtcnVsZT0iZXZlbm9kZCIgZD0iTTEwIDEuMiAxNy42IDUuNiAxMCAxMCAyLjQgNS42WiBNOC42IDMuNlY3LjZMMTIgNS42WiIvPjxwYXRoIGZpbGw9ImJsYWNrIiBkPSJNMiA2LjQgOS41IDEwLjhWMTkuMkwyIDE0LjhaIi8+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik0xOCA2LjQgMTAuNSAxMC44VjE5LjJMMTggMTQuOFoiLz48L3N2Zz4=', 26 );
	add_submenu_page( 'jeux-logistique', 'LogiPlay', 'Shortcodes et réglages', 'edit_posts', 'jeux-logistique', 'jrl_admin_page' );
	add_submenu_page( 'jeux-logistique', 'Regroupements de modules', 'Regroupements', 'edit_posts', 'admin.php?page=jeux-logistique#jrl-groupes' );
	if ( function_exists( 'jdr_admin_page' ) ) {
		add_submenu_page( 'jeux-logistique', 'Suivi des élèves', 'Suivi des élèves', function_exists( 'jdr_cap' ) ? jdr_cap() : 'edit_others_posts', 'tools.php?page=jeux-revision' );
	}
}, 20 );
/* Ancienne adresse (LogiPlay) : redirection vers le nouveau menu */
add_action( 'admin_init', function () {
	global $pagenow;
	if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && 'jeux-logistique' === $_GET['page'] ) {
		wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique' ) ); exit;
	}
} );

add_filter( 'plugin_action_links_' . plugin_basename( JRL_FILE ), function ( $liens ) {
	array_unshift( $liens, '<a href="' . esc_url( admin_url( 'admin.php?page=jeux-logistique' ) ) . '">Shortcodes</a>' );
	return $liens;
} );

/* Les anciennes extensions « Jeu de révision – Mxxx » font doublon : on propose de les désactiver */
function jrl_doublons() {
	if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	$d = array();
	foreach ( array_keys( jrl_jeux() ) as $slug ) {
		$f = 'jeu-revision-' . $slug . '/jeu-revision-' . $slug . '.php';
		if ( is_plugin_active( $f ) ) { $d[] = $f; }
	}
	return $d;
}
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) { return; }
	$d = jrl_doublons();
	if ( ! $d ) { return; }
	$url = wp_nonce_url( admin_url( 'admin.php?page=jeux-logistique&jrl_desactiver=1' ), 'jrl_desactiver' );
	echo '<div class="notice notice-warning"><p><strong>LogiPlay :</strong> ' . esc_html( sprintf( '%d ancienne(s) extension(s) « Jeu de révision – Mxxx » sont encore actives. Les mêmes jeux sont maintenant fournis par cette extension. Les progressions des élèves sont conservées.', count( $d ) ) ) . ' <a class="button button-small" href="' . esc_url( $url ) . '">Désactiver les anciennes extensions</a></p></div>';
} );
add_action( 'admin_init', function () {
	if ( empty( $_GET['jrl_desactiver'] ) || ! current_user_can( 'activate_plugins' ) ) { return; }
	check_admin_referer( 'jrl_desactiver' );
	deactivate_plugins( jrl_doublons() );
	wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique&jrl_ok=desactive' ) ); exit;
} );

add_action( 'admin_post_jrl_reglages', function () {
	if ( ! current_user_can( 'update_plugins' ) ) { wp_die( 'Accès refusé.' ); }
	check_admin_referer( 'jrl_reglages' );
	if ( isset( $_POST['jrl_affichage'] ) ) {
		$col = isset( $_POST['jrl_colonnes'] ) ? sanitize_key( wp_unslash( $_POST['jrl_colonnes'] ) ) : 'auto';
		update_option( 'jrl_colonnes', in_array( $col, array( 'auto', '1', '2', '3', '4' ), true ) ? $col : 'auto' );
		foreach ( array( 'jrl_aff_duree', 'jrl_aff_progression', 'jrl_aff_objectifs', 'jrl_cl_podium', 'jrl_cl_jeu', 'jrl_visiteurs' ) as $o ) { update_option( $o, empty( $_POST[ $o ] ) ? '0' : '1' ); }
	}
	if ( isset( $_POST['jrl_acces_jeux'] ) ) {
		// Jeux ouverts aux visiteurs sans compte : les cases cochées ; on enregistre les jeux décochés (fermés).
		$ouverts = array_map( 'sanitize_key', (array) wp_unslash( $_POST['jrl_sans_compte'] ?? array() ) );
		$bloques = array_values( array_diff( array_keys( jrl_jeux() ), $ouverts ) );
		update_option( 'jrl_visiteurs_bloques', $bloques );
	}
	$ok = 'reglages'; // LogiOne : LogiPlay se met à jour avec LogiOne (plus de mise à jour GitHub séparée).
	wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique&jrl_ok=' . $ok ) ); exit;
} );

function jrl_admin_page() {
	$jeux = jrl_jeux();
	$ok = isset( $_GET['jrl_ok'] ) ? sanitize_key( $_GET['jrl_ok'] ) : '';
	$msg = array( 'reglages' => 'Réglages enregistrés.', 'verifie' => 'GitHub a été consulté.', 'desactive' => 'Anciennes extensions désactivées.', 'groupe_ok' => 'Regroupement enregistré.', 'groupe_supprime' => 'Regroupement supprimé.' );
	?>
	<div class="wrap">
		<h1 style="margin:8px 0 14px"><img src="<?php echo esc_url( plugins_url( 'assets/logiplay-logo.svg', JRL_FILE ) ); ?>" alt="LogiPlay by LogiProf" style="height:56px;width:auto;display:block"></h1>
		<?php if ( isset( $msg[ $ok ] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg[ $ok ] ); ?></p></div><?php endif; ?>
		<?php if ( 'groupe_vide' === $ok ) : ?><div class="notice notice-error is-dismissible"><p>Donnez un nom au regroupement et cochez au moins un module.</p></div><?php endif; ?>
		<p>Copiez un shortcode dans une page ou un article (bloc « Code court »). Les élèves connectés retrouvent leur progression sur leur compte. Si l’accès des visiteurs est activé (plus bas), les personnes sans compte peuvent aussi jouer : leur progression reste dans leur navigateur, sans classement.</p>

		<h2>Catalogue complet</h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
				<tr><td style="width:42%"><code>[jeux_logistique]</code></td><td>Tous les jeux de l’extension (<?php echo (int) count( $jeux ); ?>).</td></tr>
				<tr><td><code>[jeux_logistique modules="M1"]</code></td><td>Seulement les modules M101 à M110 (M2 pour M201 à M205…).</td></tr>
				<tr><td><code>[jeux_logistique modules="M101,M203"]</code></td><td>Seulement les modules choisis.</td></tr>
				<tr><td><code>[jeux_logistique groupe="…"]</code></td><td>Un regroupement de modules que vous avez créé (voir « Regroupements de modules » plus bas) ; chaque regroupement a aussi son propre shortcode.</td></tr>
				<tr><td><code>[jeux_logistique entete="non"]</code></td><td>Sans le bandeau LogiPlay (logo, salutation, cubes) en haut du catalogue.</td></tr>
				<tr><td><code>[jeux_logistique largeur="contenu"]</code></td><td>Catalogue dans la colonne du thème. Par défaut, l’interface LogiPlay occupe toute la largeur de la page, comme LogiLive.</td></tr>
				<tr><td><code>[jeux_logistique colonnes="3"]</code></td><td>Nombre de jeux côte à côte sur cette page (1 à 4). Sans <code>colonnes</code>, le réglage « Affichage du catalogue » ci-dessous s’applique.</td></tr>
				<tr><td><code>[jeux_logistique langue="de"]</code><br><code>[jeux_logistique langue="it"]</code></td><td>Le catalogue en allemand ou en italien (cartes et jeux). Fonctionne aussi avec <code>modules="…"</code> et pour un seul jeu : <code>[jeu_m101 langue="it"]</code>. Sans <code>langue</code>, l’élève peut changer de langue dans le jeu (menu FR · DE · IT) ; sa progression est la même dans les trois langues. Un jeu pas encore traduit s’affiche en français.</td></tr>
				<tr><td><code>[jeux_revision]</code></td><td>Tous les jeux de révision installés sur le site, y compris ceux d’autres extensions.</td></tr>
			</tbody>
		</table>

		<h2>Affichage du catalogue</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jrl_reglages' ); ?>
			<input type="hidden" name="action" value="jrl_reglages"><input type="hidden" name="jrl_affichage" value="1">
			<input type="hidden" name="jrl_maj_auto" value="<?php echo esc_attr( get_option( 'jrl_maj_auto', '1' ) ); ?>">
			<table class="form-table" role="presentation" style="max-width:900px"><tbody>
				<tr><th scope="row"><label for="jrl_colonnes">Jeux côte à côte</label></th><td>
					<select name="jrl_colonnes" id="jrl_colonnes">
						<?php foreach ( array( 'auto' => 'Automatique (selon la largeur)', '1' => '1 jeu par ligne', '2' => '2 jeux côte à côte', '3' => '3 jeux côte à côte', '4' => '4 jeux côte à côte' ) as $v => $l ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>" <?php selected( get_option( 'jrl_colonnes', 'auto' ), $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Sur ordinateur. Quand la place manque (tablette, téléphone), les jeux passent automatiquement à 2 puis à 1 par ligne. Pour une seule page : <code>[jeux_logistique colonnes="2"]</code>.</p>
				</td></tr>
				<tr><th scope="row">Sur chaque carte</th><td><fieldset>
					<label><input type="checkbox" name="jrl_aff_duree" value="1" <?php checked( '1', get_option( 'jrl_aff_duree', '1' ) ); ?>> Durée (« 10 min par série »)</label><br>
					<label><input type="checkbox" name="jrl_aff_progression" value="1" <?php checked( '1', get_option( 'jrl_aff_progression', '1' ) ); ?>> Progression de l’élève</label><br>
					<label><input type="checkbox" name="jrl_aff_objectifs" value="1" <?php checked( '1', get_option( 'jrl_aff_objectifs', '1' ) ); ?>> Bouton « Objectifs »</label>
				</fieldset></td></tr>
				<tr><th scope="row">Visiteurs sans compte</th><td><fieldset>
					<label><input type="checkbox" name="jrl_visiteurs" value="1" <?php checked( '1', get_option( 'jrl_visiteurs', '1' ) ); ?>> Les visiteurs non connectés peuvent jouer</label>
					<p class="description">Sans compte, la progression reste dans le navigateur de la personne (sur cet appareil) : rien n’est enregistré sur le site, pas de bilan, pas de classement ni de podium. Un lien « Se connecter » reste proposé. Décoché : il faut un compte pour jouer.</p>
				</fieldset></td></tr>
				<tr><th scope="row">Classement des groupes</th><td><fieldset>
					<label><input type="checkbox" name="jrl_cl_podium" value="1" <?php checked( '1', get_option( 'jrl_cl_podium', '1' ) ); ?>> Podium « Classement de la semaine » au-dessus du catalogue</label><br>
					<label><input type="checkbox" name="jrl_cl_jeu" value="1" <?php checked( '1', get_option( 'jrl_cl_jeu', '1' ) ); ?>> Bouton « Classement » dans chaque jeu</label>
					<p class="description">Chaque élève ne voit que les camarades de son ou ses groupes Sensei (Sensei LMS › Groupes), avec le prénom et l’initiale du nom. Points : XP de la semaine (depuis lundi) et XP depuis le début. Les comptes enseignants ne sont pas classés. Pour une page sans podium : <code>[jeux_logistique podium="non"]</code>.</p>
				</fieldset></td></tr>
			</tbody></table>
			<p><button class="button button-primary" type="submit">Enregistrer l’affichage</button></p>
		</form>

		<?php if ( function_exists( 'jrl_groupes_admin' ) ) { jrl_groupes_admin(); } ?>

		<h2>Un jeu</h2>
		<?php $bloques = (array) get_option( 'jrl_visiteurs_bloques', array() ); $vis_on = '0' !== get_option( 'jrl_visiteurs', '1' ); ?>
		<p style="max-width:900px">Colonne <strong>« Sans compte »</strong> : cochée, le jeu est accessible aux visiteurs non connectés ; décochée, il faut se connecter pour y jouer (le bouton devient « Se connecter pour jouer »). Par défaut, tous les jeux sont accessibles sans compte.
			<?php if ( ! $vis_on ) : ?><br><strong style="color:#b32d2e">Le réglage « Les visiteurs non connectés peuvent jouer » (plus haut) est décoché : aucun jeu n’est accessible sans compte pour le moment.</strong><?php endif; ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jrl_reglages' ); ?>
			<input type="hidden" name="action" value="jrl_reglages"><input type="hidden" name="jrl_acces_jeux" value="1">
		<p>
			<button type="button" class="button button-small" onclick="this.form.querySelectorAll('input[name=&quot;jrl_sans_compte[]&quot;]').forEach(function(c){c.checked=true})">Tout cocher</button>
			<button type="button" class="button button-small" onclick="this.form.querySelectorAll('input[name=&quot;jrl_sans_compte[]&quot;]').forEach(function(c){c.checked=false})">Tout décocher</button>
		</p>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Module</th><th>Jeu</th><th>Shortcode</th><th>Épreuves</th><th style="text-align:center">Sans compte</th></tr></thead>
			<tbody>
			<?php foreach ( $jeux as $slug => $d ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $d['module'] ); ?></strong></td>
					<td><?php echo esc_html( $d['titre'] ); ?></td>
					<td><code>[<?php echo esc_html( $d['shortcode'] ); ?>]</code><br><small><code>[jeu_revision id="<?php echo esc_html( $slug ); ?>"]</code></small></td>
					<td><?php echo esc_html( preg_match( '/(\d+) épreuves/u', (string) ( $d['description'] ?? '' ), $m ) ? $m[1] : '' ); ?></td>
					<td style="text-align:center"><label><input type="checkbox" name="jrl_sans_compte[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( ! in_array( $slug, $bloques, true ) ); ?>><span class="screen-reader-text"><?php echo esc_html( $d['module'] ); ?> accessible sans compte</span></label></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><button class="button button-primary" type="submit">Enregistrer l’accès sans compte</button></p>
		</form>

		<h2>Mises à jour</h2>
		<p>LogiPlay <strong><?php echo esc_html( JRL_VERSION ); ?></strong> est intégré à <strong>LogiOne</strong> : les jeux se mettent à jour avec LogiOne. Les progressions des élèves, les regroupements et les réglages sont conservés.</p>
	</div>
	<?php
}
