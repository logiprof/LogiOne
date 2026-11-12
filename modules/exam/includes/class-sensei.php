<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Sensei {
	public static function init(): void {}

	public static function get_courses(): array {
		$post_types = array_values( array_filter( array( 'course', 'sensei_course' ), 'post_type_exists' ) );
		if ( empty( $post_types ) ) {
			return array();
		}

		$courses = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return is_array( $courses ) ? $courses : array();
	}

	public static function get_course_reference_content( int $course_id, int $max_chars = 12000 ): string {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return '';
		}

		$parts = array();
		$course = get_post( $course_id );
		if ( $course ) {
			$parts[] = self::reference_block( sprintf( __( 'Cours Sensei : %s', 'logieval-secure' ), get_the_title( $course ) ), (string) $course->post_content );
		}

		foreach ( self::get_course_lessons( $course_id ) as $lesson ) {
			$parts[] = self::reference_block( sprintf( __( 'Leçon : %s', 'logieval-secure' ), get_the_title( $lesson ) ), (string) $lesson->post_content );
		}

		$text = self::clean_reference_text( implode( "\n\n", array_filter( $parts ) ) );
		if ( strlen( $text ) > $max_chars ) {
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars ) : substr( $text, 0, $max_chars );
			$text .= "\n\n" . __( '[Référence Sensei tronquée pour rester dans une taille raisonnable.]', 'logieval-secure' );
		}
		return (string) apply_filters( 'logieval_secure_sensei_ai_reference', $text, $course_id, $max_chars );
	}

	private static function get_course_lessons( int $course_id ): array {
		$post_types = array_values( array_filter( array( 'lesson', 'sensei_lesson' ), 'post_type_exists' ) );
		if ( empty( $post_types ) ) {
			return array();
		}

		$lessons = array();
		$queries = array(
			array( 'post_parent' => $course_id ),
			array( 'meta_key' => '_lesson_course', 'meta_value' => $course_id ),
			array( 'meta_key' => '_course', 'meta_value' => $course_id ),
			array( 'meta_key' => '_course_id', 'meta_value' => $course_id ),
		);

		foreach ( $queries as $extra_args ) {
			$posts = get_posts(
				array_merge(
					array(
						'post_type'      => $post_types,
						'post_status'    => array( 'publish', 'private' ),
						'posts_per_page' => 200,
						'orderby'        => 'menu_order title',
						'order'          => 'ASC',
					),
					$extra_args
				)
			);
			foreach ( (array) $posts as $post ) {
				$lessons[ (int) $post->ID ] = $post;
			}
		}

		return array_values( $lessons );
	}

	private static function reference_block( string $title, string $content ): string {
		$content = self::clean_reference_text( $content );
		if ( '' === $content ) {
			return '';
		}
		return $title . "\n" . $content;
	}

	private static function clean_reference_text( string $content ): string {
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$content = preg_replace( '/\s+/u', ' ', $content );
		return trim( (string) $content );
	}

	public static function get_groups(): array {
		$post_groups = self::get_groups_from_sensei_pro_table();
		if ( ! empty( $post_groups ) ) {
			return $post_groups;
		}

		$taxonomies = self::group_taxonomies();
		if ( empty( $taxonomies ) ) {
			return array();
		}

		$groups = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $groups ) ) {
			return array();
		}

		$groups = (array) $groups;
		usort(
			$groups,
			static function( $a, $b ) {
				return strnatcasecmp( (string) $a->name, (string) $b->name );
			}
		);

		return $groups;
	}

	private static function get_groups_from_sensei_pro_table(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'sensei_pro_groups_students';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return array();
		}

		$group_ids = array_map( 'absint', (array) $wpdb->get_col( "SELECT DISTINCT group_id FROM {$table} ORDER BY group_id ASC" ) );
		$groups    = array();
		foreach ( $group_ids as $group_id ) {
			if ( ! $group_id ) {
				continue;
			}
			$title    = get_the_title( $group_id );
			$groups[] = (object) array(
				'term_id' => $group_id,
				'name'    => $title ? $title : sprintf( __( 'Groupe %d', 'logieval-secure' ), $group_id ),
			);
		}

		usort(
			$groups,
			static function( $a, $b ) {
				return strnatcasecmp( (string) $a->name, (string) $b->name );
			}
		);

		return $groups;
	}

	public static function group_taxonomies(): array {
		$taxonomies = array( 'sensei_lms_group', 'course-group', 'sensei_group' );
		return array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );
	}

	public static function get_group_students( $group_id ): array {
		$group_id = absint( $group_id );
		if ( ! $group_id ) {
			return array();
		}

		$student_ids = self::get_group_student_ids_from_sensei_pro_table( $group_id );
		if ( ! empty( $student_ids ) ) {
			$users = array_filter( array_map( 'get_userdata', $student_ids ) );
			return (array) apply_filters( 'logieval_secure_sensei_group_students', array_values( $users ), $group_id );
		}

		$users = get_users(
			array(
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => 'sensei_group_ids',
						'value'   => '"' . $group_id . '"',
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'sensei_group_id',
						'value'   => $group_id,
						'compare' => '=',
					),
				),
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
				'number'     => 500,
			)
		);

		$users = is_array( $users ) ? $users : array();
		return (array) apply_filters( 'logieval_secure_sensei_group_students', $users, $group_id );
	}

	private static function get_group_student_ids_from_sensei_pro_table( int $group_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'sensei_pro_groups_students';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					'absint',
					(array) $wpdb->get_col(
						$wpdb->prepare( "SELECT DISTINCT student_id FROM {$table} WHERE group_id = %d ORDER BY student_id ASC", $group_id )
					)
				)
			)
		);
	}

	public static function user_in_any_group( $user_id, array $group_ids ): bool {
		if ( empty( $group_ids ) ) {
			return true;
		}

		$user_id = absint( $user_id );
		foreach ( $group_ids as $group_id ) {
			$students = self::get_group_students( $group_id );
			foreach ( $students as $student ) {
				if ( (int) $student->ID === $user_id ) {
					return true;
				}
			}
		}

		// Fallback permissif pour les installations Sensei qui stockent les groupes autrement.
		return (bool) apply_filters( 'logieval_secure_user_in_sensei_group', false, $user_id, $group_ids );
	}

	public static function get_quiz_questions( int $course_id = 0, string $category_slug = '', string $module_key = '' ): array {
		$post_types = array_values( array_filter( array( 'question', 'multiple_question', 'sensei_question' ), 'post_type_exists' ) );
		if ( empty( $post_types ) ) {
			return array();
		}

		$category_slugs = array();
		if ( $module_key ) {
			$module = self::get_question_module( $module_key );
			if ( $module && ( ! $course_id || empty( $module['course_id'] ) || (int) $module['course_id'] === $course_id ) ) {
				$category_slugs = self::sanitize_slug_array( $module['category_slugs'] ?? array() );
			}
		}
		if ( $category_slug ) {
			$category_slugs = array( sanitize_title( $category_slug ) );
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $category_slugs ) && taxonomy_exists( 'question-category' ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'question-category',
					'field'    => 'slug',
					'terms'    => $category_slugs,
				),
			);
		}

		$posts = get_posts( $args );

		$questions = array();
		foreach ( $posts as $post ) {
			if ( $course_id && empty( $category_slugs ) && ! self::question_belongs_to_course( (int) $post->ID, $course_id ) ) {
				continue;
			}
			if ( ! empty( $category_slugs ) && ! self::question_in_any_category( (int) $post->ID, $category_slugs ) ) {
				continue;
			}
			$type = get_post_meta( $post->ID, '_question_type', true );
			$questions[] = array(
				'id'              => $post->ID,
				'title'           => get_the_title( $post ),
				'prompt'          => $post->post_content,
				'type'            => self::normalize_question_type( $type ),
				'expected_answer' => (string) get_post_meta( $post->ID, '_question_right_answer', true ),
				'options'         => get_post_meta( $post->ID, '_question_answer_order', true ),
				'points'          => (float) get_post_meta( $post->ID, '_question_grade', true ),
				'categories'      => self::get_question_category_names( (int) $post->ID ),
			);
		}

		return $questions;
	}

	public static function get_question_categories( int $course_id = 0, string $module_key = '' ): array {
		$taxonomies = self::question_taxonomies();
		if ( empty( $taxonomies ) ) {
			return array();
		}

		$allowed_slugs = array();
		if ( $module_key ) {
			$module = self::get_question_module( $module_key );
			if ( $module ) {
				$allowed_slugs = self::sanitize_slug_array( $module['category_slugs'] ?? array() );
			}
		} elseif ( $course_id ) {
			$modules = self::get_question_modules_for_course( $course_id );
			foreach ( $modules as $module ) {
				$allowed_slugs = array_merge( $allowed_slugs, self::sanitize_slug_array( $module['category_slugs'] ?? array() ) );
			}
			$allowed_slugs = array_values( array_unique( $allowed_slugs ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( (array) $terms as $term ) {
			if ( ! empty( $allowed_slugs ) && ! in_array( (string) $term->slug, $allowed_slugs, true ) ) {
				continue;
			}
			if ( empty( $allowed_slugs ) && $course_id && 0 === self::count_questions_in_category_for_course( (string) $term->slug, $course_id ) ) {
				continue;
			}
			$out[] = $term;
		}

		usort(
			$out,
			static function( $a, $b ) {
				return strnatcasecmp( (string) $a->name, (string) $b->name );
			}
		);

		return $out;
	}

	public static function get_question_modules_for_course( int $course_id = 0 ): array {
		$modules = array_values( self::get_revision_question_groups() );
		$modules = array_values(
			array_filter(
				$modules,
				static function( $module ) use ( $course_id ) {
					if ( empty( $module['enabled'] ) || empty( $module['category_slugs'] ) ) {
						return false;
					}
					return ! $course_id || empty( $module['course_id'] ) || (int) $module['course_id'] === $course_id;
				}
			)
		);
		usort(
			$modules,
			static function( $a, $b ) {
				return strnatcasecmp( (string) ( $a['sort_key'] ?? $a['label'] ), (string) ( $b['sort_key'] ?? $b['label'] ) );
			}
		);
		return $modules;
	}

	private static function get_question_module( string $module_key ): ?array {
		$module_key = sanitize_key( $module_key );
		$groups     = self::get_revision_question_groups();
		return isset( $groups[ $module_key ] ) ? $groups[ $module_key ] : null;
	}

	private static function get_revision_question_groups(): array {
		$groups = get_option( 'logiprof_revision_question_groups', array() );
		if ( ! is_array( $groups ) || empty( $groups ) ) {
			$groups = self::get_auto_question_groups();
		}

		$normalized = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$key = isset( $group['key'] ) ? sanitize_key( $group['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$label                  = isset( $group['label'] ) ? sanitize_text_field( $group['label'] ) : strtoupper( $key );
			$normalized[ $key ]     = array(
				'key'            => $key,
				'label'          => $label,
				'course_id'      => isset( $group['course_id'] ) ? absint( $group['course_id'] ) : 0,
				'category_slugs' => isset( $group['category_slugs'] ) ? self::sanitize_slug_array( $group['category_slugs'] ) : array(),
				'enabled'        => ! empty( $group['enabled'] ),
				'sort_key'       => self::get_module_sort_key( $key, $label ),
			);
		}

		return $normalized;
	}

	private static function get_auto_question_groups(): array {
		if ( ! taxonomy_exists( 'question-category' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'question-category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 500,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$groups = array();
		foreach ( (array) $terms as $term ) {
			$candidate = $term->name . ' ' . $term->slug;
			if ( ! preg_match( '/\bM?(\d{2,4})(?:[._-]\d+)?\b/i', $candidate, $match ) ) {
				continue;
			}
			$key = 'm' . $match[1];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'key'            => $key,
					'label'          => 'Module M' . $match[1],
					'course_id'      => 0,
					'category_slugs' => array(),
					'enabled'        => true,
					'sort_key'       => self::get_module_sort_key( $key, 'Module M' . $match[1] ),
				);
			}
			$groups[ $key ]['category_slugs'][] = $term->slug;
		}

		ksort( $groups, SORT_NATURAL );
		return $groups;
	}

	private static function sanitize_slug_array( $slugs ): array {
		$clean = array();
		foreach ( (array) $slugs as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' !== $slug ) {
				$clean[] = $slug;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	private static function get_module_sort_key( string $key, string $label ): string {
		foreach ( array( $key, $label ) as $candidate ) {
			if ( preg_match( '/\bM?(\d{2,4})\b/i', $candidate, $match ) ) {
				return 'M' . str_pad( $match[1], 4, '0', STR_PAD_LEFT );
			}
		}
		return $label;
	}

	public static function question_taxonomies(): array {
		$taxonomies = array( 'question-category', 'question_category', 'sensei_question_category', 'quiz-question-category' );
		return array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );
	}

	private static function count_questions_in_category_for_course( string $category_slug, int $course_id ): int {
		$count = 0;
		foreach ( self::get_quiz_questions( $course_id, $category_slug ) as $question ) {
			$count++;
		}
		return $count;
	}

	private static function question_in_category( int $question_id, string $category_slug ): bool {
		foreach ( self::question_taxonomies() as $taxonomy ) {
			if ( has_term( $category_slug, $taxonomy, $question_id ) ) {
				return true;
			}
		}
		return false;
	}

	private static function question_in_any_category( int $question_id, array $category_slugs ): bool {
		foreach ( self::sanitize_slug_array( $category_slugs ) as $category_slug ) {
			if ( self::question_in_category( $question_id, $category_slug ) ) {
				return true;
			}
		}
		return false;
	}

	private static function get_question_category_names( int $question_id ): string {
		$names = array();
		foreach ( self::question_taxonomies() as $taxonomy ) {
			$terms = get_the_terms( $question_id, $taxonomy );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$names[] = (string) $term->name;
			}
		}
		return implode( ', ', array_unique( $names ) );
	}

	private static function question_belongs_to_course( int $question_id, int $course_id ): bool {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return true;
		}

		$related_ids = array_filter(
			array_map(
				'absint',
				array(
					get_post_meta( $question_id, '_course', true ),
					get_post_meta( $question_id, '_course_id', true ),
					get_post_meta( $question_id, '_sensei_course_id', true ),
				)
			)
		);
		if ( in_array( $course_id, $related_ids, true ) ) {
			return true;
		}

		$lesson_id = absint( get_post_meta( $question_id, '_lesson_id', true ) ?: get_post_meta( $question_id, '_quiz_lesson', true ) );
		if ( $lesson_id ) {
			$lesson_course_ids = array_filter(
				array_map(
					'absint',
					array(
						get_post_meta( $lesson_id, '_lesson_course', true ),
						get_post_meta( $lesson_id, '_course', true ),
						wp_get_post_parent_id( $lesson_id ),
					)
				)
			);
			if ( in_array( $course_id, $lesson_course_ids, true ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'logieval_secure_question_in_sensei_course', false, $question_id, $course_id );
	}

	public static function normalize_question_type( $type ): string {
		$type = sanitize_key( (string) $type );
		$map  = array(
			'multiple-choice' => 'qcm',
			'multiple_choice' => 'qcm',
			'boolean'         => 'true_false',
			'true-false'      => 'true_false',
			'gap-fill'        => 'short_answer',
			'short-answer'    => 'short_answer',
			'long-answer'     => 'long_answer',
			'file-upload'     => 'document',
		);

		return $map[ $type ] ?? ( $type ?: 'long_answer' );
	}
}
