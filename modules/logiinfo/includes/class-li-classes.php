<?php
/**
 * LogiInfo – classes (groupes Sensei Pro) : chaque classe est rattachée à un formateur.
 * Quand un élève d'une classe rend un exercice, la copie part directement chez le formateur de sa classe :
 * dans LogiInfo pour les classes d'un administrateur, par e-mail (fichier joint) pour les classes d'un collègue.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LI_Classes {
	const OPT = 'logiinfo_classes'; // [ group_id => formateur_id ]

	public static function init() {
		add_action( 'admin_post_logiinfo_classes', array( __CLASS__, 'save' ) );
	}

	/**
	 * Membres des groupes Sensei Pro : même source que LogiProgress et LogiEval (extension LogiOne),
	 * table {préfixe}sensei_pro_groups_students (group_id, student_id), avec en secours les métas
	 * utilisateur « sensei_group_ids » / « sensei_group_id ».
	 */
	private static function members_table() {
		static $t = null;
		if ( null !== $t ) { return $t; }
		global $wpdb;
		$name = $wpdb->prefix . 'sensei_pro_groups_students';
		$t = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name ? $name : false;
		return $t;
	}
	public static function available() { return (bool) self::members_table(); }

	/** Groupes Sensei publiés (comme LogiProgress) : [ id => titre ]. */
	public static function groups() {
		static $g = null;
		if ( null !== $g ) { return $g; }
		$g = array();
		foreach ( get_posts( array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$g[ $p->ID ] = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
		}
		return $g;
	}
	public static function members_count() {
		global $wpdb;
		$out = array();
		$t = self::members_table();
		if ( $t ) {
			foreach ( $wpdb->get_results( "SELECT group_id g, COUNT(DISTINCT student_id) n FROM {$t} GROUP BY group_id" ) as $r ) { $out[ (int) $r->g ] = (int) $r->n; } // phpcs:ignore
			return $out;
		}
		foreach ( array_keys( self::groups() ) as $gid ) { $out[ $gid ] = count( self::meta_members( $gid ) ); }
		return $out;
	}
	private static function meta_members( $gid ) {
		return get_users( array( 'fields' => 'ID', 'number' => 500, 'meta_query' => array( 'relation' => 'OR',
			array( 'key' => 'sensei_group_ids', 'value' => '"' . (int) $gid . '"', 'compare' => 'LIKE' ),
			array( 'key' => 'sensei_group_id', 'value' => (int) $gid, 'compare' => '=' ) ) ) );
	}
	/** Groupes (publiés) d'un élève, du plus récent au plus ancien. */
	public static function of_user( $uid ) {
		$uid = absint( $uid );
		if ( ! $uid ) { return array(); }
		global $wpdb;
		$t = self::members_table();
		if ( $t ) {
			$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT group_id FROM {$t} WHERE student_id = %d", $uid ) ) ); // phpcs:ignore
		} else {
			$ids = array_map( 'absint', array_merge( (array) maybe_unserialize( get_user_meta( $uid, 'sensei_group_ids', true ) ), array( get_user_meta( $uid, 'sensei_group_id', true ) ) ) );
		}
		$ids = array_values( array_intersect( array_unique( array_filter( $ids ) ), array_keys( self::groups() ) ) );
		rsort( $ids );
		return $ids;
	}
	public static function map() { return array_map( 'intval', (array) get_option( self::OPT, array() ) ); }

	/** [ group_id, formateur_id ] de la classe de l'élève qui est rattachée à un formateur actif, sinon [ 0, 0 ]. */
	public static function route( $uid ) {
		$map = self::map(); $act = LI_Formateurs::active();
		foreach ( self::of_user( $uid ) as $g ) {
			if ( ! empty( $map[ $g ] ) && isset( $act[ $map[ $g ] ] ) ) { return array( $g, $map[ $g ] ); }
		}
		$groups = self::of_user( $uid );
		return array( $groups ? $groups[0] : 0, 0 );
	}
	public static function label( $gid ) { return $gid ? html_entity_decode( get_the_title( $gid ), ENT_QUOTES, 'UTF-8' ) : ''; }

	/* ---------------------------------------------------------------- section de la page Formateurs */
	public static function section() {
		$groups = self::groups();
		echo '<h2 style="margin-top:32px">🏫 Classes (groupes Sensei)</h2>';
		if ( ! $groups ) { echo '<p class="description">Aucun groupe Sensei trouvé sur ce site.</p>'; return; }
		if ( ! self::available() ) { echo '<div class="notice notice-warning inline"><p>La table des membres des groupes Sensei Pro (<code>sensei_pro_groups_students</code>, celle qu\'utilise LogiProgress) est introuvable : LogiInfo lit alors les membres enregistrés dans les profils. Vérifiez que Sensei Pro est actif.</p></div>'; }
		$map = self::map(); $cnt = self::members_count(); $all = LI_Formateurs::all();
		echo '<p>Rattachez chaque classe à son formateur. Un élève d\'une classe rattachée n\'a rien à choisir : sa copie part directement chez le formateur de sa classe '
			. '(dans LogiInfo pour vos classes, par e-mail avec le fichier joint pour celles d\'un collègue). Pour les élèves sans classe rattachée, la liste « Mon formateur » reste affichée.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="logiinfo_classes">';
		wp_nonce_field( 'logiinfo_classes' );
		echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>Classe</th><th>Élèves</th><th>Formateur</th><th>Réception</th></tr></thead><tbody>';
		foreach ( $groups as $gid => $title ) {
			$cur = (int) ( $map[ $gid ] ?? 0 );
			echo '<tr><td><strong>' . esc_html( $title ) . '</strong></td><td>' . (int) ( $cnt[ $gid ] ?? 0 ) . '</td><td><select name="c[' . (int) $gid . ']"><option value="0">— l\'élève choisit —</option>';
			foreach ( $all as $fid => $f ) { if ( ! empty( $f['actif'] ) || $fid === $cur ) { echo '<option value="' . (int) $fid . '"' . selected( $cur, $fid, false ) . '>' . esc_html( $f['nom'] ) . '</option>'; } }
			$f = $cur ? ( $all[ $cur ] ?? null ) : null;
			echo '</select></td><td>' . ( $f ? esc_html( in_array( $f['mode'], array( 'logiinfo', 'logiinfo_mail', 'tout' ), true ) && $f['user_id'] ? '📥 dans LogiInfo' : '✉️ ' . $f['email'] ) : '–' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button( 'Enregistrer les classes' );
		echo '</form>';
	}

	public static function save() {
		check_admin_referer( 'logiinfo_classes' );
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { wp_die( 'Accès refusé.' ); }
		$in = isset( $_POST['c'] ) && is_array( $_POST['c'] ) ? wp_unslash( $_POST['c'] ) : array(); // phpcs:ignore
		$map = array();
		foreach ( $in as $g => $f ) { if ( (int) $f && 'group' === get_post_type( (int) $g ) ) { $map[ (int) $g ] = (int) $f; } }
		update_option( self::OPT, $map, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'logiinfo-formateurs', 'li_msg' => rawurlencode( 'Classes enregistrées : ' . count( $map ) . ' classe(s) rattachée(s).' ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
LI_Classes::init();
