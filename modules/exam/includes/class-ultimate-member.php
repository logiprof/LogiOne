<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_Ultimate_Member {
	const ACCESS_CAP = 'lp_exam_teach';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'sync_capabilities' ) );
		// Les enseignants LogiExam peuvent ouvrir la médiathèque pour les images de leurs questions (choisir ou téléverser).
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_media_upload' ), 100, 4 );
	}

	public static function grant_media_upload( array $allcaps, array $caps, array $args, $user ): array {
		if ( empty( $allcaps['upload_files'] ) && in_array( 'upload_files', $caps, true ) && $user instanceof WP_User ) {
			$teach = ! empty( $allcaps[ self::ACCESS_CAP ] );
			if ( ! $teach && class_exists( 'LogiPilot_Permissions' ) ) {
				$teach = (bool) LogiPilot_Permissions::user_has( $user, self::ACCESS_CAP );
			}
			if ( $teach ) {
				$allcaps['upload_files'] = true;
			}
		}
		return $allcaps;
	}

	/** LogiOne : plus aucun droit déduit du nom des rôles (EXA-01). Les droits viennent de la matrice centrale. */
	public static function sync_capabilities(): void {
		return;
		$roles = wp_roles();
		if ( ! $roles ) {
			return;
		}

		foreach ( array_keys( (array) $roles->roles ) as $role_key ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}

			if ( 'administrator' === $role_key || self::role_is_logistics_teacher( $role_key ) ) {
				$role->add_cap( self::ACCESS_CAP );
			} else {
				$role->remove_cap( self::ACCESS_CAP );
			}
		}
	}

	public static function current_user_can_teach(): bool {
		return is_user_logged_in() && current_user_can( 'lp_exam_teach' );
	}

	public static function current_user_has_logistics_teacher_role(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		foreach ( (array) $user->roles as $role_key ) {
			if ( self::role_is_logistics_teacher( $role_key ) ) {
				return true;
			}
		}

		return false;
	}

	private static function role_is_logistics_teacher( string $role_key ): bool {
		$role_label = $role_key;
		$roles      = wp_roles();
		if ( $roles && isset( $roles->roles[ $role_key ]['name'] ) ) {
			$role_label .= ' ' . $roles->roles[ $role_key ]['name'];
		}

		$normalized = self::normalize_role_name( $role_label );
		return false !== strpos( $normalized, 'enseignant_logistique' )
			|| false !== strpos( $normalized, 'logistique_enseignant' )
			|| false !== strpos( $normalized, 'logistics_teacher' )
			|| false !== strpos( $normalized, 'teacher_logistics' );
	}

	private static function normalize_role_name( string $value ): string {
		$normalized = remove_accents( strtolower( $value ) );
		$normalized = preg_replace( '/[^a-z0-9]+/', '_', $normalized );
		return trim( (string) $normalized, '_' );
	}
}
