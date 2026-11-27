<?php
/**
 * Bibliothèque de modèles de travaux (LogiOne 1.54.0).
 *
 * Un modèle est une copie d'un travail sans ses classes, ses dates ni ses dépôts : cours, leçons, grille,
 * consigne, formats, taille des groupes, lien vers le PDF de consigne. « Utiliser ce modèle » crée un
 * nouveau travail en brouillon, à compléter avec les classes et les dates.
 *
 * Stockage : option « logiprojet_modeles » (non chargée automatiquement), aucune table créée ni modifiée.
 * Chaque enseignant voit ses modèles et ceux que ses collègues ont partagés ; un administrateur les voit tous.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Modeles {

	const OPT = 'logiprojet_modeles';
	const MAX = 300;

	private static function all() {
		$all = get_option( self::OPT, array() );
		return is_array( $all ) ? $all : array();
	}

	private static function save_all( $all ) {
		update_option( self::OPT, $all, false );
	}

	private static function err( $msg, $status = 400 ) {
		return new WP_Error( 'logiprojet_modele', $msg, array( 'status' => $status ) );
	}

	private static function can_see( $m ) {
		return LogiProjet_Access::is_admin() || (int) $m['author_id'] === get_current_user_id() || ! empty( $m['shared'] );
	}

	private static function can_edit( $m ) {
		return LogiProjet_Access::is_admin() || (int) $m['author_id'] === get_current_user_id();
	}

	private static function out( $m ) {
		$d = (array) $m['data'];
		return array(
			'id'         => $m['id'],
			'name'       => $m['name'],
			'title'      => (string) ( $d['title'] ?? '' ),
			'module'     => (string) ( $d['module'] ?? '' ),
			'course'     => (string) ( $d['course']['title'] ?? '' ),
			'lessons'    => count( (array) ( $d['lessons'] ?? array() ) ),
			'criteres'   => count( (array) ( $d['grille'] ?? array() ) ),
			'points'     => LogiProjet_Grading::total( (array) ( $d['grille'] ?? array() ) ),
			'group_size' => (int) ( $d['group_size'] ?? 2 ),
			'graded'     => ! array_key_exists( 'graded', $d ) || ! empty( $d['graded'] ),
			'formats'    => LogiProjet_Formats::label( LogiProjet_Formats::clean( $d['formats'] ?? array( 'pptx' ) ) ),
			'has_pdf'    => ! empty( $d['consigne_url'] ),
			'author'     => LogiProjet_Sensei::user_label( $m['author_id'] ),
			'mine'       => (int) $m['author_id'] === get_current_user_id(),
			'can_edit'   => self::can_edit( $m ),
			'shared'     => ! empty( $m['shared'] ),
			'created'    => get_date_from_gmt( $m['created'], 'Y-m-d\TH:i' ),
			'uses'       => (int) ( $m['uses'] ?? 0 ),
		);
	}

	public static function rest_list() {
		$out = array();
		foreach ( self::all() as $m ) {
			if ( self::can_see( $m ) ) {
				$out[] = self::out( $m );
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return ( $b['mine'] <=> $a['mine'] ) ?: strcasecmp( $a['module'] . ' ' . $a['name'], $b['module'] . ' ' . $b['name'] );
			}
		);
		return $out;
	}

	/** Enregistre un travail comme modèle. */
	public static function rest_create( $req ) {
		$t = LogiProjet_Store::travail( (int) $req['id'] );
		if ( ! $t ) {
			return self::err( 'Travail introuvable.', 404 );
		}
		if ( ! LogiProjet_Access::can_manage_travail( $t ) ) {
			return self::err( 'Ce travail appartient à un autre enseignant.', 403 );
		}
		$all = self::all();
		if ( count( $all ) >= self::MAX ) {
			return self::err( 'La bibliothèque est pleine (' . self::MAX . ' modèles) : supprimez d’anciens modèles.' );
		}
		$p    = (array) $req->get_json_params();
		$name = mb_substr( sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 0, 120 );
		$data = LogiProjet_Transfer::export( $t );
		// Le modèle ne garde ni les classes, ni les dates, ni le PDF lui-même (son lien suffit).
		unset( $data['consigne_pdf'], $data['exported'], $data['source'] );
		$data['groups']     = array();
		$data['date_debut'] = null;
		$data['due_at']     = null;
		$id                 = strtolower( wp_generate_password( 10, false, false ) );
		$all[ $id ]         = array(
			'id'        => $id,
			'name'      => '' !== $name ? $name : $t['title'],
			'author_id' => get_current_user_id(),
			'shared'    => ! empty( $p['shared'] ),
			'created'   => current_time( 'mysql', true ),
			'uses'      => 0,
			'data'      => $data,
		);
		self::save_all( $all );
		return self::out( $all[ $id ] );
	}

	public static function rest_update( $req ) {
		$all = self::all();
		$id  = sanitize_key( (string) $req['id'] );
		if ( ! isset( $all[ $id ] ) || ! self::can_see( $all[ $id ] ) ) {
			return self::err( 'Modèle introuvable.', 404 );
		}
		if ( ! self::can_edit( $all[ $id ] ) ) {
			return self::err( 'Seul l’auteur du modèle peut le modifier.', 403 );
		}
		$p = (array) $req->get_json_params();
		if ( isset( $p['name'] ) ) {
			$name = mb_substr( sanitize_text_field( (string) $p['name'] ), 0, 120 );
			if ( '' !== $name ) {
				$all[ $id ]['name'] = $name;
			}
		}
		if ( array_key_exists( 'shared', $p ) ) {
			$all[ $id ]['shared'] = ! empty( $p['shared'] );
		}
		self::save_all( $all );
		return self::out( $all[ $id ] );
	}

	public static function rest_delete( $req ) {
		$all = self::all();
		$id  = sanitize_key( (string) $req['id'] );
		if ( ! isset( $all[ $id ] ) || ! self::can_see( $all[ $id ] ) ) {
			return self::err( 'Modèle introuvable.', 404 );
		}
		if ( ! self::can_edit( $all[ $id ] ) ) {
			return self::err( 'Seul l’auteur du modèle peut le supprimer.', 403 );
		}
		unset( $all[ $id ] );
		self::save_all( $all );
		return array( 'deleted' => true );
	}

	/** Crée un nouveau travail en brouillon à partir du modèle. */
	public static function rest_use( $req ) {
		$all = self::all();
		$id  = sanitize_key( (string) $req['id'] );
		if ( ! isset( $all[ $id ] ) || ! self::can_see( $all[ $id ] ) ) {
			return self::err( 'Modèle introuvable.', 404 );
		}
		$res = LogiProjet_Transfer::import( $all[ $id ]['data'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['data']['status']    = 'brouillon';
		$res['data']['group_ids'] = array();
		$fake = new WP_REST_Request( 'POST' );
		$fake->set_header( 'Content-Type', 'application/json' );
		$fake->set_body( wp_json_encode( $res['data'] ) );
		$out = LogiProjet_Rest::create_travail( $fake );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$all[ $id ]['uses'] = (int) ( $all[ $id ]['uses'] ?? 0 ) + 1;
		self::save_all( $all );
		$out['warnings'] = $res['warnings'];
		$out['modele']   = $all[ $id ]['name'];
		return $out;
	}
}
