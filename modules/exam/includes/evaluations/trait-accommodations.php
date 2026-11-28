<?php
/**
 * LogiEval_Secure_Evaluations — partie « accommodations » (LogiOne 1.34).
 * Aménagements par élève, appliqués automatiquement dans toutes ses évaluations :
 *  - temps supplémentaire (+25 %, + un tiers, +50 %, +100 %) ;
 *  - lecture audio des questions (seuls les élèves qui ont cet aménagement voient « 🔊 Écouter ») ;
 *  - zoom / gros caractères (125 %, 150 %).
 * Rangés dans les métadonnées de l'utilisateur (aucune table modifiée).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LogiEval_Secure_Evaluations_Accommodations {

	private static string $accommodations_meta = 'logiexam_accommodations';

	/** Choix proposés pour le temps en plus : valeur => libellé. 33 = un tiers exact. */
	public static function accommodation_time_choices(): array {
		return array(
			0   => __( 'Aucun', 'logieval-secure' ),
			25  => __( '+25 %', 'logieval-secure' ),
			33  => __( '+⅓ (un tiers)', 'logieval-secure' ),
			50  => __( '+50 % (la moitié)', 'logieval-secure' ),
			100 => __( '+100 % (le double)', 'logieval-secure' ),
		);
	}

	public static function accommodation_zoom_choices(): array {
		return array(
			100 => __( 'Normal', 'logieval-secure' ),
			125 => __( '125 %', 'logieval-secure' ),
			150 => __( '150 %', 'logieval-secure' ),
		);
	}

	/** Aménagements d'un élève (valeurs sûres, même si rien n'est enregistré). */
	public static function accommodations_for( int $user_id ): array {
		$raw  = $user_id ? get_user_meta( $user_id, self::$accommodations_meta, true ) : array();
		$raw  = is_array( $raw ) ? $raw : array();
		$time = (int) ( $raw['extra_pct'] ?? 0 );
		$zoom = (int) ( $raw['zoom'] ?? 100 );
		return array(
			'extra_pct' => array_key_exists( $time, self::accommodation_time_choices() ) ? $time : 0,
			'audio'     => ! empty( $raw['audio'] ),
			'zoom'      => array_key_exists( $zoom, self::accommodation_zoom_choices() ) ? $zoom : 100,
		);
	}

	public static function has_accommodations( array $acc ): bool {
		return $acc['extra_pct'] > 0 || $acc['audio'] || $acc['zoom'] > 100;
	}

	/** Durée de l'évaluation pour cet élève, en secondes (temps supplémentaire compris). */
	public static function attempt_duration_seconds( array $evaluation, int $user_id ): int {
		if ( self::is_formative( $evaluation ) ) {
			return 0; // Test formatif : sans minuteur, à son rythme.
		}
		$base = (int) ( $evaluation['duration_minutes'] ?? 0 ) * MINUTE_IN_SECONDS;
		if ( $base <= 0 ) {
			return 0;
		}
		$pct = self::accommodations_for( $user_id )['extra_pct'];
		if ( 33 === $pct ) {
			return (int) round( $base * 4 / 3 );
		}
		return (int) round( $base * ( 100 + $pct ) / 100 );
	}

	/** Durée affichée « 30 min → 40 min ». */
	public static function accommodation_minutes_label( int $minutes, int $pct ): string {
		if ( $minutes <= 0 || ! $pct ) {
			return '';
		}
		$total = 33 === $pct ? $minutes * 4 / 3 : $minutes * ( 100 + $pct ) / 100;
		return sprintf( __( '%1$d min → %2$s min', 'logieval-secure' ), $minutes, rtrim( rtrim( number_format( $total, 1, '.', '' ), '0' ), '.' ) );
	}

	/** Petit résumé pour le suivi : « ⏱ +⅓ · 🔊 · 🔍 150 % ». */
	public static function accommodation_summary( int $user_id ): string {
		$acc   = self::accommodations_for( $user_id );
		$parts = array();
		if ( $acc['extra_pct'] ) {
			$parts[] = '⏱ ' . ( 33 === $acc['extra_pct'] ? '+⅓' : '+' . $acc['extra_pct'] . ' %' );
		}
		if ( $acc['audio'] ) {
			$parts[] = '🔊';
		}
		if ( $acc['zoom'] > 100 ) {
			$parts[] = '🔍 ' . $acc['zoom'] . ' %';
		}
		return implode( ' · ', $parts );
	}

	/** Élèves des groupes Sensei, par groupe. */
	private static function accommodation_groups(): array {
		$out = array();
		foreach ( LogiEval_Secure_Sensei::get_groups() as $group ) {
			$gid = (int) ( $group->term_id ?? ( $group->ID ?? 0 ) );
			if ( ! $gid ) {
				continue;
			}
			$students = array();
			foreach ( LogiEval_Secure_Sensei::get_group_students( $gid ) as $u ) {
				$uid = (int) ( $u->ID ?? 0 );
				if ( $uid ) {
					$students[ $uid ] = (string) ( $u->display_name ?? '' );
				}
			}
			uasort( $students, static fn( $a, $b ) => strnatcasecmp( remove_accents( $a ), remove_accents( $b ) ) );
			$out[] = array( 'id' => $gid, 'name' => (string) ( $group->name ?? ( $group->post_title ?? '' ) ), 'students' => $students );
		}
		return $out;
	}

	private static function render_accommodations_workspace(): void {
		$groups   = self::accommodation_groups();
		$selected = absint( $_GET['acc_group'] ?? 0 );
		if ( ! $selected && $groups ) {
			$selected = (int) $groups[0]['id'];
		}
		$current = null;
		foreach ( $groups as $g ) {
			if ( (int) $g['id'] === $selected ) {
				$current = $g;
			}
		}
		$with = 0;
		foreach ( $groups as $g ) {
			foreach ( array_keys( $g['students'] ) as $uid ) {
				$with += self::has_accommodations( self::accommodations_for( (int) $uid ) ) ? 1 : 0;
			}
		}
		?>
		<section class="les-panel les-acc" data-les-acc>
			<div class="les-section-title">
				<div>
					<h2><?php esc_html_e( 'Aménagements des élèves', 'logieval-secure' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Appliqués automatiquement dans toutes les évaluations de l’élève : temps supplémentaire, lecture audio des questions (seuls les élèves cochés ont le bouton « 🔊 Écouter »), zoom / gros caractères.', 'logieval-secure' ); ?></p>
				</div>
			</div>
			<?php if ( ! empty( $_GET['acc_saved'] ) ) : ?>
				<div class="notice notice-success les-message"><p><?php esc_html_e( 'Aménagements enregistrés.', 'logieval-secure' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $groups ) : ?>
				<p class="les-empty-state"><?php esc_html_e( 'Aucun groupe Sensei avec des élèves.', 'logieval-secure' ); ?></p>
			<?php else : ?>
				<form method="get" class="les-acc-filter">
					<?php
					foreach ( $_GET as $k => $v ) {
						if ( 'acc_group' === $k || 'acc_saved' === $k || is_array( $v ) ) {
							continue;
						}
						echo '<input type="hidden" name="' . esc_attr( sanitize_key( $k ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $v ) ) ) . '">';
					}
					?>
					<label><?php esc_html_e( 'Groupe', 'logieval-secure' ); ?>
						<select name="acc_group" onchange="this.form.submit()">
							<?php foreach ( $groups as $g ) : ?>
								<option value="<?php echo esc_attr( $g['id'] ); ?>" <?php selected( (int) $g['id'], $selected ); ?>><?php echo esc_html( $g['name'] . ' (' . count( $g['students'] ) . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<noscript><button type="submit" class="button"><?php esc_html_e( 'Afficher', 'logieval-secure' ); ?></button></noscript>
					<span class="description"><?php echo esc_html( sprintf( _n( '%d élève avec aménagements (tous groupes)', '%d élèves avec aménagements (tous groupes)', $with, 'logieval-secure' ), $with ) ); ?></span>
				</form>
				<?php if ( $current && $current['students'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="les-acc-form">
						<input type="hidden" name="action" value="logieval_secure_save_accommodations">
						<input type="hidden" name="acc_group" value="<?php echo esc_attr( $current['id'] ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<?php self::return_field(); ?>
						<?php self::frontend_redirect_field( array( 'logiexam_view' => 'accommodations', 'acc_group' => $current['id'], 'acc_saved' => 1 ) ); ?>
						<div class="les-table-wrap">
							<table class="les-table les-acc-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Élève', 'logieval-secure' ); ?></th>
										<th><?php esc_html_e( 'Temps supplémentaire', 'logieval-secure' ); ?></th>
										<th><?php esc_html_e( 'Lecture audio', 'logieval-secure' ); ?></th>
										<th><?php esc_html_e( 'Zoom / gros caractères', 'logieval-secure' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $current['students'] as $uid => $name ) : $acc = self::accommodations_for( (int) $uid ); ?>
										<tr class="<?php echo self::has_accommodations( $acc ) ? 'has-acc' : ''; ?>">
											<td><strong><?php echo esc_html( $name ); ?></strong><input type="hidden" name="acc[<?php echo esc_attr( $uid ); ?>][present]" value="1"></td>
											<td>
												<select name="acc[<?php echo esc_attr( $uid ); ?>][extra_pct]" aria-label="<?php echo esc_attr( sprintf( __( 'Temps supplémentaire pour %s', 'logieval-secure' ), $name ) ); ?>">
													<?php foreach ( self::accommodation_time_choices() as $v => $label ) : ?>
														<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $acc['extra_pct'] ); ?>><?php echo esc_html( $label . ( $v ? ' — ' . self::accommodation_minutes_label( 30, $v ) : '' ) ); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
											<td><label class="les-check"><input type="checkbox" name="acc[<?php echo esc_attr( $uid ); ?>][audio]" value="1" <?php checked( $acc['audio'] ); ?>> <?php esc_html_e( '🔊 Écouter les questions', 'logieval-secure' ); ?></label></td>
											<td>
												<select name="acc[<?php echo esc_attr( $uid ); ?>][zoom]" aria-label="<?php echo esc_attr( sprintf( __( 'Zoom pour %s', 'logieval-secure' ), $name ) ); ?>">
													<?php foreach ( self::accommodation_zoom_choices() as $v => $label ) : ?>
														<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $acc['zoom'] ); ?>><?php echo esc_html( $label ); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<p class="description"><?php esc_html_e( 'Le temps supplémentaire est calculé au début de chaque évaluation (ex. + un tiers : 30 min → 40 min ; +50 % : 30 min → 45 min). Une évaluation déjà commencée garde son temps.', 'logieval-secure' ); ?></p>
						<div class="les-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer les aménagements', 'logieval-secure' ); ?></button></div>
					</form>
				<?php elseif ( $current ) : ?>
					<p class="les-empty-state"><?php esc_html_e( 'Ce groupe n’a pas encore d’élèves.', 'logieval-secure' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	public static function handle_save_accommodations(): void {
		self::require_teacher_post();
		$rows    = isset( $_POST['acc'] ) && is_array( $_POST['acc'] ) ? wp_unslash( $_POST['acc'] ) : array();
		$allowed = array();
		foreach ( self::accommodation_groups() as $g ) {
			$allowed += $g['students'];
		}
		foreach ( $rows as $uid => $row ) {
			$uid = absint( $uid );
			if ( ! $uid || ! isset( $allowed[ $uid ] ) || ! is_array( $row ) || empty( $row['present'] ) ) {
				continue; // seulement des élèves des groupes Sensei
			}
			$time = (int) ( $row['extra_pct'] ?? 0 );
			$zoom = (int) ( $row['zoom'] ?? 100 );
			$data = array(
				'extra_pct' => array_key_exists( $time, self::accommodation_time_choices() ) ? $time : 0,
				'audio'     => ! empty( $row['audio'] ) ? 1 : 0,
				'zoom'      => array_key_exists( $zoom, self::accommodation_zoom_choices() ) ? $zoom : 100,
			);
			if ( self::has_accommodations( array( 'extra_pct' => $data['extra_pct'], 'audio' => (bool) $data['audio'], 'zoom' => $data['zoom'] ) ) ) {
				update_user_meta( $uid, self::$accommodations_meta, $data );
			} else {
				delete_user_meta( $uid, self::$accommodations_meta );
			}
		}
		$group = absint( $_POST['acc_group'] ?? 0 );
		wp_safe_redirect( self::post_redirect_url( admin_url( 'admin.php?page=logiexam-accommodations&acc_group=' . $group . '&acc_saved=1' ), array( 'logiexam_view' => 'accommodations', 'acc_group' => $group, 'acc_saved' => 1 ) ) );
		exit;
	}
}
