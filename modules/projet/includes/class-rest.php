<?php
/**
 * API REST de LogiProjet (espace de noms logiprojet/v1).
 * Toutes les routes exigent une connexion ; les droits sont vérifiés à chaque appel.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Rest {

	const NS = 'logiprojet/v1';

	public static function register_routes() {
		$teacher = array( __CLASS__, 'perm_teacher' );
		$logged  = array( __CLASS__, 'perm_logged' );
		$id      = array(
			'id' => array(
				'validate_callback' => function ( $v ) {
					return is_numeric( $v );
				},
			),
		);

		register_rest_route( self::NS, '/me', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'me' ), 'permission_callback' => $logged ) );

		register_rest_route( self::NS, '/courses', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'courses' ), 'permission_callback' => $teacher ) );
		register_rest_route( self::NS, '/courses/(?P<id>\d+)/lessons', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'lessons' ), 'permission_callback' => $teacher, 'args' => $id ) );
		register_rest_route( self::NS, '/groups', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'groups' ), 'permission_callback' => $teacher ) );
		register_rest_route( self::NS, '/consigne-modele', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'consigne_modele' ), 'permission_callback' => $teacher ) );
		register_rest_route( self::NS, '/grille-modele', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'grille_modele' ), 'permission_callback' => $teacher ) );

		register_rest_route(
			self::NS,
			'/travaux',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'list_travaux' ), 'permission_callback' => $teacher ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_travail' ), 'permission_callback' => $teacher ),
			)
		);
		register_rest_route(
			self::NS,
			'/travaux/(?P<id>\d+)',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_travail' ), 'permission_callback' => $teacher, 'args' => $id ),
				array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'update_travail' ), 'permission_callback' => $teacher, 'args' => $id ),
				array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_travail' ), 'permission_callback' => $teacher, 'args' => $id ),
			)
		);
		register_rest_route( self::NS, '/travaux/import', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'import_travail' ), 'permission_callback' => $teacher ) );
		register_rest_route( self::NS, '/travaux/(?P<id>\d+)/correction', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'import_correction' ), 'permission_callback' => $teacher, 'args' => $id ) );
		register_rest_route( self::NS, '/travaux/(?P<id>\d+)/export', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'export_travail' ), 'permission_callback' => $teacher, 'args' => $id ) );
		register_rest_route(
			self::NS,
			'/depots/(?P<id>\d+)',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_depot' ), 'permission_callback' => $teacher, 'args' => $id ),
				array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'update_depot' ), 'permission_callback' => $teacher, 'args' => $id ),
				array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_depot' ), 'permission_callback' => $teacher, 'args' => $id ),
			)
		);
		register_rest_route( self::NS, '/depots/(?P<id>\d+)/corriger', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'corriger' ), 'permission_callback' => $teacher, 'args' => $id ) );
		register_rest_route( self::NS, '/depots/(?P<id>\d+)/fichier', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'fichier' ), 'permission_callback' => $logged, 'args' => $id ) );

		register_rest_route( self::NS, '/eleve/travaux', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'eleve_travaux' ), 'permission_callback' => $logged ) );
		register_rest_route( self::NS, '/eleve/travaux/(?P<id>\d+)/camarades', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'eleve_camarades' ), 'permission_callback' => $logged, 'args' => $id ) );
		register_rest_route(
			self::NS,
			'/eleve/travaux/(?P<id>\d+)/groupe',
			array(
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'eleve_groupe' ), 'permission_callback' => $logged, 'args' => $id ),
				array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'eleve_quitter' ), 'permission_callback' => $logged, 'args' => $id ),
			)
		);
		register_rest_route( self::NS, '/eleve/travaux/(?P<id>\d+)/brouillon', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'eleve_brouillon' ), 'permission_callback' => $logged, 'args' => $id ) );
		register_rest_route( self::NS, '/eleve/travaux/(?P<id>\d+)/remettre', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'eleve_remettre' ), 'permission_callback' => $logged, 'args' => $id ) );
		register_rest_route( self::NS, '/eleve/travaux/(?P<id>\d+)/depot', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'eleve_depot' ), 'permission_callback' => $logged, 'args' => $id ) );
	}

	public static function perm_logged() {
		return is_user_logged_in();
	}

	public static function perm_teacher() {
		return LogiProjet_Access::is_teacher();
	}

	private static function err( $msg, $status = 400, $code = 'logiprojet_error' ) {
		return new WP_Error( $code, $msg, array( 'status' => $status ) );
	}

	/* ================= Formats de sortie ================= */

	private static function local_date( $gmt ) {
		if ( ! $gmt ) {
			return null;
		}
		return get_date_from_gmt( $gmt, 'Y-m-d\TH:i' );
	}

	public static function travail_out( $t, $teacher = true ) {
		$total = LogiProjet_Grading::total( $t['grille'] );
		$out   = array(
			'id'           => $t['id'],
			'title'        => $t['title'],
			'module'       => $t['module'],
			'course_id'    => $t['course_id'],
			'lessons'      => (array) $t['lessons'],
			'grille'       => (array) $t['grille'],
			'total'        => $total,
			'conversion'   => LogiProjet_Grading::conversion( $total ),
			'consignes'    => (string) $t['consignes'],
			'consigne_url' => (string) $t['consigne_url'],
			'due_at'       => self::local_date( $t['due_at'] ),
			'date_debut'   => self::local_date( $t['date_debut'] ?? null ),
			'consigne'     => $t['consigne'] ? $t['consigne'] : null,
			'vars'         => LogiProjet_Consigne::vars( $t ),
			'course_title' => $t['course_id'] ? html_entity_decode( get_the_title( $t['course_id'] ), ENT_QUOTES, 'UTF-8' ) : '',
			'group_titles' => self::group_titles( $t ),
			'due_passed'   => $t['due_at'] ? strtotime( $t['due_at'] . ' UTC' ) < time() : false,
			'due_in'       => $t['due_at'] ? strtotime( $t['due_at'] . ' UTC' ) - time() : null,
			'retard_actuel'=> $t['due_at'] && strtotime( $t['due_at'] . ' UTC' ) < time() ? (int) ceil( ( time() - strtotime( $t['due_at'] . ' UTC' ) ) / DAY_IN_SECONDS ) : 0,
			'oral_bonus'   => (bool) $t['oral_bonus'],
			'allow_solo'   => (bool) $t['allow_solo'],
			'group_size'   => (int) $t['group_size'],
			'graded'       => (bool) $t['graded'],
			'formats'      => (array) $t['formats'],
			'formats_label'=> LogiProjet_Formats::label( $t['formats'] ),
			'accept'       => '.' . implode( ',.', LogiProjet_Formats::extensions( $t['formats'] ) ),
			'status'       => $t['status'],
		);
		if ( $teacher ) {
			$out['group_ids'] = array_map( 'intval', (array) $t['group_ids'] );
			$out['counts']    = LogiProjet_Store::counts( $t['id'] );
			$out['author']    = LogiProjet_Sensei::user_label( $t['author_id'] );
			$out['mine']      = (int) $t['author_id'] === get_current_user_id();
			$out['print_url'] = self::print_url( array( 'travail' => $t['id'] ) );
			$out['csv_url']   = self::print_url( array( 'travail' => $t['id'], 'format' => 'csv' ) );
			$out['claude_export_url'] = LogiProjet_Claude::export_url( $t['id'] );
		}
		return $out;
	}

	/** Adresse non échappée (elle passe en JSON) : wp_nonce_url() la rendrait inutilisable. */
	private static function print_url( $args ) {
		$args['action']   = 'logiprojet_imprimer';
		$args['_wpnonce'] = wp_create_nonce( 'logiprojet_imprimer' );
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	private static function group_titles( $t ) {
		$out = array();
		foreach ( (array) $t['group_ids'] as $g ) {
			if ( 'group' === get_post_type( $g ) ) {
				$out[] = html_entity_decode( get_the_title( $g ), ENT_QUOTES, 'UTF-8' );
			}
		}
		return $out;
	}

	private static function members( $d ) {
		$m = array();
		foreach ( (array) $d['members'] as $u ) {
			if ( $u ) {
				$m[] = array(
					'id'   => (int) $u,
					'name' => LogiProjet_Sensei::user_label( $u ),
				);
			}
		}
		return $m;
	}

	public static function depot_out( $d, $t, $teacher = true ) {
		$out = array(
			'id'           => $d['id'],
			'travail_id'   => $d['travail_id'],
			'members'      => self::members( $d ),
			'file_name'    => $d['file_name'],
			'file_size'    => $d['file_size'],
			'submitted_at' => self::local_date( $d['submitted_at'] ),
			'submitted_by' => LogiProjet_Sensei::user_label( $d['submitted_by'] ),
			'status'       => 'valide' === $d['status'] || 'groupe' === $d['status'] ? $d['status'] : ( $teacher ? $d['status'] : 'depose' ),
			'has_file'     => '' !== (string) $d['file_path'],
			'file_url'     => $teacher ? add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( self::NS . '/depots/' . $d['id'] . '/fichier' ) ) : '',
			'draft'        => '' === (string) ( $d['draft_path'] ?? '' ) ? null : array(
				'name' => $d['draft_name'],
				'size' => (int) $d['draft_size'],
				'by'   => LogiProjet_Sensei::user_label( $d['draft_by'] ),
				'at'   => self::local_date( $d['draft_at'] ),
				'url'  => add_query_arg( array( 'version' => 'travail', '_wpnonce' => wp_create_nonce( 'wp_rest' ) ), rest_url( self::NS . '/depots/' . $d['id'] . '/fichier' ) ),
			),
			'slide_count'  => (int) ( $d['extract']['slide_count'] ?? 0 ),
			'ext'          => strtolower( pathinfo( (string) $d['file_name'], PATHINFO_EXTENSION ) ),
			'retard_jours' => LogiProjet_Grading::late_days( $t, $d ),
			'retard_excuse'=> ! empty( $d['late_excused'] ),
		);
		if ( $teacher ) {
			$out += array(
				'extract'      => $d['extract'],
				'ai'           => $d['ai'],
				'ai_error'     => $d['ai_error'],
				'ai_model'     => $d['ai_model'],
				'scores'       => $d['scores'] ? $d['scores'] : new stdClass(),
				'oral'         => $d['oral'] ? $d['oral'] : new stdClass(),
				'feedback'     => $d['feedback'] ? $d['feedback'] : new stdClass(),
				'points'       => $d['points'],
				'note'         => $d['note'],
				'note_finale'  => $d['note_finale'],
				'validated_at' => self::local_date( $d['validated_at'] ),
				'print_url'    => self::print_url( array( 'travail' => $t['id'], 'depot' => $d['id'] ) ),
			);
		} elseif ( 'valide' === $d['status'] ) {
			$out += array(
				'scores'      => $d['scores'],
				'oral'        => $d['oral'],
				'feedback'    => $d['feedback'],
				'points'      => $d['points'],
				'note'        => $d['note'],
				'note_finale' => $d['note_finale'],
			);
		}
		return $out;
	}

	private static function managed_travail( $id ) {
		$t = LogiProjet_Store::travail( $id );
		if ( ! $t ) {
			return self::err( 'Travail introuvable.', 404 );
		}
		if ( ! LogiProjet_Access::can_manage_travail( $t ) ) {
			return self::err( 'Ce travail appartient à un autre enseignant.', 403 );
		}
		return $t;
	}

	private static function managed_depot( $id ) {
		$d = LogiProjet_Store::depot( $id );
		if ( ! $d ) {
			return self::err( 'Dépôt introuvable.', 404 );
		}
		$t = self::managed_travail( $d['travail_id'] );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		return array( $d, $t );
	}

	/* ================= Général ================= */

	public static function me() {
		$u = wp_get_current_user();
		return array(
			'id'          => $u->ID,
			'first_name'  => $u->first_name ? $u->first_name : $u->display_name,
			'is_teacher'  => LogiProjet_Access::is_teacher(),
			'is_admin'    => LogiProjet_Access::is_admin(),
			'ai_ready'    => LogiProjet_AI::is_ready(),
			'ai_label'    => LogiProjet_AI::PROVIDERS[ LogiProjet_AI::provider() ] . ' · ' . LogiProjet_AI::model( LogiProjet_AI::provider() ),
			'max_mb'      => LogiProjet_Settings::max_mb(),
			'settings'    => current_user_can( 'manage_options' ) ? admin_url( 'options-general.php?page=logiprojet' ) : '',
			'skill_url'   => LogiProjet_Access::is_teacher() ? LogiProjet_Skill::url() : '',
			'correction_skill_url' => LogiProjet_Access::is_teacher() ? LogiProjet_Skill::url( 'logiprojet-correction' ) : '',
		);
	}

	public static function courses() {
		return LogiProjet_Sensei::courses();
	}

	public static function lessons( $req ) {
		return LogiProjet_Sensei::lessons( (int) $req['id'] );
	}

	public static function groups() {
		return LogiProjet_Sensei::groups();
	}

	public static function consigne_modele( $req ) {
		$graded = $req->get_param( 'graded' );
		return LogiProjet_Consigne::modele( count( (array) $req->get_param( 'lessons' ) ), (int) ( $req->get_param( 'group_size' ) ?: 2 ), (array) ( $req->get_param( 'formats' ) ?: array( 'pptx' ) ), null === $graded ? true : (bool) $graded );
	}

	public static function grille_modele( $req ) {
		$lessons = self::clean_lessons( $req->get_param( 'lessons' ) );
		return LogiProjet_Grading::default_grille( $lessons, sanitize_text_field( (string) $req->get_param( 'module' ) ) );
	}

	/* ================= Travaux (enseignant) ================= */

	/**
	 * Chaque enseignant ne voit que les travaux qu'il a créés.
	 * Un administrateur peut afficher aussi ceux des autres enseignants (?tous=1), pour dépanner.
	 */
	public static function list_travaux( $req = null ) {
		$tous = $req && LogiProjet_Access::is_admin() && ! empty( $req->get_param( 'tous' ) );
		$args = $tous ? array() : array( 'author_id' => get_current_user_id() );
		return array_map(
			function ( $t ) {
				return self::travail_out( $t );
			},
			LogiProjet_Store::travaux( $args )
		);
	}

	private static function clean_lessons( $raw ) {
		$out = array();
		foreach ( (array) $raw as $l ) {
			$lid = absint( $l['id'] ?? 0 );
			if ( $lid && 'lesson' === get_post_type( $lid ) ) {
				$title = sanitize_text_field( (string) ( $l['title'] ?? '' ) );
				$out[] = array(
					'id'    => $lid,
					'title' => '' !== $title ? $title : LogiProjet_Sensei::clean_lesson_title( get_the_title( $lid ) ),
				);
			}
		}
		return $out;
	}

	private static function clean_grille( $raw ) {
		$out  = array();
		$seen = array();
		$n    = 0;
		foreach ( (array) $raw as $c ) {
			$label = sanitize_text_field( (string) ( $c['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$id = sanitize_key( (string) ( $c['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				do {
					$id = 'c' . ( ++$n ) . 'x';
				} while ( isset( $seen[ $id ] ) );
			}
			$seen[ $id ] = true;
			$item        = array(
				'id'    => $id,
				'bloc'  => sanitize_text_field( (string) ( $c['bloc'] ?? '' ) ),
				'label' => mb_substr( $label, 0, 300 ),
			);
			if ( ! empty( $c['lesson_id'] ) ) {
				$item['lesson_id'] = absint( $c['lesson_id'] );
			}
			$out[] = $item;
			if ( count( $out ) >= 40 ) {
				break;
			}
		}
		return $out;
	}

	private static function travail_input( $req, $existing = null ) {
		$p    = $req->get_json_params();
		$p    = is_array( $p ) ? $p : array();
		$data = array();

		$title = sanitize_text_field( (string) ( $p['title'] ?? ( $existing['title'] ?? '' ) ) );
		if ( '' === $title ) {
			return self::err( 'Donnez un titre au travail.' );
		}
		$data['title']        = mb_substr( $title, 0, 200 );
		$data['module']       = mb_substr( sanitize_text_field( (string) ( $p['module'] ?? ( $existing['module'] ?? '' ) ) ), 0, 40 );
		$course               = absint( $p['course_id'] ?? ( $existing['course_id'] ?? 0 ) );
		$data['course_id']    = ( $course && 'course' === get_post_type( $course ) ) ? $course : 0;
		$data['lessons']      = self::clean_lessons( $p['lessons'] ?? ( $existing['lessons'] ?? array() ) );
		$data['grille']       = self::clean_grille( $p['grille'] ?? ( $existing['grille'] ?? array() ) );
		$data['consigne']     = LogiProjet_Consigne::sanitize( array_key_exists( 'consigne', $p ) ? $p['consigne'] : ( $existing['consigne'] ?? null ) );
		$data['consignes']    = sanitize_textarea_field( (string) ( $p['consignes'] ?? ( $existing['consignes'] ?? '' ) ) );
		$data['consigne_url'] = esc_url_raw( (string) ( $p['consigne_url'] ?? ( $existing['consigne_url'] ?? '' ) ), array( 'http', 'https' ) );

		$groups = array();
		foreach ( (array) ( $p['group_ids'] ?? ( $existing['group_ids'] ?? array() ) ) as $g ) {
			$g = absint( $g );
			if ( $g && 'group' === get_post_type( $g ) ) {
				$groups[] = $g;
			}
		}
		$data['group_ids'] = array_values( array_unique( $groups ) );

		$due = array_key_exists( 'due_at', $p ) ? (string) $p['due_at'] : ( $existing ? self::local_date( $existing['due_at'] ) : '' );
		if ( '' !== (string) $due ) {
			$dt = DateTime::createFromFormat( 'Y-m-d\TH:i', substr( $due, 0, 16 ), wp_timezone() );
			if ( ! $dt ) {
				return self::err( 'La date limite n’est pas valable.' );
			}
			$data['due_at'] = $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		} else {
			$data['due_at'] = null;
		}
		$deb = array_key_exists( 'date_debut', $p ) ? (string) $p['date_debut'] : ( $existing ? (string) self::local_date( $existing['date_debut'] ?? null ) : '' );
		$data['date_debut'] = null;
		if ( '' !== $deb ) {
			$dt = DateTime::createFromFormat( 'Y-m-d\TH:i', substr( $deb . ( 10 === strlen( $deb ) ? 'T08:00' : '' ), 0, 16 ), wp_timezone() );
			if ( ! $dt ) {
				return self::err( 'La date de début n’est pas valable.' );
			}
			$data['date_debut'] = $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		}
		if ( $data['consigne'] ) {
			$data['consignes'] = LogiProjet_Consigne::to_text( $data['consigne'], array_merge( $data, array( 'due_at' => $data['due_at'] ) ) );
		}
		$data['oral_bonus'] = ! empty( $p['oral_bonus'] ?? ( $existing['oral_bonus'] ?? true ) ) ? 1 : 0;
		$data['group_size'] = max( 1, min( 6, (int) ( $p['group_size'] ?? ( $existing['group_size'] ?? 2 ) ) ) );
		$data['graded']     = ! empty( $p['graded'] ?? ( $existing['graded'] ?? true ) ) ? 1 : 0;
		$data['formats']    = LogiProjet_Formats::clean( $p['formats'] ?? ( $existing['formats'] ?? array( 'pptx' ) ) );
		$data['allow_solo'] = ! empty( $p['allow_solo'] ?? ( $existing['allow_solo'] ?? false ) ) ? 1 : 0;
		$status             = sanitize_key( (string) ( $p['status'] ?? ( $existing['status'] ?? 'brouillon' ) ) );
		$data['status']     = in_array( $status, array( 'brouillon', 'ouvert', 'ferme' ), true ) ? $status : 'brouillon';
		if ( 'brouillon' !== $data['status'] && ! $data['grille'] && $data['graded'] ) {
			return self::err( 'La grille d’évaluation est vide : ajoutez au moins un critère avant d’ouvrir le travail.' );
		}
		return $data;
	}

	public static function create_travail( $req ) {
		$data = self::travail_input( $req );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$data['author_id'] = get_current_user_id();
		$id                = LogiProjet_Store::save_travail( $data );
		return self::travail_out( LogiProjet_Store::travail( $id ) );
	}

	public static function import_travail( $req ) {
		$res = LogiProjet_Transfer::import( $req->get_json_params() );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$fake = new WP_REST_Request( 'POST' );
		$fake->set_header( 'Content-Type', 'application/json' );
		$fake->set_body( wp_json_encode( $res['data'] ) );
		$out = self::create_travail( $fake );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$out['warnings'] = $res['warnings'];
		return $out;
	}

	public static function import_correction( $req ) {
		$t = self::managed_travail( (int) $req['id'] );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		return LogiProjet_Claude::import( $t, $req->get_json_params() );
	}

	public static function export_travail( $req ) {
		$t = self::managed_travail( (int) $req['id'] );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		return LogiProjet_Transfer::export( $t );
	}

	public static function get_travail( $req ) {
		$t = self::managed_travail( (int) $req['id'] );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$out           = self::travail_out( $t );
		list( $titles, $of ) = self::class_map( $t );
		$out['depots'] = array_map(
			function ( $d ) use ( $t, $titles, $of ) {
				$o = self::depot_out( $d, $t );
				unset( $o['extract'] );
				// Classe du groupe : celle du premier membre qui appartient à une classe du travail.
				$o['classe'] = '';
				foreach ( (array) $d['members'] as $m ) {
					if ( isset( $of[ (int) $m ] ) ) {
						$o['classe'] = $titles[ $of[ (int) $m ] ];
						break;
					}
				}
				return $o;
			},
			LogiProjet_Store::depots( $t['id'] )
		);
		$out['sans_groupe'] = self::without_group( $t );
		return $out;
	}

	/**
	 * Répartition par classe : pour chaque classe (groupe Sensei) visée par le travail, les apprentis
	 * qui ne sont encore dans aucun groupe. Un apprenti qui a choisi de travailler seul·e compte comme réparti.
	 * Travail ouvert à toutes les classes : toutes les classes qui ont des apprentis.
	 */
	/** Classes visées par le travail (id → nom, triées par nom) et classe de chaque apprenti (id apprenti → id classe). */
	private static function class_map( $t ) {
		$tg     = array_map( 'absint', (array) $t['group_ids'] );
		$titles = array();
		foreach ( LogiProjet_Sensei::groups() as $g ) {
			if ( ! $tg || in_array( $g['id'], $tg, true ) ) {
				$titles[ $g['id'] ] = $g['title'];
			}
		}
		$of = array();
		foreach ( $titles as $gid => $title ) {
			foreach ( LogiProjet_Sensei::group_students( array( $gid ) ) as $sid ) {
				if ( ! isset( $of[ (int) $sid ] ) ) {
					$of[ (int) $sid ] = $gid;
				}
			}
		}
		return array( $titles, $of );
	}

	private static function without_group( $t ) {
		list( $titles ) = self::class_map( $t );
		$placed = array();
		$solo   = array();
		foreach ( LogiProjet_Store::depots( $t['id'] ) as $d ) {
			$m = array_map( 'intval', (array) $d['members'] );
			$placed = array_merge( $placed, $m );
			if ( 1 === count( $m ) && (int) $t['group_size'] > 1 ) {
				$solo[] = $m[0];
			}
		}
		$out = array();
		foreach ( $titles as $gid => $title ) {
			$ids = array_filter(
				LogiProjet_Sensei::group_students( array( $gid ) ),
				function ( $id ) {
					return (bool) get_userdata( $id );
				}
			);
			if ( ! $ids ) {
				continue;
			}
			$sans = array();
			foreach ( $ids as $id ) {
				if ( ! in_array( (int) $id, $placed, true ) ) {
					$sans[] = LogiProjet_Sensei::user_label( $id );
				}
			}
			natcasesort( $sans );
			$out[] = array(
				'id'     => (int) $gid,
				'classe' => $title,
				'total'  => count( $ids ),
				'seuls'  => count( array_intersect( array_map( 'intval', $ids ), $solo ) ),
				'sans'   => array_values( $sans ),
			);
		}
		return $out;
	}

	public static function update_travail( $req ) {
		$t = self::managed_travail( (int) $req['id'] );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$data = self::travail_input( $req, $t );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		LogiProjet_Store::save_travail( $data, $t['id'] );
		$t = LogiProjet_Store::travail( $t['id'] );
		// Les notes dépendent de la grille : on les recalcule.
		foreach ( LogiProjet_Store::depots( $t['id'] ) as $d ) {
			if ( $d['scores'] ) {
				$c = LogiProjet_Grading::compute( $t, $d['scores'], (array) $d['oral'], $d );
				LogiProjet_Store::save_depot( array( 'points' => $c['points'], 'note' => $c['note'], 'note_finale' => $c['note_finale'] ), $d['id'] );
			}
		}
		return self::get_travail( $req );
	}

	public static function delete_travail( $req ) {
		$t = self::managed_travail( (int) $req['id'] );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		LogiProjet_Store::delete_travail( $t['id'] );
		return array( 'deleted' => true );
	}

	/* ================= Dépôts (enseignant) ================= */

	public static function get_depot( $req ) {
		$r = self::managed_depot( (int) $req['id'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		list( $d, $t ) = $r;
		if ( ! $d['extract'] ) {
			$d = self::ensure_extract( $d );
		}
		return self::depot_out( $d, $t );
	}

	private static function ensure_extract( $d ) {
		$path = LogiProjet_Store::file_abspath( $d['file_path'] );
		if ( ! $path || ! file_exists( $path ) || 'pptx' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return $d;
		}
		$x = LogiProjet_Pptx::extract( $path );
		if ( ! is_wp_error( $x ) ) {
			LogiProjet_Store::save_depot( array( 'extract' => $x ), $d['id'] );
			$d['extract'] = $x;
		}
		return $d;
	}

	public static function corriger( $req ) {
		$r = self::managed_depot( (int) $req['id'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		list( $d, $t ) = $r;
		if ( 'valide' === $d['status'] ) {
			return self::err( 'Ce travail est déjà validé. Retirez la validation avant de relancer l’IA.', 409 );
		}
		if ( '' === (string) $d['file_path'] ) {
			return self::err( 'Ce groupe n’a pas encore déposé de fichier.' );
		}
		if ( ! $t['grille'] ) {
			return self::err( 'La grille de ce travail est vide.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 240 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$path = LogiProjet_Store::file_abspath( $d['file_path'] );
		if ( ! $path || ! file_exists( $path ) ) {
			return self::err( 'Le fichier est introuvable sur le serveur.', 404 );
		}
		if ( 'pptx' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return self::err( 'L’IA ne sait corriger que les PowerPoint (.pptx). Notez ce dépôt vous-même dans la grille.' );
		}
		$x = LogiProjet_Pptx::extract( $path );
		if ( is_wp_error( $x ) ) {
			LogiProjet_Store::save_depot( array( 'status' => 'erreur', 'ai_error' => $x->get_error_message() ), $d['id'] );
			return self::err( $x->get_error_message() );
		}
		$d['extract'] = $x;
		$res          = LogiProjet_AI::grade( $t, $d );
		if ( is_wp_error( $res ) ) {
			LogiProjet_Store::save_depot( array( 'extract' => $x, 'status' => 'erreur', 'ai_error' => $res->get_error_message() ), $d['id'] );
			return self::err( $res->get_error_message(), 502 );
		}
		$model = $res['model'];
		unset( $res['model'] );
		// La proposition de l'IA remplit la grille de l'enseignant ; il la corrige ensuite.
		$scores = array();
		foreach ( $res['criteres'] as $cid => $c ) {
			$scores[ $cid ] = array(
				'score'   => $c['score'],
				'comment' => $c['justification'],
				'ai'      => $c['score'],
			);
		}
		$feedback = array(
			'points_forts' => implode( "\n", $res['points_forts'] ),
			'piste'        => $res['piste'],
		);
		$c = LogiProjet_Grading::compute( $t, $scores, (array) $d['oral'], $d );
		LogiProjet_Store::save_depot(
			array(
				'extract'     => $x,
				'ai'          => $res,
				'ai_error'    => null,
				'ai_model'    => $model,
				'scores'      => $scores,
				'feedback'    => $feedback,
				'status'      => 'propose',
				'points'      => $c['points'],
				'note'        => $c['note'],
				'note_finale' => $c['note_finale'],
			),
			$d['id']
		);
		return self::depot_out( LogiProjet_Store::depot( $d['id'] ), $t );
	}

	public static function update_depot( $req ) {
		$r = self::managed_depot( (int) $req['id'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		list( $d, $t ) = $r;
		if ( 'groupe' === $d['status'] ) {
			return self::err( 'Ce groupe n’a pas encore remis son travail : il n’y a rien à corriger.' );
		}
		$p             = (array) $req->get_json_params();
		$action        = sanitize_key( (string) ( $p['action'] ?? 'save' ) );
		if ( 'rendre' === $action ) {
			// Rendre le travail au groupe : le fichier remis redevient le fichier de travail, la correction est effacée.
			if ( 'valide' === $d['status'] ) {
				return self::err( 'Retirez d’abord la publication de la correction.', 409 );
			}
			if ( $d['draft_path'] ) {
				$old = LogiProjet_Store::file_abspath( $d['draft_path'] );
				if ( $old && file_exists( $old ) ) {
					wp_delete_file( $old );
				}
			}
			LogiProjet_Store::save_depot(
				array(
					'draft_path'  => $d['file_path'],
					'draft_name'  => $d['file_name'],
					'draft_size'  => (int) $d['file_size'],
					'draft_by'    => (int) $d['submitted_by'],
					'draft_at'    => $d['submitted_at'],
					'file_path'   => '',
					'file_name'   => '',
					'file_size'   => 0,
					'status'      => 'groupe',
					'extract'     => null,
					'ai'          => null,
					'ai_error'    => null,
					'ai_model'    => '',
					'scores'      => null,
					'feedback'    => null,
					'points'      => null,
					'note'        => null,
					'note_finale' => null,
				),
				$d['id']
			);
			return self::depot_out( LogiProjet_Store::depot( $d['id'] ), $t );
		}

		$scores = (array) $d['scores'];
		if ( isset( $p['scores'] ) && is_array( $p['scores'] ) ) {
			$scores = array();
			foreach ( (array) $t['grille'] as $g ) {
				$in = $p['scores'][ $g['id'] ] ?? null;
				if ( ! is_array( $in ) ) {
					continue;
				}
				$score = ( isset( $in['score'] ) && '' !== $in['score'] && null !== $in['score'] ) ? max( 0, min( 2, (int) $in['score'] ) ) : null;
				$item  = array(
					'score'   => $score,
					'comment' => mb_substr( sanitize_textarea_field( (string) ( $in['comment'] ?? '' ) ), 0, 600 ),
				);
				if ( isset( $d['scores'][ $g['id'] ]['ai'] ) ) {
					$item['ai'] = $d['scores'][ $g['id'] ]['ai'];
				}
				$scores[ $g['id'] ] = $item;
			}
		}
		$oral = (array) $d['oral'];
		if ( isset( $p['oral'] ) && is_array( $p['oral'] ) ) {
			$oral = array( 'presented' => ! empty( $p['oral']['presented'] ) );
			foreach ( array_keys( LogiProjet_Grading::ORAL_CRITERIA ) as $k ) {
				$oral[ $k ] = isset( $p['oral'][ $k ] ) && '' !== $p['oral'][ $k ] && null !== $p['oral'][ $k ] ? max( 0, min( 2, (int) $p['oral'][ $k ] ) ) : null;
			}
		}
		$feedback = (array) $d['feedback'];
		if ( isset( $p['feedback'] ) && is_array( $p['feedback'] ) ) {
			$feedback = array(
				'points_forts' => mb_substr( sanitize_textarea_field( (string) ( $p['feedback']['points_forts'] ?? '' ) ), 0, 1500 ),
				'piste'        => mb_substr( sanitize_textarea_field( (string) ( $p['feedback']['piste'] ?? '' ) ), 0, 1500 ),
			);
		}
		if ( isset( $p['retard_excuse'] ) ) {
			$d['late_excused'] = ! empty( $p['retard_excuse'] ) ? 1 : 0;
		}
		$c    = LogiProjet_Grading::compute( $t, $scores, $oral, $d );
		$save = array(
			'late_excused' => (int) ! empty( $d['late_excused'] ),
			'scores'      => $scores,
			'oral'        => $oral,
			'feedback'    => $feedback,
			'points'      => $c['points'],
			'note'        => $c['note'],
			'note_finale' => $c['note_finale'],
		);
		if ( 'validate' === $action ) {
			if ( ! $c['complete'] && $t['graded'] ) {
				return self::err( 'Notez tous les critères (0, 1 ou 2) avant de valider.' );
			}
			$save['status']       = 'valide';
			$save['validated_at'] = LogiProjet_Store::now();
			$save['validated_by'] = get_current_user_id();
		} elseif ( 'unvalidate' === $action ) {
			$save['status']       = $d['ai'] ? 'propose' : 'depose';
			$save['validated_at'] = null;
		} elseif ( 'valide' !== $d['status'] && 'erreur' !== $d['status'] && $c['points'] !== null && ! $d['ai'] ) {
			$save['status'] = 'propose';
		}
		LogiProjet_Store::save_depot( $save, $d['id'] );
		return self::depot_out( LogiProjet_Store::depot( $d['id'] ), $t );
	}

	public static function delete_depot( $req ) {
		$r = self::managed_depot( (int) $req['id'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		LogiProjet_Store::delete_depot( $r[0]['id'] );
		return array( 'deleted' => true );
	}

	/** Téléchargement du PowerPoint : enseignant responsable ou membre du duo. */
	public static function fichier( $req ) {
		$d = LogiProjet_Store::depot( (int) $req['id'] );
		if ( ! $d ) {
			return self::err( 'Dépôt introuvable.', 404 );
		}
		$t       = LogiProjet_Store::travail( $d['travail_id'] );
		$teacher = LogiProjet_Access::can_manage_travail( $t );
		$draft   = 'travail' === $req->get_param( 'version' );
		if ( ! $teacher && ! LogiProjet_Access::is_member( $d ) ) {
			return self::err( 'Accès refusé.', 403 );
		}
		// Les apprentis ne rouvrent que le fichier de travail, et seulement tant que le travail n'est pas remis.
		if ( ! $teacher && ( ! $draft || 'groupe' !== $d['status'] ) ) {
			return self::err( 'Ce travail est remis : il ne peut plus être ouvert. Demandez à votre enseignant de vous le rendre si besoin.', 403 );
		}
		$path = LogiProjet_Store::file_abspath( $draft ? $d['draft_path'] : $d['file_path'] );
		if ( ! $path || ! file_exists( $path ) ) {
			return self::err( 'Fichier introuvable.', 404 );
		}
		$orig = $draft ? $d['draft_name'] : $d['file_name'];
		$name = sanitize_file_name( $orig ? $orig : basename( $path ) );
		nocache_headers();
		header( 'Content-Type: ' . LogiProjet_Formats::mime_for_ext( pathinfo( $path, PATHINFO_EXTENSION ) ) );
		header( 'Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ================= Apprentis ================= */

	/**
	 * Camarades qu'un apprenti peut choisir : même classe, et dans aucun groupe pour CE travail.
	 * Les membres de son propre groupe restent proposés (pour modifier le groupe).
	 * Un apprenti peut être dans un groupe différent pour chaque travail.
	 */
	private static function classmates( $t, $user_id ) {
		$mine = LogiProjet_Sensei::user_groups( $user_id );
		$tg   = array_map( 'absint', (array) $t['group_ids'] );
		$scope = $tg ? array_intersect( $mine, $tg ) : $mine;
		$ids  = LogiProjet_Sensei::group_students( $scope );
		$busy = array();
		foreach ( LogiProjet_Store::depots( $t['id'] ) as $d ) {
			if ( in_array( (int) $user_id, array_map( 'intval', $d['members'] ), true ) ) {
				continue;
			}
			$busy = array_merge( $busy, array_map( 'intval', $d['members'] ) );
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( (int) $id === (int) $user_id || in_array( (int) $id, $busy, true ) || ! get_userdata( $id ) ) {
				continue;
			}
			$out[] = array(
				'id'   => (int) $id,
				'name' => LogiProjet_Sensei::user_label( $id ),
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	public static function eleve_travaux() {
		$uid = get_current_user_id();
		$out = array();
		foreach ( LogiProjet_Store::travaux( array( 'status' => array( 'ouvert', 'ferme' ) ) ) as $t ) {
			if ( ! LogiProjet_Access::student_can_see( $t, $uid ) ) {
				continue;
			}
			$o          = self::travail_out( $t, false );
			$d          = LogiProjet_Store::depot_for_user( $t['id'], $uid );
			$o['depot'] = $d ? self::depot_out( $d, $t, false ) : null;
			$o['can_submit'] = 'ouvert' === $t['status'] && ( ! $d || 'valide' !== $d['status'] );
			$o['can_group']  = $o['can_submit'] && (int) $t['group_size'] > 1 && ( ! $d || 'groupe' === $d['status'] );
			$out[]      = $o;
		}
		return $out;
	}

	public static function eleve_camarades( $req ) {
		$t = LogiProjet_Store::travail( (int) $req['id'] );
		if ( ! $t || ! LogiProjet_Access::student_can_see( $t ) ) {
			return self::err( 'Travail introuvable.', 404 );
		}
		return self::classmates( $t, get_current_user_id() );
	}

	/** Contrôle les camarades choisis. Rend la liste des id ou une erreur. */
	private static function check_partners( $t, $uid, $raw ) {
		$raw      = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$partners = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		$need     = (int) $t['group_size'] - 1;
		$allowed  = array_map( 'intval', wp_list_pluck( self::classmates( $t, $uid ), 'id' ) );
		foreach ( $partners as $pid ) {
			if ( ! in_array( $pid, $allowed, true ) ) {
				return self::err( 'Un camarade choisi ne peut pas l’être : il est dans une autre classe, ou déjà dans un autre groupe pour ce travail.' );
			}
		}
		if ( count( $partners ) > $need ) {
			return self::err( 'Trop de camarades choisis : le groupe compte ' . (int) $t['group_size'] . ' personnes.' );
		}
		if ( count( $partners ) < $need && ! $t['allow_solo'] ) {
			return self::err( 1 === $need ? 'Choisissez votre partenaire.' : 'Choisissez vos ' . $need . ' camarades de groupe.' );
		}
		return $partners;
	}

	/** Travail visible, ouvert et avant la date limite. */
	private static function open_travail_for( $id, $uid ) {
		$t = LogiProjet_Store::travail( (int) $id );
		if ( ! $t || ! LogiProjet_Access::student_can_see( $t, $uid ) ) {
			return self::err( 'Travail introuvable.', 404 );
		}
		if ( 'ouvert' !== $t['status'] ) {
			return self::err( 'Ce travail est fermé. Contactez votre enseignant.', 403 );
		}
		// Après l'échéance, la remise reste possible tant que le travail est ouvert : la pénalité de retard s'applique.
		return $t;
	}

	private static function eleve_out( $t, $uid ) {
		$d               = LogiProjet_Store::depot_for_user( $t['id'], $uid );
		$o               = self::travail_out( $t, false );
		$o['depot']      = $d ? self::depot_out( $d, $t, false ) : null;
		$o['can_submit'] = ! $d || 'valide' !== $d['status'];
		$o['can_group']  = (int) $t['group_size'] > 1 && ( ! $d || 'groupe' === $d['status'] );
		return $o;
	}

	/**
	 * Enregistre le groupe sans déposer de fichier (ou le modifie tant que rien n'est déposé).
	 * Un apprenti n'est que dans un seul groupe par travail.
	 */
	public static function eleve_groupe( $req ) {
		$uid = get_current_user_id();
		$t   = self::open_travail_for( $req['id'], $uid );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		if ( (int) $t['group_size'] < 2 ) {
			return self::err( 'Ce travail est individuel : il n’y a pas de groupe à former.' );
		}
		$existing = LogiProjet_Store::depot_for_user( $t['id'], $uid );
		if ( $existing && 'groupe' !== $existing['status'] ) {
			return self::err( 'Votre groupe a déjà déposé son travail : il ne peut plus être modifié. Contactez votre enseignant.', 403 );
		}
		$seul = ! empty( $req->get_param( 'seul' ) );
		if ( $seul && ! $t['allow_solo'] ) {
			return self::err( 'Ce travail se fait obligatoirement en ' . ( 3 === (int) $t['group_size'] ? 'trio' : ( 2 === (int) $t['group_size'] ? 'duo' : 'groupe' ) ) . '. Demandez à votre enseignant si vous devez travailler seul·e.' );
		}
		if ( $seul && $existing && count( $existing['members'] ) > 1 ) {
			return self::err( 'Vous êtes dans un groupe : quittez-le d’abord pour travailler seul·e.' );
		}
		$partners = $seul ? array() : self::check_partners( $t, $uid, $req->get_param( 'partner_ids' ) );
		if ( is_wp_error( $partners ) ) {
			return $partners;
		}
		if ( ! $partners && ! $seul ) {
			return self::err( 'Choisissez au moins un camarade, ou indiquez que vous travaillez seul·e.' );
		}
		$members = array_merge( array( $uid ), $partners );
		$data    = array(
			'user1'        => $uid,
			'user2'        => $partners ? $partners[0] : 0,
			'members'      => $members,
			'submitted_by' => $uid,
			'submitted_at' => LogiProjet_Store::now(),
		);
		if ( $existing ) {
			LogiProjet_Store::save_depot( $data, $existing['id'] );
			$id = $existing['id'];
		} else {
			$data += array(
				'travail_id' => $t['id'],
				'status'     => 'groupe',
				'file_path'  => '',
				'file_name'  => '',
				'file_size'  => 0,
			);
			$id = LogiProjet_Store::save_depot( $data );
		}
		// Course possible : un camarade vient d'être pris par un autre groupe.
		foreach ( LogiProjet_Store::depots( $t['id'] ) as $other ) {
			if ( (int) $other['id'] !== (int) $id && array_intersect( $members, $other['members'] ) ) {
				if ( $existing ) {
					LogiProjet_Store::save_depot( array( 'members' => $existing['members'], 'user1' => $existing['user1'], 'user2' => $existing['user2'] ), $id );
				} else {
					LogiProjet_Store::delete_depot( $id );
				}
				return self::err( 'Un de vos camarades vient d’être choisi par un autre groupe. Rechargez la page.', 409 );
			}
		}
		return self::eleve_out( $t, $uid );
	}

	/** Quitter son groupe tant qu'aucun fichier n'est déposé. Le groupe est supprimé s'il ne reste personne. */
	public static function eleve_quitter( $req ) {
		$uid = get_current_user_id();
		$t   = self::open_travail_for( $req['id'], $uid );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$d = LogiProjet_Store::depot_for_user( $t['id'], $uid );
		if ( ! $d ) {
			return self::err( 'Vous n’êtes dans aucun groupe pour ce travail.', 404 );
		}
		if ( 'groupe' !== $d['status'] ) {
			return self::err( 'Votre groupe a déjà déposé son travail : contactez votre enseignant pour changer de groupe.', 403 );
		}
		$rest = array_values( array_diff( array_map( 'intval', $d['members'] ), array( (int) $uid ) ) );
		if ( ! $rest && '' !== (string) $d['draft_path'] ) {
			return self::err( 'Vous travaillez seul·e et votre fichier de travail serait supprimé. Pour rejoindre des camarades, ajoutez-les plutôt avec « Travailler en groupe ».', 409 );
		}
		if ( ! $rest || ( count( $rest ) < 2 && '' === (string) $d['draft_path'] ) ) {
			// Un groupe d'une seule personne, sans fichier de travail, n'a plus de sens : chacun repart libre.
			LogiProjet_Store::delete_depot( $d['id'] );
		} else {
			// Le fichier de travail reste au(x) camarade(s) restant(s).
			LogiProjet_Store::save_depot(
				array(
					'members' => $rest,
					'user1'   => $rest[0],
					'user2'   => $rest[1] ?? 0,
				),
				$d['id']
			);
		}
		return self::eleve_out( $t, $uid );
	}

	/**
	 * Reçoit et contrôle le fichier envoyé (format, taille, contenu), puis l'enregistre dans le dossier protégé.
	 * Rend array( nom sur le serveur, nom d'origine, taille, extension ) ou une erreur.
	 */
	private static function receive_file( $req, $t ) {
		$files = $req->get_file_params();
		$f     = $files['fichier'] ?? null;
		if ( ! $f || ! isset( $f['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $f['error'] ) {
			$code = $f ? (int) $f['error'] : -1;
			$msg  = in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? 'Le fichier est trop lourd pour le serveur.' : 'Aucun fichier reçu. Réessayez.';
			return self::err( $msg );
		}
		if ( ! is_uploaded_file( $f['tmp_name'] ) && ! apply_filters( 'logiprojet_allow_test_upload', false ) ) {
			return self::err( 'Fichier non valable.' );
		}
		$max = LogiProjet_Settings::max_mb() * 1024 * 1024;
		if ( (int) $f['size'] > $max ) {
			return self::err( 'Le fichier dépasse ' . LogiProjet_Settings::max_mb() . ' Mo. Compressez les images (Fichier → Compresser les images) puis réessayez.' );
		}
		$orig  = sanitize_file_name( wp_unslash( (string) $f['name'] ) );
		$ext   = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
		$okext = LogiProjet_Formats::extensions( $t['formats'] );
		if ( ! in_array( $ext, $okext, true ) ) {
			return self::err( 'Ce format n’est pas accepté pour ce travail. Formats acceptés : ' . LogiProjet_Formats::label( $t['formats'] ) . '.' );
		}
		$cat = LogiProjet_Formats::catalogue();
		$key = LogiProjet_Formats::key_for_ext( $ext );
		if ( function_exists( 'finfo_open' ) ) {
			$fi   = finfo_open( FILEINFO_MIME_TYPE );
			$mime = (string) finfo_file( $fi, $f['tmp_name'] );
			finfo_close( $fi );
			if ( ! in_array( $mime, $cat[ $key ][2], true ) ) {
				return self::err( 'Ce fichier n’est pas un ' . $cat[ $key ][0] . ' valable.' );
			}
		}
		if ( ! LogiProjet_Formats::check_content( $f['tmp_name'], $ext ) ) {
			return self::err( 'Ce fichier n’est pas un ' . $cat[ $key ][0] . ' valable.' );
		}
		$ext  = 'jpeg' === $ext ? 'jpg' : $ext;
		$name = 'd' . $t['id'] . '-' . strtolower( wp_generate_password( 20, false, false ) ) . '.' . $ext;
		$dest = trailingslashit( LogiProjet_Install::upload_dir() ) . $name;
		$ok   = is_uploaded_file( $f['tmp_name'] ) ? move_uploaded_file( $f['tmp_name'], $dest ) : rename( $f['tmp_name'], $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $ok ) {
			return self::err( 'Le fichier n’a pas pu être enregistré sur le serveur.', 500 );
		}
		chmod( $dest, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array( $name, $orig, (int) filesize( $dest ), $ext );
	}

	private static function drop_file( $relative ) {
		$path = LogiProjet_Store::file_abspath( $relative );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/** Groupe de l'apprenti, à créer si besoin (seul·e : automatiquement ; en groupe : avec les camarades choisis). */
	private static function group_for_upload( $req, $t, $uid ) {
		$existing = LogiProjet_Store::depot_for_user( $t['id'], $uid );
		if ( $existing ) {
			return $existing;
		}
		$raw = $req->get_param( 'partner_ids' );
		if ( null === $raw ) {
			$raw = array( $req->get_param( 'partner_id' ) );
		}
		$partners = self::check_partners( $t, $uid, $raw );
		if ( is_wp_error( $partners ) ) {
			return $partners;
		}
		$members = array_merge( array( $uid ), $partners );
		$id      = LogiProjet_Store::save_depot(
			array(
				'travail_id'   => $t['id'],
				'user1'        => $uid,
				'user2'        => $partners ? $partners[0] : 0,
				'members'      => $members,
				'status'       => 'groupe',
				'submitted_by' => $uid,
				'submitted_at' => LogiProjet_Store::now(),
			)
		);
		// Course possible : deux apprentis choisissent le même camarade en même temps.
		foreach ( LogiProjet_Store::depots( $t['id'] ) as $other ) {
			if ( (int) $other['id'] !== (int) $id && array_intersect( $members, $other['members'] ) ) {
				LogiProjet_Store::delete_depot( $id );
				return self::err( 'Un de vos camarades vient d’être choisi par un autre groupe. Rechargez la page.', 409 );
			}
		}
		return LogiProjet_Store::depot( $id );
	}

	/** Passe le dépôt à « remis » avec ce fichier. Le fichier de travail est supprimé. */
	private static function hand_in( $d, $t, $uid, $name, $orig, $size, $ext ) {
		$x = 'pptx' === $ext ? LogiProjet_Pptx::extract( trailingslashit( LogiProjet_Install::upload_dir() ) . $name ) : new WP_Error( 'logiprojet_no_extract', '' );
		if ( $d['file_path'] && $d['file_path'] !== $name ) {
			self::drop_file( $d['file_path'] );
		}
		if ( $d['draft_path'] && $d['draft_path'] !== $name ) {
			self::drop_file( $d['draft_path'] );
		}
		LogiProjet_Store::save_depot(
			array(
				'file_path'    => $name,
				'file_name'    => $orig,
				'file_size'    => $size,
				'submitted_by' => $uid,
				'submitted_at' => LogiProjet_Store::now(),
				'status'       => 'depose',
				'draft_path'   => '',
				'draft_name'   => '',
				'draft_size'   => 0,
				'draft_by'     => 0,
				'draft_at'     => null,
				'extract'      => is_wp_error( $x ) ? null : $x,
				'ai'           => null,
				'ai_error'     => null,
				'scores'       => null,
				'feedback'     => null,
				'points'       => null,
				'note'         => null,
				'note_finale'  => null,
			),
			$d['id']
		);
	}

	/**
	 * Fichier de travail : déposé « en cours », sans remise. Chaque membre peut le récupérer
	 * pour continuer (par exemple si un camarade est absent), jusqu'à la remise.
	 */
	public static function eleve_brouillon( $req ) {
		$uid = get_current_user_id();
		$t   = self::open_travail_for( $req['id'], $uid );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$d = self::group_for_upload( $req, $t, $uid );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( 'groupe' !== $d['status'] ) {
			return self::err( 'Ce travail est déjà remis : le fichier de travail ne peut plus être modifié.', 403 );
		}
		$r = self::receive_file( $req, $t );
		if ( is_wp_error( $r ) ) {
			if ( '' === (string) $d['draft_path'] && 1 === count( $d['members'] ) && (int) $t['group_size'] < 2 ) {
				LogiProjet_Store::delete_depot( $d['id'] ); // Rien d'enregistré pour un travail individuel.
			}
			return $r;
		}
		list( $name, $orig, $size ) = $r;
		self::drop_file( $d['draft_path'] );
		LogiProjet_Store::save_depot(
			array(
				'draft_path' => $name,
				'draft_name' => $orig,
				'draft_size' => $size,
				'draft_by'   => $uid,
				'draft_at'   => LogiProjet_Store::now(),
			),
			$d['id']
		);
		return self::eleve_out( $t, $uid );
	}

	/** Remise du fichier de travail déjà enregistré (sans renvoyer de fichier). */
	public static function eleve_remettre( $req ) {
		$uid = get_current_user_id();
		$t   = self::open_travail_for( $req['id'], $uid );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$d = LogiProjet_Store::depot_for_user( $t['id'], $uid );
		if ( $d && 'groupe' !== $d['status'] ) {
			return self::err( 'Ce travail est déjà remis.', 409 );
		}
		if ( ! $d || '' === (string) $d['draft_path'] ) {
			return self::err( 'Aucun fichier de travail à remettre. Déposez d’abord votre fichier.' );
		}
		$need = (int) $t['group_size'] - 1;
		if ( $need > 0 && count( $d['members'] ) - 1 < $need && ! $t['allow_solo'] ) {
			return self::err( 'Votre groupe est incomplet : ajoutez vos camarades avant la remise.' );
		}
		$path = LogiProjet_Store::file_abspath( $d['draft_path'] );
		if ( ! $path || ! file_exists( $path ) ) {
			return self::err( 'Le fichier de travail est introuvable. Déposez-le à nouveau.', 404 );
		}
		self::hand_in( $d, $t, $uid, $d['draft_path'], $d['draft_name'], (int) $d['draft_size'], strtolower( pathinfo( $d['draft_path'], PATHINFO_EXTENSION ) ) );
		return self::eleve_out( $t, $uid );
	}

	/** Remise directe d'un fichier (le groupe est créé en même temps si besoin). */
	public static function eleve_depot( $req ) {
		$uid = get_current_user_id();
		$t   = self::open_travail_for( $req['id'], $uid );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$existing = LogiProjet_Store::depot_for_user( $t['id'], $uid );
		if ( $existing && 'groupe' !== $existing['status'] ) {
			return self::err( 'Ce travail est déjà remis : le fichier ne peut plus être remplacé. Demandez à votre enseignant de vous le rendre si besoin.', 403 );
		}
		$d = self::group_for_upload( $req, $t, $uid );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$r = self::receive_file( $req, $t );
		if ( is_wp_error( $r ) ) {
			if ( ! $existing ) {
				LogiProjet_Store::delete_depot( $d['id'] );
			}
			return $r;
		}
		list( $name, $orig, $size, $ext ) = $r;
		self::hand_in( $d, $t, $uid, $name, $orig, $size, $ext );
		return self::eleve_out( $t, $uid );
	}
}
