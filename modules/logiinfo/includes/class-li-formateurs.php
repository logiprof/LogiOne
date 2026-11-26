<?php
/**
 * LogiInfo – plusieurs formateurs sur le même site.
 * Chaque élève choisit son formateur dans le formulaire de remise (mémorisé). Chaque formateur reçoit ses copies :
 * dans LogiInfo (avec son compte WordPress), par e-mail avec le fichier joint, ou les deux.
 * Les administrateurs voient et corrigent tout. Un enseignant (rôle « Enseignant logisticien » ou autre) reçoit ses copies par e-mail ;
 * si l'administrateur coche « Corrige dans LogiInfo », il y corrige uniquement les copies de ses classes et de ses élèves.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LI_Formateurs {
	const OPT   = 'logiinfo_formateurs';
	const MODES = array(
		'logiinfo'      => 'Dans LogiInfo uniquement',
		'logiinfo_mail' => 'Dans LogiInfo + e-mail d\'avertissement',
		'mail'          => 'Par e-mail avec le fichier joint',
		'tout'          => 'Dans LogiInfo + e-mail avec le fichier joint',
	);

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page( 'fw-remises', 'Formateurs', 'Formateurs', LI_ADMIN_CAP, 'logiinfo-formateurs', array( __CLASS__, 'page' ) );
		}, 22 );
		add_action( 'admin_post_logiinfo_formateurs', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_logiinfo_reattribuer', array( __CLASS__, 'reassign' ) );
	}

	/* ---------------------------------------------------------------- données */
	public static function all() {
		$l = get_option( self::OPT, null );
		if ( ! is_array( $l ) || ! $l ) { // première utilisation : l'administrateur du site
			$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
			$u = $admins ? $admins[0] : null;
			$l = array( 1 => array( 'nom' => $u ? trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name : 'Formateur', 'email' => $u ? $u->user_email : get_option( 'admin_email' ), 'user_id' => $u ? $u->ID : 0, 'mode' => 'logiinfo', 'actif' => 1, 'acces' => 1 ) );
			update_option( self::OPT, $l, false );
		}
		return $l;
	}
	public static function active() { return array_filter( self::all(), function ( $f ) { return ! empty( $f['actif'] ); } ); }
	public static function get( $id ) { $l = self::all(); return $l[ (int) $id ] ?? null; }
	public static function label( $id ) { $f = self::get( $id ); return $f ? $f['nom'] : '–'; }
	/** Identifiants des formateurs liés à un compte WordPress. */
	public static function of_user( $uid ) {
		$out = array();
		foreach ( self::all() as $id => $f ) { if ( (int) $f['user_id'] === (int) $uid && $uid ) { $out[] = (int) $id; } }
		return $out;
	}
	/** Un administrateur voit tout ; un autre formateur seulement ses remises. */
	public static function sees_all() { return current_user_can( LI_ADMIN_CAP ); }
	/** Comptes proposés : administrateurs et rôles d'enseignants (« Enseignant logisticien »…). */
	public static function candidates() {
		static $out = null;
		if ( null !== $out ) { return $out; }
		$roles = array( 'administrator' );
		foreach ( wp_roles()->roles as $slug => $r ) {
			if ( preg_match( '/enseignant|formateur|teacher|prof/i', $slug . ' ' . $r['name'] ) ) { $roles[] = $slug; }
		}
		$out = array();
		foreach ( get_users( array( 'role__in' => $roles, 'orderby' => 'display_name' ) ) as $u ) {
			$lab = array();
			foreach ( (array) $u->roles as $r ) { if ( in_array( $r, $roles, true ) ) { $lab[] = translate_user_role( wp_roles()->roles[ $r ]['name'] ?? $r ); } }
			$out[ $u->ID ] = array( 'nom' => trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name, 'email' => $u->user_email, 'role' => implode( ', ', $lab ), 'admin' => in_array( 'administrator', (array) $u->roles, true ) );
		}
		return $out;
	}
	public static function scope_sql( $alias = 'r' ) {
		if ( self::sees_all() ) { return ''; }
		$ids = self::of_user( get_current_user_id() );
		return $ids ? " AND $alias.formateur_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' : ' AND 1=0';
	}
	public static function can_see( $r ) {
		return self::sees_all() || in_array( (int) ( $r['formateur_id'] ?? 0 ), self::of_user( get_current_user_id() ), true );
	}

	public static function of_student( $uid ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT formateur_id FROM ' . LIR_Core::eleves_table() . ' WHERE user_id = %d', $uid ) );
	}
	public static function set_student( $uid, $fid ) {
		global $wpdb;
		$wpdb->replace( LIR_Core::eleves_table(), array( 'user_id' => (int) $uid, 'formateur_id' => (int) $fid, 'maj' => current_time( 'mysql' ) ) );
	}

	/* ---------------------------------------------------------------- formulaire élève */
	/** Liste « Mon formateur » à insérer dans le formulaire de remise. */
	public static function select_html( $uid ) {
		$act = self::active();
		list( $g, $byclass ) = LI_Classes::route( $uid );
		if ( $byclass ) { return '<p class="fwr-prof-fixe">👤 Envoyé à <strong>' . esc_html( self::label( $byclass ) ) . '</strong> (classe ' . esc_html( LI_Classes::label( $g ) ) . ')</p>'; }
		if ( count( $act ) < 2 ) { return ''; }
		$cur = self::of_student( $uid );
		$h = '<label class="fwr-prof"><span>👤 Mon formateur / ma formatrice</span><select name="formateur" required><option value="">— Choisissez —</option>';
		foreach ( $act as $id => $f ) { $h .= '<option value="' . (int) $id . '"' . selected( $cur, $id, false ) . '>' . esc_html( $f['nom'] ) . '</option>'; }
		return $h . '</select></label>';
	}
	/** Formateur retenu pour un dépôt (0 si le choix est obligatoire et absent). */
	public static function pick( $uid, $posted ) {
		$act = self::active();
		list( , $byclass ) = LI_Classes::route( $uid );
		if ( $byclass ) { return $byclass; }
		if ( isset( $act[ (int) $posted ] ) ) { self::set_student( $uid, (int) $posted ); return (int) $posted; }
		if ( 1 === count( $act ) ) { return (int) key( $act ); }
		$cur = self::of_student( $uid );
		return isset( $act[ $cur ] ) ? $cur : 0;
	}

	/* ---------------------------------------------------------------- e-mail au formateur */
	public static function notify( $remise_id ) {
		$r = LIR_Core::get( $remise_id );
		if ( ! $r ) { return; }
		$f = self::get( $r['formateur_id'] );
		if ( ! $f || ! is_email( $f['email'] ) || 'logiinfo' === $f['mode'] ) { return; }
		$u = get_userdata( $r['user_id'] );
		$eleve = LIR_Core::user_label( $r['user_id'] );
		$lec = LIR_Core::lecon_label( $r['lecon'] );
		$L = LIR_Core::lecon( $r['lecon'] );
		$auto = (float) $r['auto_max'] ? sprintf( '%s / %s points (%d %%), note proposée %s', rtrim( rtrim( number_format( (float) $r['auto_score'], 1, '.', '' ), '0' ), '.' ), (float) $r['auto_max'], round( 100 * $r['auto_score'] / $r['auto_max'] ), LIR_Core::note_proposee( $r['auto_score'], $r['auto_max'] ) ) : 'non disponible';
		$flags = LIR_Core::trace_flags( $r );
		$attach = in_array( $f['mode'], array( 'mail', 'tout' ), true );
		$body = "Bonjour {$f['nom']},\n\n{$eleve} vient de rendre un exercice.\n\n"
			. "Leçon : {$lec}\nExercice : " . ( $L ? $L['exercice'] : '' ) . "\nVersion : {$r['version']}\nDéposé le : " . mysql2date( 'd.m.Y à H:i', $r['created_at'] ) . "\n"
			. "Contrôle automatique : {$auto}\n"
			. ( $flags ? 'À vérifier (traçabilité) : ' . implode( ' ; ', $flags ) . "\n" : '' )
			. ( 'alerte' === ( $idc = LIR_Core::identity( $r ) )['level'] ? '⚠ Auteur à vérifier : ' . implode( ' ', $idc['msgs'] ) . "\n" : '' )
			. ( $attach ? "\nLe fichier rendu est joint à ce message. Répondez directement à cet e-mail pour écrire à l'élève.\n" : '' )
			. ( $f['user_id'] && 'mail' !== $f['mode'] ? "\nCorriger dans LogiInfo : " . admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . (int) $r['id'] ) . "\n" : '' )
			. "\n— LogiInfo, " . home_url() . "\n";
		$files = array(); $tmp = array();
		if ( $attach ) {
			foreach ( $r['files'] as $k => $fl ) {
				$src = LIR_Core::abs_path( $fl['stored'] );
				if ( ! file_exists( $src ) || filesize( $src ) > 15 * MB_IN_BYTES ) { continue; }
				$dir = trailingslashit( get_temp_dir() ) . 'logiinfo-' . wp_generate_password( 8, false );
				wp_mkdir_p( $dir );
				$dest = $dir . '/' . sanitize_file_name( sprintf( '%s_%s_v%d_%s', LIR_Core::code( $r['lecon'] ), remove_accents( $eleve ), $r['version'], $fl['name'] ) );
				copy( $src, $dest ); $files[] = $dest; $tmp[] = $dir;
			}
		}
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $u && is_email( $u->user_email ) ) { $headers[] = 'Reply-To: ' . $eleve . ' <' . $u->user_email . '>'; }
		$ok = wp_mail( $f['email'], '[LogiInfo] ' . $eleve . ' – ' . LIR_Core::code( $r['lecon'] ) . ' rendu (v' . $r['version'] . ')', $body, $headers, $files );
		foreach ( $files as $p ) { @unlink( $p ); }
		foreach ( $tmp as $d ) { @rmdir( $d ); }
		LIR_Core::log( $ok ? 'email_formateur' : 'email_echec', $r['id'], $f['email'] );
	}

	/* ---------------------------------------------------------------- réattribution depuis la fiche */
	public static function reassign_html( $r ) {
		$act = self::active();
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . wp_nonce_field( 'logiinfo_reattribuer_' . $r['id'], '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="logiinfo_reattribuer"><input type="hidden" name="id" value="' . (int) $r['id'] . '"> · Formateur : <select name="formateur">';
		if ( ! isset( $act[ (int) $r['formateur_id'] ] ) ) { $h .= '<option value="0">— non attribuée —</option>'; }
		foreach ( $act as $id => $f ) { $h .= '<option value="' . (int) $id . '"' . selected( (int) $r['formateur_id'], $id, false ) . '>' . esc_html( $f['nom'] ) . '</option>'; }
		return $h . '</select> <button class="button button-small">Transmettre</button></form>';
	}
	public static function reassign() {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'logiinfo_reattribuer_' . $id );
		$r = LIR_Core::get( $id );
		if ( ! $r || ! current_user_can( LIR_CAP ) || ! self::can_see( $r ) ) { wp_die( 'Accès refusé.' ); }
		$fid = (int) ( $_POST['formateur'] ?? 0 );
		if ( ! isset( self::active()[ $fid ] ) ) { wp_die( 'Formateur inconnu.' ); }
		global $wpdb;
		$wpdb->update( LIR_Core::table(), array( 'formateur_id' => $fid ), array( 'id' => $id ) );
		self::set_student( $r['user_id'], $fid );
		LIR_Core::log( 'reattribution', $id, self::label( $fid ) );
		self::notify( $id );
		$back = self::can_see( LIR_Core::get( $id ) ) ? admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . $id ) : admin_url( 'admin.php?page=fw-remises' );
		wp_safe_redirect( add_query_arg( 'fwr_msg', rawurlencode( 'Remise transmise à ' . self::label( $fid ) . ' (l\'élève lui est désormais rattaché·e).' ), $back ) );
		exit;
	}

	/* ---------------------------------------------------------------- page « Formateurs » */
	public static function page() {
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { return; }
		global $wpdb;
		$l = self::all();
		$counts = array();
		foreach ( $wpdb->get_results( 'SELECT formateur_id f, COUNT(*) n FROM ' . LIR_Core::table() . " WHERE statut <> 'corbeille' GROUP BY formateur_id", ARRAY_A ) as $c ) { $counts[ (int) $c['f'] ] = (int) $c['n']; }
		$ne = array();
		foreach ( $wpdb->get_results( 'SELECT formateur_id f, COUNT(*) n FROM ' . LIR_Core::eleves_table() . ' GROUP BY formateur_id', ARRAY_A ) as $c ) { $ne[ (int) $c['f'] ] = (int) $c['n']; }
		$users = self::candidates();
		echo '<div class="wrap fwr-wrap"><h1>LogiInfo – Formateurs</h1>';
		if ( isset( $_GET['li_msg'] ) ) { echo '<div class="notice notice-success"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['li_msg'] ) ) ) ) . '</p></div>'; } // phpcs:ignore
		echo '<p>Quand il y a plusieurs formateurs actifs, l\'élève choisit le sien dans le formulaire « Rendre mon exercice » (son choix est mémorisé). '
			. 'Choisissez le compte d\'un administrateur ou d\'un enseignant (rôle « Enseignant logisticien »…) : son nom et son e-mail se remplissent tout seuls. '
			. 'Par défaut, un enseignant reçoit les copies de ses élèves <b>par e-mail, avec le fichier joint</b> (en répondant, il écrit à l\'élève) et n\'a <b>aucun accès à LogiInfo</b>. '
			. 'Si vous cochez « Corrige dans LogiInfo », il y voit et corrige <b>uniquement les copies de ses classes et de ses élèves</b> ; les pages Formateurs, Données, Cours et Réglages restent réservées aux administrateurs.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="logiinfo_formateurs">';
		wp_nonce_field( 'logiinfo_formateurs' );
		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr><th>Actif</th><th>Nom affiché aux élèves</th><th>E-mail</th><th>Compte WordPress</th><th>Corrige dans LogiInfo</th><th>Réception des exercices</th><th>Élèves / remises</th></tr></thead><tbody>';
		$rows = $l; $rows['new'] = array( 'nom' => '', 'email' => '', 'user_id' => 0, 'mode' => 'mail', 'actif' => 1, 'acces' => 0 );
		foreach ( $rows as $id => $f ) {
			$n = 'f[' . $id . ']';
			echo '<tr><td><input type="checkbox" name="' . esc_attr( $n ) . '[actif]" value="1"' . checked( ! empty( $f['actif'] ), true, false ) . '></td>'
				. '<td><input type="text" name="' . esc_attr( $n ) . '[nom]" value="' . esc_attr( $f['nom'] ) . '" placeholder="' . ( 'new' === $id ? 'Ajouter un formateur…' : '' ) . '" class="regular-text" style="width:200px"></td>'
				. '<td><input type="email" name="' . esc_attr( $n ) . '[email]" value="' . esc_attr( $f['email'] ) . '" style="width:230px"></td><td><select name="' . esc_attr( $n ) . '[user_id]"><option value="0">— aucun (e-mail seulement) —</option>';
			foreach ( $users as $uid => $u ) { echo '<option value="' . (int) $uid . '" data-nom="' . esc_attr( $u['nom'] ) . '" data-email="' . esc_attr( $u['email'] ) . '"' . selected( (int) $f['user_id'], (int) $uid, false ) . '>' . esc_html( $u['nom'] . ' – ' . $u['role'] ) . '</option>'; }
			$isadm = ! empty( $users[ (int) $f['user_id'] ]['admin'] );
			echo '</select></td><td style="text-align:center">' . ( $isadm ? '✅ <span class="description">administrateur</span><input type="hidden" name="' . esc_attr( $n ) . '[acces]" value="1">' : '<input type="checkbox" name="' . esc_attr( $n ) . '[acces]" value="1"' . checked( ! empty( $f['acces'] ), true, false ) . '>' ) . '</td><td><select name="' . esc_attr( $n ) . '[mode]">';
			foreach ( self::MODES as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['mode'], $k, false ) . '>' . esc_html( $v ) . '</option>'; }
			echo '</select>' . ( empty( $f['acces'] ) && ! $isadm ? '<br><span class="description">Sans accès à LogiInfo : toujours par e-mail avec le fichier joint.</span>' : '' ) . '</td><td>' . ( 'new' === $id ? '' : (int) ( $ne[ $id ] ?? 0 ) . ' élève(s) · ' . (int) ( $counts[ $id ] ?? 0 ) . ' remise(s)' ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">Un formateur ne peut pas être supprimé (ses remises restent rattachées à lui) : décochez « Actif » pour qu\'il n\'apparaisse plus dans la liste des élèves. '
			. 'Les remises non attribuées (déposées avant l\'ajout des formateurs) restent visibles par les administrateurs.</p>';
		submit_button( 'Enregistrer les formateurs' );
		echo '</form><script>document.querySelectorAll(\'select[name$="[user_id]"]\').forEach(function(s){s.addEventListener("change",function(){var o=s.options[s.selectedIndex],tr=s.closest("tr");if(!o.dataset.nom){return;}var n=tr.querySelector(\'input[name$="[nom]"]\'),e=tr.querySelector(\'input[name$="[email]"]\');if(!n.value){n.value=o.dataset.nom;}e.value=o.dataset.email;});});</script>';
		LI_Classes::section();
		echo '</div>';
	}

	public static function save() {
		check_admin_referer( 'logiinfo_formateurs' );
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { wp_die( 'Accès refusé.' ); }
		$l = self::all();
		$in = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array(); // phpcs:ignore
		foreach ( $in as $id => $f ) {
			$row = array(
				'nom'     => sanitize_text_field( $f['nom'] ?? '' ),
				'email'   => sanitize_email( $f['email'] ?? '' ),
				'user_id' => (int) ( $f['user_id'] ?? 0 ),
				'mode'    => isset( self::MODES[ $f['mode'] ?? '' ] ) ? $f['mode'] : 'logiinfo_mail',
				'actif'   => empty( $f['actif'] ) ? 0 : 1,
				'acces'   => empty( $f['acces'] ) ? 0 : 1,
			);
			$cand = self::candidates();
			if ( $row['user_id'] && ! isset( $cand[ $row['user_id'] ] ) ) { $row['user_id'] = 0; }
			if ( $row['user_id'] && $cand[ $row['user_id'] ]['admin'] ) { $row['acces'] = 1; }
			if ( ! $row['user_id'] ) { $row['acces'] = 0; }
			if ( '' === $row['email'] && $row['user_id'] ) { $row['email'] = $cand[ $row['user_id'] ]['email']; }
			if ( ! $row['acces'] ) { $row['mode'] = 'mail'; } // sans accès à LogiInfo : par e-mail avec le fichier joint
			if ( 'new' === $id ) {
				if ( '' === $row['nom'] ) { continue; }
				$id = $l ? max( array_keys( $l ) ) + 1 : 1;
			}
			$id = (int) $id;
			if ( '' === $row['nom'] && isset( $l[ $id ] ) ) { $row['nom'] = $l[ $id ]['nom']; }
			$l[ $id ] = $row;
		}
		update_option( self::OPT, $l, false );
		LIR_Core::caps(); // droits de correction mis à jour
		wp_safe_redirect( add_query_arg( array( 'page' => 'logiinfo-formateurs', 'li_msg' => rawurlencode( 'Formateurs enregistrés.' ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
LI_Formateurs::init();
