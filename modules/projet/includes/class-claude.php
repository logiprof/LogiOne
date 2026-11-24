<?php
/**
 * Correction avec Claude, sans passer par le serveur :
 * 1. l'enseignant exporte les dépôts d'un travail (.zip anonymisé) ;
 * 2. il les corrige dans Claude avec la compétence « logiprojet-correction » ;
 * 3. il importe la correction (.json) : les notes arrivent comme propositions, il valide.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Claude {

	const EXPORT_FORMAT = 'logiprojet-correction-export';
	const IMPORT_FORMAT = 'logiprojet-correction';

	/** Fichiers inclus tels quels dans l'export au-delà de cette taille : non (Claude limite les envois). */
	const MAX_FILE = 25 * 1048576;

	public static function export_url( $travail_id ) {
		return add_query_arg(
			array(
				'action'   => 'logiprojet_export_correction',
				'travail'  => (int) $travail_id,
				'_wpnonce' => wp_create_nonce( 'logiprojet_export_correction' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Règles de correction transmises à Claude (dans l'export et dans la compétence). */
	public static function rules( $t ) {
		$graded = ! isset( $t['graded'] ) || $t['graded'];
		$size   = max( 1, (int) ( $t['group_size'] ?? 2 ) );
		return array(
			'Tu es un enseignant expérimenté de la formation professionnelle suisse (CFC/AFP). Les apprentis ont de 15 à 20 ans.',
			'Pour CHAQUE dépôt et CHAQUE critère de la grille : 0 = absent ou faux ; 1 = partiel ou imprécis ; 2 = atteint. Ne donne pas 2 par défaut.',
			'Compare le contenu aux leçons de référence (« lecons »). Une erreur de fond fait baisser le critère.',
			'Pour un PowerPoint, lis « extrait » (texte, notes du présentateur, nombre d’images, tableaux, graphiques par slide). Si le fichier est joint (« fichier »), regarde aussi les slides et les images. Pour un PDF ou une image, lis le fichier joint.',
			'Justification : une phrase courte et précise, avec le numéro de slide ou de page quand c’est utile (ex. « Slide 4 : définition juste, mais aucun exemple. »).',
			'« a_revoir » : les critères où tu hésites entre deux notes. « copie_suspecte » : true si des passages semblent copiés mot pour mot des leçons.',
			'Retour aux apprentis : ' . ( $size > 1 ? 'vouvoiement (le groupe)' : 'vouvoiement' ) . ', ton bienveillant, 2 points forts et 1 piste d’amélioration concrète. Français de Suisse romande, phrases courtes, sans jargon.',
			'« resume » : 2 phrases pour l’enseignant, qui ne seront pas montrées aux apprentis.',
			$graded ? 'Ne calcule pas la note : LogiProjet la calcule à partir des points (note = points / total × 5 + 1, arrondie au demi-point).' : 'Cette activité n’est pas notée : les points servent seulement de critères de réussite, n’écris jamais de note sur 6.',
			'Les noms des apprentis ont été remplacés par [Apprenti 1], [Apprenti 2]… : ne cherche pas à les deviner et n’écris aucun nom dans les retours.',
			'Rends UN fichier JSON au format « ' . self::IMPORT_FORMAT . ' » décrit dans « reponse_attendue », avec un objet par dépôt, et les mêmes « ref » et « depot » que dans cet export.',
		);
	}

	/** Remplace les noms dans toutes les chaînes d'une structure. */
	private static function anon_deep( $v, $depot ) {
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$v[ $k ] = self::anon_deep( $x, $depot );
			}
			return $v;
		}
		return is_string( $v ) ? LogiProjet_AI::anonymize( $v, $depot ) : $v;
	}

	public static function export() {
		check_admin_referer( 'logiprojet_export_correction' );
		if ( ! LogiProjet_Access::is_teacher() ) {
			wp_die( 'Réservé aux enseignants.', 403 );
		}
		$t = LogiProjet_Store::travail( isset( $_GET['travail'] ) ? absint( $_GET['travail'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $t || ! LogiProjet_Access::can_manage_travail( $t ) ) {
			wp_die( 'Travail introuvable.', 404 );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'L’extension PHP « zip » est nécessaire pour l’export. Demandez à votre hébergeur de l’activer.' );
		}
		$with_pptx = ! empty( $_GET['pptx'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$tous      = ! empty( $_GET['tous'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$tmp = wp_tempnam( 'logiprojet-correction' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_die( 'L’export n’a pas pu être préparé.' );
		}

		$depots  = array();
		$ignores = 0;
		$n       = 0;
		foreach ( LogiProjet_Store::depots( $t['id'] ) as $d ) {
			if ( 'groupe' === $d['status'] ) {
				continue; // Groupe enregistré, pas encore de fichier.
			}
			if ( 'valide' === $d['status'] && ! $tous ) {
				++$ignores;
				continue;
			}
			++$n;
			$ref  = sprintf( 'D%02d', $n );
			$path = LogiProjet_Store::file_abspath( $d['file_path'] );
			$ext  = strtolower( pathinfo( (string) $d['file_name'], PATHINFO_EXTENSION ) );
			$item = array(
				'ref'     => $ref,
				'depot'   => (int) $d['id'],
				'membres' => count( (array) $d['members'] ),
				'format'  => $ext,
				'fichier' => null,
				'extrait' => null,
				'remarque' => '',
			);
			if ( 'pptx' === $ext && $path && file_exists( $path ) ) {
				$x = $d['extract'];
				if ( ! $x ) {
					$x = LogiProjet_Pptx::extract( $path );
					if ( ! is_wp_error( $x ) ) {
						LogiProjet_Store::save_depot( array( 'extract' => $x ), $d['id'] );
					}
				}
				if ( is_array( $x ) && ! is_wp_error( $x ) ) {
					$item['extrait'] = self::anon_deep( $x, $d );
				}
			}
			$joindre = $path && file_exists( $path ) && ( 'pptx' !== $ext || $with_pptx || ! $item['extrait'] );
			if ( $joindre && filesize( $path ) <= self::MAX_FILE ) {
				$item['fichier'] = 'depots/' . $ref . '.' . $ext;
				$zip->addFile( $path, $item['fichier'] );
				if ( 'pptx' !== $ext ) {
					$item['remarque'] = 'Fichier original : les noms des apprentis peuvent y figurer. N’en écris aucun dans les retours.';
				}
			} elseif ( $joindre ) {
				$item['remarque'] = 'Fichier trop lourd pour l’export : à corriger directement dans LogiProjet.';
			} elseif ( ! $path || ! file_exists( $path ) ) {
				$item['remarque'] = 'Fichier introuvable sur le serveur.';
			}
			$depots[] = $item;
		}

		$lecons = array();
		foreach ( (array) $t['lessons'] as $l ) {
			$lecons[] = array(
				'titre' => $l['title'],
				'texte' => LogiProjet_Sensei::lesson_text( $l['id'], 12000 ),
			);
		}
		$graded  = ! isset( $t['graded'] ) || $t['graded'];
		$export  = array(
			'format'      => self::EXPORT_FORMAT,
			'version'     => 1,
			'logiprojet'  => LOGIPROJET_VERSION,
			'site'        => home_url( '/' ),
			'exporte_le'  => gmdate( 'c' ),
			'travail'     => array(
				'id'         => (int) $t['id'],
				'titre'      => $t['title'],
				'module'     => $t['module'],
				'notee'      => $graded,
				'taille_groupe' => (int) ( $t['group_size'] ?? 2 ),
				'total'      => LogiProjet_Grading::total( $t['grille'] ),
				'consigne'   => $t['consigne'] ? LogiProjet_Consigne::to_text( $t['consigne'], $t ) : wp_strip_all_tags( (string) $t['consignes'] ),
				'grille'     => array_map(
					function ( $c ) {
						return array(
							'id'    => $c['id'],
							'bloc'  => $c['bloc'],
							'label' => $c['label'],
						);
					},
					(array) $t['grille']
				),
			),
			'lecons'      => $lecons,
			'regles'      => self::rules( $t ),
			'reponse_attendue' => array(
				'format'     => self::IMPORT_FORMAT,
				'version'    => 1,
				'travail_id' => (int) $t['id'],
				'modele'     => 'Claude (nom du modèle)',
				'depots'     => array(
					array(
						'ref'            => 'D01',
						'depot'          => $depots ? $depots[0]['depot'] : 0,
						'criteres'       => array(
							array(
								'id'            => $t['grille'] ? $t['grille'][0]['id'] : 'c1',
								'score'         => 2,
								'justification' => 'Slide 3 : définition juste, avec un exemple de l’entrepôt.',
							),
						),
						'points_forts'   => array( '…', '…' ),
						'piste'          => '…',
						'resume'         => '…',
						'a_revoir'       => array(),
						'copie_suspecte' => false,
					),
				),
			),
			'depots'      => $depots,
		);
		$zip->addFromString( 'correction.json', wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString(
			'LISEZ-MOI.txt',
			implode(
				"\r\n",
				array(
					'Export de correction LogiProjet : ' . $t['title'],
					count( $depots ) . ' dépôt(s) à corriger' . ( $ignores ? ', ' . $ignores . ' déjà validé(s) laissé(s) de côté' : '' ) . '.',
					'',
					'1. Dans Claude, avec la compétence « logiprojet-correction », envoyez ce fichier .zip et demandez : « Corrige cet export LogiProjet ».',
					'2. Claude rend un fichier correction_LogiProjet.json.',
					'3. Dans LogiProjet, ouvrez le travail et cliquez sur « Importer la correction ».',
					'4. Vérifiez chaque dépôt, puis validez : c’est vous qui décidez de la note.',
				)
			)
		);
		$zip->close();

		$name = sanitize_file_name( ( $t['module'] ? $t['module'] . '_' : '' ) . 'correction_' . gmdate( 'Y-m-d' ) . '.zip' );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		exit;
	}

	/**
	 * Importe la correction rendue par Claude. Les notes deviennent des propositions :
	 * l'enseignant vérifie et valide chaque dépôt.
	 */
	public static function import( $t, $data ) {
		if ( ! is_array( $data ) || self::IMPORT_FORMAT !== ( $data['format'] ?? '' ) ) {
			return new WP_Error( 'logiprojet_import', 'Ce fichier n’est pas une correction LogiProjet (format « ' . self::IMPORT_FORMAT . ' »).', array( 'status' => 400 ) );
		}
		if ( isset( $data['travail_id'] ) && (int) $data['travail_id'] !== (int) $t['id'] ) {
			return new WP_Error( 'logiprojet_import', 'Cette correction a été faite pour un autre travail (n° ' . (int) $data['travail_id'] . '). Ouvrez le bon travail avant d’importer.', array( 'status' => 400 ) );
		}
		if ( ! $t['grille'] ) {
			return new WP_Error( 'logiprojet_import', 'La grille de ce travail est vide.', array( 'status' => 400 ) );
		}
		$items = (array) ( $data['depots'] ?? array() );
		if ( ! $items ) {
			return new WP_Error( 'logiprojet_import', 'Le fichier ne contient aucun dépôt corrigé.', array( 'status' => 400 ) );
		}
		$model = sanitize_text_field( (string) ( $data['modele'] ?? '' ) );
		$model = ( '' !== $model && false === strpos( $model, '(nom' ) ? $model : 'Claude' ) . ' · import du ' . wp_date( 'd.m.Y' );

		$ok       = 0;
		$warnings = array();
		$seen     = array();
		foreach ( $items as $it ) {
			$ref = sanitize_text_field( (string) ( $it['ref'] ?? '?' ) );
			$id  = (int) ( $it['depot'] ?? 0 );
			$d   = $id ? LogiProjet_Store::depot( $id ) : null;
			if ( ! $d || (int) $d['travail_id'] !== (int) $t['id'] ) {
				$warnings[] = $ref . ' : dépôt introuvable dans ce travail, ignoré.';
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				$warnings[] = $ref . ' : présent deux fois dans le fichier, seule la première correction est gardée.';
				continue;
			}
			$seen[ $id ] = true;
			if ( 'groupe' === $d['status'] ) {
				$warnings[] = $ref . ' : ce groupe n’a pas de fichier déposé, ignoré.';
				continue;
			}
			if ( 'valide' === $d['status'] ) {
				$warnings[] = $ref . ' : déjà validé, laissé tel quel. Retirez la publication pour réimporter.';
				continue;
			}
			$res = LogiProjet_AI::normalize( (array) $it, (array) $t['grille'] );
			$missing = count(
				array_filter(
					$res['criteres'],
					function ( $c ) {
						return 0 === strpos( $c['justification'], 'L’IA n’a pas évalué' );
					}
				)
			);
			if ( $missing === count( $res['criteres'] ) ) {
				$warnings[] = $ref . ' : aucun critère de la grille reconnu, ignoré.';
				continue;
			}
			if ( $missing ) {
				$warnings[] = $ref . ' : ' . $missing . ' critère(s) sans note, marqué(s) « à revoir ».';
			}
			$scores = array();
			foreach ( $res['criteres'] as $cid => $c ) {
				$scores[ $cid ] = array(
					'score'   => $c['score'],
					'comment' => mb_substr( $c['justification'], 0, 600 ),
					'ai'      => $c['score'],
				);
			}
			$feedback = array(
				'points_forts' => implode( "\n", $res['points_forts'] ),
				'piste'        => $res['piste'],
			);
			$c = LogiProjet_Grading::compute( $t, $scores, (array) $d['oral'], $d );
			LogiProjet_Store::save_depot(
				array(
					'ai'          => $res,
					'ai_error'    => null,
					'ai_model'    => $model,
					'scores'      => $scores,
					'feedback'    => $feedback,
					'status'      => 'propose',
					'points'      => $c['points'],
					'note'        => $c['note'],
					'note_finale' => $c['note_finale'],
				),
				$d['id']
			);
			++$ok;
		}
		return array(
			'importes' => $ok,
			'warnings' => $warnings,
		);
	}
}
