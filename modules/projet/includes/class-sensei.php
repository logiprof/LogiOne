<?php
/**
 * Lecture des cours, leçons et groupes Sensei LMS.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Sensei {

	public static function courses() {
		$posts = get_posts(
			array(
				'post_type'      => 'course',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		return array_map(
			function ( $p ) {
				return array(
					'id'     => (int) $p->ID,
					'title'  => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
					'module' => self::module_code( get_the_title( $p ) ),
				);
			},
			$posts
		);
	}

	public static function module_code( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' );
		return preg_match( '/\b([A-Z]{1,3}\s?\d{2,4})\b/u', $title, $m ) ? str_replace( ' ', '', $m[1] ) : '';
	}

	/** Leçons d'un cours dans l'ordre du cours. « suggested » = leçon de contenu numérotée (ex. 9.1). */
	public static function lessons( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || 'course' !== get_post_type( $course_id ) ) {
			return array();
		}
		$ids = array();
		if ( function_exists( 'Sensei' ) && isset( Sensei()->course ) && method_exists( Sensei()->course, 'course_lessons' ) ) {
			$found = Sensei()->course->course_lessons( $course_id, array( 'publish', 'private', 'draft' ), 'ids' );
			if ( is_array( $found ) ) {
				$ids = array_map( 'absint', $found );
			}
		}
		if ( ! $ids ) {
			$ids = get_posts(
				array(
					'post_type'      => 'lesson',
					'post_status'    => array( 'publish', 'private', 'draft' ),
					'posts_per_page' => 500,
					'fields'         => 'ids',
					'meta_key'       => '_lesson_course', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery
					'orderby'        => 'menu_order date',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			$order = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $course_id, '_lesson_order', true ) ) ) );
			if ( $order ) {
				$pos = array_flip( $order );
				usort(
					$ids,
					function ( $a, $b ) use ( $pos ) {
						return ( isset( $pos[ $a ] ) ? $pos[ $a ] : 9999 ) - ( isset( $pos[ $b ] ) ? $pos[ $b ] : 9999 );
					}
				);
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			$title = self::clean_lesson_title( get_the_title( $id ) );
			$out[] = array(
				'id'        => (int) $id,
				'title'     => $title,
				'suggested' => (bool) preg_match( '/^\d+(\.\d+)*\s/u', $title ),
			);
		}
		return $out;
	}

	/** « M109 – 9.1 Autorisation d’accès » → « 9.1 Autorisation d’accès ». */
	public static function clean_lesson_title( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' );
		$title = preg_replace( '/^\s*[A-Z]{1,3}\s?\d{2,4}\s*[–—\-:|]\s*/u', '', $title );
		return trim( $title );
	}

	/** Texte d'une leçon, nettoyé, pour servir de référence à l'IA. */
	public static function lesson_text( $lesson_id, $max = 6000 ) {
		$post = get_post( $lesson_id );
		if ( ! $post || 'lesson' !== $post->post_type ) {
			return '';
		}
		$text = strip_shortcodes( (string) $post->post_content );
		$text = preg_replace( '/<!--.*?-->/s', ' ', $text );
		$text = preg_replace( '#<(script|style)[^>]*>.*?</\1>#si', ' ', $text );
		$text = preg_replace( '#</(p|li|h[1-6]|div|tr|br)>#i', "\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', $text );
		$text = trim( preg_replace( "/\n\s*\n+/", "\n", $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	public static function groups() {
		$posts = get_posts(
			array(
				'post_type'      => 'group',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		return array_map(
			function ( $p ) {
				return array(
					'id'    => (int) $p->ID,
					'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				);
			},
			$posts
		);
	}

	private static function groups_table() {
		global $wpdb;
		static $exists = null;
		$table = $wpdb->prefix . 'sensei_pro_groups_students';
		if ( null === $exists ) {
			$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $exists ? $table : '';
	}

	public static function user_groups( $user_id ) {
		global $wpdb;
		$table = self::groups_table();
		if ( ! $table ) {
			return array();
		}
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT group_id FROM {$table} WHERE student_id = %d", absint( $user_id ) ) ) ); // phpcs:ignore WordPress.DB
	}

	public static function group_students( $group_ids ) {
		global $wpdb;
		$table     = self::groups_table();
		$group_ids = array_filter( array_map( 'absint', (array) $group_ids ) );
		if ( ! $table || ! $group_ids ) {
			return array();
		}
		$in = implode( ',', $group_ids );
		return array_map( 'absint', $wpdb->get_col( "SELECT DISTINCT student_id FROM {$table} WHERE group_id IN ({$in})" ) ); // phpcs:ignore WordPress.DB
	}

	public static function user_label( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return '';
		}
		$name = trim( $u->first_name . ' ' . $u->last_name );
		return $name ? $name : $u->display_name;
	}
}
