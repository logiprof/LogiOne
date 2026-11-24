<?php
/**
 * Import et export d'un travail (fichier .json « logiprojet-travail »).
 * Le cours et les leçons sont retrouvés par identifiant, puis par slug, puis par titre :
 * un fichier fait sur un autre site (ou par Claude) s'importe donc aussi.
 * Le PDF de consigne peut voyager dans le fichier ; il est alors versé dans la médiathèque.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Transfer {

	const FORMAT  = 'logiprojet-travail';
	const VERSION = 1;
	const MAX_PDF = 10485760; // 10 Mo.

	/* ================= Export ================= */

	public static function export( $t ) {
		$course = $t['course_id'] ? get_post( $t['course_id'] ) : null;
		$out    = array(
			'format'     => self::FORMAT,
			'version'    => self::VERSION,
			'exported'   => gmdate( 'c' ),
			'source'     => home_url(),
			'title'      => $t['title'],
			'module'     => $t['module'],
			'course'     => $course ? array(
				'id'    => (int) $course->ID,
				'slug'  => $course->post_name,
				'title' => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
			) : null,
			'lessons'    => array(),
			'grille'     => (array) $t['grille'],
			'consignes'  => (string) $t['consignes'],
			'consigne'   => $t['consigne'] ? $t['consigne'] : null,
			'date_debut' => ! empty( $t['date_debut'] ) ? get_date_from_gmt( $t['date_debut'], 'Y-m-d\TH:i' ) : null,
			'due_at'     => $t['due_at'] ? get_date_from_gmt( $t['due_at'], 'Y-m-d\TH:i' ) : null,
			'oral_bonus' => (bool) $t['oral_bonus'],
			'allow_solo' => (bool) $t['allow_solo'],
			'group_size' => (int) $t['group_size'],
			'graded'     => (bool) $t['graded'],
			'auteur'     => LogiProjet_Sensei::user_label( $t['author_id'] ),
			'formats'    => (array) $t['formats'],
			'groups'     => array(),
		);
		foreach ( (array) $t['lessons'] as $l ) {
			$out['lessons'][] = array(
				'id'    => (int) $l['id'],
				'slug'  => (string) get_post_field( 'post_name', $l['id'] ),
				'title' => $l['title'],
			);
		}
		foreach ( (array) $t['group_ids'] as $g ) {
			if ( 'group' === get_post_type( $g ) ) {
				$out['groups'][] = html_entity_decode( get_the_title( $g ), ENT_QUOTES, 'UTF-8' );
			}
		}
		$out['consigne_url'] = (string) $t['consigne_url'];
		$att                 = $t['consigne_url'] ? attachment_url_to_postid( $t['consigne_url'] ) : 0;
		$file                = $att ? get_attached_file( $att ) : '';
		if ( $file && file_exists( $file ) && filesize( $file ) <= self::MAX_PDF && 'application/pdf' === get_post_mime_type( $att ) ) {
			$out['consigne_pdf'] = array(
				'name' => basename( $file ),
				'data' => base64_encode( file_get_contents( $file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions
			);
		}
		return $out;
	}

	/* ================= Import ================= */

	private static function norm( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' );
		$s = str_replace( array( '’', '‘', '–', '—', "\xC2\xA0" ), array( "'", "'", '-', '-', ' ' ), $s );
		$s = remove_accents( $s );
		return trim( preg_replace( '/\s+/', ' ', strtolower( $s ) ) );
	}

	private static function find_course( $c ) {
		if ( ! is_array( $c ) ) {
			return 0;
		}
		$id = absint( $c['id'] ?? 0 );
		if ( $id && 'course' === get_post_type( $id ) ) {
			$p = get_post( $id );
			if ( ( ! empty( $c['slug'] ) && $p->post_name === $c['slug'] ) || ( ! empty( $c['title'] ) && self::norm( get_the_title( $p ) ) === self::norm( $c['title'] ) ) ) {
				return $id;
			}
		}
		if ( ! empty( $c['slug'] ) ) {
			$p = get_page_by_path( sanitize_title( $c['slug'] ), OBJECT, 'course' );
			if ( $p ) {
				return (int) $p->ID;
			}
		}
		if ( ! empty( $c['title'] ) ) {
			$want = self::norm( $c['title'] );
			foreach ( LogiProjet_Sensei::courses() as $course ) {
				if ( self::norm( $course['title'] ) === $want ) {
					return (int) $course['id'];
				}
			}
			// Dernier essai : même code de module (ex. M109).
			$code = LogiProjet_Sensei::module_code( $c['title'] );
			if ( $code ) {
				foreach ( LogiProjet_Sensei::courses() as $course ) {
					if ( $course['module'] === $code ) {
						return (int) $course['id'];
					}
				}
			}
		}
		return 0;
	}

	/** Retrouve une leçon du cours : par identifiant, puis slug, puis titre (sans le préfixe « M109 – »). */
	private static function find_lesson( $l, $course_lessons ) {
		$id = absint( $l['id'] ?? 0 );
		foreach ( $course_lessons as $cl ) {
			if ( $id && (int) $cl['id'] === $id ) {
				return $cl;
			}
		}
		if ( ! empty( $l['slug'] ) ) {
			foreach ( $course_lessons as $cl ) {
				if ( get_post_field( 'post_name', $cl['id'] ) === $l['slug'] ) {
					return $cl;
				}
			}
		}
		$want = self::norm( LogiProjet_Sensei::clean_lesson_title( $l['title'] ?? '' ) );
		if ( '' !== $want ) {
			foreach ( $course_lessons as $cl ) {
				if ( self::norm( $cl['title'] ) === $want ) {
					return $cl;
				}
			}
			// Même numéro de leçon (ex. « 9.3 »).
			if ( preg_match( '/^(\d+(?:\.\d+)+)\s/', $want, $m ) ) {
				foreach ( $course_lessons as $cl ) {
					if ( 0 === strpos( self::norm( $cl['title'] ), $m[1] . ' ' ) ) {
						return $cl;
					}
				}
			}
		}
		return null;
	}

	private static function save_pdf( $pdf, &$warnings ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			$warnings[] = 'Le PDF de consigne n’a pas été importé : votre compte ne peut pas ajouter de fichiers à la médiathèque.';
			return '';
		}
		$bytes = base64_decode( (string) ( $pdf['data'] ?? '' ), true );
		if ( false === $bytes || strlen( $bytes ) > self::MAX_PDF || 0 !== strpos( $bytes, '%PDF' ) ) {
			$warnings[] = 'Le PDF de consigne joint au fichier n’est pas valable : il n’a pas été importé.';
			return '';
		}
		$name = sanitize_file_name( (string) ( $pdf['name'] ?? 'consigne.pdf' ) );
		if ( 'pdf' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$name .= '.pdf';
		}
		$up = wp_upload_bits( $name, null, $bytes );
		if ( ! empty( $up['error'] ) ) {
			$warnings[] = 'Le PDF de consigne n’a pas pu être enregistré : ' . $up['error'];
			return '';
		}
		$att = wp_insert_attachment(
			array(
				'post_title'     => preg_replace( '/\.pdf$/i', '', $name ),
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			$up['file']
		);
		if ( is_wp_error( $att ) || ! $att ) {
			$warnings[] = 'Le PDF de consigne n’a pas pu être ajouté à la médiathèque.';
			return '';
		}
		return (string) $up['url'];
	}

	/**
	 * Crée un travail en brouillon à partir d'un fichier d'export.
	 * Retourne array( 'data' => données du travail, 'warnings' => [] ) ou WP_Error.
	 */
	public static function import( $in ) {
		if ( ! is_array( $in ) || self::FORMAT !== ( $in['format'] ?? '' ) ) {
			return new WP_Error( 'logiprojet_import', 'Ce fichier n’est pas un travail LogiProjet (.json).', array( 'status' => 400 ) );
		}
		if ( (int) ( $in['version'] ?? 0 ) > self::VERSION ) {
			return new WP_Error( 'logiprojet_import', 'Ce fichier vient d’une version plus récente de LogiProjet : mettez l’extension à jour.', array( 'status' => 400 ) );
		}
		$warnings = array();
		$title    = sanitize_text_field( (string) ( $in['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'logiprojet_import', 'Le fichier ne contient pas de titre de travail.', array( 'status' => 400 ) );
		}

		$course_id = self::find_course( $in['course'] ?? null );
		$lessons   = array();
		$map       = array();
		if ( ! $course_id && ! empty( $in['course'] ) ) {
			$warnings[] = 'Le cours « ' . sanitize_text_field( (string) ( $in['course']['title'] ?? '' ) ) . ' » n’existe pas sur ce site : choisissez le cours dans le formulaire.';
		}
		if ( $course_id ) {
			$course_lessons = LogiProjet_Sensei::lessons( $course_id );
			foreach ( (array) ( $in['lessons'] ?? array() ) as $l ) {
				$found = self::find_lesson( (array) $l, $course_lessons );
				if ( $found ) {
					$lessons[]                          = array(
						'id'    => (int) $found['id'],
						'title' => $found['title'],
					);
					$map[ absint( $l['id'] ?? 0 ) ] = (int) $found['id'];
				} else {
					$warnings[] = 'Leçon introuvable dans ce cours : « ' . sanitize_text_field( (string) ( $l['title'] ?? '' ) ) . ' ». Son critère est gardé dans la grille.';
				}
			}
		}

		$grille = array();
		foreach ( (array) ( $in['grille'] ?? array() ) as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$old = absint( $c['lesson_id'] ?? 0 );
			unset( $c['lesson_id'] );
			if ( $old && isset( $map[ $old ] ) ) {
				$c['lesson_id'] = $map[ $old ];
			}
			$grille[] = $c;
		}

		$groups = array();
		$wanted = array_map( array( __CLASS__, 'norm' ), (array) ( $in['groups'] ?? array() ) );
		if ( $wanted ) {
			foreach ( LogiProjet_Sensei::groups() as $g ) {
				if ( in_array( self::norm( $g['title'] ), $wanted, true ) ) {
					$groups[] = $g['id'];
				}
			}
			if ( count( $groups ) < count( $wanted ) ) {
				$warnings[] = 'Certaines classes du fichier n’existent pas sur ce site : vérifiez les classes concernées.';
			}
		}

		$url = '';
		if ( ! empty( $in['consigne_pdf'] ) && is_array( $in['consigne_pdf'] ) ) {
			$url = self::save_pdf( $in['consigne_pdf'], $warnings );
		}
		if ( '' === $url && ! empty( $in['consigne_url'] ) ) {
			$url = (string) $in['consigne_url'];
		}

		$due = (string) ( $in['due_at'] ?? '' );
		if ( '' !== $due && ! DateTime::createFromFormat( 'Y-m-d\TH:i', substr( $due, 0, 16 ), wp_timezone() ) ) {
			$due        = '';
			$warnings[] = 'La date limite du fichier n’est pas valable : indiquez-la dans le formulaire.';
		}

		return array(
			'data'     => array(
				'title'        => $title,
				'module'       => sanitize_text_field( (string) ( $in['module'] ?? '' ) ),
				'course_id'    => $course_id,
				'lessons'      => $lessons,
				'grille'       => $grille,
				'consignes'    => (string) ( $in['consignes'] ?? '' ),
				'consigne'     => LogiProjet_Consigne::sanitize( $in['consigne'] ?? null ),
				'date_debut'   => ( ! empty( $in['date_debut'] ) && DateTime::createFromFormat( 'Y-m-d\TH:i', substr( (string) $in['date_debut'], 0, 16 ), wp_timezone() ) ) ? substr( (string) $in['date_debut'], 0, 16 ) : '',
				'consigne_url' => $url,
				'group_ids'    => $groups,
				'due_at'       => '' !== $due ? substr( $due, 0, 16 ) : '',
				'oral_bonus'   => ! array_key_exists( 'oral_bonus', $in ) || ! empty( $in['oral_bonus'] ),
				'allow_solo'   => ! empty( $in['allow_solo'] ),
				'group_size'   => max( 1, min( 6, (int) ( $in['group_size'] ?? 2 ) ) ),
				'graded'       => ! array_key_exists( 'graded', $in ) || ! empty( $in['graded'] ),
				'formats'      => LogiProjet_Formats::clean( $in['formats'] ?? array( 'pptx' ) ),
				'status'       => 'brouillon',
			),
			'warnings' => $warnings,
		);
	}
}
