<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Export {
	public static function init(): void {
		add_action( 'admin_post_logieval_secure_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	public static function export_csv(): void {
		if ( ! LogiEval_Secure_Ultimate_Member::current_user_can_teach() || ! current_user_can( 'lp_exam_export' ) ) {
			wp_die( esc_html__( 'Accès non autorisé.', 'logieval-secure' ) );
		}
		check_admin_referer( 'logieval_secure_export' );

		$evaluation_id = absint( $_GET['evaluation_id'] ?? 0 );
		$evaluation    = LogiEval_Secure_Evaluations::get_evaluation( $evaluation_id );
		if ( ! LogiEval_Secure_Evaluations::can_manage_evaluation( $evaluation ) ) {
			wp_die( esc_html__( 'Évaluation introuvable.', 'logieval-secure' ) );
		}

		$class = isset( $_GET['class'] ) ? sanitize_text_field( wp_unslash( $_GET['class'] ) ) : '';

		// Copies avec leur classe, filtrées si une classe est demandée, triées par classe puis par élève.
		$rows = array();
		foreach ( LogiEval_Secure_Evaluations::get_attempts( $evaluation_id ) as $attempt ) {
			$label = LogiEval_Secure_Evaluations::attempt_class_label( $evaluation, (int) $attempt['user_id'] );
			if ( '' !== $class && $label !== $class ) {
				continue;
			}
			$user   = get_userdata( (int) $attempt['user_id'] );
			$rows[] = array(
				'class'   => $label,
				'user'    => $user,
				'name'    => $user ? $user->display_name : '#' . $attempt['user_id'],
				'attempt' => $attempt,
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['class'], $b['class'] ) ?: strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		$filename = sanitize_title( $evaluation['title'] ) . ( '' !== $class ? '-' . sanitize_title( $class ) : '' ) . '-resultats.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" ); // BOM : accents corrects à l'ouverture dans Excel.
		fputcsv( $output, array( 'Classe', 'Élève', 'Email', 'Points', 'Bonus', 'Malus', 'Total', 'Note', 'Statut' ), ';' );

		foreach ( $rows as $row ) {
			$attempt   = $row['attempt'];
			$user      = $row['user'];
			$result    = LogiEval_Secure_Evaluations::get_result_by_attempt( (int) $attempt['id'] );
			$cancelled = LogiEval_Secure_Evaluations::attempt_is_cheating_cancelled( $attempt );
			fputcsv(
				$output,
				array(
					self::csv_cell( $row['class'] ),
					self::csv_cell( $row['name'] ),
					self::csv_cell( $user ? $user->user_email : '' ),
					self::csv_cell( $result['points_obtained'] ?? '' ),
					self::csv_cell( $result['bonus_points'] ?? '' ),
					self::csv_cell( $result['malus_points'] ?? '' ),
					self::csv_cell( $result['final_total'] ?? '' ),
					self::csv_cell( $cancelled ? LogiEval_Secure_Evaluations::CHEATING_GRADE : ( $result['final_grade'] ?? '' ) ),
					self::csv_cell( $cancelled ? 'Annulée - tricherie' : LogiEval_Secure_Evaluations::export_status_label( $attempt, $result ) ),
				),
				';'
			);
		}

		fclose( $output );
		exit;
	}

	private static function csv_cell( $value ): string {
		$value = (string) $value;
		return preg_match( '/^\s*[=+\-@]/', $value ) ? "'" . $value : $value;
	}
}
