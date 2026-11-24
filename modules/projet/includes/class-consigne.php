<?php
/**
 * Consigne structurée, section par section, comme le document Word :
 * encadré de note, mission, 3 actions, plan, règles, étapes par période, rendu, jour J, check-list.
 *
 * Chaque élément est une ligne de texte. **mot** = gras.
 * Les champs « actions » et « plan » s'écrivent « Titre | texte ».
 * Repères remplacés à l'affichage : {module}, {total}, {n}, {lecons}, {periodes}, {date_debut}, {date_fin}.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Consigne {

	const LISTS = array( 'mission', 'actions', 'plan', 'faire', 'eviter', 'etapes', 'rendu', 'jourj', 'checklist' );

	const TITLES = array(
		'mission'   => '1 · Votre mission',
		'actions'   => '2 · Ce que vous devez présenter',
		'plan'      => '3 · Le plan de votre présentation',
		'faire'     => '4 · Les règles : à faire',
		'eviter'    => '4 · Les règles : à éviter',
		'etapes'    => '5 · Les étapes de travail en classe',
		'rendu'     => '6 · Le rendu',
		'jourj'     => '7 · Le jour de la présentation',
		'checklist' => '8 · Check-list avant de rendre',
	);

	/** Les mots qui changent selon la taille du groupe (2 = textes identiques au document Word). */
	public static function words( $size ) {
		$size = max( 1, min( 6, (int) $size ) );
		$nom  = array( 1 => 'Nom', 2 => 'Nom1_Nom2', 3 => 'Nom1_Nom2_Nom3' );
		$n    = array( 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq', 6 => 'six' );
		if ( 1 === $size ) {
			return array( 'groupe' => 'travail individuel', 'groupes' => 'apprentis', 'en' => 'Seul·e', 'par' => 'par apprenti·e', 'membres' => 'Vous recevez votre propre note.', 'entre' => '', 'les_n' => 'vous', 'tous' => 'Vous prenez la parole', 'noms' => 'Nom' );
		}
		$g = 2 === $size ? 'duo' : ( 3 === $size ? 'trio' : 'groupe de ' . $size );
		return array(
			'groupe'  => $g,
			'groupes' => 2 === $size ? 'duos' : ( 3 === $size ? 'trios' : 'groupes' ),
			'en'      => 'En ' . $g,
			'par'     => 'par ' . $g,
			'membres' => 'Les ' . $n[ $size ] . ' membres du ' . $g . ' reçoivent la même note.',
			'entre'   => ' Se répartir les leçons entre vous ' . $n[ $size ] . '.',
			'les_n'   => 'les ' . $n[ $size ],
			'tous'    => 'Les ' . $n[ $size ] . ' membres du ' . $g . ' prennent la parole',
			'noms'    => $nom[ $size ] ?? 'Nom1_Nom2_…',
		);
	}

	/** Modèle identique au document Word « Consignes et grille d'évaluation ». */
	const TITRE_KEYS = array( 'mission', 'contenu', 'plan', 'page2', 'regles', 'etapes', 'rendu', 'jourj', 'checklist', 'grille' );

	public static function default_titres( $graded = true ) {
		return array(
			'mission'   => 'Votre mission',
			'contenu'   => 'Ce que vous devez présenter',
			'plan'      => 'Le plan de votre présentation (8 à 12 slides)',
			'page2'     => 'Comment réaliser votre présentation',
			'regles'    => 'Les règles de réalisation',
			'etapes'    => 'Les étapes de travail en classe',
			'rendu'     => 'Le rendu',
			'jourj'     => 'Le jour de la présentation',
			'checklist' => 'Check-list avant de rendre',
			'grille'    => $graded ? 'Grille d’évaluation de la présentation' : 'Critères de réussite',
		);
	}

	public static function modele( $n = 5, $size = 2, $formats = array( 'pptx' ), $graded = true ) {
		$n       = max( 1, (int) $n );
		$w       = self::words( $size );
		$formats = LogiProjet_Formats::clean( $formats );
		$cat     = LogiProjet_Formats::catalogue();
		$labels  = array_map( function ( $k ) use ( $cat ) { return $cat[ $k ][0]; }, $formats );
		$quoi    = 1 === count( $labels ) ? $labels[0] : implode( ', ', array_slice( $labels, 0, -1 ) ) . ' ou ' . end( $labels );
		$ext     = $cat[ $formats[0] ][1][0];
		return array(
			'note'      => ! $graded ? '**Cette activité n’est pas notée.** Elle vous entraîne avant les travaux notés : faites-la avec le même sérieux. Votre enseignant vous donnera un retour avec les critères de réussite ci-dessous.' : '**Ce travail est évalué et compte pour votre moyenne.** Votre présentation PowerPoint est notée sur {total} points avec la grille de la page 3. ' . $w['membres'] . ' Lisez la grille **avant** de commencer : elle vous dit exactement ce qui rapporte des points.',
			'mission'   => array(
				$w['en'] . ', vous créez une **présentation PowerPoint qui explique le contenu du module {module}** à la classe. Mettez-vous à la place de l’enseignant : grâce à votre présentation, un·e camarade qui n’a pas suivi le cours doit comprendre l’essentiel du module.',
				'Votre présentation doit pouvoir être présentée en **10 minutes**. Le jour J, 1 ou 2 ' . $w['groupes'] . ' seront tirés au sort pour la présenter devant la classe.',
			),
			'actions'   => array(
				'① Expliquer | Dites ce que c’est et à quoi ça sert, avec vos propres mots.',
				'② Donner un exemple | Montrez un cas concret dans une entreprise logistique (entrepôt, poste, transport).',
				'③ Illustrer | Ajoutez une image, un schéma ou un tableau qui aide à comprendre.',
			),
			'plan'      => array(
				'Slide 1 | **Titre** : numéro et titre du module, vos deux noms, votre classe.',
				'Slide 2 | **Plan** : la liste des {n} leçons que vous allez présenter.',
				( 5 === $n ? 'Slides 3 à 9' : 'Slides suivantes' ) . ' | **Les {n} leçons** : 1 à 2 slides par leçon (expliquer, exemple, illustration).',
				'Avant-dernière | **À retenir** : un résumé du module en 4 à 6 points courts.',
				'Dernière | **Sources** : d’où viennent vos informations et vos images (logiprof, sites, livres…).',
			),
			'faire'     => array(
				'Écrire avec **vos propres mots**',
				'Maximum **6 lignes courtes** par slide',
				'Texte assez grand pour être lu du fond de la classe',
				'Des images et schémas **utiles**, avec leur source',
				'Écrire ce que vous direz à l’oral dans les **notes du présentateur**',
				( $size > 1 ? 'Se répartir le travail : **' . $w['les_n'] . '** préparent et connaissent toute la présentation' : 'Bien connaître toute la présentation' ),
			),
			'eviter'    => array(
				'Copier-coller le cours, Internet ou une IA',
				'Des slides remplies de texte à lire',
				'Des images décoratives sans lien avec le sujet',
				'Plus de 12 slides',
				'Des fautes d’orthographe non relues',
				'Oublier la slide « Sources »',
			),
			'etapes'    => array(
				'**Lire le module {module}** sur logiprof : les leçons {lecons} et leurs vidéos.' . $w['entre'],
				'**Préparer le contenu** : pour chaque leçon, rédiger l’explication, trouver un exemple concret et choisir une illustration.',
				'**Construire les slides** en suivant le plan de la page 1. Écrire les notes du présentateur.',
				'**Vous auto-évaluer** avec la grille de la page 3 et la check-list. Corriger ce qui manque. **Rendre le fichier** et vous entraîner à présenter en 10 minutes.',
			),
			'rendu'     => array_merge(
				array(
					'Un fichier **' . $quoi . '** ' . $w['par'],
					'Nom du fichier : **{module}_' . $w['noms'] . '.' . $ext . '**',
					'Rendu au plus tard le {date_fin}',
				),
				// Pénalités de retard appliquées automatiquement par LogiProjet (travaux notés seulement).
				$graded ? array( 'En retard : **−0.5** de 1 à 7 jours, **−1** de 8 à 14 jours, **note 1** au-delà (chaque jour commencé compte)' ) : array(),
				array( 'Déposé sur **LogiProjet** (page « Mes travaux » de logiprof)' )
			),
			'jourj'     => array_merge( array(
				'1 ou 2 ' . $w['groupes'] . ' sont **tirés au sort**',
				'**10 minutes** de présentation + **5 minutes** de questions',
				$w['tous'],
			), $graded ? array( 'Bonus possible : **+0.5** sur la note (voir page 3)' ) : array() ),
			'checklist' => array(
				'Les {n} leçons ({lecons}) sont présentes',
				'Chaque leçon a une explication, un exemple et une illustration',
				'Il y a une slide Titre, Plan, À retenir et Sources',
				'Entre 8 et 12 slides',
				'Aucune slide n’a plus de 6 lignes',
				'Les notes du présentateur sont remplies',
				( $size > 1 ? 'L’orthographe a été relue par ' . $w['les_n'] : 'L’orthographe a été relue' ),
				'Le fichier est bien nommé {module}_' . $w['noms'] . '.' . $ext,
			),
		);
	}

	public static function sanitize( $in ) {
		if ( ! is_array( $in ) ) {
			return null;
		}
		$out  = array( 'note' => mb_substr( sanitize_text_field( (string) ( $in['note'] ?? '' ) ), 0, 1000 ) );
		$any  = '' !== $out['note'];
		$titres = array();
		foreach ( self::TITRE_KEYS as $k ) {
			$v = trim( sanitize_text_field( (string) ( $in['titres'][ $k ] ?? '' ) ) );
			if ( '' !== $v ) {
				$titres[ $k ] = mb_substr( $v, 0, 160 );
			}
		}
		if ( $titres ) {
			$out['titres'] = $titres;
		}
		foreach ( array( 'intro_lecons', 'intro_actions' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = mb_substr( sanitize_text_field( (string) $in[ $k ] ), 0, 400 );
			}
		}
		foreach ( self::LISTS as $k ) {
			$items = array();
			foreach ( (array) ( $in[ $k ] ?? array() ) as $line ) {
				$line = trim( sanitize_text_field( (string) $line ) );
				if ( '' !== $line ) {
					$items[] = mb_substr( $line, 0, 600 );
				}
				if ( count( $items ) >= 20 ) {
					break;
				}
			}
			$out[ $k ] = $items;
			$any       = $any || $items;
		}
		return $any ? $out : null;
	}

	/** « 9.1 à 9.5 » à partir des leçons numérotées. */
	public static function lecons_label( $lessons ) {
		$nums = array();
		foreach ( (array) $lessons as $l ) {
			if ( preg_match( '/^(\d+(?:\.\d+)*)\s/u', (string) $l['title'], $m ) ) {
				$nums[] = $m[1];
			}
		}
		if ( count( $nums ) >= 2 ) {
			return reset( $nums ) . ' à ' . end( $nums );
		}
		return 1 === count( $nums ) ? $nums[0] : 'du module';
	}

	public static function vars( $t ) {
		$fmt = function ( $gmt, $time ) {
			if ( ! $gmt ) {
				return 'date à venir';
			}
			$ts   = strtotime( $gmt . ' UTC' );
			$days = array( 'dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi' );
			$s    = $days[ (int) wp_date( 'w', $ts ) ] . ' ' . wp_date( 'd.m.Y', $ts );
			if ( $time ) {
				$m  = wp_date( 'i', $ts );
				$s .= ' à ' . wp_date( 'G', $ts ) . ' h' . ( '00' !== $m ? ' ' . $m : '' );
			}
			return $s;
		};
		return array(
			'{module}'     => $t['module'] ? $t['module'] : 'du cours',
			'{total}'      => (string) LogiProjet_Grading::total( $t['grille'] ),
			'{n}'          => (string) count( (array) $t['lessons'] ),
			'{lecons}'     => self::lecons_label( $t['lessons'] ),
			'{periodes}'   => (string) count( (array) ( $t['consigne']['etapes'] ?? array() ) ),
			'{date_debut}' => $fmt( $t['date_debut'] ?? null, false ),
			'{date_fin}'   => $fmt( $t['due_at'] ?? null, true ),
		);
	}

	/** Texte brut de la consigne (pour l'IA et l'ancien champ « consignes »). */
	public static function to_text( $c, $t ) {
		if ( ! $c ) {
			return '';
		}
		$v     = self::vars( $t );
		$clean = function ( $s ) use ( $v ) {
			return str_replace( '**', '', strtr( (string) $s, $v ) );
		};
		$tit   = array_merge( self::default_titres( ! isset( $t['graded'] ) || $t['graded'] ), (array) ( $c['titres'] ?? array() ) );
		$map   = array( 'mission' => 'mission', 'actions' => 'contenu', 'plan' => 'plan', 'faire' => 'regles', 'eviter' => 'regles', 'etapes' => 'etapes', 'rendu' => 'rendu', 'jourj' => 'jourj', 'checklist' => 'checklist' );
		$out   = array( ( ! isset( $t['graded'] ) || $t['graded'] ) ? 'TRAVAIL NOTÉ · note sur 6.' : 'ACTIVITÉ NON NOTÉE.', $clean( $c['note'] ) );
		foreach ( self::LISTS as $k ) {
			if ( empty( $c[ $k ] ) ) {
				continue;
			}
			$out[] = '';
			$out[] = mb_strtoupper( $tit[ $map[ $k ] ] . ( 'faire' === $k ? ' : à faire' : ( 'eviter' === $k ? ' : à éviter' : '' ) ) );
			if ( 'actions' === $k ) {
				$out[] = 'Les ' . $v['{n}'] . ' leçons : ' . implode( ' · ', wp_list_pluck( (array) $t['lessons'], 'title' ) ) . '. Pour chaque leçon :';
			}
			$i = 0;
			foreach ( $c[ $k ] as $line ) {
				++$i;
				$line  = str_replace( ' | ', ' : ', $clean( $line ) );
				$out[] = ( 'etapes' === $k ? 'Période ' . $i . ' : ' : ( 'checklist' === $k ? '☐ ' : '- ' ) ) . $line;
			}
		}
		return implode( "\n", $out );
	}
}
