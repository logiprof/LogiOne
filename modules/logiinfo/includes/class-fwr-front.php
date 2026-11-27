<?php
/** Côté apprenti : formulaire de dépôt [fw_remise lecon="3"] et tableau [fw_mes_remises]. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Front {

	public static function init() {
		add_shortcode( 'fw_remise', array( __CLASS__, 'sc_remise' ) );
		add_shortcode( 'fw_mes_remises', array( __CLASS__, 'sc_mes_remises' ) );
		add_action( 'admin_post_fwr_depot', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_fwr_depot', array( __CLASS__, 'handle_upload' ) );
		add_action( 'wp_enqueue_scripts', function () { wp_register_style( 'fwr', LIR_URL . 'assets/fwr.css', array(), LIR_VERSION ); } );
	}

	/* ---------------------------------------------------------------- dépôt */
	public static function handle_upload() {
		$lecon = isset( $_POST['lecon'] ) ? (int) $_POST['lecon'] : 0;
		$back  = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : home_url();
		$go = function ( $code, $msg = '' ) use ( $back ) {
			wp_safe_redirect( add_query_arg( array( 'fwr' => $code, 'fwr_msg' => rawurlencode( $msg ) ), $back ) . '#fwr-depot' );
			exit;
		};
		if ( ! is_user_logged_in() ) { $go( 'err', 'Vous devez être connecté·e pour déposer un exercice.' ); }
		if ( ! isset( $_POST['_fwr'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_fwr'] ) ), 'fwr_depot_' . $lecon ) ) { $go( 'err', 'Session expirée, rechargez la page et réessayez.' ); }
		$L = LIR_Core::lecon( $lecon );
		if ( ! $L ) { $go( 'err', 'Leçon inconnue.' ); }
		$allowed = (array) $L['types'];
		$soft = array( 'xlsx' => 'un classeur Excel (.xlsx)', 'docx' => 'un document Word (.docx)', 'pptx' => 'une présentation PowerPoint (.pptx)' )[ $L['type'] ] ?? 'un fichier accepté (.' . implode( ', .', $allowed ) . ')';
		if ( empty( $_FILES['fwr_files'] ) || empty( $_FILES['fwr_files']['name'][0] ) ) { $go( 'err', 'Choisissez au moins un fichier (.' . implode( ', .', $allowed ) . ').' ); }

		$s = LIR_Core::settings();
		$max = (int) $s['max_mo'] * 1024 * 1024;
		$F = $_FILES['fwr_files']; // phpcs:ignore
		$n = count( $F['name'] );
		if ( $n > max( 1, (int) $L['fichiers'] ) + ( 'libre' === $L['type'] ? 0 : 1 ) ) { $go( 'err', sprintf( 'Vous pouvez déposer au maximum %d fichiers pour cet exercice.', (int) $L['fichiers'] + 1 ) ); }
		$uid = get_current_user_id();
		$prof = LI_Formateurs::pick( $uid, (int) ( $_POST['formateur'] ?? 0 ) );
		if ( ! $prof && count( LI_Formateurs::active() ) > 1 ) { $go( 'err', 'Choisissez votre formateur ou votre formatrice dans la liste.' ); }
		$ver = LIR_Core::next_version( $uid, $lecon );
		$sub = LIR_Core::code( $lecon );
		$dir = LIR_Core::storage_dir() . '/' . $uid . '/' . $sub;
		wp_mkdir_p( $dir );
		$files = array();
		for ( $k = 0; $k < $n; $k++ ) {
			$name = sanitize_file_name( wp_unslash( $F['name'][ $k ] ) );
			if ( UPLOAD_ERR_OK !== $F['error'][ $k ] ) { $go( 'err', 'Le fichier « ' . $name . ' » n\'a pas pu être envoyé (taille trop grande ?).' ); }
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed, true ) ) { $go( 'err', '« ' . $name . ' » n\'est pas ' . $soft . '.' ); }
			if ( $F['size'][ $k ] > $max ) { $go( 'err', '« ' . $name . ' » dépasse ' . (int) $s['max_mo'] . ' Mo.' ); }
			if ( ! LI_Exercices::valid_file( $F['tmp_name'][ $k ], $ext ) ) { $go( 'err', '« ' . $name . ' » est endommagé ou ne correspond pas à son extension.' ); }
			$stored_rel = $uid . '/' . $sub . '/v' . $ver . '_' . wp_generate_password( 24, false ) . '.' . $ext;
			if ( ! move_uploaded_file( $F['tmp_name'][ $k ], LIR_Core::abs_path( $stored_rel ) ) ) { $go( 'err', 'Enregistrement impossible sur le serveur.' ); }
			$files[] = array( 'name' => $name, 'stored' => $stored_rel, 'size' => (int) $F['size'][ $k ], 'sha1' => (string) @sha1_file( LIR_Core::abs_path( $stored_rel ) ) );
		}
		$rep = LIR_Core::analyse( $lecon, $files, $uid );
		global $wpdb;
		$wpdb->insert( LIR_Core::table(), array(
			'user_id' => $uid, 'lecon' => $lecon, 'version' => $ver, 'files' => wp_json_encode( $files ), 'created_at' => current_time( 'mysql' ),
			'auto_score' => $rep['score'], 'auto_max' => $rep['max'], 'auto_report' => wp_json_encode( $rep ), 'statut' => 'depose',
			'detail' => (int) $s['detail_defaut'], 'formateur_id' => $prof, 'groupe_id' => LI_Classes::route( $uid )[0],
		) );
		$rid = (int) $wpdb->insert_id;
		LIR_Core::log( 'depot', $rid, LIR_Core::code( $lecon ) . ' v' . $ver );
		LI_Formateurs::notify( $rid );
		do_action( 'fwr_remise_deposee', $rid, $uid, $lecon );
		$go( 'ok', $s['message'] );
	}

	/* ---------------------------------------------------------------- [fw_remise lecon="3"] */
	public static function sc_remise( $atts ) {
		$a = shortcode_atts( array( 'lecon' => 0, 'formation' => 'word', 'cle' => 0 ), $atts );
		$lecon = $a['cle'] ? (int) $a['cle'] : LIR_Core::key( $a['lecon'], sanitize_key( $a['formation'] ) );
		$L = LIR_Core::lecon( $lecon );
		if ( ! $L ) { return '<p><em>[fw_remise] : numéro de leçon manquant ou inconnu.</em></p>'; }
		wp_enqueue_style( 'fwr' );
		ob_start();
		echo '<div class="fwr-box" id="fwr-depot"><div class="fwr-band"><span class="fwr-band-k">📤 Exercice terminé ?</span><h3 class="fwr-h">Rendre mon exercice</h3><span class="fwr-band-t">' . esc_html( $L['exercice'] ) . '</span></div><div class="fwr-in">';
		if ( isset( $_GET['fwr'] ) ) { // phpcs:ignore
			$ok = 'ok' === $_GET['fwr']; // phpcs:ignore
			$msg = isset( $_GET['fwr_msg'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['fwr_msg'] ) ) ) : ''; // phpcs:ignore
			echo '<div class="fwr-alert ' . ( $ok ? 'ok' : 'err' ) . '">' . esc_html( $msg ) . '</div>';
		}
		if ( ! is_user_logged_in() ) {
			echo '<p>Connectez-vous pour déposer votre fichier. <a class="fwr-btn" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Se connecter</a></p></div></div>';
			return ob_get_clean();
		}
		$nb = (int) $L['fichiers'];
		$xl = 'xlsx' === $L['type'];
		$pp = 'pptx' === $L['type'];
		$libre = 'libre' === $L['type'];
		$exts = '.' . implode( ', .', (array) $L['types'] );
		if ( $libre ) {
			$hint = ( $L['consignes'] ? $L['consignes'] . ' ' : '' ) . ( $nb > 1 ? sprintf( 'Déposez jusqu\'à %d fichiers en une seule fois (%s).', $nb, $exts ) : 'Déposez votre fichier (' . $exts . ').' );
		} else {
		$hint = $pp ? 'Déposez votre présentation .pptx (le fichier PowerPoint enregistré à la fin de l\'exercice).' : ( $nb > 1 ? sprintf( 'Déposez vos %d fichiers .docx en une seule fois (sélection multiple avec Ctrl).', $nb ) : ( $xl ? 'Déposez votre classeur .xlsx (le fichier Excel enregistré à la fin de l\'exercice).' : 'Déposez votre fichier .docx (le document Word enregistré à la fin de l\'exercice).' ) );
		}
		$back = remove_query_arg( array( 'fwr', 'fwr_msg' ), ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
		echo '<form class="fwr-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="fwr_depot"><input type="hidden" name="lecon" value="' . esc_attr( $lecon ) . '"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
		wp_nonce_field( 'fwr_depot_' . $lecon, '_fwr' );
		echo '<p class="fwr-hint">' . esc_html( $hint ) . '</p>';
		$pick = 'var n=[].map.call(this.files,function(f){return f.name}).join(\', \');this.parentNode.querySelector(\'.fwr-drop-t\').textContent=n||this.parentNode.getAttribute(\'data-empty\');this.parentNode.classList.toggle(\'has\',!!n)';
		$empty = $libre ? ( $nb > 1 ? 'Cliquez ici pour choisir vos fichiers' : 'Cliquez ici pour choisir votre fichier' ) . ' (' . $exts . ')'
			: ( $pp ? 'Cliquez ici pour choisir votre fichier .pptx' : ( $nb > 1 ? 'Cliquez ici pour choisir vos fichiers .docx' : ( $xl ? 'Cliquez ici pour choisir votre fichier .xlsx' : 'Cliquez ici pour choisir votre fichier .docx' ) ) );
		echo '<label class="fwr-drop" data-empty="' . esc_attr( $empty ) . '"><span class="fwr-drop-i">' . ( $xl ? '📊' : ( $pp ? '📙' : ( $libre ? '📎' : '📄' ) ) ) . '</span><span class="fwr-drop-t">' . esc_html( $empty ) . '</span>';
		echo $libre ? '<input type="file" name="fwr_files[]" accept="' . esc_attr( '.' . implode( ',.', (array) $L['types'] ) ) . '"' . ( $nb > 1 ? ' multiple' : '' ) . ' required onchange="' . esc_attr( $pick ) . '">'
			: ( $pp ? '<input type="file" name="fwr_files[]" accept=".pptx,application/vnd.openxmlformats-officedocument.presentationml.presentation" required onchange="' . esc_attr( $pick ) . '">'
			: ( $xl ? '<input type="file" name="fwr_files[]" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onchange="' . esc_attr( $pick ) . '">'
			: '<input type="file" name="fwr_files[]" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple required onchange="' . esc_attr( $pick ) . '">' ) );
		echo '</label>' . LI_Formateurs::select_html( get_current_user_id() ) . '<button type="submit" class="fwr-btn fwr-btn-big">📤 Envoyer à mon formateur</button></form>';

		$rows = LIR_Core::for_user( get_current_user_id(), $lecon );
		if ( $rows ) {
			echo '<h4 class="fwr-h4">Mes dépôts</h4><table class="fwr-table"><thead><tr><th>Version</th><th>Date</th><th>Fichier(s)</th><th>Statut</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$links = array();
				foreach ( $r['files'] as $k => $f ) { $links[] = '<a href="' . esc_url( LIR_Core::download_url( $r['id'], $k ) ) . '">' . esc_html( $f['name'] ) . '</a>'; }
				$st = self::status_tag( $r );
				echo '<tr><td>v' . (int) $r['version'] . '</td><td>' . esc_html( mysql2date( 'd.m.Y H:i', $r['created_at'] ) ) . '</td><td>' . implode( '<br>', $links ) . '</td><td>' . $st . '</td></tr>'; // phpcs:ignore
				if ( 'publie' === $r['statut'] ) {
					echo '<tr class="fwr-fb"><td colspan="4">' . self::feedback_html( $r ) . '</td></tr>'; // phpcs:ignore
				}
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/** Étiquette de statut vue par l'apprenti·e (la décision n'apparaît qu'une fois le retour publié). */
	public static function status_tag( $r ) {
		if ( 'publie' !== $r['statut'] ) { return '<span class="fwr-tag">Déposé</span>'; }
		if ( 'valide' === $r['validation'] ) { return '<span class="fwr-tag ok">✅ Validé</span>'; }
		if ( 'invalide' === $r['validation'] ) { return '<span class="fwr-tag ko">❌ Invalidé</span>'; }
		return '<span class="fwr-tag ok">Corrigé</span>';
	}
	public static function feedback_html( $r ) {
		$h = '<div class="fwr-feedback">';
		if ( 'valide' === $r['validation'] ) {
			$h .= '<div class="fwr-verdict ok"><strong>✅ Exercice validé</strong>' . ( $r['motif'] ? '<br>' . esc_html( $r['motif'] ) : '' ) . '</div>';
		} elseif ( 'invalide' === $r['validation'] ) {
			$h .= '<div class="fwr-verdict ko"><strong>❌ Exercice invalidé</strong><br>Motif : ' . esc_html( $r['motif'] ) . '<br><span class="fwr-verdict-next">Vous pouvez déposer une nouvelle version avec le formulaire ci-dessus, ou en parler avec votre formateur.</span></div>';
		}
		if ( '' !== $r['note'] ) { $h .= '<div class="fwr-note">Note : <strong>' . esc_html( $r['note'] ) . '</strong></div>'; }
		if ( $r['commentaire'] ) { $h .= '<div class="fwr-comment">' . wpautop( esc_html( $r['commentaire'] ) ) . '</div>'; }
		if ( $r['detail'] && $r['auto_report'] && ! empty( $r['auto_report']['items'] ) ) {
			$h .= '<details class="fwr-details"><summary>Détail des points vérifiés</summary><ul class="fwr-crit">';
			foreach ( $r['auto_report']['items'] as $it ) {
				$h .= '<li class="' . ( $it['ok'] ? 'ok' : ( $it['pts'] > 0 ? 'mid' : 'ko' ) ) . '">' . esc_html( $it['label'] ) . ( ! empty( $it['penalty'] ) && ! $it['ok'] ? ' <strong>(' . esc_html( $it['pts'] ) . ' pt)</strong>' : '' ) . '</li>';
			}
			$h .= '</ul></details>';
		}
		return $h . '</div>';
	}

	/* ---------------------------------------------------------------- [fw_mes_remises] */
	public static function sc_mes_remises( $atts = array() ) {
		$a = shortcode_atts( array( 'formation' => '' ), $atts );
		wp_enqueue_style( 'fwr' );
		if ( ! is_user_logged_in() ) { return '<p>Connectez-vous pour voir vos exercices rendus. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Se connecter</a></p>'; }
		$rows = LIR_Core::for_user( get_current_user_id() );
		$last = array();
		foreach ( $rows as $r ) { if ( ! isset( $last[ $r['lecon'] ] ) ) { $last[ $r['lecon'] ] = $r; } }
		$h = '<div class="fwr-box"><h3 class="fwr-h">📚 Mes exercices rendus</h3><table class="fwr-table"><thead><tr><th>Leçon</th><th>Dernier dépôt</th><th>Statut</th><th>Note</th></tr></thead><tbody>';
		foreach ( LIR_Core::lecons() as $n => $L ) {
			if ( $a['formation'] && $a['formation'] !== $L['formation'] ) { continue; }
			$r = isset( $last[ $n ] ) ? $last[ $n ] : null;
			$st = ! $r ? '<span class="fwr-tag off">À rendre</span>' : self::status_tag( $r );
			$h .= '<tr><td>' . esc_html( LIR_Core::lecon_label( $n ) ) . '</td><td>' . ( $r ? esc_html( mysql2date( 'd.m.Y', $r['created_at'] ) . ' (v' . $r['version'] . ')' ) : '–' ) . '</td><td>' . $st . '</td><td>' . ( $r && 'publie' === $r['statut'] ? esc_html( $r['note'] ) : '' ) . '</td></tr>';
			if ( $r && 'publie' === $r['statut'] && ( $r['commentaire'] || $r['detail'] || $r['validation'] ) ) { $h .= '<tr class="fwr-fb"><td colspan="4">' . self::feedback_html( $r ) . '</td></tr>'; }
		}
		return $h . '</tbody></table></div>';
	}
}
