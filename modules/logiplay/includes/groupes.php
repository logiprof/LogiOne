<?php
/*
 * Regroupements de modules (menu LogiPlay > Regroupements).
 * Un regroupement = un nom (ex. « Modules 1re année ») + une liste de jeux. Il donne deux shortcodes :
 *   [jeux_logistique groupe="modules-1re-annee"]   et   [jeux_modules_1re_annee]
 * Les attributs langue="de" et colonnes="…" fonctionnent comme pour le catalogue.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Tous les regroupements : array( slug => array( 'nom', 'jeux' => array( slug de jeu… ), 'colonnes' ) ), dans l'ordre de création. */
function jrl_groupes() {
	$g = get_option( 'jrl_groupes', array() );
	return is_array( $g ) ? $g : array();
}
function jrl_groupe( $slug ) {
	$g = jrl_groupes(); $slug = sanitize_title( (string) $slug );
	return isset( $g[ $slug ] ) ? $g[ $slug ] : null;
}
/** Shortcode dédié d'un regroupement : jeux_<slug avec des _>. */
function jrl_groupe_tag( $slug ) { return 'jeux_' . str_replace( '-', '_', sanitize_title( $slug ) ); }

/* Shortcodes dédiés (après ceux des jeux ; un nom déjà pris n'est pas remplacé) */
add_action( 'init', function () {
	foreach ( jrl_groupes() as $slug => $g ) {
		$tag = jrl_groupe_tag( $slug );
		if ( shortcode_exists( $tag ) ) { continue; }
		add_shortcode( $tag, function ( $atts = array() ) use ( $slug ) {
			$atts = is_array( $atts ) ? $atts : array();
			$atts['groupe'] = $slug;
			return jrl_shortcode_catalogue( $atts );
		} );
	}
}, 21 );

/* Enregistrer / supprimer un regroupement */
add_action( 'admin_post_jrl_groupe', function () {
	if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Accès refusé.' ); }
	check_admin_referer( 'jrl_groupe' );
	$g = jrl_groupes();
	$ancien = isset( $_POST['ancien'] ) ? sanitize_title( wp_unslash( $_POST['ancien'] ) ) : '';
	if ( ! empty( $_POST['supprimer'] ) ) {
		unset( $g[ $ancien ] ); update_option( 'jrl_groupes', $g, false );
		wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique&jrl_ok=groupe_supprime#jrl-groupes' ) ); exit;
	}
	$nom = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';
	$tous = jrl_jeux();
	$jeux = array();
	foreach ( (array) ( $_POST['jeux'] ?? array() ) as $j ) { $j = sanitize_key( wp_unslash( $j ) ); if ( isset( $tous[ $j ] ) ) { $jeux[] = $j; } }
	$jeux = array_values( array_intersect( array_keys( $tous ), $jeux ) ); /* ordre du catalogue */
	if ( '' === $nom || ! $jeux ) {
		wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique&jrl_ok=groupe_vide' . ( $ancien ? '&groupe=' . rawurlencode( $ancien ) : '' ) . '#jrl-groupes' ) ); exit;
	}
	$slug = sanitize_title( $nom );
	if ( '' === $slug ) { $slug = 'groupe-' . ( count( $g ) + 1 ); }
	$base = $slug; $n = 2;
	while ( isset( $g[ $slug ] ) && $slug !== $ancien ) { $slug = $base . '-' . $n++; }
	$col = isset( $_POST['colonnes'] ) ? sanitize_key( wp_unslash( $_POST['colonnes'] ) ) : '';
	$val = array( 'nom' => $nom, 'jeux' => $jeux, 'colonnes' => in_array( $col, array( '1', '2', '3', '4' ), true ) ? $col : '' );
	if ( $ancien && isset( $g[ $ancien ] ) && $ancien !== $slug ) {
		/* renommé : on garde la place dans la liste */
		$nv = array();
		foreach ( $g as $k => $v ) { $nv[ $k === $ancien ? $slug : $k ] = $k === $ancien ? $val : $v; }
		$g = $nv;
	} else {
		$g[ $slug ] = $val;
	}
	update_option( 'jrl_groupes', $g, false );
	wp_safe_redirect( admin_url( 'admin.php?page=jeux-logistique&jrl_ok=groupe_ok#jrl-groupes' ) ); exit;
} );

