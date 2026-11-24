<?php
/**
 * LogiOne 1.53.0 : passage d'année des classes LogiEval (une fois par année scolaire).
 *
 * - « CLLOG-C2e · 2ème année · 2025-2026 » devient « CLLOG-C3e · 3ème année · 2026-2027 » : même classe,
 *   mêmes élèves, mêmes enseignants, mêmes évaluations (la note précédente reste reprise d'une année à l'autre).
 * - Une classe de dernière année (C3…) est archivée : elle disparaît des listes et des nouvelles évaluations,
 *   mais rien n'est supprimé (élèves, évaluations, réponses) et elle peut être restaurée.
 * - Aucun changement de tables : l'archivage et l'historique des passages sont enregistrés dans des options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LOGIEVAL_Rollover {

	const ARCHIVE_OPTION = 'logieval_archived_classes';
	const HISTORY_OPTION = 'logieval_class_history';
	const LAST_YEAR      = 3; // C3 = dernière année : archivée au lieu d'être montée

	public static function boot(): void {
		add_action( 'admin_post_logieval_rollover', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_logieval_restore_class', array( __CLASS__, 'handle_restore' ) );
	}

	/** @return int[] */
	public static function archived_ids(): array {
		$all = get_option( self::ARCHIVE_OPTION, array() );
		return is_array( $all ) ? array_map( 'intval', array_keys( $all ) ) : array();
	}

	/** Nom (et année) qu'avait la classe à une date donnée, d'après l'historique des passages d'année. */
	public static function label_at( array $cl, string $when ): string {
		$hist = get_option( self::HISTORY_OPTION, array() );
		$ts   = strtotime( $when );
		foreach ( ( is_array( $hist ) ? ( $hist[ (int) $cl['id'] ] ?? array() ) : array() ) as $h ) {
			if ( $ts && $ts < strtotime( (string) $h['at'] ) && ! empty( $h['avant'] ) ) {
				return trim( $h['avant']['nom'] . ( $h['avant']['annee_scolaire'] !== '' ? ' · ' . $h['avant']['annee_scolaire'] : '' ) );
			}
		}
		return LOGIEVAL_Database::class_label( $cl );
	}

	public static function is_archived( int $classe_id ): bool {
		return in_array( $classe_id, self::archived_ids(), true );
	}

	/** Retire les classes archivées d'une liste de lignes (tableaux avec 'id'). */
	public static function without_archived( array $rows ): array {
		$archived = self::archived_ids();
		if ( ! $archived ) {
			return $rows;
		}
		return array_values( array_filter( $rows, static fn( $r ) => ! in_array( (int) ( is_array( $r ) ? ( $r['id'] ?? 0 ) : ( $r->id ?? 0 ) ), $archived, true ) ) );
	}

	/** Clause SQL « AND cl.id NOT IN (…) » pour exclure les classes archivées. */
	public static function sql_exclude( string $column ): string {
		$archived = self::archived_ids();
		return $archived ? ' AND ' . $column . ' NOT IN (' . implode( ',', $archived ) . ')' : '';
	}

	/* ── 1.53.6 : proposition automatique ─────────────────────────────
	 * La rentrée est fin août. Une classe créée au moins 60 jours avant la rentrée appartient à l'année
	 * qui se termine : elle est proposée au passage d'année. Une classe créée plus tard (ex. import d'août
	 * ou de novembre pour l'année en cours) n'est pas touchée avant l'été suivant. */
	const DONE_OPTION   = 'logieval_rollover_done';
	const RENTREE_MD    = '08-31';
	const DELAY_DAYS    = 60;
	const PROPOSE_FROM  = 7; // la proposition apparaît dès juillet

	/** Année de la rentrée concernée (2026 pour 2026-2027). */
	public static function target_year(): int {
		$y = (int) current_time( 'Y' );
		return (int) current_time( 'n' ) >= self::PROPOSE_FROM ? $y : $y - 1;
	}
	public static function target_label(): string {
		$y = self::target_year();
		return $y . '-' . ( $y + 1 );
	}
	private static function year_start( string $annee ): int {
		return preg_match( '/(\d{4})/', $annee, $m ) ? (int) $m[1] : 0;
	}
	/** La classe doit-elle être proposée au passage d'année cette fois-ci ? */
	public static function is_due( array $cl ): bool {
		$y = self::target_year();
		if ( self::is_archived( (int) $cl['id'] ) ) return false;
		$done = get_option( self::DONE_OPTION, array() );
		if ( is_array( $done ) && (int) ( $done[ (int) $cl['id'] ] ?? 0 ) >= $y ) return false;
		$start = self::year_start( (string) $cl['annee_scolaire'] );
		if ( $start && $start >= $y ) return false; // déjà dans la nouvelle année (ou plus tard)
		$limit = strtotime( $y . '-' . self::RENTREE_MD . ' 00:00:00' ) - self::DELAY_DAYS * DAY_IN_SECONDS;
		$created = strtotime( (string) ( $cl['created_at'] ?? '' ) );
		return $created && $created <= $limit;
	}
	/** Classes à proposer maintenant. */
	public static function due_classes(): array {
		$t = LOGIEVAL_Database::tables();
		return array_values( array_filter( LOGIEVAL_Database::get_rows( $t['classes'], array(), 'nom' ), array( __CLASS__, 'is_due' ) ) );
	}
	/** Bandeau « nouvelle année scolaire » (page Classes et tableau de bord). */
	public static function render_notice( bool $with_link = true ): void {
		if ( ! current_user_can( 'lp_eval_admin' ) ) return;
		$due = self::due_classes();
		if ( ! $due ) return;
		echo '<div class="notice notice-info logieval-notice" style="margin:12px 0"><p><strong>🎓 Nouvelle année scolaire ' . esc_html( self::target_label() ) . '</strong> : '
			. count( $due ) . ' classe(s) de l’année précédente peuvent passer à l’année suivante ('
			. esc_html( implode( ', ', array_map( static fn( $c ) => $c['nom'], array_slice( $due, 0, 8 ) ) ) ) . ( count( $due ) > 8 ? '…' : '' ) . ').'
			. ( $with_link ? ' <a href="' . esc_url( admin_url( 'admin.php?page=logieval-classes#logieval-rollover' ) ) . '">Voir la proposition</a>' : ' Vérifiez la proposition ci-dessous.' )
			. '</p></div>';
	}

	private static function next_year( string $year ): string {
		return (string) preg_replace_callback( '/(\d{4})\s*([-\/])\s*(\d{2,4})/', static function ( $m ) {
			$b = (int) $m[3];
			$b = strlen( $m[3] ) === 2 ? str_pad( (string) ( ( $b + 1 ) % 100 ), 2, '0', STR_PAD_LEFT ) : (string) ( $b + 1 );
			return ( (int) $m[1] + 1 ) . $m[2] . $b;
		}, $year, 1 );
	}

	private static function level_label( int $n ): string {
		return $n . ( 1 === $n ? 'ère' : 'ème' ) . ' année';
	}

	/** Proposition pour une classe : action « up » (monter), « archive » ou « none », avec les nouvelles valeurs. */
	public static function proposal( array $cl ): array {
		$name = (string) $cl['nom'];
		// C = CFC (3 ans), CR = CFC réduit (2 ans), A = AFP (2 ans).
		$re = '/(?<![a-z])(cr|c|a)(\d)/i';
		if ( ! preg_match( $re, $name, $m ) ) {
			return array( 'action' => 'none', 'reason' => 'Nom sans « C1 », « CR1 » ou « A1 » : à traiter à la main.' );
		}
		$prefix = strtoupper( $m[1] );
		$level  = (int) $m[2];
		$last   = 'C' === $prefix ? self::LAST_YEAR : 2;
		if ( $level >= $last ) {
			return array( 'action' => 'archive', 'reason' => 'Dernière année (' . $prefix . $level . ') : archivée.' );
		}
		$new_name   = (string) preg_replace_callback( $re, static fn( $x ) => $x[1] . ( (int) $x[2] + 1 ), $name, 1 );
		$new_name   = self::next_year( $new_name ); // une année écrite dans le nom suit aussi
		$niveau     = (string) $cl['niveau'];
		$new_niveau = preg_match( '/\d/', $niveau )
			? (string) preg_replace_callback( '/(\d)(\s*)(ère|ere|ème|eme|e)?/iu', static fn( $x ) => ( (int) $x[1] + 1 ) . $x[2] . ( '' !== ( $x[3] ?? '' ) ? 'ème' : '' ), $niveau, 1 )
			: self::level_label( $level + 1 );
		$year       = (string) $cl['annee_scolaire'];
		$new_year   = '' !== trim( $year ) ? self::next_year( $year ) : '';

		return array( 'action' => 'up', 'nom' => $new_name, 'niveau' => $new_niveau, 'annee_scolaire' => $new_year, 'reason' => $prefix . $level . ' → ' . $prefix . ( $level + 1 ) );
	}

	public static function render_panel(): void {
		if ( ! current_user_can( 'lp_eval_admin' ) ) {
			return;
		}
		$t        = LOGIEVAL_Database::tables();
		$classes  = LOGIEVAL_Database::get_rows( $t['classes'], array(), 'nom' );
		$archived = self::archived_ids();
		$active   = array_values( array_filter( $classes, static fn( $c ) => ! in_array( (int) $c['id'], $archived, true ) ) );
		$done     = isset( $_GET['logieval_rollover'] ) ? sanitize_text_field( wp_unslash( $_GET['logieval_rollover'] ) ) : '';
		global $wpdb;
		?>
		<?php if ( '' !== $done ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $done ); ?></p></div>
		<?php endif; ?>
		<?php self::render_notice( false ); $due_ids = array_map( static fn( $c ) => (int) $c['id'], self::due_classes() ); ?>
		<details id="logieval-rollover" class="logieval-card logieval-rollover" style="margin:16px 0;max-width:1100px"<?php echo $due_ids ? ' open' : ''; ?>>
			<summary style="cursor:pointer;font-weight:700;font-size:15px">🎓 Passage d’année (une fois par année scolaire)</summary>
			<p class="description">Chaque classe monte d’une année (C1 → C2, C2 → C3, « 2ème année » → « 3ème année », 2025-2026 → 2026-2027) avec ses élèves, ses enseignants et ses évaluations. Les classes de dernière année (C3) sont <strong>archivées</strong> : elles disparaissent des listes et des nouvelles évaluations, mais rien n’est supprimé et vous pouvez les restaurer. Vérifiez chaque ligne avant de valider.</p>
			<?php if ( ! $active ) : ?>
				<p>Aucune classe active.</p>
			<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Appliquer le passage d’année aux classes cochées ?');">
				<input type="hidden" name="action" value="logieval_rollover">
				<?php wp_nonce_field( 'logieval_rollover' ); ?>
				<table class="wp-list-table widefat striped">
					<thead><tr><th style="width:32px"></th><th>Classe actuelle</th><th>Action</th><th>Nouveau nom</th><th>Niveau</th><th>Année scolaire</th><th>Remarque</th></tr></thead>
					<tbody>
					<?php foreach ( $active as $cl ) :
						$p      = self::proposal( $cl );
						$id     = (int) $cl['id'];
						$is_due = in_array( $id, $due_ids, true );
						// Une classe du même nom existe déjà pour la nouvelle année (ex. créée par l'import) : on ne la double pas.
						if ( 'up' === $p['action'] ) {
							$twin = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['classes']} WHERE nom=%s AND annee_scolaire=%s AND id<>%d LIMIT 1", $p['nom'], $p['annee_scolaire'], $id ) );
							if ( $twin && ! self::is_archived( (int) $twin ) ) {
								$p = array( 'action' => 'archive', 'reason' => $p['nom'] . ' ' . $p['annee_scolaire'] . ' existe déjà (import) : cette classe est archivée, ses évaluations restent liées aux élèves.' );
							}
						}
						if ( ! $is_due ) {
							$p['reason'] = 'Pas proposée : ' . ( self::year_start( (string) $cl['annee_scolaire'] ) >= self::target_year() ? 'déjà dans l’année ' . $cl['annee_scolaire'] : 'créée moins de 60 jours avant la rentrée, ou déjà traitée' ) . '. ' . $p['reason'];
						}
						$open   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['evaluations']} WHERE classe_id=%d AND statut='active'", $id ) );
						$nb     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['eleves']} WHERE classe_id=%d", $id ) );
						?>
						<tr>
							<td><input type="checkbox" name="rollover[<?php echo $id; ?>][apply]" value="1" <?php checked( $is_due && 'none' !== $p['action'] ); ?>></td>
							<td><strong><?php echo esc_html( $cl['nom'] ); ?></strong><br><small><?php echo esc_html( trim( $cl['niveau'] . ' · ' . $cl['annee_scolaire'] . ' · ' . $nb . ' élève(s)', ' ·' ) ); ?></small></td>
							<td>
								<select name="rollover[<?php echo $id; ?>][action]">
									<option value="up" <?php selected( $p['action'], 'up' ); ?>>Monter d’une année</option>
									<option value="archive" <?php selected( $p['action'], 'archive' ); ?>>Archiver</option>
									<option value="none" <?php selected( $p['action'], 'none' ); ?>>Ne rien faire</option>
								</select>
							</td>
							<td><input type="text" name="rollover[<?php echo $id; ?>][nom]" value="<?php echo esc_attr( $p['nom'] ?? $cl['nom'] ); ?>" style="width:100%"></td>
							<td><input type="text" name="rollover[<?php echo $id; ?>][niveau]" value="<?php echo esc_attr( $p['niveau'] ?? $cl['niveau'] ); ?>" style="width:100%"></td>
							<td><input type="text" name="rollover[<?php echo $id; ?>][annee_scolaire]" value="<?php echo esc_attr( $p['annee_scolaire'] ?? $cl['annee_scolaire'] ); ?>" style="width:100%"></td>
							<td><small><?php echo esc_html( $p['reason'] ); ?><?php if ( $open ) : ?><br><strong style="color:#b45309"><?php echo esc_html( $open . ' évaluation(s) encore en cours' ); ?></strong><?php endif; ?></small></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Seules les classes cochées sont traitées. Cochée avec « Ne rien faire » : la classe n’est plus proposée cette année.</p>
				<p><button class="button button-primary">Appliquer le passage d’année</button></p>
			</form>
			<?php endif; ?>
			<?php if ( $archived ) : ?>
				<h3 style="margin-top:18px">Classes archivées</h3>
				<ul>
				<?php foreach ( $classes as $cl ) : if ( ! in_array( (int) $cl['id'], $archived, true ) ) { continue; } ?>
					<li><?php echo esc_html( $cl['nom'] . ' · ' . $cl['niveau'] . ' · ' . $cl['annee_scolaire'] ); ?>
						<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=logieval_restore_class&id=' . (int) $cl['id'] ), 'logieval_restore_class_' . (int) $cl['id'] ) ); ?>">Restaurer</a></li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</details>
		<?php
	}

	public static function handle(): void {
		if ( ! current_user_can( 'lp_eval_admin' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'logieval_rollover' );
		$t        = LOGIEVAL_Database::tables();
		$rows     = isset( $_POST['rollover'] ) && is_array( $_POST['rollover'] ) ? wp_unslash( $_POST['rollover'] ) : array();
		$archive  = get_option( self::ARCHIVE_OPTION, array() );
		$archive  = is_array( $archive ) ? $archive : array();
		$history  = get_option( self::HISTORY_OPTION, array() );
		$history  = is_array( $history ) ? $history : array();
		$up       = 0;
		$archived = 0;
		$done_list = get_option( self::DONE_OPTION, array() );
		$done_list = is_array( $done_list ) ? $done_list : array();
		foreach ( $rows as $id => $row ) {
			$id = absint( $id );
			if ( ! $id || empty( $row['apply'] ) ) {
				continue;
			}
			$cl = LOGIEVAL_Database::get_row( $t['classes'], array( 'id' => $id ) );
			if ( ! $cl || isset( $archive[ $id ] ) ) {
				continue;
			}
			$action = sanitize_key( $row['action'] ?? 'none' );
			$done_list[ $id ] = self::target_year();
			$entry  = array( 'at' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'action' => $action, 'avant' => array( 'nom' => $cl['nom'], 'niveau' => $cl['niveau'], 'annee_scolaire' => $cl['annee_scolaire'] ) );
			if ( 'up' === $action ) {
				$data = array(
					'nom'            => sanitize_text_field( (string) ( $row['nom'] ?? $cl['nom'] ) ),
					'niveau'         => sanitize_text_field( (string) ( $row['niveau'] ?? $cl['niveau'] ) ),
					'annee_scolaire' => sanitize_text_field( (string) ( $row['annee_scolaire'] ?? $cl['annee_scolaire'] ) ),
				);
				if ( '' === $data['nom'] ) {
					continue;
				}
				LOGIEVAL_Database::update( $t['classes'], $data, array( 'id' => $id ) );
				$entry['apres']    = $data;
				$history[ $id ][]  = $entry;
				$up++;
			} elseif ( 'archive' === $action ) {
				$archive[ $id ]   = array( 'at' => current_time( 'mysql' ), 'by' => get_current_user_id() );
				$history[ $id ][] = $entry;
				$archived++;
			}
		}
		update_option( self::ARCHIVE_OPTION, $archive, false );
		update_option( self::HISTORY_OPTION, $history, false );
		update_option( self::DONE_OPTION, $done_list, false );
		$msg = sprintf( 'Passage d’année appliqué : %d classe(s) montée(s), %d classe(s) archivée(s).', $up, $archived );
		wp_safe_redirect( add_query_arg( 'logieval_rollover', rawurlencode( $msg ), admin_url( 'admin.php?page=logieval-classes' ) ) );
		exit;
	}

	public static function handle_restore(): void {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! current_user_can( 'lp_eval_admin' ) ) {
			wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'logieval_restore_class_' . $id );
		$archive = get_option( self::ARCHIVE_OPTION, array() );
		if ( is_array( $archive ) && isset( $archive[ $id ] ) ) {
			unset( $archive[ $id ] );
			update_option( self::ARCHIVE_OPTION, $archive, false );
		}
		wp_safe_redirect( add_query_arg( 'logieval_rollover', rawurlencode( 'Classe restaurée.' ), admin_url( 'admin.php?page=logieval-classes' ) ) );
		exit;
	}
}
