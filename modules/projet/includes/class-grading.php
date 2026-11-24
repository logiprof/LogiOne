<?php
/**
 * Calcul des points et de la note suisse (1 à 6, au demi-point).
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Grading {

	const ORAL_CRITERIA = array(
		'clarte'    => 'Clarté de la présentation',
		'questions' => 'Réponses aux questions',
		'temps'     => 'Temps respecté (10 min ± 1)',
	);
	const ORAL_THRESHOLD = 4; // Sur 6.
	const ORAL_BONUS     = 0.5;

	public static function total( $grille ) {
		return 2 * count( (array) $grille );
	}

	public static function round_half( $x ) {
		return floor( $x * 2 + 0.5 ) / 2;
	}

	public static function note( $points, $total ) {
		if ( $total <= 0 ) {
			return null;
		}
		return max( 1, min( 6, self::round_half( $points / $total * 5 + 1 ) ) );
	}

	/** Tableau de conversion points → note, regroupé par note. */
	public static function conversion( $total ) {
		$rows = array();
		for ( $p = 0; $p <= $total; $p++ ) {
			$n = (string) self::note( $p, $total );
			if ( ! isset( $rows[ $n ] ) ) {
				$rows[ $n ] = array( $p, $p );
			}
			$rows[ $n ][1] = $p;
		}
		$out = array();
		foreach ( $rows as $n => $r ) {
			$out[] = array(
				'note' => (float) $n,
				'min'  => $r[0],
				'max'  => $r[1],
			);
		}
		return $out;
	}

	public static function oral_points( $oral ) {
		$sum = 0;
		foreach ( array_keys( self::ORAL_CRITERIA ) as $k ) {
			$sum += isset( $oral[ $k ] ) ? max( 0, min( 2, (int) $oral[ $k ] ) ) : 0;
		}
		return $sum;
	}

	/**
	 * Recalcule points, note et note finale d'un dépôt à partir des notes de l'enseignant
	 * (ou, à défaut, de la proposition de l'IA).
	 */
	/**
	 * Jours de retard d'un travail remis : tout jour commencé compte (1 minute après l'échéance = 1 jour).
	 * 0 si pas d'échéance, pas encore remis, ou remis à temps.
	 */
	public static function late_days( $travail, $depot ) {
		if ( ! $depot || empty( $travail['due_at'] ) || empty( $depot['submitted_at'] ) || 'groupe' === ( $depot['status'] ?? '' ) ) {
			return 0;
		}
		$late = strtotime( $depot['submitted_at'] . ' UTC' ) - strtotime( $travail['due_at'] . ' UTC' );
		return $late > 0 ? (int) ceil( $late / DAY_IN_SECONDS ) : 0;
	}

	/**
	 * Pénalité de retard : 1 à 7 jours → −0.5 ; 8 à 14 jours → −1 ; plus de 14 jours → note 1.
	 * Rend null (pas de pénalité), un nombre (points retirés) ou 'note1'.
	 */
	public static function late_penalty( $days ) {
		if ( $days <= 0 ) {
			return null;
		}
		if ( $days <= 7 ) {
			return 0.5;
		}
		if ( $days <= 14 ) {
			return 1.0;
		}
		return 'note1';
	}

	public static function compute( $travail, $scores, $oral, $depot = null ) {
		$grille = (array) $travail['grille'];
		$total  = self::total( $grille );
		$points = 0;
		$filled = 0;
		foreach ( $grille as $c ) {
			if ( isset( $scores[ $c['id'] ]['score'] ) && null !== $scores[ $c['id'] ]['score'] && '' !== $scores[ $c['id'] ]['score'] ) {
				$points += max( 0, min( 2, (int) $scores[ $c['id'] ]['score'] ) );
				++$filled;
			}
		}
		if ( 0 === $filled ) {
			return array(
				'points'      => null,
				'note'        => null,
				'note_finale' => null,
				'bonus'       => 0,
				'complete'    => false,
			);
		}
		$note  = self::note( $points, $total );
		$bonus = 0;
		if ( ! empty( $travail['oral_bonus'] ) && ! empty( $oral['presented'] ) && self::oral_points( $oral ) >= self::ORAL_THRESHOLD ) {
			$bonus = self::ORAL_BONUS;
		}
		$finale  = min( 6, $note + $bonus );
		$days    = self::late_days( $travail, $depot );
		$penalty = ( ! isset( $travail['graded'] ) || $travail['graded'] ) && empty( $depot['late_excused'] ) ? self::late_penalty( $days ) : null;
		if ( 'note1' === $penalty ) {
			$finale = 1.0;
		} elseif ( $penalty ) {
			$finale = max( 1.0, $finale - $penalty );
		}
		return array(
			'points'      => $points,
			'note'        => $note,
			'note_finale' => $finale,
			'bonus'       => $bonus,
			'retard'      => $days,
			'penalite'    => $penalty,
			'complete'    => count( $grille ) === $filled,
		);
	}

	/** Modèle de grille : un critère par leçon (bloc A), puis les blocs B à E. */
	public static function default_grille( $lessons, $module = '' ) {
		$crit = array();
		$i    = 0;
		$n    = count( $lessons );
		$bloc = 'A · Contenu des ' . $n . ' leçon' . ( $n > 1 ? 's' : '' ) . ( $module ? ' du ' . $module : '' );
		foreach ( $lessons as $l ) {
			$crit[] = array(
				'id'        => 'c' . ( ++$i ),
				'bloc'      => $bloc,
				'label'     => $l['title'] . ' : contenu juste et bien expliqué',
				'lesson_id' => (int) $l['id'],
			);
		}
		$model = array(
			'B · Explications et exemples' => array( 'Explications claires, avec vos propres mots (pas de copier-coller)', 'Exemples concrets tirés du monde de la logistique' ),
			'C · Structure'                 => array( 'Introduction avec le plan de la présentation', 'Conclusion ou résumé des points essentiels' ),
			'D · Lisibilité'                => array( 'Peu de texte par slide, lisible de loin, 8 à 12 slides', 'Images, schémas ou tableaux utiles pour comprendre' ),
			'E · Forme'                     => array( 'Orthographe soignée et slide « Sources » complète' ),
		);
		foreach ( $model as $b => $labels ) {
			foreach ( $labels as $label ) {
				$crit[] = array(
					'id'    => 'c' . ( ++$i ),
					'bloc'  => $b,
					'label' => $label,
				);
			}
		}
		return $crit;
	}
}
