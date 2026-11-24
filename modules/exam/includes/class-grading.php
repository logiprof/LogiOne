<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Grading {
	public static function final_total( $points_obtained, $bonus, $malus ): float {
		return max( 0, round( (float) $points_obtained + (float) $bonus - (float) $malus, 2 ) );
	}

	public static function grade( $points_obtained, $total_points ): ?float {
		$total_points = (float) $total_points;
		if ( $total_points <= 0 ) {
			return null;
		}

		$grade = ( (float) $points_obtained / $total_points * 5 ) + 1;
		return round( min( 6, max( 1, $grade ) ) * 2 ) / 2;
	}

	public static function auto_points( array $question, $answer ): ?float {
		$type     = isset( $question['type'] ) ? (string) $question['type'] : '';
		$expected = isset( $question['expected_answer'] ) ? trim( wp_strip_all_tags( (string) $question['expected_answer'] ) ) : '';
		$points   = isset( $question['points'] ) ? (float) $question['points'] : 0.0;

		$special = LogiEval_Secure_Question_Types::auto_points( $question, $answer, $points );
		if ( null !== $special || in_array( $type, LogiEval_Secure_Question_Types::AUTO_TYPES, true ) ) {
			return $special;
		}

		// 1.52.2 : les QCM sont corrigés automatiquement (avant, « qcm » et « multiple_choice » ne l'étaient jamais :
		// ils n'étaient ni notés par LogiExam ni, pour multiple_choice, envoyés à la correction IA).
		if ( '' !== $expected && 'qcm' === $type ) {
			$given = $answer;
			if ( is_string( $given ) && '[' === substr( trim( $given ), 0, 1 ) ) {
				$decoded = json_decode( $given, true );
				$given   = is_array( $decoded ) ? (string) reset( $decoded ) : $given;
			}
			if ( '' === trim( (string) ( is_array( $given ) ? reset( $given ) : $given ) ) ) {
				return 0.0;
			}
			return self::normalize_answer( is_array( $given ) ? reset( $given ) : $given ) === self::normalize_answer( $expected ) ? $points : 0.0;
		}
		if ( '' !== $expected && 'multiple_choice' === $type ) {
			$answer_items   = self::answer_items( $answer );
			$expected_items = self::answer_items( $expected );
			if ( empty( $expected_items ) ) {
				return null;
			}
			$correct = count( array_intersect( $answer_items, $expected_items ) );
			$wrong   = count( array_diff( $answer_items, $expected_items ) );
			$score   = max( 0, $correct - $wrong ) / count( $expected_items );
			return round( $points * min( 1, $score ), 2 );
		}

		if ( '' === $expected || ! in_array( $type, array( 'true_false', 'calculation' ), true ) ) {
			return null;
		}

		if ( 'calculation' === $type ) {
			// Résultat final comparé selon la précision choisie par l'enseignant (exact, arrondi…).
			$given = $answer;
			if ( is_string( $given ) ) {
				$decoded = json_decode( $given, true );
				$given   = is_array( $decoded ) ? $decoded : $given;
			}
			$ok = LogiEval_Secure_Question_Types::calculation_result_ok( (string) ( $question['expected_answer'] ?? '' ), $given );
			return null === $ok ? null : ( $ok ? $points : 0.0 );
		}

		$normalized_answer   = self::normalize_answer( $answer );
		$normalized_expected = self::normalize_answer( $expected );

		if ( 'multiple_choice' === $type ) {
			$answer_items   = self::answer_items( $answer );
			$expected_items = self::answer_items( $expected );
			if ( empty( $expected_items ) ) {
				return null;
			}
			$correct = count( array_intersect( $answer_items, $expected_items ) );
			$wrong   = count( array_diff( $answer_items, $expected_items ) );
			$score   = max( 0, $correct - $wrong ) / count( $expected_items );
			return round( $points * min( 1, $score ), 2 );
		}

		return $normalized_answer === $normalized_expected ? $points : 0.0;
	}

	private static function normalize_answer( $value ): string {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : null;
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		}
		if ( is_array( $value ) ) {
			sort( $value );
			$value = implode( '|', $value );
		}

		$value = remove_accents( strtolower( trim( wp_strip_all_tags( (string) $value ) ) ) );
		return preg_replace( '/\s+/', ' ', $value );
	}

	private static function answer_items( $value ): array {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : null;
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		}
		if ( ! is_array( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n|;|\||,/', (string) $value );
		}

		$items = array();
		foreach ( (array) $value as $item ) {
			$item = self::normalize_answer( $item );
			if ( '' !== $item ) {
				$items[] = $item;
			}
		}
		sort( $items );
		return array_values( array_unique( $items ) );
	}
}
