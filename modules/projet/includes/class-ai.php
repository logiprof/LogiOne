<?php
/**
 * Correction assistée par IA : Gemini, OpenAI ou Claude (Anthropic).
 * L'IA propose une note par critère avec une justification ; l'enseignant décide.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_AI {

	const PROVIDERS = array(
		'gemini'    => 'Google Gemini',
		'openai'    => 'OpenAI',
		'anthropic' => 'Claude (Anthropic)',
	);

	public static function key( $provider ) {
		$const = 'LOGIPROJET_' . strtoupper( $provider ) . '_KEY';
		if ( defined( $const ) && constant( $const ) ) {
			return (string) constant( $const );
		}
		return (string) LogiProjet_Settings::get( $provider . '_key' );
	}

	public static function provider() {
		$p = (string) LogiProjet_Settings::get( 'provider' );
		return isset( self::PROVIDERS[ $p ] ) ? $p : 'gemini';
	}

	public static function model( $provider ) {
		return (string) LogiProjet_Settings::get( $provider . '_model' );
	}

	public static function is_ready() {
		return '' !== self::key( self::provider() ) && '' !== self::model( self::provider() );
	}

	/**
	 * Corrige un dépôt. Retourne le résultat normalisé ou WP_Error.
	 */
	public static function grade( $travail, $depot ) {
		$provider = self::provider();
		$key      = self::key( $provider );
		$model    = self::model( $provider );
		if ( '' === $key || '' === $model ) {
			return new WP_Error( 'logiprojet_ai_config', 'Aucune clé API configurée pour ' . self::PROVIDERS[ $provider ] . '. Allez dans Réglages → LogiProjet.' );
		}
		$system = self::system_prompt();
		$user   = self::user_prompt( $travail, $depot );

		switch ( $provider ) {
			case 'openai':
				$text = self::call_openai( $key, $model, $system, $user );
				break;
			case 'anthropic':
				$text = self::call_anthropic( $key, $model, $system, $user );
				break;
			default:
				$text = self::call_gemini( $key, $model, $system, $user );
		}
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$json = self::parse_json( $text );
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'logiprojet_ai_json', 'La réponse de l’IA n’était pas lisible. Relancez la correction.' );
		}
		$result          = self::normalize( $json, (array) $travail['grille'] );
		$result['model'] = self::PROVIDERS[ $provider ] . ' · ' . $model;
		return $result;
	}

	public static function system_prompt() {
		return implode(
			"\n",
			array(
				'Tu es un enseignant expérimenté de la formation professionnelle suisse (CFC/AFP logisticien·ne).',
				'Tu évalues la présentation PowerPoint réalisée en duo par des apprentis de 15 à 20 ans.',
				'Tu reçois : la consigne, la grille d’évaluation, le contenu de référence des leçons du cours, et le contenu extrait de chaque slide (texte, nombre de mots, images, tableaux, graphiques, notes du présentateur).',
				'Tu ne vois pas les images elles-mêmes, seulement leur nombre : ne juge pas leur contenu, seulement leur présence et leur pertinence probable d’après le titre de la slide.',
				'',
				'Règles de notation, pour CHAQUE critère de la grille :',
				'- 0 = absent ou faux ; 1 = partiel ou imprécis ; 2 = atteint.',
				'- Compare le contenu aux leçons de référence. Une erreur de fond fait baisser le critère.',
				'- Sois juste et bienveillant, mais ne donne pas 2 par défaut.',
				'- La justification tient en une phrase courte, précise, avec le numéro de slide quand c’est utile (ex. « Slide 4 : définition juste, mais aucun exemple. »).',
				'- Signale dans « a_revoir » les critères où tu hésites entre deux notes.',
				'- « copie_suspecte » = true si des passages semblent copiés mot pour mot dans les leçons de référence (texte identique sur plusieurs lignes).',
				'',
				'Feedback aux apprentis : vouvoiement (le duo), ton bienveillant, 2 points forts et 1 piste d’amélioration concrète.',
				'Écris en français de Suisse romande, phrases courtes, sans jargon.',
				'',
				'Réponds UNIQUEMENT avec un objet JSON valide, sans texte autour, de cette forme :',
				'{"criteres":[{"id":"c1","score":2,"justification":"..."}],"points_forts":["...","..."],"piste":"...","resume":"2 phrases pour l’enseignant","a_revoir":["c3"],"copie_suspecte":false}',
			)
		);
	}

	/** Remplace les noms des apprentis par [Apprenti 1] / [Apprenti 2] avant l'envoi à l'IA. */
	public static function anonymize( $text, $depot ) {
		$i = 0;
		foreach ( (array) $depot['members'] as $uid ) {
			++$i;
			$u = $uid ? get_userdata( $uid ) : null;
			if ( ! $u ) {
				continue;
			}
			foreach ( array( trim( $u->first_name . ' ' . $u->last_name ), trim( $u->last_name . ' ' . $u->first_name ), $u->display_name, $u->first_name, $u->last_name, $u->user_login ) as $name ) {
				$name = trim( (string) $name );
				if ( function_exists( 'mb_strlen' ) ? mb_strlen( $name ) >= 3 : strlen( $name ) >= 3 ) {
					$text = preg_replace( '/(?<![\p{L}\p{N}])' . preg_quote( $name, '/' ) . '(?![\p{L}\p{N}])/iu', '[Apprenti ' . $i . ']', $text );
				}
			}
		}
		return $text;
	}

	public static function user_prompt( $travail, $depot ) {
		$out   = array();
		$out[] = '# Travail : ' . $travail['title'] . ( $travail['module'] ? ' (module ' . $travail['module'] . ')' : '' );
		if ( $travail['consignes'] ) {
			$out[] = "\n## Consigne donnée aux apprentis\n" . wp_strip_all_tags( $travail['consignes'] );
		}
		$out[] = "\n## Grille d'évaluation (chaque critère noté 0, 1 ou 2)";
		foreach ( (array) $travail['grille'] as $c ) {
			$out[] = '- ' . $c['id'] . ' [' . $c['bloc'] . '] ' . $c['label'];
		}
		$out[] = "\n## Contenu de référence des leçons";
		$budget = 30000;
		foreach ( (array) $travail['lessons'] as $l ) {
			$txt = LogiProjet_Sensei::lesson_text( $l['id'], min( 8000, max( 1500, $budget ) ) );
			$budget -= strlen( $txt );
			$out[] = '### ' . $l['title'] . "\n" . ( '' !== $txt ? $txt : '(contenu non disponible)' );
		}
		$x     = (array) $depot['extract'];
		$out[] = "\n## Présentation du duo";
		$out[] = 'Nombre de slides : ' . (int) ( $x['slide_count'] ?? 0 ) . ' · mots au total : ' . (int) ( $x['words'] ?? 0 ) . ' · visuels : ' . (int) ( $x['visuals'] ?? 0 ) . ' · slides avec notes du présentateur : ' . (int) ( $x['notes_count'] ?? 0 );
		$slides = '';
		foreach ( (array) ( $x['slides'] ?? array() ) as $s ) {
			$slides .= "\n### Slide " . $s['n'] . ( $s['hidden'] ? ' (masquée)' : '' ) . ( '' !== $s['title'] ? ' — ' . $s['title'] : '' ) . "\n";
			$slides .= 'Mots : ' . $s['words'] . ' · lignes : ' . count( $s['lines'] ) . ' · images : ' . $s['images'] . ' · tableaux : ' . $s['tables'] . ' · graphiques : ' . $s['charts'] . ' · SmartArt : ' . $s['diagrams'] . "\n";
			if ( $s['lines'] ) {
				$slides .= implode( "\n", $s['lines'] ) . "\n";
			}
			if ( '' !== $s['notes'] ) {
				$slides .= 'Notes du présentateur : ' . $s['notes'] . "\n";
			}
		}
		$out[] = self::anonymize( $slides, $depot );
		return implode( "\n", $out );
	}

	private static function post( $url, $headers, $body ) {
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => (int) apply_filters( 'logiprojet_ai_timeout', 150 ),
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'logiprojet_ai_http', 'Le service d’IA ne répond pas : ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = '';
			if ( is_array( $data ) ) {
				$msg = $data['error']['message'] ?? ( $data['error']['type'] ?? '' );
			}
			return new WP_Error( 'logiprojet_ai_http', 'Erreur du service d’IA (' . $code . ')' . ( $msg ? ' : ' . wp_strip_all_tags( $msg ) : '' ) );
		}
		return is_array( $data ) ? $data : new WP_Error( 'logiprojet_ai_http', 'Réponse vide du service d’IA.' );
	}

	private static function call_gemini( $key, $model, $system, $user ) {
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
		$data = self::post(
			$url,
			array( 'x-goog-api-key' => $key ),
			array(
				'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
				'contents'          => array(
					array(
						'role'  => 'user',
						'parts' => array( array( 'text' => $user ) ),
					),
				),
				'generationConfig'  => array(
					'responseMimeType' => 'application/json',
					'temperature'      => 0.2,
				),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= $part['text'] ?? '';
		}
		return '' !== $text ? $text : new WP_Error( 'logiprojet_ai_empty', 'Gemini n’a renvoyé aucun texte.' );
	}

	private static function call_openai( $key, $model, $system, $user ) {
		$data = self::post(
			'https://api.openai.com/v1/chat/completions',
			array( 'Authorization' => 'Bearer ' . $key ),
			array(
				'model'           => $model,
				'messages'        => array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
				'response_format' => array( 'type' => 'json_object' ),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = (string) ( $data['choices'][0]['message']['content'] ?? '' );
		return '' !== $text ? $text : new WP_Error( 'logiprojet_ai_empty', 'OpenAI n’a renvoyé aucun texte.' );
	}

	private static function call_anthropic( $key, $model, $system, $user ) {
		$data = self::post(
			'https://api.anthropic.com/v1/messages',
			array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			array(
				'model'      => $model,
				'max_tokens' => 4000,
				'system'     => $system,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= $block['text'];
			}
		}
		return '' !== $text ? $text : new WP_Error( 'logiprojet_ai_empty', 'Claude n’a renvoyé aucun texte.' );
	}

	public static function parse_json( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
		$data = json_decode( $text, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
		}
		return is_array( $data ) ? $data : null;
	}

	/** Ne garde que les critères de la grille, avec des notes 0, 1 ou 2. */
	public static function normalize( $json, $grille ) {
		$by_id = array();
		foreach ( (array) ( $json['criteres'] ?? array() ) as $c ) {
			if ( isset( $c['id'] ) ) {
				$by_id[ (string) $c['id'] ] = $c;
			}
		}
		$criteres = array();
		$missing  = array();
		foreach ( $grille as $g ) {
			$id = $g['id'];
			if ( isset( $by_id[ $id ] ) && is_numeric( $by_id[ $id ]['score'] ?? null ) ) {
				$criteres[ $id ] = array(
					'score'         => max( 0, min( 2, (int) round( (float) $by_id[ $id ]['score'] ) ) ),
					'justification' => sanitize_textarea_field( (string) ( $by_id[ $id ]['justification'] ?? '' ) ),
				);
			} else {
				$missing[]       = $id;
				$criteres[ $id ] = array(
					'score'         => 0,
					'justification' => 'L’IA n’a pas évalué ce critère : à noter vous-même.',
				);
			}
		}
		$ids      = wp_list_pluck( $grille, 'id' );
		$a_revoir = array_values( array_unique( array_merge( array_intersect( array_map( 'strval', (array) ( $json['a_revoir'] ?? array() ) ), $ids ), $missing ) ) );
		return array(
			'criteres'       => $criteres,
			'points_forts'   => array_slice( array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $json['points_forts'] ?? array() ) ) ), 0, 3 ),
			'piste'          => sanitize_textarea_field( (string) ( $json['piste'] ?? '' ) ),
			'resume'         => sanitize_textarea_field( (string) ( $json['resume'] ?? '' ) ),
			'a_revoir'       => $a_revoir,
			'copie_suspecte' => ! empty( $json['copie_suspecte'] ),
			'at'             => gmdate( 'c' ),
		);
	}
}
