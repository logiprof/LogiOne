<?php
/**
 * Grilles imprimables (une page A4 par duo, charte LogiOne) et export CSV des notes.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Print {

	private static function fmt( $n ) {
		return null === $n ? '' : rtrim( rtrim( number_format( (float) $n, 1, '.', '' ), '0' ), '.' );
	}

	public static function render() {
		check_admin_referer( 'logiprojet_imprimer' );
		$t = LogiProjet_Store::travail( absint( $_GET['travail'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $t || ! LogiProjet_Access::can_manage_travail( $t ) ) {
			wp_die( 'Accès refusé.', 403 );
		}
		$depot_id = absint( $_GET['depot'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$depots   = LogiProjet_Store::depots( $t['id'] );
		if ( $depot_id ) {
			$depots = array_values(
				array_filter(
					$depots,
					function ( $d ) use ( $depot_id ) {
						return (int) $d['id'] === $depot_id;
					}
				)
			);
		}
		if ( 'csv' === ( $_GET['format'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::csv( $t, $depots );
		}
		$blank = ! $depots || ! empty( $_GET['vierge'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $blank ) {
			$depots = array( null );
		}
		$total = LogiProjet_Grading::total( $t['grille'] );
		$conv  = LogiProjet_Grading::conversion( $total );
		nocache_headers();
		header( 'X-Robots-Tag: noindex' );
		?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title><?php echo esc_html( 'Grilles – ' . $t['title'] ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( LOGIPROJET_URL . 'assets/logiprojet-print.css?v=' . LOGIPROJET_VERSION ); ?>">
</head><body>
<div class="noprint"><button onclick="window.print()">Imprimer / enregistrer en PDF</button></div>
		<?php
		foreach ( $depots as $d ) :
			$scores = $d ? (array) $d['scores'] : array();
			$oral   = $d ? (array) $d['oral'] : array();
			$fb     = $d ? (array) $d['feedback'] : array();
			$names  = array();
			if ( $d ) {
				foreach ( (array) $d['members'] as $u ) {
					$names[] = LogiProjet_Sensei::user_label( $u );
				}
			}
			$slots = max( (int) $t['group_size'], count( $names ) );
			$bloc = null;
			?>
<section class="page">
	<header class="band">
		<?php $w = LogiProjet_Consigne::words( $t['group_size'] ); ?>
		<div class="title"><div class="kicker"><?php echo esc_html( trim( ( $t['module'] ? 'Module ' . $t['module'] . ' · ' : '' ) . ( (int) $t['group_size'] > 1 ? 'Travail en ' . $w['groupe'] : 'Travail individuel' ) . ( $t['graded'] ? ' · Travail noté' : ' · Non noté' ) ) ); ?></div>
		<h1><?php echo esc_html( $t['consigne']['titres']['grille'] ?? ( $t['graded'] ? 'Grille d’évaluation' : 'Critères de réussite' ) ); ?></h1><div class="sub"><?php echo esc_html( $t['title'] ); ?></div></div>
		<div class="stamp"><b><?php echo $t['graded'] ? '/ ' . (int) $total : 'FORMATIF'; ?></b><span><?php echo $t['graded'] ? 'points' : 'non noté'; ?></span></div>
	</header>
	<div class="inner">
		<div class="ids" style="grid-template-columns:repeat(<?php echo (int) $slots; ?>,1fr) .6fr">
			<?php for ( $i = 0; $i < $slots; $i++ ) : ?>
				<div><label>Apprenti·e <?php echo (int) ( $i + 1 ); ?></label><?php echo esc_html( $names[ $i ] ?? '' ); ?></div>
			<?php endfor; ?>
			<div><label>Remis le</label><?php echo esc_html( $d && $d['submitted_at'] && 'groupe' !== $d['status'] ? get_date_from_gmt( $d['submitted_at'], 'd.m.Y' ) : '' ); ?></div>
		</div>
		<div class="levels"><span>Chaque critère :</span><span><b>0</b> = absent ou faux</span><span><b>1</b> = partiel</span><span><b>2</b> = atteint</span></div>
		<table class="grid">
			<tr><th>Critère</th><th class="c">0</th><th class="c">1</th><th class="c">2</th></tr>
			<?php
			foreach ( (array) $t['grille'] as $c ) :
				if ( $c['bloc'] !== $bloc ) :
					$bloc = $c['bloc'];
					$nb   = count(
						array_filter(
							$t['grille'],
							function ( $x ) use ( $bloc ) {
								return $x['bloc'] === $bloc;
							}
						)
					);
					?>
					<tr class="blk"><td colspan="4"><?php echo esc_html( $bloc ); ?><?php if ( $t['graded'] ) : ?><span class="pts"><?php echo (int) ( 2 * $nb ); ?> pts</span><?php endif; ?></td></tr>
				<?php endif; ?>
				<?php $sc = $scores[ $c['id'] ]['score'] ?? null; ?>
				<tr>
					<td><?php echo esc_html( $c['label'] ); ?>
						<?php if ( ! empty( $scores[ $c['id'] ]['comment'] ) ) : ?><div class="cm"><?php echo esc_html( $scores[ $c['id'] ]['comment'] ); ?></div><?php endif; ?>
					</td>
					<?php for ( $v = 0; $v <= 2; $v++ ) : ?>
						<td class="c"><span class="box<?php echo ( null !== $sc && (int) $sc === $v ) ? ' on' : ''; ?>"></span></td>
					<?php endfor; ?>
				</tr>
			<?php endforeach; ?>
			<?php if ( $t['graded'] ) : ?>
			<tr class="total"><td colspan="2">Total du support</td><td colspan="2" class="c"><?php echo esc_html( $d ? self::fmt( $d['points'] ) : '' ); ?> / <?php echo (int) $total; ?></td></tr>
			<?php endif; ?>
		</table>
		<?php if ( $t['graded'] ) : ?>
		<div class="bottom">
			<div>
				<div class="h">Conversion points → note</div>
				<table class="conv"><tr><th>Points</th>
				<?php foreach ( $conv as $r ) : ?>
					<td class="<?php echo $r['note'] >= 4 ? 'g4' : ''; ?>"><?php echo esc_html( $r['min'] === $r['max'] ? $r['min'] : $r['min'] . '-' . $r['max'] ); ?></td>
				<?php endforeach; ?>
				</tr><tr><th>Note</th>
				<?php foreach ( $conv as $r ) : ?>
					<td class="<?php echo $r['note'] >= 4 ? 'g4' : ''; ?>"><?php echo esc_html( self::fmt( $r['note'] ) ); ?></td>
				<?php endforeach; ?>
				</tr></table>
				<div class="small">Note = points ÷ <?php echo (int) $total; ?> × 5 + 1, arrondie au demi-point.</div>
			</div>
			<?php if ( $t['oral_bonus'] ) : ?>
			<div class="tile y"><div class="h">Bonus oral · seulement si tiré au sort</div>
				<?php foreach ( LogiProjet_Grading::ORAL_CRITERIA as $k => $label ) : ?>
					<div class="orow"><span><?php echo esc_html( $label ); ?></span><span>
					<?php for ( $v = 0; $v <= 2; $v++ ) : ?>
						<span class="box sm<?php echo ( ! empty( $oral['presented'] ) && isset( $oral[ $k ] ) && null !== $oral[ $k ] && (int) $oral[ $k ] === $v ) ? ' on' : ''; ?>"></span> <?php echo (int) $v; ?>&nbsp;
					<?php endfor; ?></span></div>
				<?php endforeach; ?>
				<div class="small">4 / 6 ou plus : <b>+0.5</b> sur la note (maximum 6).</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		$parts = $d ? LogiProjet_Grading::compute( $t, (array) $d['scores'], (array) $d['oral'], $d ) : null;
		$bonus = $parts && null !== $parts['note'] ? $parts['bonus'] : null;
		$late  = $d ? LogiProjet_Grading::late_days( $t, $d ) : 0;
		?>
		<div class="result">
			<div><label>Points</label><div class="val"><?php echo esc_html( $d ? self::fmt( $d['points'] ) : '' ); ?> / <?php echo (int) $total; ?></div></div>
			<div><label>Note du support</label><div class="val"><?php echo esc_html( $d ? self::fmt( $d['note'] ) : '' ); ?></div></div>
			<div><label>Bonus oral</label><div class="val">+ <?php echo esc_html( $bonus ? self::fmt( $bonus ) : '' ); ?></div></div>
			<?php if ( $late ) : ?>
			<div class="late"><label>Retard : <?php echo (int) $late; ?> j<?php echo ! empty( $d['late_excused'] ) ? ' (excusé)' : ''; ?></label><div class="val"><?php echo esc_html( ! empty( $d['late_excused'] ) ? '0' : ( 'note1' === $parts['penalite'] ? 'note 1' : '− ' . self::fmt( $parts['penalite'] ) ) ); ?></div></div>
			<?php endif; ?>
			<div class="final"><label>Note finale</label><div class="val"><?php echo esc_html( $d ? self::fmt( $d['note_finale'] ) : '' ); ?></div></div>
		</div>
		<?php endif; ?>
		<div class="comment"><label><?php echo $t['graded'] ? 'Points forts et piste d’amélioration' : 'Retour de l’enseignant'; ?></label>
			<?php if ( ! empty( $fb['points_forts'] ) ) : ?><p><b>Points forts :</b> <?php echo nl2br( esc_html( $fb['points_forts'] ) ); ?></p><?php endif; ?>
			<?php if ( ! empty( $fb['piste'] ) ) : ?><p><b>Piste d’amélioration :</b> <?php echo nl2br( esc_html( $fb['piste'] ) ); ?></p><?php endif; ?>
		</div>
	</div>
	<div class="foot"><span>LogiProf · <?php echo esc_html( LogiProjet_Sensei::user_label( $t['author_id'] ) ); ?> · <?php echo esc_html( $t['module'] ? 'Module ' . $t['module'] . ' · ' : '' ); ?>Grille d’évaluation</span><span><?php echo esc_html( $d && 'valide' === $d['status'] ? 'Validée le ' . get_date_from_gmt( $d['validated_at'], 'd.m.Y' ) : ( $d ? 'Non validée' : '' ) ); ?></span></div>
</section>
		<?php endforeach; ?>
</body></html>
		<?php
		exit;
	}

	private static function csv( $t, $depots ) {
		$name = sanitize_file_name( 'notes-' . ( $t['module'] ? $t['module'] . '-' : '' ) . $t['title'] . '.csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$total = LogiProjet_Grading::total( $t['grille'] );
		$head  = array( 'Classe', 'Membres du groupe', 'Statut', 'Points', 'Total', 'Note du support', 'Bonus oral', 'Jours de retard', 'Pénalité retard', 'Note finale', 'Remis le', 'Validé le' );
		foreach ( (array) $t['grille'] as $c ) {
			$head[] = $c['label'];
		}
		fputcsv( $out, $head, ';' );
		$labels = array(
			'depose'  => 'Remis',
			'propose' => 'À valider',
			'valide'  => 'Validé',
			'erreur'  => 'Erreur IA',
			'groupe'  => 'En cours, pas remis',
		);
		$classes = array();
		foreach ( LogiProjet_Sensei::groups() as $g ) {
			if ( ! $t['group_ids'] || in_array( (int) $g['id'], array_map( 'intval', (array) $t['group_ids'] ), true ) ) {
				foreach ( LogiProjet_Sensei::group_students( array( $g['id'] ) ) as $sid ) {
					$classes[ (int) $sid ] = $classes[ (int) $sid ] ?? $g['title'];
				}
			}
		}
		foreach ( $depots as $d ) {
			$parts = LogiProjet_Grading::compute( $t, (array) $d['scores'], (array) $d['oral'], $d );
			$late  = LogiProjet_Grading::late_days( $t, $d );
			$pen   = ! empty( $d['late_excused'] ) ? 'excusé' : ( 'note1' === $parts['penalite'] ? 'note 1' : ( $parts['penalite'] ? '-' . self::fmt( $parts['penalite'] ) : '' ) );
			$cl    = '';
			foreach ( (array) $d['members'] as $m ) {
				if ( isset( $classes[ (int) $m ] ) ) {
					$cl = $classes[ (int) $m ];
					break;
				}
			}
			$row = array(
				self::csv_cell( $cl ),
				self::csv_cell( implode( ' & ', array_map( array( 'LogiProjet_Sensei', 'user_label' ), (array) $d['members'] ) ) ),
				$labels[ $d['status'] ] ?? $d['status'],
				self::fmt( $d['points'] ),
				$total,
				self::fmt( $d['note'] ),
				null !== $parts['note'] && $parts['bonus'] ? self::fmt( $parts['bonus'] ) : '',
				$late ? $late : '',
				$late ? $pen : '',
				self::fmt( $d['note_finale'] ),
				$d['submitted_at'] && 'groupe' !== $d['status'] ? get_date_from_gmt( $d['submitted_at'], 'd.m.Y H:i' ) : '',
				$d['validated_at'] ? get_date_from_gmt( $d['validated_at'], 'd.m.Y' ) : '',
			);
			foreach ( (array) $t['grille'] as $c ) {
				$row[] = $d['scores'][ $c['id'] ]['score'] ?? '';
			}
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Empêche l'injection de formules dans Excel. */
	private static function csv_cell( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $v : $v;
	}
}
