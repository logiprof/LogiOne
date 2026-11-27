<?php
/** Administration : liste des remises, fiche de correction, réglages. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WP_List_Table' ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; }

class LIR_List extends WP_List_Table {
	public function __construct() { parent::__construct( array( 'singular' => 'remise', 'plural' => 'remises', 'ajax' => false ) ); }

	public function get_columns() {
		return array( 'cb' => '<input type="checkbox">', 'apprenti' => 'Apprenti·e', 'lecon' => 'Leçon', 'version' => 'Version', 'created_at' => 'Déposé le', 'auto' => 'Contrôle auto', 'trace' => 'Traçabilité', 'note' => 'Note', 'statut' => 'Statut' ) + ( LI_Classes::groups() ? array( 'classe' => 'Classe' ) : array() ) + ( count( LI_Formateurs::all() ) > 1 ? array( 'formateur' => 'Formateur' ) : array() );
	}
	protected function get_sortable_columns() { return array( 'lecon' => array( 'lecon', false ), 'created_at' => array( 'created_at', true ), 'auto' => array( 'auto', false ) ); }
	protected function column_cb( $r ) { return '<input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '">'; }
	protected function get_bulk_actions() {
		return array( 'export_zip' => 'Exporter les fichiers (ZIP + récapitulatif)', 'export_ia' => 'Exporter pour correction par l\'IA (copies à corriger seulement)', 'export_ia_tout' => 'Exporter pour l\'IA, même déjà corrigée', 'publier' => 'Publier les retours', 'valider_publier' => 'Marquer « Validé » et publier', 'reanalyser' => 'Relancer le contrôle automatique', 'corbeille' => 'Mettre à la corbeille', 'restaurer' => 'Restaurer (sortir de la corbeille)' );
	}
	public static function filters() {
		return array(
			'lecon'  => isset( $_REQUEST['lecon'] ) ? (int) $_REQUEST['lecon'] : 0, // phpcs:ignore
			'statut' => isset( $_REQUEST['statut'] ) ? sanitize_key( $_REQUEST['statut'] ) : '', // phpcs:ignore
			'toutes' => ! empty( $_REQUEST['toutes'] ), // phpcs:ignore
			'formateur' => isset( $_REQUEST['formateur'] ) ? (int) $_REQUEST['formateur'] : -1, // phpcs:ignore
			'classe' => isset( $_REQUEST['classe'] ) ? (int) $_REQUEST['classe'] : 0, // phpcs:ignore
			's'      => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '', // phpcs:ignore
		);
	}
	/** Requête commune à la liste et aux exports « selon le filtre ». */
	public static function query_ids( $f, $orderby = 'created_at', $order = 'DESC', $limit = 0, $offset = 0, &$total = null ) {
		global $wpdb;
		$t = LIR_Core::table();
		$w = array( '1=1' ); $a = array();
		if ( $f['lecon'] ) { $w[] = 'r.lecon = %d'; $a[] = $f['lecon']; }
		if ( ! empty( $f['classe'] ) ) { $w[] = 'r.groupe_id = %d'; $a[] = $f['classe']; }
		if ( isset( $f['formateur'] ) && $f['formateur'] >= 0 ) { $w[] = 'r.formateur_id = %d'; $a[] = $f['formateur']; }
		$scope = LI_Formateurs::scope_sql( 'r' ); if ( $scope ) { $w[] = substr( $scope, 5 ); }
		if ( $f['statut'] && isset( LIR_Core::STATUTS[ $f['statut'] ] ) ) { $w[] = 'r.statut = %s'; $a[] = $f['statut']; } else { $w[] = "r.statut <> 'corbeille'"; }
		if ( ! $f['toutes'] && 'corbeille' !== $f['statut'] ) { $w[] = "r.version = (SELECT MAX(r2.version) FROM $t r2 WHERE r2.user_id = r.user_id AND r2.lecon = r.lecon AND r2.statut <> 'corbeille'" . ( LI_Formateurs::sees_all() ? '' : ' AND r2.formateur_id = r.formateur_id' ) . ')'; }
		if ( $f['s'] ) {
			$like = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$w[] = "r.user_id IN (SELECT u.ID FROM {$wpdb->users} u LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key IN ('first_name','last_name') WHERE u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR m.meta_value LIKE %s)";
			array_push( $a, $like, $like, $like, $like );
		}
		$ob = array( 'lecon' => 'r.lecon', 'created_at' => 'r.created_at', 'auto' => '(r.auto_score / NULLIF(r.auto_max,0))' );
		$ob = isset( $ob[ $orderby ] ) ? $ob[ $orderby ] : 'r.created_at';
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$where = implode( ' AND ', $w );
		$count_sql = "SELECT COUNT(*) FROM $t r WHERE $where";
		$total = (int) ( $a ? $wpdb->get_var( $wpdb->prepare( $count_sql, $a ) ) : $wpdb->get_var( $count_sql ) );
		$sql = "SELECT r.id FROM $t r WHERE $where ORDER BY $ob $order, r.id DESC" . ( $limit ? $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset ) : '' );
		return array_map( 'intval', $a ? $wpdb->get_col( $wpdb->prepare( $sql, $a ) ) : $wpdb->get_col( $sql ) );
	}
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$per = 30;
		$page = $this->get_pagenum();
		$total = 0;
		$ids = self::query_ids( self::filters(), isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at', isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC', $per, ( $page - 1 ) * $per, $total ); // phpcs:ignore
		$this->items = array_filter( array_map( array( 'LIR_Core', 'get' ), $ids ) );
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
	}
	protected function column_default( $r, $c ) {
		switch ( $c ) {
			case 'apprenti':
				$u = get_userdata( $r['user_id'] );
				$url = admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . (int) $r['id'] );
				return '<strong><a href="' . esc_url( $url ) . '">' . esc_html( LIR_Core::user_label( $r['user_id'] ) ) . '</a></strong><br><span class="description">' . esc_html( $u ? $u->user_email : '' ) . '</span>'
					. '<div class="row-actions"><span><a href="' . esc_url( $url ) . '">Corriger</a> | </span><span><a href="' . esc_url( LIR_Core::download_url( $r['id'], 0 ) ) . '">Télécharger</a></span></div>';
			case 'lecon': return esc_html( LIR_Core::lecon_label( $r['lecon'] ) );
			case 'version': return 'v' . (int) $r['version'];
			case 'created_at': return esc_html( mysql2date( 'd.m.Y H:i', $r['created_at'] ) );
			case 'auto': return LIR_Admin::badge( $r );
			case 'trace': return LIR_Admin::trace_badge( $r );
			case 'note':
				$h = '' !== $r['note'] ? '<strong>' . esc_html( $r['note'] ) . '</strong>' : '<span class="description">' . esc_html( LIR_Core::note_proposee( $r['auto_score'], $r['auto_max'] ) ) . ' (proposée)</span>';
				if ( ! empty( $r['ia_at'] ) ) { $h .= '<br><span class="fwr-ia-tag" title="Observations de l\'IA disponibles">🤖 IA' . ( '' !== (string) $r['ia_note'] ? ' : ' . esc_html( $r['ia_note'] ) : '' ) . '</span>'; }
				if ( ! empty( $r['ia_export_at'] ) && 'depose' === $r['statut'] && ( empty( $r['ia_at'] ) || $r['ia_at'] < $r['ia_export_at'] ) ) { $h .= '<br><span class="fwr-ia-tag" style="opacity:.75" title="Copie déjà envoyée à l\'IA, correction pas encore importée">📤 envoyée à l\'IA le ' . esc_html( mysql2date( 'd.m', $r['ia_export_at'] ) ) . '</span>'; }
				return $h;
			case 'classe': return esc_html( LI_Classes::label( (int) ( $r['groupe_id'] ?? 0 ) ) ?: '–' );
			case 'formateur': return esc_html( $r['formateur_id'] ? LI_Formateurs::label( $r['formateur_id'] ) : '–' );
			case 'statut': return '<span class="fwr-st fwr-st-' . esc_attr( $r['statut'] ) . '">' . esc_html( LIR_Core::STATUTS[ $r['statut'] ] ?? $r['statut'] ) . '</span>' . LIR_Admin::decision_tag( $r );
		}
		return '';
	}
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) { return; }
		$f = self::filters();
		echo '<div class="alignleft actions"><select name="lecon"><option value="0">Toutes les leçons</option>';
		foreach ( LIR_Core::lecons() as $n => $L ) { echo '<option value="' . (int) $n . '"' . selected( $f['lecon'], $n, false ) . '>' . esc_html( LIR_Core::lecon_label( $n ) ) . '</option>'; }
		echo '</select><select name="statut"><option value="">Tous les statuts</option>';
		foreach ( LIR_Core::STATUTS as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['statut'], $k, false ) . '>' . esc_html( $v ) . '</option>'; }
		echo '</select>';
		$cls = LI_Classes::groups();
		if ( $cls ) {
			echo '<select name="classe"><option value="0">Toutes les classes</option>';
			foreach ( $cls as $gid => $gt ) { echo '<option value="' . (int) $gid . '"' . selected( $f['classe'], $gid, false ) . '>' . esc_html( $gt ) . '</option>'; }
			echo '</select>';
		}
		if ( LI_Formateurs::sees_all() && count( LI_Formateurs::all() ) > 1 ) {
			echo '<select name="formateur"><option value="-1">Tous les formateurs</option><option value="0"' . selected( $f['formateur'], 0, false ) . '>Non attribuées</option>';
			foreach ( LI_Formateurs::all() as $fid => $fo ) { echo '<option value="' . (int) $fid . '"' . selected( $f['formateur'], $fid, false ) . '>' . esc_html( $fo['nom'] ) . '</option>'; }
			echo '</select>';
		}
		echo '<label style="margin:0 8px"><input type="checkbox" name="toutes" value="1"' . checked( $f['toutes'], true, false ) . '> Toutes les versions</label>';
		submit_button( 'Filtrer', '', 'filtrer', false );
		echo '</div>';
	}
	public function no_items() { echo 'Aucune remise pour ce filtre.'; }
}