/** Section « Regroupements » de la page de réglages. */
function jrl_groupes_admin() {
	$jeux = jrl_jeux();
	$groupes = jrl_groupes();
	$edit_slug = isset( $_GET['groupe'] ) ? sanitize_title( wp_unslash( $_GET['groupe'] ) ) : '';
	$edit = $edit_slug && isset( $groupes[ $edit_slug ] ) ? $groupes[ $edit_slug ] : null;
	$coches = $edit ? (array) $edit['jeux'] : array();
	$series = array();
	foreach ( $jeux as $slug => $d ) { $series[ substr( strtoupper( $d['module'] ), 0, 2 ) ][ $slug ] = $d; }
	?>
	<h2 id="jrl-groupes">Regroupements de modules</h2>
	<p>Rassemblez plusieurs modules sous un nom (par exemple « Modules 1re année ») : chaque regroupement reçoit son propre shortcode.</p>
	<?php if ( $groupes ) : ?>
	<table class="widefat striped" style="max-width:900px">
		<thead><tr><th>Regroupement</th><th>Shortcodes</th><th>Jeux</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $groupes as $slug => $g ) :
			$mods = array();
			foreach ( (array) $g['jeux'] as $j ) { if ( isset( $jeux[ $j ] ) ) { $mods[] = $jeux[ $j ]['module']; } }
			$tag = jrl_groupe_tag( $slug ); ?>
			<tr>
				<td><strong><?php echo esc_html( $g['nom'] ); ?></strong><?php echo ! empty( $g['colonnes'] ) ? '<br><small>' . esc_html( $g['colonnes'] ) . ' jeux côte à côte</small>' : ''; ?></td>
				<td><code>[<?php echo esc_html( $tag ); ?>]</code><br><small><code>[jeux_logistique groupe="<?php echo esc_html( $slug ); ?>"]</code></small></td>
				<td><?php echo (int) count( $mods ); ?> : <small><?php echo esc_html( implode( ', ', $mods ) ); ?></small></td>
				<td><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=jeux-logistique&groupe=' . rawurlencode( $slug ) . '#jrl-groupes' ) ); ?>">Modifier</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<h3><?php echo $edit ? 'Modifier « ' . esc_html( $edit['nom'] ) . ' »' : 'Nouveau regroupement'; ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jrl-gform">
		<?php wp_nonce_field( 'jrl_groupe' ); ?>
		<input type="hidden" name="action" value="jrl_groupe">
		<input type="hidden" name="ancien" value="<?php echo esc_attr( $edit ? $edit_slug : '' ); ?>">
		<table class="form-table" role="presentation" style="max-width:900px"><tbody>
			<tr><th scope="row"><label for="jrl_gnom">Nom</label></th><td><input type="text" class="regular-text" id="jrl_gnom" name="nom" value="<?php echo esc_attr( $edit ? $edit['nom'] : '' ); ?>" placeholder="Modules 1re année" required>
				<p class="description">Le shortcode est formé à partir du nom : « Modules 1re année » donne <code>[jeux_modules_1re_annee]</code>.</p></td></tr>
			<tr><th scope="row"><label for="jrl_gcol">Jeux côte à côte</label></th><td><select id="jrl_gcol" name="colonnes">
				<?php foreach ( array( '' => 'Comme le réglage général', '1' => '1', '2' => '2', '3' => '3', '4' => '4' ) as $v => $l ) : ?>
				<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $edit ? (string) $edit['colonnes'] : '', $v ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?></select></td></tr>
			<tr><th scope="row">Modules</th><td>
				<p><span class="jrl-gcount"><?php echo (int) count( $coches ); ?></span> module(s) sélectionné(s).</p>
				<?php foreach ( $series as $serie => $liste ) : ?>
				<fieldset class="jrl-gserie" style="margin:0 0 14px;padding:10px 12px;border:1px solid #dcdcde;border-radius:6px;background:#fff">
					<legend style="padding:0 6px"><strong>Série <?php echo esc_html( $serie ); ?>00</strong> · <label><input type="checkbox" class="jrl-gtout"> tout cocher</label></legend>
					<?php foreach ( $liste as $slug => $d ) : ?>
					<label style="display:block;margin:3px 0"><input type="checkbox" name="jeux[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $coches, true ) ); ?>> <?php echo esc_html( $d['titre'] ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<?php endforeach; ?>
			</td></tr>
		</tbody></table>
		<p>
			<button class="button button-primary" type="submit"><?php echo $edit ? 'Enregistrer le regroupement' : 'Créer le regroupement'; ?></button>
			<?php if ( $edit ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=jeux-logistique#jrl-groupes' ) ); ?>">Annuler</a>
				<button class="button button-link-delete" type="submit" name="supprimer" value="1" onclick="return confirm('Supprimer ce regroupement ? Les pages qui utilisent son shortcode n’afficheront plus rien.');">Supprimer</button>
			<?php endif; ?>
		</p>
	</form>
	<script>
	(function () {
		var f = document.querySelector('.jrl-gform'); if (!f) return;
		function compte() { f.querySelector('.jrl-gcount').textContent = f.querySelectorAll('input[name="jeux[]"]:checked').length; }
		f.querySelectorAll('.jrl-gserie').forEach(function (s) {
			var tout = s.querySelector('.jrl-gtout'), cases = s.querySelectorAll('input[name="jeux[]"]');
			function maj() { var n = s.querySelectorAll('input[name="jeux[]"]:checked').length; tout.checked = n === cases.length; tout.indeterminate = n > 0 && n < cases.length; }
			tout.addEventListener('change', function () { cases.forEach(function (c) { c.checked = tout.checked; }); compte(); });
			cases.forEach(function (c) { c.addEventListener('change', function () { maj(); compte(); }); });
			maj();
		});
	})();
	</script>
	<?php
}
