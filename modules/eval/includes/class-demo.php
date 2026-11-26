<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LogiOne 1.53.19 : mode démonstration de LogiEval (présentation aux collègues).
 *
 * Quand l'administrateur l'active (Réglages › Mode démonstration), l'écran de connexion propose
 * « Essayer LogiEval (démonstration) » : le visiteur choisit son profil (maître de classe ou enseignant)
 * et reçoit SON espace d'essai, sans identifiant : une classe fictive de 5 élèves, deux collègues fictifs
 * et une évaluation courte (3 objectifs, 4 questions). Chaque essai est indépendant des autres.
 *
 * Aucune table n'est modifiée : les données d'essai sont des lignes ordinaires, repérées dans l'option
 * « logieval_demo ». Les objectifs de démonstration sont inactifs (jamais dans une vraie évaluation),
 * les classes, élèves, enseignants et évaluations d'essai sont masqués dans l'administration,
 * n'envoient aucun e-mail et sont supprimés automatiquement après 3 jours (ou d'un clic).
 */
class LOGIEVAL_Demo {

	const OPTION         = 'logieval_demo';
	const ENABLED_OPTION = 'logieval_demo_enabled';
	const KEEP_DAYS      = 3;
	const MAX_PER_HOUR   = 80; // essais créés par heure et par adresse (une salle entière derrière la même adresse)

	/* ------------------------------------------------------------------ état */

	public static function enabled(): bool {
		return (bool) get_option( self::ENABLED_OPTION, 0 );
	}

	private static function data(): array {
		$d = get_option( self::OPTION, array() );
		$d = is_array( $d ) ? $d : array();
		return $d + array( 'objectifs' => array(), 'fakes' => array(), 'sandboxes' => array() );
	}

	private static function save( array $d ): void {
		update_option( self::OPTION, $d, false );
	}

	public static function objective_ids(): array {
		return array_map( 'intval', self::data()['objectifs'] );
	}

	public static function class_ids(): array {
		return array_map( 'intval', array_keys( self::data()['sandboxes'] ) );
	}

	/** Enseignants d'essai : visiteurs et collègues fictifs. */
	public static function teacher_ids(): array {
		$d   = self::data();
		$ids = array_map( 'intval', $d['fakes'] );
		foreach ( $d['sandboxes'] as $sb ) {
			$ids[] = (int) ( $sb['ens'] ?? 0 );
		}
		return array_values( array_filter( array_unique( $ids ) ) );
	}

	public static function is_demo_class( int $classe_id ): bool {
		return $classe_id > 0 && in_array( $classe_id, self::class_ids(), true );
	}

