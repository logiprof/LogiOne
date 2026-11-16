<?php
/**
 * LogiExam : types de questions « Vrai / Faux argumenté » et « Remettre dans l'ordre » (LogiOne 1.18).
 *
 * Vrai / Faux argumenté (true_false_justify)
 * - Attendu (expected_answer, JSON) : choice (Vrai|Faux), justification (argument attendu), choice_points.
 * - Réponse élève (JSON) : choice, justification.
 * - Correction automatique : les points du choix seulement ; l'argumentation est corrigée par l'enseignant (ou proposée par l'IA).
 *
 * Remettre dans l'ordre (ordering)
 * - options (JSON) : éléments DANS LE BON ORDRE [{id, text, media_id}] ; les id sont aléatoires (ils ne trahissent pas l'ordre).
 * - Attendu (JSON) : mode (items|letters), scoring (partial|all), labels (pour l'affichage enseignant).
 * - L'élève reçoit les éléments mélangés (toujours le même mélange pour lui), jamais dans le bon ordre.
 * - Réponse élève (JSON) : order (id dans l'ordre choisi), labels.
 * - Correction automatique : comparaison élément par élément (deux lettres identiques sont interchangeables) ;
 *   « partial » = points au prorata des éléments à la bonne place, « all » = tout juste ou rien.
 *
 * 1.36 : « Relier » (matching) : options = paires [{id, left, rid, right}] (rid ≠ id : l'élève ne devine pas les paires ;
 *   une ligne sans gauche = intrus) ; réponse {pairs: {id gauche: rid droite}}.
 * 1.36 : « Texte à trous » (fill_blanks) : attendu {kind, text avec [réponse|variante], bank, tolerant, scoring} ;
 *   réponse {blanks: [...]}. Correction sans majuscules, accents ni espaces superflus.
 * 1.36 : calculatrice sur toute question : colonne recommended_time (inutilisée jusque-là) = drapeau, 1 = calculatrice.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Question_Types {
	public const TFJ      = 'true_false_justify';
	public const ORDERING = 'ordering';
	public const MATCHING = 'matching';
	public const BLANKS   = 'fill_blanks';

	/** Types notés automatiquement par LogiExam (pas besoin de l'IA). */
	public const AUTO_TYPES = array( self::TFJ, self::ORDERING, self::MATCHING, self::BLANKS );

	/* ---------------------------------------------------------------------
	 * Calculatrice sur n'importe quelle question (1.36)
	 * ------------------------------------------------------------------- */

	public static function has_calculator( array $question ): bool {
		return 'calculation' === ( $question['type'] ?? '' ) || ( (int) ( $question['recommended_time'] ?? 0 ) & 1 ) === 1;
	}

	/* ---------------------------------------------------------------------
	 * Relier (1.36)
	 * ------------------------------------------------------------------- */

	/** @return array<int, array{id:string, left:string, rid:string, right:string}> */
	public static function match_pairs( $options ): array {
		$list = is_array( $options ) ? $options : json_decode( (string) $options, true );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $p ) {
			if ( ! is_array( $p ) || empty( $p['rid'] ) || ! array_key_exists( 'right', $p ) ) {
				continue;
			}
			$out[] = array(
				'id'    => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $p['id'] ?? '' ) ) ),
				'left'  => (string) ( $p['left'] ?? '' ),
				'rid'   => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $p['rid'] ) ),
				'right' => (string) $p['right'],
			);
		}
		return $out;
	}

	/** Paires avec un élément à gauche (les intrus n'ont que la droite). */
	private static function match_lefts( array $pairs ): array {
		return array_values( array_filter( $pairs, static fn( $p ) => '' !== trim( $p['left'] ) && '' !== $p['id'] ) );
	}

	public static function match_settings( $expected ): array {
		$d = is_array( $expected ) ? $expected : json_decode( (string) $expected, true );
		return array( 'scoring' => is_array( $d ) && 'all' === ( $d['scoring'] ?? '' ) ? 'all' : 'partial' );
	}

	/** Pour l'élève : gauche dans l'ordre de l'enseignant, droite mélangée (toujours le même mélange pour lui). */
	public static function student_match( array $question, int $user_id ): array {
		$pairs = self::match_pairs( $question['options'] ?? '' );
		$left  = array();
		foreach ( self::match_lefts( $pairs ) as $p ) {
			$left[] = array( 'id' => $p['id'], 'text' => $p['left'] );
		}
		$right = array();
		foreach ( $pairs as $p ) {
			$right[] = array( 'id' => $p['rid'], 'text' => $p['right'] );
		}
		$n = count( $right );
		if ( $n > 1 ) {
			mt_srand( crc32( 'logiexam-match|' . (int) ( $question['id'] ?? 0 ) . '|' . $user_id ) );
			for ( $i = $n - 1; $i > 0; $i-- ) {
				$j           = mt_rand( 0, $i );
				$tmp         = $right[ $i ];
				$right[ $i ] = $right[ $j ];
				$right[ $j ] = $tmp;
			}
			mt_srand();
			if ( wp_list_pluck( $right, 'id' ) === wp_list_pluck( $pairs, 'rid' ) ) {
				$right = array_merge( array_slice( $right, 1 ), array( $right[0] ) ); // jamais dans l'ordre des paires
			}
		}
		return array( 'left' => $left, 'right' => $right );
	}

	private static function match_answer_pairs( $answer ): array {
		$a   = is_array( $answer ) ? $answer : json_decode( (string) $answer, true );
		$a   = is_array( $a ) && isset( $a['pairs'] ) ? (array) $a['pairs'] : ( is_array( $a ) ? $a : array() );
		$out = array();
		foreach ( $a as $l => $r ) {
			$l = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $l ) );
			$r = preg_replace( '/[^a-z0-9]/', '', strtolower( is_scalar( $r ) ? (string) $r : '' ) );
			if ( '' !== $l && '' !== $r ) {
				$out[ $l ] = $r;
			}
		}
		return $out;
	}

	/** Pour chaque élément de gauche : left, ok, given (texte relié), right (texte juste). */
	public static function match_marks( array $question, $answer ): array {
		$pairs  = self::match_pairs( $question['options'] ?? '' );
		$given  = self::match_answer_pairs( $answer );
		$by_rid = array();
		foreach ( $pairs as $p ) {
			$by_rid[ $p['rid'] ] = $p['right'];
		}
		$norm  = static fn( $s ) => remove_accents( mb_strtolower( trim( (string) $s ) ) );
		$marks = array();
		foreach ( self::match_lefts( $pairs ) as $p ) {
			$g   = $given[ $p['id'] ] ?? '';
			$txt = '' !== $g && isset( $by_rid[ $g ] ) ? $by_rid[ $g ] : '';
			// Deux éléments de droite identiques sont interchangeables.
			$marks[ $p['id'] ] = array( 'left' => $p['left'], 'ok' => '' !== $txt && $norm( $txt ) === $norm( $p['right'] ), 'given' => $txt, 'right' => $p['right'] );
		}
		return $marks;
	}

	/* ---------------------------------------------------------------------
	 * Texte à trous (1.36)
	 * ------------------------------------------------------------------- */

	/** Découpe « Le [FIFO] signifie… » : parts (texte, null pour un trou) et blanks (variantes acceptées). */
	public static function parse_blanks_text( string $text ): array {
		$parts  = array();
		$blanks = array();
		$offset = 0;
		$buffer = '';
		if ( preg_match_all( '/\[([^\[\]]+)\]/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $i => $whole ) {
				$buffer  .= substr( $text, $offset, $whole[1] - $offset );
				$offset   = $whole[1] + strlen( $whole[0] );
				$variants = array_values( array_filter( array_map( 'trim', explode( '|', $m[1][ $i ][0] ) ), 'strlen' ) );
				if ( ! $variants ) {
					$buffer .= $whole[0];
					continue;
				}
				$parts[]  = $buffer;
				$buffer   = '';
				$parts[]  = null;
				$blanks[] = $variants;
			}
		}
		$parts[] = $buffer . substr( $text, $offset );
		return array( 'parts' => $parts, 'blanks' => $blanks );
	}

	public static function blanks_expected( $expected ): array {
		$d    = is_array( $expected ) ? $expected : json_decode( (string) $expected, true );
		$d    = is_array( $d ) ? $d : array();
		$text = (string) ( $d['text'] ?? '' );
		$p    = self::parse_blanks_text( $text );
		return array(
			'text'     => $text,
			'parts'    => $p['parts'],
			'blanks'   => $p['blanks'],
			'bank'     => ! empty( $d['bank'] ),
			'tolerant' => ! empty( $d['tolerant'] ),
			'scoring'  => 'all' === ( $d['scoring'] ?? '' ) ? 'all' : 'partial',
		);
	}

	/** Pour l'élève : le texte découpé, le nombre de trous et (si choisi) la liste des mots mélangée — jamais quel mot va où. */
	public static function student_blanks( array $question, int $user_id ): array {
		$e    = self::blanks_expected( $question['expected_answer'] ?? '' );
		$bank = array();
		if ( $e['bank'] ) {
			foreach ( $e['blanks'] as $variants ) {
				$bank[] = $variants[0];
			}
			if ( count( $bank ) > 1 ) {
				mt_srand( crc32( 'logiexam-bank|' . (int) ( $question['id'] ?? 0 ) . '|' . $user_id ) );
				for ( $i = count( $bank ) - 1; $i > 0; $i-- ) {
					$j          = mt_rand( 0, $i );
					$tmp        = $bank[ $i ];
					$bank[ $i ] = $bank[ $j ];
					$bank[ $j ] = $tmp;
				}
				mt_srand();
			}
		}
		return array( 'parts' => $e['parts'], 'count' => count( $e['blanks'] ), 'bank' => $bank );
	}

	private static function blank_norm( string $s ): string {
		$s = remove_accents( mb_strtolower( wp_strip_all_tags( $s ) ) );
		$s = str_replace( array( "\u{2019}", '`' ), "'", $s );
		$s = preg_replace( '/\s+/u', ' ', trim( $s ) );
		return trim( $s, " .,;:!?\"«»" );
	}

	public static function blank_ok( string $given, array $variants, bool $tolerant ): bool {
		$g = self::blank_norm( $given );
		if ( '' === $g ) {
			return false;
		}
		foreach ( $variants as $v ) {
			$v = self::blank_norm( (string) $v );
			if ( $g === $v ) {
				return true;
			}
			// Une petite faute (lettre en trop, en moins ou changée) dans un mot d'au moins 5 lettres.
			if ( $tolerant && mb_strlen( $v ) >= 5 && levenshtein( $g, $v ) <= 1 ) {
				return true;
			}
		}
		return false;
	}

	private static function blanks_answer( $answer ): array {
		$a = is_array( $answer ) ? $answer : json_decode( (string) $answer, true );
		$a = is_array( $a ) && isset( $a['blanks'] ) ? (array) $a['blanks'] : array();
		return array_map( static fn( $v ) => is_scalar( $v ) ? (string) $v : '', array_values( $a ) );
	}

	/** Pour chaque trou : given, ok, expected. */
	public static function blanks_marks( array $question, $answer ): array {
		$e     = self::blanks_expected( $question['expected_answer'] ?? '' );
		$given = self::blanks_answer( $answer );
		$out   = array();
		foreach ( $e['blanks'] as $i => $variants ) {
			$g     = $given[ $i ] ?? '';
			$out[] = array( 'given' => $g, 'ok' => self::blank_ok( $g, $variants, $e['tolerant'] ), 'expected' => implode( ' / ', $variants ) );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Lecture des données enregistrées
	 * ------------------------------------------------------------------- */

	public static function tfj_expected( $value ): array {
		$d = is_array( $value ) ? $value : json_decode( (string) $value, true );
		$d = is_array( $d ) ? $d : array( 'choice' => (string) $value );
		$choice = (string) ( $d['choice'] ?? '' );
		return array(
			'choice'        => in_array( $choice, array( 'Vrai', 'Faux' ), true ) ? $choice : '',
			'justification' => (string) ( $d['justification'] ?? '' ),
			'choice_points' => isset( $d['choice_points'] ) && '' !== $d['choice_points'] ? max( 0, (float) $d['choice_points'] ) : null,
		);
	}

	/** @return array<int, array{id:string, text:string, media_id:int}> */
	public static function order_items( $options ): array {
		$list = is_array( $options ) ? $options : json_decode( (string) $options, true );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			$out[] = array(
				'id'       => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $item['id'] ) ),
				'text'     => (string) ( $item['text'] ?? '' ),
				'media_id' => absint( $item['media_id'] ?? 0 ),
			);
		}
		return $out;
	}

	public static function order_settings( $expected ): array {
		$d = is_array( $expected ) ? $expected : json_decode( (string) $expected, true );
		$d = is_array( $d ) ? $d : array();
		return array(
			'mode'    => 'letters' === ( $d['mode'] ?? '' ) ? 'letters' : 'items',
			'scoring' => 'all' === ( $d['scoring'] ?? '' ) ? 'all' : 'partial',
		);
	}

	/** Libellé lisible d'un élément (côté enseignant : une image sans légende devient « Image n »). */
	public static function item_label( array $item, int $position ): string {
		$text = trim( (string) $item['text'] );
		if ( $item['media_id'] ) {
			/* translators: %d: position de l'image dans le bon ordre */
			return $text ? sprintf( __( 'Image « %s »', 'logieval-secure' ), $text ) : sprintf( __( 'Image %d', 'logieval-secure' ), $position );
		}
		return $text;
	}

	/** Clé de comparaison : deux éléments identiques (même lettre, même texte) sont interchangeables. */
	private static function item_key( array $item ): string {
		return $item['media_id'] ? 'm' . $item['media_id'] . '|' . strtolower( trim( $item['text'] ) ) : 't|' . remove_accents( mb_strtolower( trim( $item['text'] ) ) );
	}

	private static function new_id(): string {
		return strtolower( wp_generate_password( 8, false, false ) );
	}

	/* ---------------------------------------------------------------------
	 * Éditeur : construction à partir du formulaire
	 * ------------------------------------------------------------------- */

	/**
	 * @param callable $image_resolver fn(int $current_id, bool $remove, array $file_path_suffix): int
	 * @return array{expected_answer:string, options:string}|null
	 */
	public static function build_from_form( string $type, array $row, callable $image_resolver ): ?array {
		if ( self::TFJ === $type ) {
			$choice = sanitize_text_field( wp_unslash( $row['tfj_choice'] ?? '' ) );
			$cp     = trim( (string) wp_unslash( $row['tfj_choice_points'] ?? '' ) );
			return array(
				'expected_answer' => wp_json_encode(
					array(
						'choice'        => in_array( $choice, array( 'Vrai', 'Faux' ), true ) ? $choice : '',
						'justification' => sanitize_textarea_field( wp_unslash( $row['tfj_justification'] ?? '' ) ),
						'choice_points' => '' === $cp ? null : max( 0, (float) str_replace( ',', '.', $cp ) ),
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				),
				'options'         => '[]',
			);
		}
		if ( self::MATCHING === $type ) {
			$pairs = array();
			$seen  = array();
			foreach ( (array) ( $row['match_pairs'] ?? array() ) as $p ) {
				$p     = (array) $p;
				$left  = sanitize_text_field( wp_unslash( $p['left'] ?? '' ) );
				$right = sanitize_text_field( wp_unslash( $p['right'] ?? '' ) );
				if ( '' === $right ) {
					continue; // ligne vide (ou sans élément à relier)
				}
				$id  = sanitize_key( (string) ( $p['id'] ?? '' ) );
				$rid = sanitize_key( (string) ( $p['rid'] ?? '' ) );
				$id  = '' !== $id && ! isset( $seen[ $id ] ) ? $id : self::new_id();
				$seen[ $id ] = true;
				$rid = '' !== $rid && ! isset( $seen[ $rid ] ) ? $rid : self::new_id();
				$seen[ $rid ] = true;
				$pairs[] = array( 'id' => '' !== $left ? $id : '', 'left' => $left, 'rid' => $rid, 'right' => $right );
			}
			$labels = array();
			foreach ( $pairs as $p ) {
				$labels[] = '' !== $p['left'] ? $p['left'] . ' → ' . $p['right'] : '(' . __( 'intrus', 'logieval-secure' ) . ') ' . $p['right'];
			}
			return array(
				'expected_answer' => wp_json_encode( array( 'kind' => self::MATCHING, 'scoring' => 'all' === ( $row['match_scoring'] ?? '' ) ? 'all' : 'partial', 'labels' => $labels ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'options'         => wp_json_encode( $pairs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			);
		}
		if ( self::BLANKS === $type ) {
			$text = sanitize_textarea_field( wp_unslash( $row['blanks_text'] ?? '' ) );
			return array(
				'expected_answer' => wp_json_encode(
					array(
						'kind'     => self::BLANKS,
						'text'     => $text,
						'bank'     => ! empty( $row['blanks_bank'] ) ? 1 : 0,
						'tolerant' => ! empty( $row['blanks_tolerant'] ) ? 1 : 0,
						'scoring'  => 'all' === ( $row['blanks_scoring'] ?? '' ) ? 'all' : 'partial',
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				),
				'options'         => '[]',
			);
		}
		if ( self::ORDERING !== $type ) {
			return null;
		}

		$mode    = 'letters' === ( $row['order_mode'] ?? '' ) ? 'letters' : 'items';
		$scoring = 'all' === ( $row['order_scoring'] ?? '' ) ? 'all' : 'partial';
		$items   = array();
		if ( 'letters' === $mode ) {
			$word = preg_replace( '/\s+/u', '', sanitize_text_field( wp_unslash( $row['order_word'] ?? '' ) ) );
			$old  = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $row['order_word_ids'] ?? '' ) ) ) ) );
			foreach ( preg_split( '//u', (string) $word, -1, PREG_SPLIT_NO_EMPTY ) as $i => $letter ) {
				$items[] = array( 'id' => $old[ $i ] ?? self::new_id(), 'text' => mb_strtoupper( $letter ), 'media_id' => 0 );
			}
		} else {
			foreach ( (array) ( $row['order_items'] ?? array() ) as $key => $item ) {
				$item     = (array) $item;
				$text     = sanitize_text_field( wp_unslash( $item['text'] ?? '' ) );
				$media_id = (int) $image_resolver( absint( $item['media_id'] ?? 0 ), ! empty( $item['remove_image'] ), array( 'order_items', $key ) );
				if ( '' === $text && ! $media_id ) {
					continue; // ligne vide
				}
				$id      = sanitize_key( (string) ( $item['id'] ?? '' ) );
				$items[] = array( 'id' => '' !== $id ? $id : self::new_id(), 'text' => $text, 'media_id' => $media_id );
			}
		}
		// Identifiants uniques (une ligne recopiée garde sinon le même).
		$seen = array();
		foreach ( $items as &$item ) {
			while ( isset( $seen[ $item['id'] ] ) ) {
				$item['id'] = self::new_id();
			}
			$seen[ $item['id'] ] = true;
		}
		unset( $item );

		$labels = array();
		foreach ( $items as $i => $item ) {
			$labels[] = self::item_label( $item, $i + 1 );
		}
		return array(
			'expected_answer' => wp_json_encode( array( 'mode' => $mode, 'scoring' => $scoring, 'labels' => $labels ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'options'         => wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Élève
	 * ------------------------------------------------------------------- */

	/** Éléments mélangés pour un élève : même mélange à chaque fois pour lui, jamais le bon ordre (s'il y a plus d'un ordre possible). */
	public static function student_items( array $question, int $user_id ): array {
		$items = self::order_items( $question['options'] ?? '' );
		$n     = count( $items );
		if ( $n < 2 ) {
			return self::public_items( $items );
		}
		$seed     = crc32( 'logiexam-order|' . (int) ( $question['id'] ?? 0 ) . '|' . $user_id );
		$shuffled = $items;
		for ( $tries = 0; $tries < 12; $tries++ ) {
			mt_srand( $seed + $tries );
			for ( $i = $n - 1; $i > 0; $i-- ) {
				$j = mt_rand( 0, $i );
				$tmp = $shuffled[ $i ];
				$shuffled[ $i ] = $shuffled[ $j ];
				$shuffled[ $j ] = $tmp;
			}
			if ( self::score_ratio( $items, wp_list_pluck( $shuffled, 'id' ) ) < 1 ) {
				break;
			}
		}
		mt_srand();
		if ( self::score_ratio( $items, wp_list_pluck( $shuffled, 'id' ) ) >= 1 ) {
			// Tous les mélanges essayés retombent sur le bon ordre (ex. lettres presque toutes identiques) : on décale d'un cran.
			$shuffled = array_merge( array_slice( $items, 1 ), array( $items[0] ) );
		}
		return self::public_items( $shuffled );
	}

	private static function public_items( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$image = $item['media_id'] && class_exists( 'LogiEval_Secure_Evaluations' ) ? LogiEval_Secure_Evaluations::question_image_data( $item['media_id'] ) : array();
			$out[] = array(
				'id'    => $item['id'],
				'text'  => $item['text'],
				'image' => $image ? array( 'url' => $image['url'], 'full' => $image['full'] ) : null,
			);
		}
		return $out;
	}

	/** Réponse envoyée par le navigateur → valeur enregistrée (JSON). Retourne null si le type n'est pas concerné. */
	public static function answer_to_store( array $question, $answer ): ?string {
		$type = (string) ( $question['type'] ?? '' );
		if ( self::TFJ === $type ) {
			$a = is_array( $answer ) ? $answer : json_decode( (string) $answer, true );
			$a = is_array( $a ) ? $a : array();
			$choice = (string) ( $a['choice'] ?? '' );
			$just   = sanitize_textarea_field( (string) ( $a['justification'] ?? '' ) );
			if ( ! in_array( $choice, array( 'Vrai', 'Faux' ), true ) ) {
				$choice = '';
			}
			if ( '' === $choice && '' === trim( $just ) ) {
				return '';
			}
			return wp_json_encode( array( 'choice' => $choice, 'justification' => $just ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		if ( self::ORDERING === $type ) {
			$ids = self::answer_order_ids( $answer );
			if ( ! $ids ) {
				return '';
			}
			$items    = self::order_items( $question['options'] ?? '' );
			$by_id    = array();
			foreach ( $items as $i => $item ) {
				$by_id[ $item['id'] ] = self::item_label( $item, $i + 1 );
			}
			$order = array_values( array_filter( $ids, static fn( $id ) => isset( $by_id[ $id ] ) ) );
			$order = array_values( array_unique( $order ) );
			if ( ! $order ) {
				return '';
			}
			return wp_json_encode( array( 'order' => $order, 'labels' => array_map( static fn( $id ) => $by_id[ $id ], $order ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		if ( self::MATCHING === $type ) {
			$pairs  = self::match_pairs( $question['options'] ?? '' );
			$lefts  = array();
			$rights = array();
			foreach ( $pairs as $p ) {
				$rights[ $p['rid'] ] = $p['right'];
				if ( '' !== $p['id'] && '' !== trim( $p['left'] ) ) {
					$lefts[ $p['id'] ] = $p['left'];
				}
			}
			$out = array();
			foreach ( self::match_answer_pairs( $answer ) as $l => $r ) {
				if ( isset( $lefts[ $l ], $rights[ $r ] ) ) {
					$out[ $l ] = $r;
				}
			}
			if ( ! $out ) {
				return '';
			}
			$labels = array();
			foreach ( $lefts as $l => $text ) {
				$labels[] = $text . ' → ' . ( isset( $out[ $l ] ) ? $rights[ $out[ $l ] ] : '…' );
			}
			return wp_json_encode( array( 'pairs' => $out, 'labels' => $labels ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		if ( self::BLANKS === $type ) {
			$count  = count( self::blanks_expected( $question['expected_answer'] ?? '' )['blanks'] );
			$given  = self::blanks_answer( $answer );
			$blanks = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$blanks[] = mb_substr( sanitize_text_field( $given[ $i ] ?? '' ), 0, 200 );
			}
			if ( '' === trim( implode( '', $blanks ) ) ) {
				return '';
			}
			return wp_json_encode( array( 'blanks' => $blanks ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		return null;
	}

	private static function answer_order_ids( $answer ): array {
		if ( is_string( $answer ) ) {
			$decoded = json_decode( $answer, true );
			$answer  = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_array( $answer ) && isset( $answer['order'] ) ) {
			$answer = (array) $answer['order'];
		}
		return array_values( array_filter( array_map( static fn( $v ) => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $v ) ), is_array( $answer ) ? $answer : array() ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Correction automatique
	 * ------------------------------------------------------------------- */

	private static function score_ratio( array $items, array $order ): float {
		$n = count( $items );
		if ( ! $n ) {
			return 0.0;
		}
		$keys = array();
		foreach ( $items as $item ) {
			$keys[ $item['id'] ] = self::item_key( $item );
		}
		$good = 0;
		foreach ( $items as $i => $item ) {
			$given = $order[ $i ] ?? '';
			if ( '' !== $given && isset( $keys[ $given ] ) && $keys[ $given ] === self::item_key( $item ) ) {
				$good++;
			}
		}
		return $good / $n;
	}

	/** Positions justes (pour l'affichage) : tableau de booléens dans l'ordre de l'élève. */
	public static function order_marks( array $question, $answer ): array {
		$items = self::order_items( $question['options'] ?? '' );
		$order = self::answer_order_ids( $answer );
		$keys  = array();
		foreach ( $items as $item ) {
			$keys[ $item['id'] ] = self::item_key( $item );
		}
		$marks = array();
		foreach ( $order as $i => $id ) {
			$marks[] = isset( $items[ $i ], $keys[ $id ] ) && $keys[ $id ] === self::item_key( $items[ $i ] );
		}
		return $marks;
	}

	/** @return float|null null = type non concerné */
	public static function auto_points( array $question, $answer, float $points ): ?float {
		$type = (string) ( $question['type'] ?? '' );
		if ( self::TFJ === $type ) {
			$expected = self::tfj_expected( $question['expected_answer'] ?? '' );
			if ( '' === $expected['choice'] ) {
				return null;
			}
			$a      = is_array( $answer ) ? $answer : json_decode( (string) $answer, true );
			$choice = is_array( $a ) ? (string) ( $a['choice'] ?? '' ) : '';
			$share  = null === $expected['choice_points'] ? $points / 2 : min( $points, $expected['choice_points'] );
			return $choice === $expected['choice'] ? round( $share, 2 ) : 0.0;
		}
		if ( self::ORDERING === $type ) {
			$items = self::order_items( $question['options'] ?? '' );
			if ( count( $items ) < 2 ) {
				return null;
			}
			$order = self::answer_order_ids( $answer );
			if ( ! $order ) {
				return 0.0;
			}
			$ratio = self::score_ratio( $items, $order );
			if ( 'all' === self::order_settings( $question['expected_answer'] ?? '' )['scoring'] ) {
				return $ratio >= 1 ? $points : 0.0;
			}
			return round( $points * $ratio * 4 ) / 4; // au quart de point
		}
		if ( self::MATCHING === $type ) {
			$marks = self::match_marks( $question, $answer );
			if ( ! $marks ) {
				return null;
			}
			$ratio = count( array_filter( wp_list_pluck( $marks, 'ok' ) ) ) / count( $marks );
			if ( 'all' === self::match_settings( $question['expected_answer'] ?? '' )['scoring'] ) {
				return $ratio >= 1 ? $points : 0.0;
			}
			return round( $points * $ratio * 4 ) / 4;
		}
		if ( self::BLANKS === $type ) {
			$e = self::blanks_expected( $question['expected_answer'] ?? '' );
			if ( ! $e['blanks'] ) {
				return null;
			}
			$marks = self::blanks_marks( $question, $answer );
			$ratio = count( array_filter( wp_list_pluck( $marks, 'ok' ) ) ) / count( $marks );
			if ( 'all' === $e['scoring'] ) {
				return $ratio >= 1 ? $points : 0.0;
			}
			return round( $points * $ratio * 4 ) / 4;
		}
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Lecture par l'enseignant (correction, PDF, IA)
	 * ------------------------------------------------------------------- */

	/** Texte lisible pour une valeur JSON des nouveaux types, ou null. */
	public static function readable( array $decoded ): ?string {
		if ( array_key_exists( 'choice', $decoded ) && array_key_exists( 'justification', $decoded ) ) {
			$choice = '' !== (string) $decoded['choice'] ? (string) $decoded['choice'] : __( '(aucun choix)', 'logieval-secure' );
			$just   = trim( (string) $decoded['justification'] );
			$out    = sprintf( __( 'Choix : %s', 'logieval-secure' ), $choice );
			if ( isset( $decoded['choice_points'] ) && null !== $decoded['choice_points'] ) {
				$out .= ' (' . sprintf( __( '%s pt pour le bon choix', 'logieval-secure' ), rtrim( rtrim( number_format( (float) $decoded['choice_points'], 2, '.', '' ), '0' ), '.' ) ) . ')';
			}
			return $out . "\n" . __( 'Argumentation', 'logieval-secure' ) . ' : ' . ( '' !== $just ? $just : __( '(vide)', 'logieval-secure' ) );
		}
		if ( isset( $decoded['labels'] ) && is_array( $decoded['labels'] ) && ( isset( $decoded['pairs'] ) || self::MATCHING === ( $decoded['kind'] ?? '' ) ) ) {
			$out = implode( "\n", array_map( 'strval', $decoded['labels'] ) );
			if ( self::MATCHING === ( $decoded['kind'] ?? '' ) ) {
				$out .= "\n" . ( 'all' === ( $decoded['scoring'] ?? '' ) ? __( '(tout juste ou rien)', 'logieval-secure' ) : __( '(points au prorata des liaisons justes)', 'logieval-secure' ) );
			}
			return $out;
		}
		if ( self::BLANKS === ( $decoded['kind'] ?? '' ) ) {
			$e     = self::blanks_expected( $decoded );
			$lines = array();
			foreach ( $e['blanks'] as $i => $v ) {
				/* translators: 1: numéro du trou, 2: réponses acceptées */
				$lines[] = sprintf( __( 'Trou %1$d : %2$s', 'logieval-secure' ), $i + 1, implode( ' / ', $v ) );
			}
			return trim( $e['text'] ) . "\n" . implode( "\n", $lines );
		}
		if ( isset( $decoded['blanks'] ) && is_array( $decoded['blanks'] ) && ! isset( $decoded['kind'] ) ) {
			$lines = array();
			foreach ( array_values( $decoded['blanks'] ) as $i => $v ) {
				$lines[] = sprintf( __( 'Trou %1$d : %2$s', 'logieval-secure' ), $i + 1, '' !== trim( (string) $v ) ? (string) $v : __( '(vide)', 'logieval-secure' ) );
			}
			return implode( "\n", $lines );
		}
		if ( isset( $decoded['labels'] ) && is_array( $decoded['labels'] ) && ( isset( $decoded['order'] ) || isset( $decoded['mode'] ) ) ) {
			$lines = array();
			foreach ( array_values( $decoded['labels'] ) as $i => $label ) {
				$lines[] = ( $i + 1 ) . '. ' . (string) $label;
			}
			if ( isset( $decoded['mode'] ) && 'letters' === $decoded['mode'] ) {
				return implode( '', array_map( 'strval', $decoded['labels'] ) ) . "\n" . implode( "\n", $lines );
			}
			return implode( "\n", $lines );
		}
		return null;
	}

	/** Affichage riche de la réponse « remettre dans l'ordre » (✓ / ✗ par position, vignettes). */
	public static function render_order_answer( array $question, string $answer ): bool {
		if ( self::MATCHING === ( $question['type'] ?? '' ) ) {
			return self::render_match_answer( $question, $answer );
		}
		if ( self::BLANKS === ( $question['type'] ?? '' ) ) {
			return self::render_blanks_answer( $question, $answer );
		}
		if ( self::ORDERING !== ( $question['type'] ?? '' ) ) {
			return false;
		}
		$items = self::order_items( $question['options'] ?? '' );
		$by_id = array();
		foreach ( $items as $i => $item ) {
			$by_id[ $item['id'] ] = array( $item, $i + 1 );
		}
		$order = self::answer_order_ids( $answer );
		if ( ! $order ) {
			echo '<pre>' . esc_html__( 'Non répondu', 'logieval-secure' ) . '</pre>';
			return true;
		}
		$marks   = self::order_marks( $question, $answer );
		$letters = 'letters' === self::order_settings( $question['expected_answer'] ?? '' )['mode'];
		echo '<ol class="les-order-review' . ( $letters ? ' is-letters' : '' ) . '">';
		foreach ( $order as $i => $id ) {
			if ( ! isset( $by_id[ $id ] ) ) {
				continue;
			}
			list( $item, $right_pos ) = $by_id[ $id ];
			$ok    = ! empty( $marks[ $i ] );
			$image = $item['media_id'] ? LogiEval_Secure_Evaluations::question_image_data( $item['media_id'] ) : array();
			echo '<li class="' . ( $ok ? 'is-right' : 'is-wrong' ) . '"><span class="les-order-mark" aria-label="' . esc_attr( $ok ? __( 'bien placé', 'logieval-secure' ) : __( 'mal placé', 'logieval-secure' ) ) . '">' . ( $ok ? '✓' : '✗' ) . '</span>';
			if ( $image ) {
				echo '<img src="' . esc_url( $image['url'] ) . '" alt="">';
			}
			echo '<span>' . esc_html( self::item_label( $item, $right_pos ) ) . '</span>';
			if ( ! $ok && ! $letters ) {
				/* translators: %d: bonne position */
				echo ' <small>' . esc_html( sprintf( __( '(place correcte : %d)', 'logieval-secure' ), $right_pos ) ) . '</small>';
			}
			echo '</li>';
		}
		echo '</ol>';
		$good = count( array_filter( $marks ) );
		echo '<p class="les-order-score">' . esc_html( sprintf( _n( '%1$d élément bien placé sur %2$d', '%1$d éléments bien placés sur %2$d', $good, 'logieval-secure' ), $good, count( $items ) ) ) . '</p>';
		return true;
	}

	private static function render_match_answer( array $question, string $answer ): bool {
		if ( '' === trim( $answer ) ) {
			echo '<pre>' . esc_html__( 'Non répondu', 'logieval-secure' ) . '</pre>';
			return true;
		}
		$marks = self::match_marks( $question, $answer );
		echo '<ul class="les-match-review">';
		foreach ( $marks as $m ) {
			echo '<li class="' . ( $m['ok'] ? 'is-right' : 'is-wrong' ) . '"><span class="les-order-mark">' . ( $m['ok'] ? '✓' : '✗' ) . '</span> <strong>' . esc_html( $m['left'] ) . '</strong> → ' . esc_html( '' !== $m['given'] ? $m['given'] : __( '(pas relié)', 'logieval-secure' ) );
			if ( ! $m['ok'] ) {
				/* translators: %s: bonne réponse */
				echo ' <small>' . esc_html( sprintf( __( '(juste : %s)', 'logieval-secure' ), $m['right'] ) ) . '</small>';
			}
			echo '</li>';
		}
		echo '</ul>';
		$good = count( array_filter( wp_list_pluck( $marks, 'ok' ) ) );
		echo '<p class="les-order-score">' . esc_html( sprintf( _n( '%1$d liaison juste sur %2$d', '%1$d liaisons justes sur %2$d', $good, 'logieval-secure' ), $good, count( $marks ) ) ) . '</p>';
		return true;
	}

	private static function render_blanks_answer( array $question, string $answer ): bool {
		if ( '' === trim( $answer ) ) {
			echo '<pre>' . esc_html__( 'Non répondu', 'logieval-secure' ) . '</pre>';
			return true;
		}
		$e     = self::blanks_expected( $question['expected_answer'] ?? '' );
		$marks = self::blanks_marks( $question, $answer );
		$k     = 0;
		echo '<p class="les-blanks-review">';
		foreach ( $e['parts'] as $part ) {
			if ( null !== $part ) {
				echo nl2br( esc_html( $part ) );
				continue;
			}
			$m = $marks[ $k++ ] ?? array( 'given' => '', 'ok' => false, 'expected' => '' );
			echo '<span class="les-blank-mark ' . ( $m['ok'] ? 'is-right' : 'is-wrong' ) . '">' . ( $m['ok'] ? '✓ ' : '✗ ' ) . esc_html( '' !== trim( $m['given'] ) ? $m['given'] : '…' ) . '</span>';
			if ( ! $m['ok'] ) {
				echo ' <small class="les-blank-expected">(' . esc_html( $m['expected'] ) . ')</small>';
			}
		}
		echo '</p>';
		$good = count( array_filter( wp_list_pluck( $marks, 'ok' ) ) );
		echo '<p class="les-order-score">' . esc_html( sprintf( _n( '%1$d trou juste sur %2$d', '%1$d trous justes sur %2$d', $good, 'logieval-secure' ), $good, count( $marks ) ) ) . '</p>';
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Calcul : précision du résultat attendue (exact, arrondi à l'entier, à n décimales)
	 * ------------------------------------------------------------------- */

	public const PRECISIONS = array( 'exact', '0', '1', '2', '3', '4' );

	/** Libellé affiché à l'élève, à l'enseignant et dans l'export ; '' si l'enseignant n'a rien précisé. */
	public static function precision_label( string $precision ): string {
		if ( 'exact' === $precision ) {
			return __( 'Résultat exact demandé (sans arrondi)', 'logieval-secure' );
		}
		if ( '0' === $precision ) {
			return __( 'Résultat arrondi à l’entier', 'logieval-secure' );
		}
		if ( in_array( $precision, array( '1', '2', '3', '4' ), true ) ) {
			/* translators: %d: nombre de décimales */
			return sprintf( _n( 'Résultat arrondi à %d décimale', 'Résultat arrondi à %d décimales', (int) $precision, 'logieval-secure' ), (int) $precision );
		}
		return '';
	}

	public static function calculation_precision( $expected ): string {
		$d = is_array( $expected ) ? $expected : json_decode( (string) $expected, true );
		$p = is_array( $d ) ? (string) ( $d['precision'] ?? '' ) : '';
		return in_array( $p, self::PRECISIONS, true ) ? $p : '';
	}

	/** Nombre écrit par l'élève (virgule ou point, espaces et apostrophes de milliers) ; null si aucun nombre. */
	public static function parse_number( $value ): ?float {
		$text = str_replace( array( "\u{2019}", "'", "\u{00A0}", "\u{202F}", ' ' ), '', (string) $value );
		$text = str_replace( array( '−', '–' ), '-', $text );
		if ( ! preg_match( '/-?\d+(?:[.,]\d+)?/u', $text, $m ) ) {
			return null;
		}
		return (float) str_replace( ',', '.', $m[0] );
	}

	/**
	 * Résultat final juste ? Avec une précision choisie : comparaison numérique (exact, ou à ± une demi-unité
	 * de la dernière décimale demandée). Sans précision : même texte, ou même nombre. null = rien à comparer.
	 */
	public static function calculation_result_ok( $expected, $given ): ?bool {
		$d        = is_array( $expected ) ? $expected : json_decode( (string) $expected, true );
		$target   = is_array( $d ) ? (string) ( $d['final_result'] ?? '' ) : (string) $expected;
		$given    = is_array( $given ) ? (string) ( $given['final_result'] ?? '' ) : (string) $given;
		if ( '' === trim( $target ) ) {
			return null;
		}
		if ( '' === trim( $given ) ) {
			return false;
		}
		$precision = self::calculation_precision( $d );
		$b         = self::parse_number( $target );
		$numbers   = self::numbers_in( $given ); // Le résultat peut être écrit dans une phrase, avec une unité.
		if ( null !== $b && $numbers ) {
			$target_dec = self::decimals_of( $target );
			foreach ( $numbers as $n ) {
				list( $a, $dec ) = $n;
				if ( abs( $a - $b ) <= 1e-9 * max( 1, abs( $b ) ) ) {
					return true;
				}
				if ( 'exact' === $precision ) {
					continue;
				}
				if ( '' !== $precision ) {
					if ( abs( $a - $b ) <= 0.5 * pow( 10, -(int) $precision ) + 1e-9 ) {
						return true;
					}
					continue;
				}
				// Sans précision imposée : un arrondi correct est accepté (233,33 pour 233,33333), avec au moins
				// une décimale quand le résultat attendu n'est pas entier (7 pour 6,6667 reste refusé).
				if ( $dec < $target_dec && $dec >= min( 1, $target_dec ) && abs( $a - $b ) <= 0.5 * pow( 10, -$dec ) + 1e-9 ) {
					return true;
				}
			}
		}
		$norm = static fn( $v ) => preg_replace( '/\s+/', ' ', remove_accents( strtolower( trim( wp_strip_all_tags( (string) $v ) ) ) ) );
		return $norm( $given ) === $norm( $target );
	}

	/** Tous les nombres d'un texte, avec leur nombre de décimales : [[valeur, décimales], …]. */
	public static function numbers_in( $value ): array {
		$text = str_replace( array( "\u{2019}", "'", "\u{00A0}", "\u{202F}" ), '', (string) $value );
		$text = preg_replace( '/(?<=\d) (?=\d{3}\b)/u', '', $text ); // 3 500 → 3500
		$text = str_replace( array( '−', '–' ), '-', $text );
		preg_match_all( '/-?\d+(?:[.,](\d+))?/u', $text, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $x ) {
			$out[] = array( (float) str_replace( ',', '.', $x[0] ), isset( $x[1] ) ? strlen( $x[1] ) : 0 );
		}
		return $out;
	}

	private static function decimals_of( $value ): int {
		$n = self::numbers_in( $value );
		return $n ? (int) $n[0][1] : 0;
	}

	/* ---------------------------------------------------------------------
	 * Éditeur : champs
	 * ------------------------------------------------------------------- */

	public static function render_editor_fields( string $name_prefix, array $question ): void {
		$type     = sanitize_key( $question['type'] ?? '' );
		$tfj      = self::tfj_expected( self::TFJ === $type ? ( $question['expected_answer'] ?? '' ) : '' );
		$items    = self::ORDERING === $type ? self::order_items( $question['options'] ?? '' ) : array();
		$settings = self::order_settings( self::ORDERING === $type ? ( $question['expected_answer'] ?? '' ) : '' );
		$p        = $name_prefix;
		?>
		<div data-logiexam-field-types="<?php echo esc_attr( self::TFJ ); ?>" class="les-tfj-editor">
			<div class="les-form-row">
				<label><?php esc_html_e( 'Réponse correcte', 'logieval-secure' ); ?>
					<select class="widefat" name="<?php echo esc_attr( $p ); ?>[tfj_choice]">
						<option value=""><?php esc_html_e( 'Sélectionner', 'logieval-secure' ); ?></option>
						<option value="Vrai" <?php selected( $tfj['choice'], 'Vrai' ); ?>><?php esc_html_e( 'Vrai', 'logieval-secure' ); ?></option>
						<option value="Faux" <?php selected( $tfj['choice'], 'Faux' ); ?>><?php esc_html_e( 'Faux', 'logieval-secure' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Points pour le bon choix', 'logieval-secure' ); ?>
					<input type="number" step="0.25" min="0" name="<?php echo esc_attr( $p ); ?>[tfj_choice_points]" value="<?php echo esc_attr( null === $tfj['choice_points'] ? '' : $tfj['choice_points'] ); ?>" placeholder="<?php esc_attr_e( 'la moitié', 'logieval-secure' ); ?>">
				</label>
			</div>
			<label><?php esc_html_e( 'Argumentation attendue', 'logieval-secure' ); ?><textarea class="widefat" name="<?php echo esc_attr( $p ); ?>[tfj_justification]" rows="3" placeholder="<?php esc_attr_e( 'Les arguments qui justifient la bonne réponse (pour vous et pour la pré-correction IA)', 'logieval-secure' ); ?>"><?php echo esc_textarea( $tfj['justification'] ); ?></textarea></label>
			<p class="description"><?php esc_html_e( 'L’élève choisit Vrai ou Faux puis justifie sa réponse. Le bon choix est noté automatiquement (par défaut la moitié des points) ; l’argumentation reste à corriger, vous-même ou avec la pré-correction IA.', 'logieval-secure' ); ?></p>
		</div>
		<div data-logiexam-field-types="<?php echo esc_attr( self::ORDERING ); ?>" class="les-order-editor" data-les-order-editor>
			<div class="les-form-row">
				<label><?php esc_html_e( 'Que faut-il remettre dans l’ordre ?', 'logieval-secure' ); ?>
					<select name="<?php echo esc_attr( $p ); ?>[order_mode]" data-les-order-mode>
						<option value="items" <?php selected( $settings['mode'], 'items' ); ?>><?php esc_html_e( 'Des éléments (texte et/ou image)', 'logieval-secure' ); ?></option>
						<option value="letters" <?php selected( $settings['mode'], 'letters' ); ?>><?php esc_html_e( 'Les lettres d’un mot', 'logieval-secure' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Points', 'logieval-secure' ); ?>
					<select name="<?php echo esc_attr( $p ); ?>[order_scoring]">
						<option value="partial" <?php selected( $settings['scoring'], 'partial' ); ?>><?php esc_html_e( 'Au prorata des éléments bien placés', 'logieval-secure' ); ?></option>
						<option value="all" <?php selected( $settings['scoring'], 'all' ); ?>><?php esc_html_e( 'Tout juste ou rien', 'logieval-secure' ); ?></option>
					</select>
				</label>
			</div>
			<div data-les-order-for="letters" <?php echo 'letters' === $settings['mode'] ? '' : 'hidden'; ?>>
				<label><?php esc_html_e( 'Mot à reconstituer', 'logieval-secure' ); ?>
					<input type="text" class="widefat" name="<?php echo esc_attr( $p ); ?>[order_word]" value="<?php echo esc_attr( 'letters' === $settings['mode'] ? implode( '', wp_list_pluck( $items, 'text' ) ) : '' ); ?>" placeholder="<?php esc_attr_e( 'ex. PALETTE', 'logieval-secure' ); ?>" autocomplete="off">
				</label>
				<input type="hidden" name="<?php echo esc_attr( $p ); ?>[order_word_ids]" value="<?php echo esc_attr( 'letters' === $settings['mode'] ? implode( ',', wp_list_pluck( $items, 'id' ) ) : '' ); ?>">
				<p class="description"><?php esc_html_e( 'L’élève reçoit les lettres mélangées et doit les remettre dans l’ordre.', 'logieval-secure' ); ?></p>
			</div>
			<div data-les-order-for="items" <?php echo 'letters' === $settings['mode'] ? 'hidden' : ''; ?>>
				<p class="description"><?php esc_html_e( 'Saisissez les éléments dans le BON ordre (le premier en haut). L’élève les reçoit mélangés. Chaque élément peut être un texte, une image, ou une image avec sa légende.', 'logieval-secure' ); ?></p>
				<ol class="les-order-rows" data-les-order-rows>
					<?php
					$rows = 'letters' === $settings['mode'] ? array() : $items;
					while ( count( $rows ) < 3 ) {
						$rows[] = array( 'id' => '', 'text' => '', 'media_id' => 0 );
					}
					foreach ( $rows as $k => $item ) :
						$image = $item['media_id'] ? LogiEval_Secure_Evaluations::question_image_data( (int) $item['media_id'] ) : array();
						$n     = $p . '[order_items][' . $k . ']';
						?>
						<li class="les-order-row" data-les-order-row>
							<span class="les-order-num" aria-hidden="true"></span>
							<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $item['id'] ); ?>" data-les-order-id>
							<input type="hidden" name="<?php echo esc_attr( $n ); ?>[media_id]" value="<?php echo esc_attr( (int) $item['media_id'] ); ?>" data-les-order-media>
							<input type="text" class="les-order-text" name="<?php echo esc_attr( $n ); ?>[text]" value="<?php echo esc_attr( $item['text'] ); ?>" placeholder="<?php esc_attr_e( 'Texte de l’élément (ou légende de l’image)', 'logieval-secure' ); ?>">
							<span class="les-order-image">
								<?php if ( $image ) : ?>
									<img src="<?php echo esc_url( $image['url'] ); ?>" alt="">
									<label class="les-check"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[remove_image]" value="1"> <span><?php esc_html_e( 'Retirer', 'logieval-secure' ); ?></span></label>
								<?php endif; ?>
								<label class="les-order-file"><span><?php echo esc_html( $image ? __( 'Changer l’image', 'logieval-secure' ) : __( 'Image', 'logieval-secure' ) ); ?></span><input type="file" name="<?php echo esc_attr( $n ); ?>[image]" accept="image/*"></label>
								<?php LogiEval_Secure_Evaluations::render_media_pick_button( __( '📁 Médiathèque', 'logieval-secure' ) ); ?>
							</span>
							<span class="les-order-tools">
								<button type="button" class="button" data-les-order-up aria-label="<?php esc_attr_e( 'Monter', 'logieval-secure' ); ?>">↑</button>
								<button type="button" class="button" data-les-order-down aria-label="<?php esc_attr_e( 'Descendre', 'logieval-secure' ); ?>">↓</button>
								<button type="button" class="button les-button-danger" data-les-order-remove aria-label="<?php esc_attr_e( 'Supprimer l’élément', 'logieval-secure' ); ?>">×</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
				<p><button type="button" class="button les-button-secondary" data-les-order-add><?php esc_html_e( '+ Ajouter un élément', 'logieval-secure' ); ?></button></p>
			</div>
		</div>
		<?php
		// 1.36 : Relier
		$pairs    = self::MATCHING === $type ? self::match_pairs( $question['options'] ?? '' ) : array();
		$match_sc = self::match_settings( self::MATCHING === $type ? ( $question['expected_answer'] ?? '' ) : '' )['scoring'];
		while ( count( $pairs ) < 3 ) {
			$pairs[] = array( 'id' => '', 'left' => '', 'rid' => '', 'right' => '' );
		}
		?>
		<div data-logiexam-field-types="<?php echo esc_attr( self::MATCHING ); ?>" class="les-match-editor" data-les-match-editor>
			<p class="description"><?php esc_html_e( 'Écrivez chaque paire juste sur une ligne : l’élément de gauche et celui de droite qui va avec. L’élève reçoit la colonne de droite mélangée et relie les éléments. Une ligne avec seulement la droite remplie ajoute un intrus (un élément qui ne va avec rien).', 'logieval-secure' ); ?></p>
			<div class="les-match-rows" data-les-match-rows data-next="<?php echo esc_attr( count( $pairs ) ); ?>" data-prefix="<?php echo esc_attr( $p ); ?>">
				<div class="les-match-head" aria-hidden="true"><span><?php esc_html_e( 'À gauche', 'logieval-secure' ); ?></span><span></span><span><?php esc_html_e( 'À droite (la bonne réponse)', 'logieval-secure' ); ?></span><span></span></div>
				<?php foreach ( $pairs as $k => $pair ) : $n = $p . '[match_pairs][' . $k . ']'; ?>
					<div class="les-match-row" data-les-match-row>
						<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $pair['id'] ); ?>">
						<input type="hidden" name="<?php echo esc_attr( $n ); ?>[rid]" value="<?php echo esc_attr( $pair['rid'] ); ?>">
						<input type="text" name="<?php echo esc_attr( $n ); ?>[left]" value="<?php echo esc_attr( $pair['left'] ); ?>" placeholder="<?php esc_attr_e( 'ex. FIFO', 'logieval-secure' ); ?>" aria-label="<?php esc_attr_e( 'Élément de gauche', 'logieval-secure' ); ?>">
						<span class="les-match-arrow" aria-hidden="true">↔</span>
						<input type="text" name="<?php echo esc_attr( $n ); ?>[right]" value="<?php echo esc_attr( $pair['right'] ); ?>" placeholder="<?php esc_attr_e( 'ex. Premier entré, premier sorti', 'logieval-secure' ); ?>" aria-label="<?php esc_attr_e( 'Élément de droite', 'logieval-secure' ); ?>">
						<button type="button" class="button les-button-danger" data-les-match-remove aria-label="<?php esc_attr_e( 'Supprimer la ligne', 'logieval-secure' ); ?>">×</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button les-button-secondary" data-les-match-add><?php esc_html_e( '+ Ajouter une paire', 'logieval-secure' ); ?></button></p>
			<label><?php esc_html_e( 'Points', 'logieval-secure' ); ?>
				<select name="<?php echo esc_attr( $p ); ?>[match_scoring]">
					<option value="partial" <?php selected( $match_sc, 'partial' ); ?>><?php esc_html_e( 'Au prorata des liaisons justes', 'logieval-secure' ); ?></option>
					<option value="all" <?php selected( $match_sc, 'all' ); ?>><?php esc_html_e( 'Tout juste ou rien', 'logieval-secure' ); ?></option>
				</select>
			</label>
		</div>
		<?php
		// 1.36 : Texte à trous
		$bl = self::blanks_expected( self::BLANKS === $type ? ( $question['expected_answer'] ?? '' ) : '' );
		?>
		<div data-logiexam-field-types="<?php echo esc_attr( self::BLANKS ); ?>" class="les-blanks-editor" data-les-blanks-editor>
			<label><?php esc_html_e( 'Texte à compléter', 'logieval-secure' ); ?>
				<textarea class="widefat" rows="5" name="<?php echo esc_attr( $p ); ?>[blanks_text]" data-les-blanks-text placeholder="<?php esc_attr_e( 'ex. Le principe [FIFO] signifie : premier entré, premier [sorti|sortie].', 'logieval-secure' ); ?>"><?php echo esc_textarea( $bl['text'] ); ?></textarea>
			</label>
			<p class="description"><?php esc_html_e( 'Mettez chaque mot à trouver entre crochets : [FIFO]. Plusieurs réponses acceptées : séparez-les par une barre [sorti|sortie]. Les majuscules, les accents et les espaces en trop ne comptent pas.', 'logieval-secure' ); ?></p>
			<p class="les-blanks-preview" data-les-blanks-preview aria-live="polite"></p>
			<div class="les-form-row">
				<label class="les-check"><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[blanks_bank]" value="1" <?php checked( $bl['bank'] ); ?>> <?php esc_html_e( 'Montrer la liste des mots à placer (mélangés)', 'logieval-secure' ); ?></label>
				<label class="les-check"><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[blanks_tolerant]" value="1" <?php checked( $bl['tolerant'] ); ?>> <?php esc_html_e( 'Accepter une petite faute de frappe (mots de 5 lettres ou plus)', 'logieval-secure' ); ?></label>
				<label><?php esc_html_e( 'Points', 'logieval-secure' ); ?>
					<select name="<?php echo esc_attr( $p ); ?>[blanks_scoring]">
						<option value="partial" <?php selected( $bl['scoring'], 'partial' ); ?>><?php esc_html_e( 'Au prorata des trous justes', 'logieval-secure' ); ?></option>
						<option value="all" <?php selected( $bl['scoring'], 'all' ); ?>><?php esc_html_e( 'Tout juste ou rien', 'logieval-secure' ); ?></option>
					</select>
				</label>
			</div>
		</div>
		<?php
	}
}
