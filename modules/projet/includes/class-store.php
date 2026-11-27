<?php
/**
 * Lecture et écriture des travaux et des dépôts.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Store {

	const JSON_TRAVAIL = array( 'lessons', 'grille', 'group_ids', 'consigne', 'formats' );
	const JSON_DEPOT   = array( 'extract', 'ai', 'scores', 'oral', 'feedback', 'members' );

	public static function now() {
		return current_time( 'mysql', true );
	}

	private static function decode( $row, $fields ) {
		if ( ! $row ) {
			return null;
		}
		foreach ( $fields as $f ) {
			$row[ $f ] = isset( $row[ $f ] ) && '' !== $row[ $f ] && null !== $row[ $f ] ? json_decode( $row[ $f ], true ) : null;
		}
		foreach ( array( 'id', 'course_id', 'author_id', 'travail_id', 'user1', 'user2', 'file_size', 'submitted_by', 'validated_by' ) as $f ) {
			if ( isset( $row[ $f ] ) ) {
				$row[ $f ] = (int) $row[ $f ];
			}
		}
		if ( isset( $row['group_size'] ) ) {
			$row['group_size'] = max( 1, min( 6, (int) $row['group_size'] ) );
		}
		if ( array_key_exists( 'formats', $row ) ) {
			$row['formats'] = LogiProjet_Formats::clean( $row['formats'] );
		}
		if ( array_key_exists( 'members', $row ) ) {
			$m = is_array( $row['members'] ) ? array_map( 'intval', $row['members'] ) : array();
			if ( ! $m ) {
				$m = array_values( array_filter( array( (int) $row['user1'], (int) $row['user2'] ) ) );
			}
			$row['members'] = array_values( array_unique( $m ) );
		}
		foreach ( array( 'oral_bonus', 'allow_solo', 'graded' ) as $f ) {
			if ( isset( $row[ $f ] ) ) {
				$row[ $f ] = (bool) (int) $row[ $f ];
			}
		}
		foreach ( array( 'points', 'note', 'note_finale' ) as $f ) {
			if ( array_key_exists( $f, $row ) ) {
				$row[ $f ] = null === $row[ $f ] ? null : (float) $row[ $f ];
			}
		}
		return $row;
	}

	private static function encode( $data, $fields ) {
		foreach ( $fields as $f ) {
			if ( array_key_exists( $f, $data ) ) {
				$data[ $f ] = null === $data[ $f ] ? null : wp_json_encode( $data[ $f ] );
			}
		}
		return $data;
	}

	/* ---------- Travaux ---------- */

	public static function travail( $id ) {
		global $wpdb;
		$t = LogiProjet_Install::table( 'travaux' );
		return self::decode( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", absint( $id ) ), ARRAY_A ), self::JSON_TRAVAIL ); // phpcs:ignore WordPress.DB
	}

	public static function travaux( $args = array() ) {
		global $wpdb;
		$t     = LogiProjet_Install::table( 'travaux' );
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['author_id'] ) ) {
			$where[] = 'author_id = %d';
			$vals[]  = absint( $args['author_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$st      = array_map( 'sanitize_key', (array) $args['status'] );
			$where[] = "status IN ('" . implode( "','", $st ) . "')";
		}
		$sql  = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . ' ORDER BY (due_at IS NULL), due_at DESC, id DESC';
		$rows = $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = self::decode( $r, self::JSON_TRAVAIL );
		}
		return $out;
	}

	public static function save_travail( $data, $id = 0 ) {
		global $wpdb;
		$t                  = LogiProjet_Install::table( 'travaux' );
		$data['updated_at'] = self::now();
		$data               = self::encode( $data, self::JSON_TRAVAIL );
		if ( $id ) {
			$wpdb->update( $t, $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB
			return absint( $id );
		}
		$data['created_at'] = $data['updated_at'];
		$wpdb->insert( $t, $data ); // phpcs:ignore WordPress.DB
		return (int) $wpdb->insert_id;
	}

	public static function delete_travail( $id ) {
		global $wpdb;
		foreach ( self::depots( $id ) as $d ) {
			self::delete_depot( $d['id'] );
		}
		$wpdb->delete( LogiProjet_Install::table( 'travaux' ), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB
		LogiProjet_Access::forget( $id );
	}

	/* ---------- Dépôts ---------- */

	public static function depot( $id ) {
		global $wpdb;
		$t = LogiProjet_Install::table( 'depots' );
		return self::decode( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", absint( $id ) ), ARRAY_A ), self::JSON_DEPOT ); // phpcs:ignore WordPress.DB
	}

	public static function depots( $travail_id ) {
		global $wpdb;
		$t    = LogiProjet_Install::table( 'depots' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE travail_id = %d ORDER BY submitted_at ASC, id ASC", absint( $travail_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = self::decode( $r, self::JSON_DEPOT );
		}
		return $out;
	}

	public static function depot_for_user( $travail_id, $user_id ) {
		foreach ( self::depots( $travail_id ) as $d ) {
			if ( in_array( (int) $user_id, $d['members'], true ) ) {
				return $d;
			}
		}
		return null;
	}

	public static function save_depot( $data, $id = 0 ) {
		global $wpdb;
		$t                  = LogiProjet_Install::table( 'depots' );
		$data['updated_at'] = self::now();
		$data               = self::encode( $data, self::JSON_DEPOT );
		if ( $id ) {
			$wpdb->update( $t, $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB
			return absint( $id );
		}
		$wpdb->insert( $t, $data ); // phpcs:ignore WordPress.DB
		return (int) $wpdb->insert_id;
	}

	public static function delete_depot( $id ) {
		global $wpdb;
		$d = self::depot( $id );
		foreach ( array( 'file_path', 'draft_path' ) as $k ) {
			if ( $d && ! empty( $d[ $k ] ) ) {
				$path = self::file_abspath( $d[ $k ] );
				if ( $path && file_exists( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
		$wpdb->delete( LogiProjet_Install::table( 'depots' ), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB
	}

	public static function counts( $travail_id ) {
		$c = array(
			'total'   => 0,
			'groupe'  => 0,
			'depose'  => 0,
			'propose' => 0,
			'valide'  => 0,
			'erreur'  => 0,
		);
		foreach ( self::depots( $travail_id ) as $d ) {
			if ( 'groupe' !== $d['status'] ) {
				++$c['total']; // « total » = fichiers déposés ; les groupes sans fichier sont comptés à part.
			}
			if ( isset( $c[ $d['status'] ] ) ) {
				++$c[ $d['status'] ];
			}
		}
		return $c;
	}

	/** Chemin absolu d'un fichier déposé, jamais en dehors du dossier LogiProjet. */
	public static function file_abspath( $relative ) {
		$relative = basename( (string) $relative );
		$ext      = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
		if ( ! $relative || ! preg_match( '/^[A-Za-z0-9_\-]+\.[a-z0-9]{2,4}$/', $relative ) || ! in_array( $ext, LogiProjet_Formats::all_extensions(), true ) ) {
			return '';
		}
		return trailingslashit( LogiProjet_Install::upload_dir() ) . $relative;
	}
}