class LIR_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_menu', function () { add_submenu_page( 'fw-remises', 'Réglages', 'Réglages', LI_ADMIN_CAP, 'fw-remises-reglages', array( __CLASS__, 'page_settings' ) ); }, 30 );
		add_action( 'admin_enqueue_scripts', function ( $h ) {
			if ( false === strpos( $h, 'fw-remises' ) ) { return; }
			wp_enqueue_style( 'fwr-admin', LIR_URL . 'assets/fwr-admin.css', array(), LIR_VERSION );
			if ( isset( $_GET['vue'], $_GET['id'] ) && 'fiche' === $_GET['vue'] ) { // phpcs:ignore
				$r = LIR_Core::get( (int) $_GET['id'] ); // phpcs:ignore
				if ( $r && ! LIR_Core::is_checked( $r['lecon'] ) && ! LIR_Core::is_libre( $r['lecon'] ) ) { self::enqueue_fiche( $r ); }
			}
		} );
		add_action( 'admin_post_fwr_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_fwr_bulk', array( __CLASS__, 'bulk' ) );
		add_action( 'load-toplevel_page_fw-remises', array( __CLASS__, 'list_bulk' ) );
		add_action( 'admin_post_fwr_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function menu() {
		global $wpdb;
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . LIR_Core::table() . " r WHERE r.statut = 'depose'" . LI_Formateurs::scope_sql( 'r' ) ); // la corbeille n'est pas comptée
		$bubble = $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '';
		add_menu_page( 'LogiInfo', 'LogiInfo' . $bubble, LIR_CAP, 'fw-remises', array( __CLASS__, 'page' ), 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M2 3h16v10H2zM4 5v6h12V5zM7 15h6v2H7z"/><path fill="#a7aaad" d="M5 6h4v1H5zm0 2h6v1H5z"/></svg>' ), 26 );
		add_submenu_page( 'fw-remises', 'Remises des exercices', 'Remises des exercices', LIR_CAP, 'fw-remises', array( __CLASS__, 'page' ) );
		add_submenu_page( 'fw-remises', 'Correction par l\'IA', 'Correction par l\'IA', LIR_CAP, 'fw-remises-ia', array( 'LIR_Export', 'page_ia' ) );
	}

	public static function badge( $r ) {
		if ( null === $r['auto_max'] || ! (float) $r['auto_max'] ) { return '–'; }
		$p = round( 100 * $r['auto_score'] / $r['auto_max'] );
		$cls = $p >= 80 ? 'ok' : ( $p >= 50 ? 'mid' : 'ko' );
		return '<span class="fwr-badge ' . $cls . '">' . $p . ' %</span> <span class="description">' . esc_html( rtrim( rtrim( number_format( $r['auto_score'], 1, '.', '' ), '0' ), '.' ) . '/' . (float) $r['auto_max'] ) . '</span>';
	}

	/** Pastille de traçabilité pour la liste des remises. */
	public static function trace_badge( $r ) {
		$id = LIR_Core::identity( $r );
		$pre = 'alerte' === $id['level'] ? '<span class="fwr-id ko" title="' . esc_attr( implode( ' ', $id['msgs'] ) ) . '">🪪 Auteur à vérifier</span><br>' : '';
		return $pre . self::trace_badge_core( $r );
	}
	private static function trace_badge_core( $r ) {
		$flags = LIR_Core::trace_flags( $r );
		if ( null === $flags ) { return '<span class="description" title="Remise contrôlée avant la version 1.2 : relancez le contrôle automatique">–</span>'; }
		$auth = array_keys( LIR_Core::authors( $r ) );
		$who = $auth ? esc_html( implode( ', ', array_slice( $auth, 0, 2 ) ) . ( count( $auth ) > 2 ? '…' : '' ) ) : '<em>aucun auteur</em>';
		if ( ! $flags ) { return '<span class="fwr-tr ok" title="' . ( LIR_Core::is_checked( $r['lecon'] ) ? 'Fichier de départ, enregistré par' : 'Suivi verrouillé, un seul auteur' ) . '">' . ( LIR_Core::is_xlsx( $r['lecon'] ) ? '📊 ' : ( LIR_Core::is_pptx( $r['lecon'] ) ? '📙 ' : '🔒 ' ) ) . $who . '</span>'; }
		return '<span class="fwr-tr ko" title="' . esc_attr( implode( ' · ', $flags ) ) . '">⚠️ ' . $who . '</span>';
	}

	/** Encadré « Traçabilité du fichier » de la fiche de correction. */
	public static function decision_tag( $r ) {
		if ( 'valide' === $r['validation'] ) { return '<br><span class="fwr-dec ok" title="' . esc_attr( (string) $r['motif'] ) . '">✅ Validé</span>'; }
		if ( 'invalide' === $r['validation'] ) { return '<br><span class="fwr-dec ko" title="' . esc_attr( (string) $r['motif'] ) . '">❌ Invalidé</span>'; }
		return '';
	}
	/** Vérification du compte qui a déposé (encart en tête de la fiche de correction). */
	private static function identity_card( $r ) {
		$id = LIR_Core::identity( $r );
		$cls = array( 'ok' => 'fwr-idc ok', 'info' => 'fwr-idc info', 'alerte' => 'fwr-idc ko' )[ $id['level'] ];
		$tit = array( 'ok' => '✅ Auteur cohérent avec le compte', 'info' => 'ℹ️ Auteur impossible à vérifier', 'alerte' => '🪪 Incohérence : vérifiez qui a fait l\'exercice' )[ $id['level'] ];
		echo '<div class="' . esc_attr( $cls ) . '"><strong>' . esc_html( $tit ) . '</strong><ul>';
		foreach ( $id['msgs'] as $m ) { echo '<li>' . esc_html( $m ) . '</li>'; }
		echo '</ul>';
		if ( 'alerte' === $id['level'] && 'invalide' !== $r['validation'] ) {
			echo '<p><button type="button" class="button" onclick="var f=document.getElementById(\'fwr-dec-invalide\');f.checked=true;f.dispatchEvent(new Event(\'change\'));var m=document.getElementById(\'fwr-motif\');if(!m.value){m.value=' . esc_attr( wp_json_encode( $id['motif'] ?: LIR_Core::motifs()[0] ) ) . ';}m.scrollIntoView({block:\'center\'});m.focus();">❌ Préparer l\'invalidation</button> <span class="description">Vérifiez d\'abord (demandez à l\'apprenti·e, comparez avec les autres copies). Rien n\'est enregistré avant « Enregistrer ».</span></p>';
		}
		echo '</div>';
	}
	private static function trace_card( $r ) {
		$rep = $r['auto_report'];
		echo '<div class="fwr-card fwr-trace"><h2>🕵️ Qui a travaillé sur ce fichier ?</h2>';
		if ( ! is_array( $rep ) || ! isset( $rep['trace'] ) ) {
			echo '<p class="description">Cette remise a été contrôlée avant l\'ajout de la traçabilité. Cliquez sur « Relancer le contrôle » pour lire le suivi des modifications.</p></div>';
			return;
		}
		$lab = array( 'verrouillee' => '🔒 intacte (mot de passe des exercices)', 'absente' => '⚠️ retirée', 'autre' => '⚠️ autre type de protection', 'autre_mdp' => '⚠️ autre mot de passe' );
		echo '<table class="widefat striped"><thead><tr><th>Fichier</th><th>Protection du suivi</th><th>Suivi</th><th>Auteurs des modifications</th><th>Période</th><th>Dernier enregistrement par</th></tr></thead><tbody>';
		foreach ( $rep['trace'] as $t ) {
			if ( isset( $t['kind'] ) && in_array( $t['kind'], array( 'xlsx', 'pptx' ), true ) ) {
				$dt = function ( $v ) { return $v ? mysql2date( 'd.m.Y H:i', get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $v ) ) ) ) : '?'; };
				$pp = 'pptx' === $t['kind'];
				echo '<tr><td>' . esc_html( $t['file'] ) . '</td><td>' . ( $pp ? '📙 PowerPoint : ' : '📊 Excel : ' ) . ( $t['depart'] ? 'fichier de départ de la formation' : '⚠️ ' . ( $pp ? 'présentation' : 'classeur' ) . ' créé(e) par « ' . esc_html( $t['creator'] ?: '?' ) . ' »' ) . '</td><td>–</td><td>' . ( $t['last_by'] ? '<strong>' . esc_html( $t['last_by'] ) . '</strong>' : '<em>aucun nom enregistré</em>' ) . '</td><td>' . esc_html( $dt( $t['first'] ) . ' → ' . $dt( $t['last'] ) ) . '</td><td>' . esc_html( $t['last_by'] ?: '–' ) . '</td></tr>';
				continue;
			}
			$auth = array();
			foreach ( $t['authors'] as $k => $n ) { $auth[] = '<strong>' . esc_html( $k ) . '</strong> (' . (int) $n . ')'; }
			$per = $t['first'] ? esc_html( mysql2date( 'd.m.Y H:i', get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $t['first'] ) ) ) ) . ' → ' . mysql2date( 'd.m.Y H:i', get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $t['last'] ) ) ) ) ) : '–';
			echo '<tr><td>' . esc_html( $t['file'] ) . '</td><td>' . esc_html( $lab[ $t['protection'] ] ?? $t['protection'] ) . '</td><td>' . ( $t['tracking'] ? 'activé' : '⚠️ désactivé' ) . '</td><td>' . ( $auth ? implode( '<br>', $auth ) : '<em>aucune modification enregistrée</em>' ) . '</td><td>' . $per . '</td><td>' . esc_html( $t['last_by'] ?: '–' ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
		$flags = LIR_Core::trace_flags( $r );
		$shared = LIR_Core::shared_authors( $r );
		if ( $flags || $shared ) {
			echo '<div class="fwr-trace-alert"><strong>À vérifier :</strong><ul>';
			foreach ( $flags as $f ) { echo '<li>' . esc_html( ucfirst( $f ) ) . '</li>'; }
			foreach ( $shared as $who => $users ) { echo '<li>L\'auteur « <strong>' . esc_html( $who ) . '</strong> » apparaît aussi dans les remises de : ' . esc_html( implode( ', ', $users ) ) . '</li>'; }
			echo '</ul></div>';
		} else {
			echo '<p class="fwr-trace-ok">✅ Le suivi est verrouillé et toutes les modifications ont été faites par un seul auteur, qu\'on ne retrouve chez aucun autre apprenti.</p>';
		}
		echo '<p class="description">Le nom affiché est le nom d\'utilisateur d\'Office (Word, Excel, PowerPoint) de l\'ordinateur utilisé (Fichier › Options › Général). Un nom inattendu ou partagé avec un autre apprenti signale un fichier échangé.</p></div>';
	}

	public static function notice() {
		if ( empty( $_GET['fwr_msg'] ) ) { return; } // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['fwr_msg'] ) ) ) . '</p></div>'; // phpcs:ignore
	}

	/* ---------------------------------------------------------------- liste */
	public static function page() {
		if ( isset( $_GET['vue'] ) && 'fiche' === $_GET['vue'] ) { self::fiche( (int) ( $_GET['id'] ?? 0 ) ); return; } // phpcs:ignore
		$t = new LIR_List();
		$t->prepare_items();
		echo '<div class="wrap fwr-wrap"><h1 class="wp-heading-inline">Remises des exercices</h1>';
		$f = LIR_List::filters();
		$q = array_filter( array( 'lecon' => $f['lecon'], 'statut' => $f['statut'], 'toutes' => $f['toutes'] ? 1 : 0, 's' => $f['s'] ) ) + ( $f['formateur'] >= 0 ? array( 'formateur' => $f['formateur'] ) : array() ) + ( $f['classe'] ? array( 'classe' => $f['classe'] ) : array() );
		echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fwr_bulk&op=export_zip&tout=1&' . http_build_query( $q ) ), 'fwr_bulk' ) ) . '">⬇ Exporter tout le filtre (ZIP)</a>';
		echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fwr_bulk&op=export_csv&tout=1&' . http_build_query( $q ) ), 'fwr_bulk' ) ) . '">⬇ Récapitulatif (CSV / Excel)</a>';
		echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fwr_bulk&op=export_ia&tout=1&' . http_build_query( $q ) ), 'fwr_bulk' ) ) . '" title="Seules les copies « À corriger » du filtre sont envoyées">🤖 Exporter le filtre pour l\'IA</a>';
		echo '<hr class="wp-header-end">';
		self::notice();
		self::stats();
		echo '<form method="get" id="fwr-list-form"><input type="hidden" name="page" value="fw-remises">';
		$t->search_box( 'Rechercher un·e apprenti·e', 'fwr-s' );
		$t->display();
		echo '</form></div>';
	}

	private static function stats() {
		global $wpdb;
		$t = LIR_Core::table();
		$rows = $wpdb->get_results( "SELECT lecon, COUNT(DISTINCT user_id) n, SUM(statut='depose') a FROM $t r WHERE statut <> 'corbeille'" . LI_Formateurs::scope_sql( 'r' ) . ' GROUP BY lecon', ARRAY_A );
		if ( ! $rows ) { echo '<p class="description">Aucun exercice n\'a encore été déposé. Les leçons Word, Excel et PowerPoint contiennent déjà le formulaire (<code>[fw_remise lecon="1"]</code> pour Word, <code>[fw_remise lecon="1" formation="excel"]</code> pour Excel, <code>[fw_remise lecon="1" formation="powerpoint"]</code> pour PowerPoint) dans vos leçons Sensei.</p>'; return; }
		$by = array(); foreach ( $rows as $r ) { $by[ (int) $r['lecon'] ] = $r; }
		echo '<div class="fwr-stats">';
		foreach ( LIR_Core::lecons() as $n => $L ) {
			$r = $by[ $n ] ?? array( 'n' => 0, 'a' => 0 );
			$url = admin_url( 'admin.php?page=fw-remises&lecon=' . $n );
			echo '<a class="fwr-stat" href="' . esc_url( $url ) . '" title="' . esc_attr( LIR_Core::lecon_label( $n ) ) . '"><b>' . esc_html( LIR_Core::code( $n ) ) . '</b><span>' . (int) $r['n'] . ' apprenti·e·s</span>' . ( $r['a'] ? '<em>' . (int) $r['a'] . ' à corriger</em>' : '' ) . '</a>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------- fiche de correction */
	public static function enqueue_fiche( $r ) {
		wp_enqueue_script( 'fwr-jszip', LIR_URL . 'assets/vendor/jszip.min.js', array(), '3.10.1', true );
		wp_enqueue_script( 'fwr-docx-preview', LIR_URL . 'assets/vendor/docx-preview.min.js', array( 'fwr-jszip' ), '0.4.1', true );
		wp_enqueue_script( 'fwr-compare', LIR_URL . 'assets/fwr-compare.js', array( 'fwr-docx-preview' ), LIR_VERSION, true );
		$left = array();
		foreach ( $r['files'] as $k => $f ) {
			$left[] = array( 'label' => $f['name'], 'docx' => LIR_Core::download_url( $r['id'], $k ), 'objets' => LIR_Core::objets_non_affiches( LIR_Core::abs_path( $f['stored'] ) ) );
		}
		$right = array();
		foreach ( LIR_Core::references( $r['lecon'] ) as $k => $ref ) {
			$right[] = array( 'label' => $ref['label'], 'docx' => $ref['docx'] ? LIR_Core::ref_url( $r['lecon'], $k, 'docx' ) : '', 'pdf' => $ref['pdf'] ? LIR_Core::ref_url( $r['lecon'], $k, 'pdf' ) : '',
				'objets' => $ref['docx'] ? LIR_Core::objets_non_affiches( $ref['docx'] ) : array() );
		}
		// leçon 14 : mettre en face la lettre type et les lettres fusionnées
		$start_right = 0;
		wp_add_inline_script( 'fwr-compare', 'window.LIR_COMPARE = ' . wp_json_encode( array( 'left' => $left, 'right' => $right, 'startRight' => $start_right ) ) . ';', 'before' );
	}

	public static function fiche( $id ) {
		$r = LIR_Core::get( $id );
		echo '<div class="wrap fwr-wrap">';
		if ( ! $r || ! LI_Formateurs::can_see( $r ) ) { echo '<h1>Remise introuvable</h1><p>Cette remise n\'existe pas ou elle est attribuée à un autre formateur.</p></div>'; return; }
		// 1.54.5 : une remise encore à corriger, analysée avec l'ancien barème, est recontrôlée automatiquement (pénalités).
		if ( 'depose' === $r['statut'] && is_array( $r['auto_report'] ) && (int) ( $r['auto_report']['v'] ?? 1 ) < LIR_Core::REPORT_VERSION ) {
			LIR_Core::reanalyse( $r['id'] );
			$r = LIR_Core::get( $id );
		}
		$L = LIR_Core::lecon( $r['lecon'] );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fw-remises' ) ) . '">← Retour à la liste</a></p>';
		echo '<h1>' . esc_html( LIR_Core::user_label( $r['user_id'] ) ) . ' – ' . esc_html( LIR_Core::lecon_label( $r['lecon'] ) ) . '</h1>';
		self::notice();
		echo '<p class="fwr-meta">Exercice : <strong>' . esc_html( $L['exercice'] ) . '</strong> · Version ' . (int) $r['version'] . ' déposée le ' . esc_html( mysql2date( 'd.m.Y à H:i', $r['created_at'] ) ) . ' · <span class="fwr-st fwr-st-' . esc_attr( $r['statut'] ) . '">' . esc_html( LIR_Core::STATUTS[ $r['statut'] ] ) . '</span> · ';
		foreach ( $r['files'] as $k => $f ) { echo '<a href="' . esc_url( LIR_Core::download_url( $r['id'], $k ) ) . '">⬇ ' . esc_html( $f['name'] ) . '</a> '; }
		if ( count( LI_Formateurs::active() ) > 1 || ! $r['formateur_id'] ) { echo LI_Formateurs::reassign_html( $r ); } // phpcs:ignore
		$others = LIR_Core::for_user( $r['user_id'], $r['lecon'] );
		if ( count( $others ) > 1 ) {
			echo ' · Autres versions : ';
			foreach ( $others as $o ) { if ( (int) $o['id'] !== (int) $r['id'] ) { echo '<a href="' . esc_url( admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . $o['id'] ) ) . '">v' . (int) $o['version'] . '</a> '; } }
		}
		echo '</p>';

		self::identity_card( $r );
		self::trace_card( $r );

		/* ---- comparaison côte à côte (Word) ou lien vers le résultat attendu (Excel) */
		if ( LIR_Core::is_libre( $r['lecon'] ) ) {
			echo '<div class="fwr-card"><h2>📎 Exercice libre</h2><p>Exercice ajouté avec le code court <code>[logiinfo_remise]</code>' . ( ! empty( $L['post_id'] ) ? ' dans « <a href="' . esc_url( get_permalink( $L['post_id'] ) ) . '" target="_blank">' . esc_html( get_the_title( $L['post_id'] ) ) . '</a> »' : '' ) . '. Ouvrez la copie (lien ⬇ ci-dessus), puis saisissez votre note et votre commentaire.</p></div>';
		} elseif ( LIR_Core::is_pptx( $r['lecon'] ) ) {
			$res = content_url( sprintf( 'uploads/%1$s/L%2$02d/L%2$02d_resultat_attendu.pdf', LIR_Core::folder( $r['lecon'] ), $L['num'] ) );
			echo '<div class="fwr-card"><h2>📙 Présentation PowerPoint</h2><p>Ouvrez la copie dans PowerPoint (lien ⬇ ci-dessus), lancez le diaporama et comparez-la au <a href="' . esc_url( $res ) . '" target="_blank">résultat attendu (PDF)</a>. Le contrôle automatique ci-dessous vérifie les points de l\'exercice (diapositives, dispositions, objets, thème, masque, transitions, animations, notes…). Le PDF ne montre ni les animations ni les transitions : vérifiez-les dans le diaporama.</p></div>';
		} elseif ( LIR_Core::is_xlsx( $r['lecon'] ) ) {
			$res = content_url( sprintf( 'uploads/Excel2026/L%1$02d/L%1$02d_resultat_attendu.pdf', $L['num'] ) );
			echo '<div class="fwr-card"><h2>📊 Classeur Excel</h2><p>Ouvrez la copie dans Excel (lien ⬇ ci-dessus) et comparez-la au <a href="' . esc_url( $res ) . '" target="_blank">résultat attendu (PDF)</a>. Le contrôle automatique ci-dessous compare le classeur au résultat attendu (valeurs, formules, formats, mises en forme, graphiques, filtres…) ; la traçabilité ci-dessus indique qui a créé et enregistré le classeur.</p></div>';
		} else {
		echo '<div class="fwr-compare" id="fwr-compare">';
		echo '<div class="fwr-cmp-bar"><strong>Comparaison</strong>';
		echo '<span class="fwr-cmp-zoom"><button type="button" class="button" data-zoom="-">−</button><span id="fwr-zoom-val">100 %</span><button type="button" class="button" data-zoom="+">+</button><button type="button" class="button" data-zoom="fit">Ajuster</button></span>';
		echo '<label><input type="checkbox" id="fwr-sync" checked> Défilement synchronisé</label>';
		echo '<button type="button" class="button" id="fwr-full">⛶ Plein écran</button></div>';
		echo '<div class="fwr-cmp-panes">';
		echo '<div class="fwr-pane" id="fwr-left"><div class="fwr-pane-head"><span class="fwr-pane-title">📝 Copie de l\'apprenti·e</span><select class="fwr-pane-sel"></select></div><div class="fwr-pane-body"><p class="fwr-wait">Chargement du document…</p></div><div class="fwr-pane-objets"></div></div>';
		echo '<div class="fwr-pane" id="fwr-right"><div class="fwr-pane-head"><span class="fwr-pane-title">🎯 Résultat attendu</span><select class="fwr-pane-sel"></select>'
			. '<span class="fwr-seg"><button type="button" class="on" data-mode="docx">Aperçu Word</button><button type="button" data-mode="pdf">PDF officiel</button></span></div><div class="fwr-pane-body"><p class="fwr-wait">Chargement du document…</p></div><div class="fwr-pane-objets"></div></div>';
		echo '</div><p class="description fwr-cmp-note">Les deux documents sont affichés avec le même moteur d\'aperçu, pour une comparaison à égalité. Cet aperçu simplifié ne dessine ni les graphiques, ni les SmartArt, ni certains effets d\'image : ces éléments sont listés sous chaque document. En cas de doute, ouvrez le fichier dans Word. Le bouton « PDF officiel » montre le résultat attendu exact.</p></div>';
		}

		/* ---- contrôle auto | observations IA | correction */
		echo '<div class="fwr-cols3">';
		echo '<div class="fwr-card"><h2>🔎 Contrôle automatique ' . self::badge( $r ) . '</h2>'; // phpcs:ignore
		$rep = $r['auto_report'];
		if ( $rep ) {
			if ( ! empty( $rep['errors'] ) ) { echo '<div class="notice notice-error inline"><p>' . esc_html( implode( ' / ', $rep['errors'] ) ) . '</p></div>'; }
			echo '<table class="widefat striped fwr-crit"><tbody>';
			foreach ( $rep['items'] as $it ) {
				if ( ! empty( $it['penalty'] ) ) {
					echo '<tr class="fwr-pen' . ( $it['ok'] ? '' : ' is-on' ) . '"><td>' . ( $it['ok'] ? '✅' : '⛔' ) . '</td><td>' . esc_html( $it['label'] ) . '</td><td class="fwr-pts">' . ( $it['ok'] ? '<span title="Pénalité non appliquée">—</span>' : '<strong>' . esc_html( str_replace( '-', '−', (string) $it['pts'] ) ) . '</strong>' ) . '</td></tr>'; // phpcs:ignore
					continue;
				}
				$ic = $it['ok'] ? '✅' : ( $it['pts'] > 0 ? '🟠' : '❌' );
				echo '<tr><td>' . $ic . '</td><td>' . esc_html( $it['label'] ) . '</td><td class="fwr-pts">' . esc_html( $it['pts'] . '/' . $it['max'] ) . '</td></tr>'; // phpcs:ignore
			}
			echo '</tbody></table>';
		}
		echo '<p><a class="button-link" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fwr_bulk&op=reanalyser&ids[]=' . $r['id'] . '&retour=' . $r['id'] ), 'fwr_bulk' ) ) . '">↻ Relancer le contrôle</a></p>';
		echo '<details class="fwr-struct"><summary>Structure lue dans le fichier</summary><pre>';
		foreach ( $r['files'] as $f ) {
			$d = LIR_Core::reader( LIR_Core::abs_path( $f['stored'] ) );
			echo '=== ' . esc_html( $f['name'] ) . " ===\n" . esc_html( $d->ok ? $d->summary() : $d->error ) . "\n\n";
		}
		echo '</pre></details></div>';

		echo '<div class="fwr-card fwr-ia"><h2>🤖 Observations de l\'IA</h2>';
		if ( $r['ia_at'] ) {
			$det = $r['ia_details'];
			echo '<p class="fwr-ia-meta">Importées le ' . esc_html( mysql2date( 'd.m.Y à H:i', $r['ia_at'] ) ) . '</p>';
			if ( '' !== (string) $r['ia_note'] ) { echo '<p class="fwr-ia-note">Note proposée : <strong>' . esc_html( $r['ia_note'] ) . '</strong> <button type="button" class="button button-small" data-take="note">Reprendre la note</button></p>'; }
			if ( $r['ia_commentaire'] ) { echo '<div class="fwr-ia-com">' . wpautop( esc_html( $r['ia_commentaire'] ) ) . '</div>'; }
			if ( ! empty( $det['points_forts'] ) ) { echo '<h4>👍 Points forts</h4><ul class="fwr-ia-list">'; foreach ( $det['points_forts'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; } echo '</ul>'; }
			if ( ! empty( $det['a_ameliorer'] ) ) { echo '<h4>🛠️ À améliorer</h4><ul class="fwr-ia-list">'; foreach ( $det['a_ameliorer'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; } echo '</ul>'; }
			if ( ! empty( $det['ecart_controle_auto'] ) ) { echo '<div class="fwr-ia-ecart"><strong>Écart avec le contrôle automatique :</strong> ' . esc_html( $det['ecart_controle_auto'] ) . '</div>'; }
			echo '<p><button type="button" class="button" data-take="commentaire">Reprendre le commentaire</button></p>';
			echo '<textarea id="fwr-ia-texte" hidden>' . esc_textarea( self::ia_texte( $r ) ) . '</textarea><input type="hidden" id="fwr-ia-note" value="' . esc_attr( (string) $r['ia_note'] ) . '">';
		} else {
			echo '<p class="description">Aucune observation pour cette copie. Exportez-la pour l\'IA, faites-la corriger par Claude, puis importez le fichier <code>correction.json</code> : les observations s\'afficheront ici.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=fw-remises-ia' ) ) . '">Correction par l\'IA</a></p>';
		}
		echo '</div>';

		echo '<div class="fwr-card"><h2>✍️ Ma correction</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fwr_save"><input type="hidden" name="id" value="' . (int) $r['id'] . '">';
		wp_nonce_field( 'fwr_save_' . $r['id'] );
		$prop = LIR_Core::note_proposee( $r['auto_score'], $r['auto_max'] );
		echo '<p><label><strong>Note</strong> <span class="description">(' . esc_html( LIR_Core::echelle_label() ) . ')</span><br><input type="text" id="fwr-note" name="note" value="' . esc_attr( '' !== $r['note'] ? $r['note'] : $prop ) . '" style="width:110px"></label> <span class="description">Contrôle auto : ' . esc_html( $prop ) . ( '' !== (string) $r['ia_note'] && null !== $r['ia_note'] ? ' · IA : ' . esc_html( $r['ia_note'] ) : '' ) . '</span></p>';
		$dec = (string) $r['validation'];
		echo '<fieldset class="fwr-decision"><legend><strong>Décision</strong> <span class="description">(visible par l\'apprenti·e une fois le retour publié)</span></legend>';
		foreach ( array( '' => 'Pas encore décidé', 'valide' => '✅ Validé', 'invalide' => '❌ Invalidé' ) as $v => $lab ) {
			echo '<label class="fwr-dec-opt"><input type="radio" name="validation" id="fwr-dec-' . esc_attr( $v ?: 'aucune' ) . '" value="' . esc_attr( $v ) . '"' . checked( $dec, $v, false ) . ' onchange="document.getElementById(\'fwr-motif-box\').classList.toggle(\'req\',this.value===\'invalide\');document.getElementById(\'fwr-motif\').required=this.value===\'invalide\'"> ' . esc_html( $lab ) . '</label> ';
		}
		echo '<div id="fwr-motif-box" class="' . ( 'invalide' === $dec ? 'req' : '' ) . '"><label><strong>Motif</strong> <span class="description">(bref, visible par l\'apprenti·e ; obligatoire pour invalider)</span><br>';
		echo '<input type="text" id="fwr-motif" name="motif" class="large-text" maxlength="300" value="' . esc_attr( (string) $r['motif'] ) . '"' . ( 'invalide' === $dec ? ' required' : '' ) . ' placeholder="Ex. : le fichier a été réalisé sur le compte d\'un autre élève."></label>';
		echo '<br><select onchange="if(this.value){document.getElementById(\'fwr-motif\').value=this.value;var f=document.getElementById(\'fwr-dec-invalide\');f.checked=true;f.dispatchEvent(new Event(\'change\'));}this.selectedIndex=0"><option value="">Motifs fréquents…</option>';
		foreach ( LIR_Core::motifs() as $m ) { echo '<option value="' . esc_attr( $m ) . '">' . esc_html( $m ) . '</option>'; }
		echo '</select></div></fieldset>';
		echo '<p><label><strong>Commentaire pour l\'apprenti·e</strong><br><textarea id="fwr-commentaire" name="commentaire" rows="12" class="large-text">' . esc_textarea( (string) $r['commentaire'] ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="detail" value="1"' . checked( (int) $r['detail'], 1, false ) . '> Montrer aussi la liste des points vérifiés</label></p>';
		echo '<p class="fwr-actions"><button class="button" name="publier" value="0">💾 Enregistrer sans publier</button> <button class="button button-primary" name="publier" value="1">📣 Enregistrer et publier</button>';
		if ( 'publie' === $r['statut'] ) { echo ' <button class="button-link" name="publier" value="-1">Retirer la publication</button>'; }
		echo '</p></form></div></div></div>';
	}

	/** Texte de l'IA mis en forme pour le champ commentaire. */
	public static function ia_texte( $r ) {
		$t = trim( (string) $r['ia_commentaire'] );
		$d = $r['ia_details'];
		if ( ! empty( $d['points_forts'] ) ) { $t .= "\n\nPoints forts :\n- " . implode( "\n- ", $d['points_forts'] ); }
		if ( ! empty( $d['a_ameliorer'] ) ) { $t .= "\n\nÀ améliorer :\n- " . implode( "\n- ", $d['a_ameliorer'] ); }
		return trim( $t );
	}

	public static function save() {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'fwr_save_' . $id );
		if ( ! current_user_can( LIR_CAP ) || ! LI_Formateurs::can_see( (array) LIR_Core::get( $id ) ) ) { wp_die( 'Accès refusé.' ); }
		$pub = (int) ( $_POST['publier'] ?? 0 );
		$val = sanitize_key( $_POST['validation'] ?? '' );
		$val = isset( LIR_Core::DECISIONS[ $val ] ) ? $val : '';
		$motif = sanitize_text_field( wp_unslash( $_POST['motif'] ?? '' ) );
		if ( 'invalide' === $val && '' === $motif ) { $motif = 'Exercice invalidé par votre formateur : venez en parler avec lui.'; }
		$data = array(
			'validation' => $val, 'motif' => $motif,
			'note' => sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ),
			'commentaire' => sanitize_textarea_field( wp_unslash( $_POST['commentaire'] ?? '' ) ),
			'detail' => empty( $_POST['detail'] ) ? 0 : 1,
			'corrige_par' => get_current_user_id(), 'corrige_at' => current_time( 'mysql' ),
			'statut' => 1 === $pub ? 'publie' : 'corrige',
		);
		if ( 1 === $pub ) { $data['publie_at'] = current_time( 'mysql' ); }
		global $wpdb;
		$wpdb->update( LIR_Core::table(), $data, array( 'id' => $id ) );
		LIR_Core::log( 'correction', $id, $data['statut'] . ( $val ? ' · ' . $val : '' ) );
		if ( 1 === $pub ) { do_action( 'fwr_retour_publie', $id ); }
		$msg = 1 === $pub ? 'Retour publié : l\'apprenti·e le voit sous l\'exercice.' : 'Correction enregistrée (non visible par l\'apprenti·e).';
		wp_safe_redirect( admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . $id . '&fwr_msg=' . rawurlencode( $msg ) ) );
		exit;
	}

	/* ---------------------------------------------------------------- actions groupées */
	/** Actions groupées choisies dans la liste (formulaire GET de WP_List_Table). */
	public static function list_bulk() {
		$op = '';
		foreach ( array( 'action', 'action2' ) as $k ) { if ( ! empty( $_REQUEST[ $k ] ) && '-1' !== $_REQUEST[ $k ] ) { $op = sanitize_key( $_REQUEST[ $k ] ); break; } } // phpcs:ignore
		if ( ! $op || empty( $_REQUEST['ids'] ) ) { return; } // phpcs:ignore
		check_admin_referer( 'bulk-remises' );
		self::process( $op, array_map( 'intval', (array) $_REQUEST['ids'] ) ); // phpcs:ignore
	}
	/** Liens « exporter tout le filtre » et actions ponctuelles (admin-post.php?action=fwr_bulk&op=…). */
	public static function bulk() {
		check_admin_referer( 'fwr_bulk' );
		$op = sanitize_key( $_REQUEST['op'] ?? '' );
		$ids = array_map( 'intval', (array) ( $_REQUEST['ids'] ?? array() ) );
		if ( ! empty( $_REQUEST['tout'] ) ) {
			$f = array( 'lecon' => (int) ( $_REQUEST['lecon'] ?? 0 ), 'classe' => (int) ( $_REQUEST['classe'] ?? 0 ), 'formateur' => isset( $_REQUEST['formateur'] ) ? (int) $_REQUEST['formateur'] : -1, 'statut' => sanitize_key( $_REQUEST['statut'] ?? '' ), 'toutes' => ! empty( $_REQUEST['toutes'] ), 's' => sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) ) );
			$ids = LIR_List::query_ids( $f, 'lecon', 'ASC' );
		}
		self::process( $op, $ids );
	}
	private static function process( $op, $ids ) {
		if ( ! current_user_can( LIR_CAP ) ) { wp_die( 'Accès refusé.' ); }
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ), function ( $i ) { $r = LIR_Core::get( $i ); return $r && LI_Formateurs::can_see( $r ); } ) );
		$back = admin_url( 'admin.php?page=fw-remises' );
		if ( ! $ids ) { wp_safe_redirect( add_query_arg( 'fwr_msg', rawurlencode( 'Aucune remise sélectionnée.' ), $back ) ); exit; }
		global $wpdb;
		switch ( $op ) {
			case 'export_zip': LIR_Export::zip( $ids ); break;
			case 'export_csv': LIR_Export::csv( $ids ); break;
			case 'export_ia':
			case 'export_ia_tout':
				$keep = LIR_Export::ia_ids( $ids, 'export_ia_tout' === $op );
				if ( ! $keep ) {
					$msg = sprintf( 'Rien à envoyer à l\'IA : les %d remise(s) choisie(s) sont déjà corrigées, publiées ou à la corbeille. Seules les copies « À corriger » sont exportées (aucun frais en double).', count( $ids ) );
					break;
				}
				LIR_Export::ia( $keep ); break;
			case 'valider_publier': // les copies déjà invalidées ne sont pas touchées
				$n = 0;
				foreach ( $ids as $id ) {
					$r = LIR_Core::get( $id );
					if ( ! $r || 'invalide' === $r['validation'] || 'corbeille' === $r['statut'] ) { continue; }
					$wpdb->update( LIR_Core::table(), array( 'validation' => 'valide', 'statut' => 'publie', 'publie_at' => current_time( 'mysql' ), 'corrige_par' => get_current_user_id(), 'corrige_at' => $r['corrige_at'] ?: current_time( 'mysql' ) ), array( 'id' => $id ) );
					LIR_Core::log( 'publication', $id, 'valide' ); do_action( 'fwr_retour_publie', $id ); $n++;
				}
				$msg = $n . ' remise(s) validée(s) et publiée(s). Les remises invalidées ne sont pas modifiées.';
				break;
			case 'publier':
				$n = 0;
				foreach ( $ids as $id ) { $k = (int) $wpdb->update( LIR_Core::table(), array( 'statut' => 'publie', 'publie_at' => current_time( 'mysql' ) ), array( 'id' => $id, 'statut' => 'corrige' ) ); if ( $k ) { LIR_Core::log( 'publication', $id ); } $n += $k; }
				$msg = $n . ' retour(s) publié(s). Seules les remises au statut « Corrigé » sont publiées.';
				break;
			case 'reanalyser':
				foreach ( $ids as $id ) { LIR_Core::reanalyse( $id ); }
				$msg = count( $ids ) . ' remise(s) recontrôlée(s).';
				if ( ! empty( $_REQUEST['retour'] ) ) { $back = admin_url( 'admin.php?page=fw-remises&vue=fiche&id=' . (int) $_REQUEST['retour'] ); }
				break;
			case 'corbeille': // rien n'est effacé : la remise et ses fichiers restent sur le serveur et peuvent être restaurés
				foreach ( $ids as $id ) {
					$r = LIR_Core::get( $id );
					if ( ! $r || 'corbeille' === $r['statut'] ) { continue; }
					$rep = is_array( $r['auto_report'] ) ? $r['auto_report'] : array();
					$rep['statut_avant_corbeille'] = $r['statut'];
					$wpdb->update( LIR_Core::table(), array( 'statut' => 'corbeille', 'auto_report' => wp_json_encode( $rep ) ), array( 'id' => $id ) );
					LIR_Core::log( 'corbeille', $id );
				}
				$msg = count( $ids ) . ' remise(s) mise(s) à la corbeille. Elles restent récupérables (filtre « Corbeille », puis « Restaurer »).';
				break;
			case 'restaurer':
				foreach ( $ids as $id ) {
					$r = LIR_Core::get( $id );
					if ( ! $r || 'corbeille' !== $r['statut'] ) { continue; }
					$st = $r['auto_report']['statut_avant_corbeille'] ?? 'depose';
					$wpdb->update( LIR_Core::table(), array( 'statut' => isset( LIR_Core::STATUTS[ $st ] ) && 'corbeille' !== $st ? $st : 'depose' ), array( 'id' => $id ) );
					LIR_Core::log( 'restauration', $id );
				}
				$msg = count( $ids ) . ' remise(s) restaurée(s).';
				break;
			default: $msg = 'Choisissez une action groupée.';
		}
		wp_safe_redirect( add_query_arg( 'fwr_msg', rawurlencode( $msg ), $back ) );
		exit;
	}

	/* ---------------------------------------------------------------- réglages */
	public static function page_settings() {
		$s = LIR_Core::settings();
		echo '<div class="wrap fwr-wrap"><h1>Réglages – Remises des exercices</h1>';
		self::notice();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fwr_settings">';
		wp_nonce_field( 'fwr_settings' );
		echo '<table class="form-table"><tr><th>Taille maximale par fichier</th><td><input type="number" min="1" max="100" name="max_mo" value="' . (int) $s['max_mo'] . '"> Mo <p class="description">Limite aussi par la configuration PHP du serveur : ' . esc_html( size_format( wp_max_upload_size() ) ) . '.</p></td></tr>';
		echo '<tr><th>Échelle de notes</th><td><select name="echelle">';
		foreach ( array( 'suisse' => 'Note de 1 à 6 (demi-points)', 'vingt' => 'Note sur 20', 'pourcent' => 'Pourcentage' ) as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['echelle'], $k, false ) . '>' . esc_html( $v ) . '</option>'; }
		echo '</select></td></tr>';
		echo '<tr><th>Détail des points vérifiés</th><td><label><input type="checkbox" name="detail_defaut" value="1"' . checked( (int) $s['detail_defaut'], 1, false ) . '> Coché par défaut lors de la correction</label></td></tr>';
		echo '<tr><th>Message après dépôt</th><td><input type="text" class="large-text" name="message" value="' . esc_attr( $s['message'] ) . '"></td></tr></table>';
		submit_button( 'Enregistrer' );
		echo '</form><h2>🔒 Protection des fichiers d\'exercice</h2><table class="form-table"><tr><th>Mot de passe du suivi des modifications</th><td><code class="fwr-mdp">' . esc_html( LIR_MDP_EXERCICES ) . '</code>'
			. '<p class="description">Les fichiers de départ (données brutes) de la formation Word ont le suivi des modifications activé et verrouillé par ce mot de passe. Les apprentis ne peuvent ni le désactiver, ni accepter ou refuser les modifications : chaque modification garde le nom de son auteur.<br>'
			. 'Pour lever la protection dans Word : <strong>Révision › Restreindre la modification › Arrêter la protection</strong>, puis saisir ce mot de passe. Ce mot de passe est intégré aux fichiers : le changer ici n\'aurait aucun effet sur les fichiers déjà distribués.</p></td></tr></table>';
		echo '<h2>Codes courts</h2><p><code>[fw_remise lecon="3"]</code> : formulaire de dépôt de l\'exercice Word de la leçon 3 (1 à 15) ; <code>[fw_remise lecon="3" formation="excel"]</code> : idem pour Excel (fichier .xlsx) ; <code>[fw_remise lecon="3" formation="powerpoint"]</code> : idem pour PowerPoint (fichier .pptx), avec l\'historique des dépôts et le retour publié.<br><code>[fw_mes_remises]</code> : tableau récapitulatif de tous les exercices de l\'apprenti·e connecté·e.</p>';
		echo '<p>Fichiers stockés dans <code>wp-content/uploads/logiinfo/copies-eleves/</code> (accès direct bloqué ; téléchargement uniquement via WordPress, pour le formateur et l\'auteur du dépôt).</p></div>';
	}
	public static function save_settings() {
		check_admin_referer( 'fwr_settings' );
		if ( ! current_user_can( LI_ADMIN_CAP ) ) { wp_die( 'Accès refusé.' ); }
		update_option( 'fwr_settings', array(
			'max_mo' => max( 1, min( 100, (int) ( $_POST['max_mo'] ?? 15 ) ) ),
			'echelle' => in_array( $_POST['echelle'] ?? '', array( 'suisse', 'vingt', 'pourcent' ), true ) ? sanitize_key( $_POST['echelle'] ) : 'suisse',
			'detail_defaut' => empty( $_POST['detail_defaut'] ) ? 0 : 1,
			'message' => sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) ),
		) );
		wp_safe_redirect( admin_url( 'admin.php?page=fw-remises-reglages&fwr_msg=' . rawurlencode( 'Réglages enregistrés.' ) ) );
		exit;
	}
}
