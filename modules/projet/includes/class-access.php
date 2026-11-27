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

	/**
	 * LogiOne 1.53.18 : statut par classe. Option « logiprojet_class_status » = [ travail_id => [ group_id => statut ] ]
	 * (aucune modification de table). Une classe sans réglage suit le statut du travail.
	 */
	const CLASS_STATUS_OPTION = 'logiprojet_class_status';
	const STATUSES            = array( 'brouillon', 'ouvert', 'ferme' );

	public static function class_status( $travail ) {
		$all = get_option( self::CLASS_STATUS_OPTION, array() );
		$out = array();
		if ( is_array( $all ) && isset( $all[ (int) $travail['id'] ] ) && is_array( $all[ (int) $travail['id'] ] ) ) {
			$groups = array_map( 'absint', (array) $travail['group_ids'] );
			foreach ( $all[ (int) $travail['id'] ] as $g => $st ) {
				if ( in_array( (int) $g, $groups, true ) && in_array( $st, self::STATUSES, true ) ) {
					$out[ (int) $g ] = $st;
				}
			}
		}
		return $out;
	}

	/** Enregistre les statuts par classe ($map = [ group_id => statut | '' ]) ; '' = comme le travail. */
	public static function save_class_status( $travail_id, $map ) {
		$all = get_option( self::CLASS_STATUS_OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$cur = array();
		foreach ( (array) $map as $g => $st ) {
			$st = sanitize_key( (string) $st );
			if ( absint( $g ) && in_array( $st, self::STATUSES, true ) ) {
				$cur[ absint( $g ) ] = $st;
			}
		}
		if ( $cur ) {
			$all[ (int) $travail_id ] = $cur;
		} else {
			unset( $all[ (int) $travail_id ] );
		}
		update_option( self::CLASS_STATUS_OPTION, $all, false );
	}

	/** Statut d'une classe visée par le travail. */
	public static function status_for_group( $travail, $group_id ) {
		$cs = self::class_status( $travail );
		return $cs[ (int) $group_id ] ?? $travail['status'];
	}

	/** Statut du travail tel que le voit un apprenti (le plus ouvert parmi ses classes visées). */
	public static function status_for( $travail, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$groups  = array_map( 'absint', (array) $travail['group_ids'] );
		$cs      = self::class_status( $travail );
		if ( ! $groups || ! $cs ) {
			return $travail['status'];
		}
		$rank = array( 'brouillon' => 0, 'ferme' => 1, 'ouvert' => 2 );
		$best = null;
		foreach ( array_intersect( $groups, LogiProjet_Sensei::user_groups( $user_id ) ) as $g ) {
			$st = $cs[ $g ] ?? $travail['status'];
			if ( null === $best || $rank[ $st ] > $rank[ $best ] ) {
				$best = $st;
			}
		}
		return $best ?? $travail['status'];
	}

	/**
	 * LogiOne 1.54.0 : date de début par classe. Option « logiprojet_class_start » = [ travail_id => [ group_id => 'Y-m-d H:i:s' (UTC) ] ]
	 * (aucune modification de table). Une classe sans réglage suit la date de début du travail.
	 * Avant sa date de début, un travail ouvert apparaît chez l'apprenti, verrouillé : il ne peut pas l'ouvrir.
	 */
	const CLASS_START_OPTION = 'logiprojet_class_start';

	public static function class_start( $travail ) {
		$all = get_option( self::CLASS_START_OPTION, array() );
		$out = array();
		if ( is_array( $all ) && isset( $all[ (int) $travail['id'] ] ) && is_array( $all[ (int) $travail['id'] ] ) ) {
			$groups = array_map( 'absint', (array) $travail['group_ids'] );
			foreach ( $all[ (int) $travail['id'] ] as $g => $gmt ) {
				if ( in_array( (int) $g, $groups, true ) && is_string( $gmt ) && preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $gmt ) ) {
					$out[ (int) $g ] = $gmt;
				}
			}
		}
		return $out;
	}

	/** Enregistre les dates par classe ($map = [ group_id => 'Y-m-d\TH:i' (heure locale) | '' ]) ; '' = comme le travail. */
	public static function save_class_start( $travail_id, $map ) {
		$all = get_option( self::CLASS_START_OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$cur = array();
		foreach ( (array) $map as $g => $local ) {
			$local = trim( (string) $local );
			if ( ! absint( $g ) || '' === $local ) {
				continue;
			}
			$dt = DateTime::createFromFormat( 'Y-m-d\TH:i', substr( $local . ( 10 === strlen( $local ) ? 'T08:00' : '' ), 0, 16 ), wp_timezone() );
			if ( $dt ) {
				$cur[ absint( $g ) ] = $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		if ( $cur ) {
			$all[ (int) $travail_id ] = $cur;
		} else {
			unset( $all[ (int) $travail_id ] );
		}
		update_option( self::CLASS_START_OPTION, $all, false );
	}

	/** Oublie les réglages par classe d'un travail supprimé. */
	public static function forget( $travail_id ) {
		foreach ( array( self::CLASS_STATUS_OPTION, self::CLASS_START_OPTION ) as $opt ) {
			$all = get_option( $opt, array() );
			if ( is_array( $all ) && isset( $all[ (int) $travail_id ] ) ) {
				unset( $all[ (int) $travail_id ] );
				update_option( $opt, $all, false );
			}
		}
	}

	/** Date de début (UTC) d'une classe visée par le travail. */
	public static function start_for_group( $travail, $group_id ) {
		$cs = self::class_start( $travail );
		return $cs[ (int) $group_id ] ?? ( $travail['date_debut'] ?? null );
	}

	/**
	 * Date de début (UTC) telle que la voit un apprenti : la plus proche parmi ses classes visées qui lui montrent le travail.
	 * Rend null si aucune date n'est fixée.
	 */
	public static function start_for( $travail, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$groups  = array_map( 'absint', (array) $travail['group_ids'] );
		$cs      = self::class_start( $travail );
		if ( ! $groups || ! $cs ) {
			return ! empty( $travail['date_debut'] ) ? $travail['date_debut'] : null;
		}
		$best = false;
		foreach ( array_intersect( $groups, LogiProjet_Sensei::user_groups( $user_id ) ) as $g ) {
			if ( ! in_array( self::status_for_group( $travail, $g ), array( 'ouvert', 'ferme' ), true ) ) {
				continue;
			}
			$st = $cs[ $g ] ?? ( ! empty( $travail['date_debut'] ) ? $travail['date_debut'] : null );
			if ( null === $st ) {
				return null; // Une de ses classes n'a pas de date : rien ne bloque.
			}
			if ( false === $best || strcmp( $st, $best ) < 0 ) {
				$best = $st;
			}
		}
		return false === $best ? ( ! empty( $travail['date_debut'] ) ? $travail['date_debut'] : null ) : $best;
	}

	/** Vrai si l'apprenti voit le travail mais ne peut pas encore l'ouvrir (date de début pas encore atteinte). */
	public static function is_locked( $travail, $user_id = 0 ) {
		$start = self::start_for( $travail, $user_id );
		return $start && strtotime( $start . ' UTC' ) > time();
	}

	/** Un apprenti voit un travail ouvert (ou fermé) pour sa classe s'il est dans un des groupes visés, ou si aucun groupe n'est visé. */
	public static function student_can_see( $travail, $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$groups = array_map( 'absint', (array) $travail['group_ids'] );
		if ( $groups && ! array_intersect( $groups, LogiProjet_Sensei::user_groups( $user_id ) ) ) {
			return false;
		}
		return in_array( self::status_for( $travail, $user_id ), array( 'ouvert', 'ferme' ), true );
	}
}