	public static function is_demo_eval( $eval ): bool {
		if ( ! is_array( $eval ) ) {
			$eval = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t( 'evaluations' ), array( 'id' => (int) $eval ) );
		}
		return $eval && self::is_demo_class( (int) $eval['classe_id'] );
	}

	public static function is_demo_teacher( int $ens_id ): bool {
		return $ens_id > 0 && in_array( $ens_id, self::teacher_ids(), true );
	}

	/** Le visiteur connecté et son espace d'essai, ou null. */
	public static function current_sandbox(): ?array {
		$ens = LOGIEVAL_Auth::current_enseignant_id();
		if ( ! $ens ) {
			return null;
		}
		foreach ( self::data()['sandboxes'] as $cid => $sb ) {
			if ( (int) ( $sb['ens'] ?? 0 ) === $ens ) {
				return $sb + array( 'classe_id' => (int) $cid );
			}
		}
		return null;
	}

	/** Retire les lignes d'essai d'une liste de l'administration. */
	public static function hide( array $rows, string $kind ): array {
		$ids = 'classes' === $kind ? self::class_ids() : ( 'enseignants' === $kind ? self::teacher_ids() : array() );
		if ( 'eleves' === $kind || 'evaluations' === $kind ) {
			$ids = self::class_ids();
		}
		if ( ! $ids ) {
			return $rows;
		}
		$key = ( 'eleves' === $kind || 'evaluations' === $kind ) ? 'classe_id' : 'id';
		return array_values( array_filter( $rows, static function ( $r ) use ( $ids, $key ) {
			$v = is_array( $r ) ? ( $r[ $key ] ?? 0 ) : ( $r->$key ?? 0 );
			return ! in_array( (int) $v, $ids, true );
		} ) );
	}

	/** Clause SQL qui exclut les classes d'essai. */
	public static function sql_exclude( string $column ): string {
		$ids = self::class_ids();
		return $ids ? ' AND ' . $column . ' NOT IN (' . implode( ',', $ids ) . ')' : '';
	}

	/* ------------------------------------------------------------------ grille courte */

	/** 3 objectifs, 4 questions : de quoi voir Oui / Non / Sans avis, un choix unique et un objectif du maître. */
	private static function ensure_objectives(): array {
		global $wpdb;
		$t  = LOGIEVAL_Database::tables();
		$d  = self::data();
		$ok = array_filter( $d['objectifs'], static fn( $id ) => (bool) LOGIEVAL_Database::get_row( $t['objectifs'], array( 'id' => (int) $id ) ) );
		if ( count( $ok ) === 3 ) {
			return array_map( 'intval', $ok );
		}
		$defs = array(
			array( 'Travail en classe', 'checkbox', 0, array( array( 'A', 'Se met au travail sans rappel', 1 ), array( 'B', 'Rend ses travaux dans les délais', 1 ) ) ),
			array( 'Attitude et respect', 'radio', 0, array( array( 'A', 'Toujours respectueux·se et poli·e', 2 ), array( 'B', 'Le plus souvent respectueux·se', 1 ), array( 'C', 'Doit encore s’améliorer', 0 ) ) ),
			array( 'Tenue du classeur', 'checkbox', 1, array( array( 'A', 'Classeur complet et en ordre', 1 ) ) ),
		);
		$ids = array();
		foreach ( $defs as $i => $def ) {
			// actif = 0 : un objectif de démonstration n'entre jamais dans une vraie évaluation.
			$oid = LOGIEVAL_Database::insert( $t['objectifs'], array( 'libelle' => $def[0], 'type' => $def[1], 'maitre_seulement' => $def[2], 'actif' => 0, 'ordre' => 9000 + $i ) );
			foreach ( $def[3] as $k => $sc ) {
				LOGIEVAL_Database::insert( $t['sous_criteres'], array( 'objectif_id' => $oid, 'lettre' => $sc[0], 'libelle' => $sc[1], 'points' => $sc[2], 'ordre' => $k ) );
			}
			$ids[] = $oid;
		}
		$d              = self::data();
		$d['objectifs'] = $ids;
		self::save( $d );
		return $ids;
	}

	/** Grille d'une évaluation d'essai (même forme que LOGIEVAL_Calculator::grid). */
	public static function grid(): array {
		global $wpdb;
		$t    = LOGIEVAL_Database::tables();
		$ids  = self::ensure_objectives();
		$objs = $wpdb->get_results( 'SELECT * FROM ' . $t['objectifs'] . ' WHERE id IN (' . implode( ',', $ids ) . ') ORDER BY ordre', ARRAY_A ) ?: array();
		$scs  = $wpdb->get_results( 'SELECT * FROM ' . $t['sous_criteres'] . ' WHERE objectif_id IN (' . implode( ',', $ids ) . ') ORDER BY objectif_id, ordre', ARRAY_A ) ?: array();
		$by   = array();
		foreach ( $scs as $sc ) {
			$by[ $sc['objectif_id'] ][] = $sc;
		}
		$total = 0.0;
		foreach ( $objs as $o ) {
			$total += LOGIEVAL_Calculator::objective_max( $o, $by[ $o['id'] ] ?? array() );
		}
		return array( 'objectifs' => $objs, 'sous_by_obj' => $by, 'total_max' => $total );
	}

	/* ------------------------------------------------------------------ espaces d'essai */

	private static function fake_teacher( string $prenom, string $nom ): int {
		$t     = LOGIEVAL_Database::tables();
		$ident = 'DEMO-' . strtoupper( bin2hex( random_bytes( 7 ) ) );
		return LOGIEVAL_Database::insert( $t['enseignants'], array(
			'prenom' => $prenom, 'nom' => $nom, 'email' => LOGIEVAL_Database::placeholder_email( strtolower( $ident ) ),
			'identifiant_unique' => $ident, 'actif' => 1,
		) );
	}

	private static function ensure_fakes(): array {
		$t  = LOGIEVAL_Database::tables();
		$d  = self::data();
		$ok = array_values( array_filter( $d['fakes'], static fn( $id ) => (bool) LOGIEVAL_Database::get_row( $t['enseignants'], array( 'id' => (int) $id ) ) ) );
		if ( count( $ok ) === 2 ) {
			return array_map( 'intval', $ok );
		}
		$ok         = array( self::fake_teacher( 'Marie', 'Exemple (démo)' ), self::fake_teacher( 'Paul', 'Modèle (démo)' ) );
		$d          = self::data();
		$d['fakes'] = $ok;
		self::save( $d );
		return $ok;
	}

	public static function can_create(): bool {
		$key = 'logieval_demo_rate_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		$n   = (int) get_transient( $key );
		if ( $n >= self::MAX_PER_HOUR ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Crée l'espace d'essai d'un visiteur et le connecte.
	 * $profil : 'maitre' (il est maître de classe, un collègue fictif a déjà terminé)
	 *           ou 'enseignant' (la maîtresse de classe et un collègue sont fictifs).
	 */
	public static function start( string $profil, string $prenom = '' ): bool {
		if ( ! self::enabled() || ! self::can_create() ) {
			return false;
		}
		self::purge_old();
		$t      = LOGIEVAL_Database::tables();
		$profil = 'maitre' === $profil ? 'maitre' : 'enseignant';
		$prenom = trim( wp_strip_all_tags( $prenom ) );
		$prenom = '' !== $prenom ? mb_substr( $prenom, 0, 40 ) : 'Visiteur';
		$objs   = self::ensure_objectives();
		list( $marie, $paul ) = self::ensure_fakes();

		$me   = self::fake_teacher( $prenom, 'Essai (démo)' );
		$year = class_exists( 'LOGIEVAL_Importer' ) && method_exists( 'LOGIEVAL_Importer', 'current_school_year' ) ? LOGIEVAL_Importer::current_school_year() : '';
		$cid  = LOGIEVAL_Database::insert( $t['classes'], array( 'nom' => 'DÉMO · Classe de ' . $prenom, 'niveau' => 'Démo', 'annee_scolaire' => $year ) );
		$d    = self::data();
		$d['sandboxes'][ $cid ] = array( 'ens' => $me, 'profil' => $profil, 'created' => time() );
		self::save( $d );

		$eleves = array();
		foreach ( array( array( 'Léa', 'Martin' ), array( 'Noah', 'Dubois' ), array( 'Emma', 'Rochat' ), array( 'Lucas', 'Favre' ), array( 'Chloé', 'Meier' ) ) as $k => $e ) {
			$eleves[] = LOGIEVAL_Database::insert( $t['eleves'], array( 'classe_id' => $cid, 'prenom' => $e[0], 'nom' => $e[1], 'num_eleve' => 'DEMO-' . $cid . '-' . ( $k + 1 ) ) );
		}
		$team = 'maitre' === $profil ? array( $me => 1, $paul => 0 ) : array( $marie => 1, $paul => 0, $me => 0 );
		if ( 'maitre' === $profil ) {
			$team[ $marie ] = 0;
		}
		foreach ( $team as $ens => $maitre ) {
			LOGIEVAL_Database::insert( $t['classe_enseignant'], array( 'classe_id' => $cid, 'enseignant_id' => $ens, 'est_maitre' => $maitre ) );
		}
		$eid = LOGIEVAL_Database::insert( $t['evaluations'], array(
			'classe_id' => $cid, 'semestre' => 'Démonstration', 'date_echeance' => gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS ),
			'statut' => 'active', 'mode_calcul' => 'majoritaire', 'created_by' => 'maitre' === $profil ? $me : $marie,
			'mail_teacher_completed' => 1, 'mail_all_completed' => 1, 'mail_closure_maitre' => 1, 'last_teacher_reminder_sent' => 1,
		) );
		update_option( 'logieval_j7_sent_' . $eid, current_time( 'mysql' ), false );

		// Paul a déjà tout rempli et terminé ; Marie a commencé (profil « enseignant » : elle est la maîtresse de classe).
		$grid = self::grid();
		foreach ( array_keys( $team ) as $ens ) {
			LOGIEVAL_Database::insert( $t['eval_enseignant'], array(
				'evaluation_id' => $eid, 'enseignant_id' => $ens,
				'statut' => $ens === $paul ? 'termine' : 'en_cours', 'date_fin' => $ens === $paul ? current_time( 'mysql' ) : null,
			) );
		}
		$fill = array( $paul => count( $eleves ), $marie => 'maitre' === $profil ? 2 : 3 );
		foreach ( $fill as $ens => $n ) {
			foreach ( array_slice( $eleves, 0, $n ) as $k => $el ) {
				foreach ( $grid['objectifs'] as $o ) {
					if ( ! empty( $o['maitre_seulement'] ) && ! ( $ens === $marie && 'enseignant' === $profil ) ) {
						continue;
					}
					$scs = $grid['sous_by_obj'][ $o['id'] ] ?? array();
					if ( 'radio' === $o['type'] ) {
						$pick = $scs[ ( $k + ( $ens === $paul ? 0 : 1 ) ) % 2 ] ?? null; // A ou B
						if ( $pick ) {
							self::vote( $eid, $ens, $el, (int) $pick['id'], 'oui' );
						}
						continue;
					}
					foreach ( $scs as $j => $sc ) {
						$vote = ( 1 === $k && 1 === $j ) ? 'non' : 'oui'; // Noah : un « non » pour voir un désaccord
						self::vote( $eid, $ens, $el, (int) $sc['id'], $vote );
					}
				}
			}
		}
		return LOGIEVAL_Auth::login_demo( $me );
	}

	private static function vote( int $eid, int $ens, int $el, int $sc, string $vote ): void {
		LOGIEVAL_Database::insert( LOGIEVAL_Database::t( 'reponses' ), array(
			'evaluation_id' => $eid, 'enseignant_id' => $ens, 'eleve_id' => $el, 'sous_critere_id' => $sc, 'vote' => $vote, 'vote_set' => 1,
		) );
	}

	/** Supprime un espace d'essai (classe, élèves, évaluations, réponses, visiteur). */
	public static function delete_sandbox( int $cid ): void {
		global $wpdb;
		$t  = LOGIEVAL_Database::tables();
		$d  = self::data();
		$sb = $d['sandboxes'][ $cid ] ?? null;
		if ( ! $sb ) {
			return;
		}
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['evaluations']} WHERE classe_id=%d", $cid ) ) ?: array() as $eid ) {
			foreach ( array( 'reponses', 'eval_enseignant', 'non_evaluables', 'arbitrages' ) as $tb ) {
				$wpdb->delete( $t[ $tb ], array( 'evaluation_id' => (int) $eid ) );
			}
			$wpdb->delete( $t['notes_ref'], array( 'evaluation_id_dest' => (int) $eid ) );
			$wpdb->delete( $t['notes_ref'], array( 'evaluation_id_source' => (int) $eid ) );
			foreach ( array( 'logieval_grid_', 'logieval_j7_sent_' ) as $opt ) {
				delete_option( $opt . (int) $eid );
			}
			$wpdb->delete( $t['evaluations'], array( 'id' => (int) $eid ) );
		}
		$wpdb->delete( $t['eleves'], array( 'classe_id' => $cid ) );
		$wpdb->delete( $t['classe_enseignant'], array( 'classe_id' => $cid ) );
		$wpdb->delete( $t['classes'], array( 'id' => $cid ) );
		if ( ! empty( $sb['ens'] ) ) {
			$wpdb->delete( $t['enseignants'], array( 'id' => (int) $sb['ens'] ) );
		}
		$d = self::data();
		unset( $d['sandboxes'][ $cid ] );
		self::save( $d );
	}

	public static function purge_old(): void {
		foreach ( self::data()['sandboxes'] as $cid => $sb ) {
			if ( (int) ( $sb['created'] ?? 0 ) < time() - self::KEEP_DAYS * DAY_IN_SECONDS ) {
				self::delete_sandbox( (int) $cid );
			}
		}
	}

	/** Supprime toutes les données de démonstration (bouton des réglages). */
	public static function purge_all(): int {
		global $wpdb;
		$t = LOGIEVAL_Database::tables();
		$n = 0;
		foreach ( array_keys( self::data()['sandboxes'] ) as $cid ) {
			self::delete_sandbox( (int) $cid );
			$n++;
		}
		$d = self::data();
		foreach ( $d['fakes'] as $id ) {
			$wpdb->delete( $t['classe_enseignant'], array( 'enseignant_id' => (int) $id ) );
			$wpdb->delete( $t['enseignants'], array( 'id' => (int) $id ) );
		}
		foreach ( $d['objectifs'] as $id ) {
			$wpdb->delete( $t['sous_criteres'], array( 'objectif_id' => (int) $id ) );
			$wpdb->delete( $t['objectifs'], array( 'id' => (int) $id ) );
		}
		delete_option( self::OPTION );
		return $n;
	}

	public static function count(): int {
		return count( self::data()['sandboxes'] );
	}
}
