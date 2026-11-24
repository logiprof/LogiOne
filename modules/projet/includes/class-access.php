<?php
/**
 * Qui est enseignant, qui peut voir quoi.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Access {

	public static function is_teacher( $user_id = 0 ) {
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) || user_can( $user, LogiProjet_Install::CAP ) ) {
			return true;
		}
		$roles = (array) LogiProjet_Settings::get( 'teacher_roles' );
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	public static function is_admin( $user_id = 0 ) {
		return $user_id ? user_can( $user_id, 'manage_options' ) : current_user_can( 'manage_options' );
	}

	/** Un enseignant gère ses travaux ; un administrateur les gère tous. */
	public static function can_manage_travail( $travail, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $travail || ! self::is_teacher( $user_id ) ) {
			return false;
		}
		return self::is_admin( $user_id ) || (int) $travail['author_id'] === (int) $user_id;
	}

	public static function is_member( $depot, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return $user_id && in_array( (int) $user_id, array_map( 'intval', (array) $depot['members'] ), true );
	}

	/** Un apprenti voit un travail ouvert (ou fermé) s'il est dans un des groupes visés, ou si aucun groupe n'est visé. */
	public static function student_can_see( $travail, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id || ! in_array( $travail['status'], array( 'ouvert', 'ferme' ), true ) ) {
			return false;
		}
		$groups = array_map( 'absint', (array) $travail['group_ids'] );
		if ( ! $groups ) {
			return true;
		}
		return (bool) array_intersect( $groups, LogiProjet_Sensei::user_groups( $user_id ) );
	}
}
