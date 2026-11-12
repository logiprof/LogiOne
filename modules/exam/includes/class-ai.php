<?php
/**
 * Pré-correction IA : réglages personnels par enseignant et appels aux différents fournisseurs.
 *
 * Chaque enseignant enregistre sa propre clé API (chiffrée en base) : les frais d'utilisation
 * sont donc facturés directement sur son compte chez le fournisseur choisi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogiEval_Secure_AI {
	const USER_META = 'logieval_secure_ai_settings';

	public static function providers(): array {
		return array(
			'openai'     => array(
				'label'         => __( 'OpenAI (ChatGPT)', 'logieval-secure' ),
				'default_model' => 'gpt-5.4-mini',
				'key_url'       => 'https://platform.openai.com/api-keys',
				'models_url'    => 'https://platform.openai.com/docs/models',
			),
			'anthropic'  => array(
				'label'         => __( 'Anthropic (Claude)', 'logieval-secure' ),
				'default_model' => 'claude-haiku-4-5',
				'key_url'       => 'https://console.anthropic.com/settings/keys',
				'models_url'    => 'https://docs.claude.com/en/docs/about-claude/models',
			),
			'mistral'    => array(
				'label'         => __( 'Mistral AI', 'logieval-secure' ),
				'default_model' => 'mistral-small-latest',
				'key_url'       => 'https://console.mistral.ai/api-keys',
				'models_url'    => 'https://docs.mistral.ai/getting-started/models/',
			),
			'gemini'     => array(
				'label'         => __( 'Google Gemini', 'logieval-secure' ),
				'default_model' => 'gemini-2.5-flash',
				'key_url'       => 'https://aistudio.google.com/apikey',
				'models_url'    => 'https://ai.google.dev/gemini-api/docs/models',
			),
			'compatible' => array(
				'label'         => __( 'Compatible OpenAI (URL personnalisée)', 'logieval-secure' ),
				'default_model' => '',
				'key_url'       => '',
				'models_url'    => '',
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Réglages personnels
	 * ------------------------------------------------------------------- */

	/**
	 * Réglages de l'enseignant. LogiOne 1.27.0 : plusieurs fournisseurs peuvent être connectés en même temps
	 * (« profiles », une clé et un modèle par fournisseur). « provider » = fournisseur par défaut pour la correction,
	 * « gen_provider » = fournisseur par défaut pour créer des questions. Les champs model / base_url / api_key
	 * renvoyés ici sont ceux du fournisseur par défaut (compatibilité avec les versions précédentes).
	 */
	public static function get_user_settings( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::USER_META, true );
		$raw = is_array( $raw ) ? $raw : array();
		$settings = wp_parse_args(
			$raw,
			array(
				'enabled'      => 0,
				'provider'     => 'openai',
				'gen_provider' => '',
				'model'        => '',
				'base_url'     => '',
				'api_key'      => '',
				'profiles'     => array(),
			)
		);
		if ( ! array_key_exists( $settings['provider'], self::providers() ) ) {
			$settings['provider'] = 'openai';
		}
		$profiles = array();
		foreach ( (array) $settings['profiles'] as $key => $profile ) {
			if ( array_key_exists( $key, self::providers() ) && is_array( $profile ) ) {
				$profiles[ $key ] = wp_parse_args( $profile, array( 'model' => '', 'base_url' => '', 'api_key' => '' ) );
			}
		}
		// Anciennes versions : une seule clé, celle du fournisseur choisi.
		if ( ! isset( $profiles[ $settings['provider'] ] ) && ( '' !== (string) $settings['api_key'] || '' !== (string) $settings['model'] || '' !== (string) $settings['base_url'] ) ) {
			$profiles[ $settings['provider'] ] = array( 'model' => (string) $settings['model'], 'base_url' => (string) $settings['base_url'], 'api_key' => (string) $settings['api_key'] );
		}
		$settings['profiles'] = $profiles;
		$default              = $profiles[ $settings['provider'] ] ?? array( 'model' => '', 'base_url' => '', 'api_key' => '' );
		$settings['model']    = (string) $default['model'];
		$settings['base_url'] = (string) $default['base_url'];
		$settings['api_key']  = (string) $default['api_key'];
		if ( ! array_key_exists( (string) $settings['gen_provider'], self::providers() ) ) {
			$settings['gen_provider'] = '';
		}
		return $settings;
	}

	/**
	 * Enregistre les réglages. Format actuel : profiles[fournisseur][model|base_url|api_key|clear_api_key],
	 * provider (défaut correction), gen_provider (défaut création). Ancien format accepté : provider + model + api_key.
	 */
	public static function save_user_settings( int $user_id, array $input ): void {
		$current  = self::get_user_settings( $user_id );
		$provider = sanitize_key( $input['provider'] ?? $current['provider'] );
		if ( ! array_key_exists( $provider, self::providers() ) ) {
			$provider = 'openai';
		}
		$profiles = $current['profiles'];
		$incoming = isset( $input['profiles'] ) && is_array( $input['profiles'] ) ? $input['profiles'] : array();
		if ( ! $incoming && ( isset( $input['api_key'] ) || isset( $input['model'] ) || isset( $input['base_url'] ) || isset( $input['clear_api_key'] ) ) ) {
			$incoming = array( $provider => array_intersect_key( $input, array_flip( array( 'model', 'base_url', 'api_key', 'clear_api_key' ) ) ) );
		}
		foreach ( $incoming as $key => $row ) {
			$key = sanitize_key( (string) $key );
			if ( ! array_key_exists( $key, self::providers() ) || ! is_array( $row ) ) {
				continue;
			}
			$profile = $profiles[ $key ] ?? array( 'model' => '', 'base_url' => '', 'api_key' => '' );
			if ( array_key_exists( 'model', $row ) ) {
				$profile['model'] = sanitize_text_field( (string) $row['model'] );
			}
			if ( array_key_exists( 'base_url', $row ) ) {
				$profile['base_url'] = esc_url_raw( trim( (string) $row['base_url'] ) );
			}
			$new_key = trim( (string) ( $row['api_key'] ?? '' ) );
			if ( ! empty( $row['clear_api_key'] ) ) {
				$profile['api_key'] = '';
			} elseif ( '' !== $new_key ) {
				$profile['api_key'] = self::encrypt( $new_key );
			}
			$profiles[ $key ] = $profile;
		}
		$gen = sanitize_key( (string) ( $input['gen_provider'] ?? $current['gen_provider'] ) );
		update_user_meta(
			$user_id,
			self::USER_META,
			array(
				'enabled'      => ! empty( $input['enabled'] ) ? 1 : 0,
				'provider'     => $provider,
				'gen_provider' => array_key_exists( $gen, self::providers() ) ? $gen : '',
				'profiles'     => $profiles,
			)
		);
	}

	/** Fournisseur connecté = clé enregistrée (et adresse pour un service compatible). */
	public static function provider_is_configured( int $user_id, string $provider ): bool {
		$settings = self::get_user_settings( $user_id );
		$profile  = $settings['profiles'][ $provider ] ?? null;
		if ( ! $profile || '' === (string) $profile['api_key'] ) {
			return false;
		}
		return 'compatible' !== $provider || '' !== trim( (string) $profile['base_url'] );
	}

	/** @return string[] fournisseurs connectés, le fournisseur par défaut en premier. */
	public static function configured_providers( int $user_id ): array {
		$settings = self::get_user_settings( $user_id );
		$list     = array();
		foreach ( array_merge( array( $settings['provider'] ), array_keys( self::providers() ) ) as $key ) {
			if ( ! in_array( $key, $list, true ) && self::provider_is_configured( $user_id, $key ) ) {
				$list[] = $key;
			}
		}
		return $list;
	}

	/** Fournisseur à utiliser : celui demandé s'il est connecté, sinon le défaut de l'usage (correction / création). */
	public static function resolve_provider( int $user_id, string $requested = '', string $purpose = 'grading' ): string {
		$requested = sanitize_key( $requested );
		if ( '' !== $requested && self::provider_is_configured( $user_id, $requested ) ) {
			return $requested;
		}
		$settings = self::get_user_settings( $user_id );
		if ( 'generation' === $purpose && '' !== $settings['gen_provider'] && self::provider_is_configured( $user_id, $settings['gen_provider'] ) ) {
			return $settings['gen_provider'];
		}
		if ( self::provider_is_configured( $user_id, $settings['provider'] ) ) {
			return $settings['provider'];
		}
		$list = self::configured_providers( $user_id );
		return $list ? $list[0] : $settings['provider'];
	}

	public static function get_api_key( int $user_id, string $provider = '' ): string {
		$settings = self::get_user_settings( $user_id );
		$provider = '' !== $provider ? $provider : $settings['provider'];
		$key      = (string) ( $settings['profiles'][ $provider ]['api_key'] ?? '' );
		return '' === $key ? '' : self::decrypt( $key );
	}

	/** Au moins un fournisseur a une clé enregistrée. */
	public static function has_api_key( int $user_id ): bool {
		foreach ( array_keys( self::providers() ) as $key ) {
			if ( '' !== self::get_api_key( $user_id, $key ) ) {
				return true;
			}
		}
		return false;
	}

	public static function model_for( int $user_id, string $provider = '' ): string {
		$settings = self::get_user_settings( $user_id );
		$provider = '' !== $provider ? $provider : $settings['provider'];
		$model    = trim( (string) ( $settings['profiles'][ $provider ]['model'] ?? '' ) );
		if ( '' === $model ) {
			$model = (string) ( self::providers()[ $provider ]['default_model'] ?? '' );
		}
		return $model;
	}

	public static function model_label( int $user_id, string $provider = '' ): string {
		$settings = self::get_user_settings( $user_id );
		$provider = '' !== $provider ? $provider : $settings['provider'];
		$label    = (string) ( self::providers()[ $provider ]['label'] ?? $provider );
		return $label . ' - ' . self::model_for( $user_id, $provider );
	}

	/** L'enseignant a activé l'IA et au moins un fournisseur est complet. */
	public static function user_is_ready( int $user_id ): bool {
		$settings = self::get_user_settings( $user_id );
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		foreach ( self::configured_providers( $user_id ) as $key ) {
			if ( '' !== self::model_for( $user_id, $key ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Consommation (pour comparer les coûts des fournisseurs)
	 * ------------------------------------------------------------------- */

	const USAGE_META = 'logieval_secure_ai_usage';

	private static function record_usage( int $user_id, string $provider, string $model, array $body ): void {
		$in  = (int) ( $body['usage']['input_tokens'] ?? $body['usage']['prompt_tokens'] ?? $body['usageMetadata']['promptTokenCount'] ?? 0 );
		$out = (int) ( $body['usage']['output_tokens'] ?? $body['usage']['completion_tokens'] ?? $body['usageMetadata']['candidatesTokenCount'] ?? 0 );
		$all = get_user_meta( $user_id, self::USAGE_META, true );
		$all = is_array( $all ) ? $all : array();
		$month = current_time( 'Y-m' );
		$key   = $provider . '|' . $model;
		$row   = $all[ $month ][ $key ] ?? array( 'calls' => 0, 'in' => 0, 'out' => 0 );
		$row['calls']++;
		$row['in']  += $in;
		$row['out'] += $out;
		$all[ $month ][ $key ] = $row;
		ksort( $all );
		update_user_meta( $user_id, self::USAGE_META, array_slice( $all, -12, null, true ) );
	}

	/** @return array<string, array{provider:string,model:string,calls:int,in:int,out:int}> consommation d'un mois */
	public static function usage_for_month( int $user_id, string $month = '' ): array {
		$all   = get_user_meta( $user_id, self::USAGE_META, true );
		$month = '' !== $month ? $month : current_time( 'Y-m' );
		$out   = array();
		foreach ( (array) ( is_array( $all ) ? ( $all[ $month ] ?? array() ) : array() ) as $key => $row ) {
			list( $provider, $model ) = array_pad( explode( '|', (string) $key, 2 ), 2, '' );
			$out[ $key ] = array( 'provider' => $provider, 'model' => $model, 'calls' => (int) $row['calls'], 'in' => (int) $row['in'], 'out' => (int) $row['out'] );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Appel au fournisseur
	 * ------------------------------------------------------------------- */

	/**
	 * Envoie le prompt au fournisseur de l'enseignant et renvoie le texte de la réponse.
	 *
	 * @return array{text:string,model:string}|WP_Error
	 */
	public static function complete( string $prompt, int $user_id, array $options = array() ) {
		// Longueur maximale de la réponse et délai d'attente (la génération de questions demande plus que la pré-correction).
		$max_tokens = max( 200, (int) ( $options['max_tokens'] ?? 1000 ) );
		$timeout    = max( 10, (int) ( $options['timeout'] ?? 45 ) );
		$provider = self::resolve_provider( $user_id, (string) ( $options['provider'] ?? '' ), (string) ( $options['purpose'] ?? 'grading' ) );
		$settings = self::get_user_settings( $user_id );
		$profile  = $settings['profiles'][ $provider ] ?? array( 'model' => '', 'base_url' => '', 'api_key' => '' );
		$settings['provider'] = $provider;
		$settings['base_url'] = (string) $profile['base_url'];
		$api_key  = self::get_api_key( $user_id, $provider );
		$model    = self::model_for( $user_id, $provider );
		if ( '' === $api_key ) {
			return new WP_Error( 'logieval_ai_missing_key', __( 'Aucune clé API n’est enregistrée dans vos réglages de pré-correction.', 'logieval-secure' ) );
		}
		if ( '' === $model ) {
			return new WP_Error( 'logieval_ai_missing_model', __( 'Indiquez le modèle à utiliser dans vos réglages de pré-correction.', 'logieval-secure' ) );
		}

		switch ( $settings['provider'] ) {
			case 'anthropic':
				$request = array(
					'url'     => 'https://api.anthropic.com/v1/messages',
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'    => array(
						'model'      => $model,
						'max_tokens' => $max_tokens,
						'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
					),
				);
				break;

			case 'gemini':
				$request = array(
					'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
					'headers' => array(
						'x-goog-api-key' => $api_key,
						'Content-Type'   => 'application/json',
					),
					'body'    => array(
						'contents'         => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $prompt ) ) ) ),
						'generationConfig' => array(
							'responseMimeType' => 'application/json',
							// Gemini 2.5 et plus « réfléchissent » aussi sur ce budget : on leur laisse de la marge.
							'maxOutputTokens'  => $max_tokens + ( preg_match( '/gemini-(2\.5|[3-9])/i', $model ) ? 4000 : 0 ),
							'temperature'      => 0.2,
						),
					),
				);
				break;

			case 'mistral':
			case 'compatible':
				$base = 'mistral' === $settings['provider'] ? 'https://api.mistral.ai/v1' : untrailingslashit( (string) $settings['base_url'] );
				if ( '' === $base ) {
					return new WP_Error( 'logieval_ai_missing_url', __( 'Indiquez l’adresse (URL) de l’API compatible OpenAI dans vos réglages.', 'logieval-secure' ) );
				}
				$url  = preg_match( '#/chat/completions$#', $base ) ? $base : $base . '/chat/completions';
				$body = array(
					'model'       => $model,
					'messages'    => array( array( 'role' => 'user', 'content' => $prompt ) ),
					'max_tokens'  => $max_tokens,
					'temperature' => 0.2,
				);
				if ( 'mistral' === $settings['provider'] ) {
					$body['response_format'] = array( 'type' => 'json_object' );
				}
				$request = array(
					'url'     => $url,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
				);
				break;

			case 'openai':
			default:
				$body = array(
					'model'             => $model,
					'input'             => $prompt,
					'max_output_tokens' => $max_tokens,
				);
				// Modèles « qui réfléchissent » (gpt-5…, o3, o4…) : la réflexion est comptée dans la longueur maximale.
				// Sans réglage, elle pouvait tout consommer et la réponse arrivait coupée (« réponse illisible »).
				if ( self::is_reasoning_model( $model ) ) {
					$body['reasoning']         = array( 'effort' => 'low' );
					$body['max_output_tokens'] = $max_tokens + 4000;
				}
				if ( ! empty( $options['json'] ) ) {
					$body['text'] = array( 'format' => array( 'type' => 'json_object' ) );
				}
				$request = array(
					'url'     => 'https://api.openai.com/v1/responses',
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
				);
				break;
		}

		$send = static function ( array $request ) use ( $timeout ) {
			return wp_remote_post(
				$request['url'],
				array(
					'timeout' => $timeout,
					'headers' => $request['headers'],
					'body'    => wp_json_encode( $request['body'] ),
				)
			);
		};
		$response = $send( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();
		// Un modèle qui n'accepte pas ces réglages (réflexion, format JSON) : on renvoie la demande sans eux.
		if ( 400 === $status && ( isset( $request['body']['reasoning'] ) || isset( $request['body']['text'] ) ) ) {
			unset( $request['body']['reasoning'], $request['body']['text'] );
			$response = $send( $request );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$body   = is_array( $body ) ? $body : array();
		}
		if ( $status < 200 || $status >= 300 ) {
			$label   = (string) ( self::providers()[ $settings['provider'] ]['label'] ?? '' );
			$message = self::error_message( $body );
			return new WP_Error(
				'logieval_ai_http_error',
				sprintf( __( '%1$s a refusé la demande (code %2$d) : %3$s', 'logieval-secure' ), $label, $status, $message ?: __( 'erreur inconnue', 'logieval-secure' ) )
			);
		}

		$text      = self::extract_text( $settings['provider'], $body );
		$truncated = self::was_truncated( $settings['provider'], $body );
		self::record_usage( $user_id, $provider, $model, $body );
		if ( '' === trim( $text ) ) {
			if ( $truncated ) {
				return new WP_Error( 'logieval_ai_truncated', __( 'L’IA a manqué de place pour répondre (réponse coupée).', 'logieval-secure' ) );
			}
			return new WP_Error( 'logieval_ai_empty', __( 'Le fournisseur IA a renvoyé une réponse vide.', 'logieval-secure' ) );
		}
		return array(
			'truncated' => $truncated,
			'text'     => $text,
			'model'    => self::model_label( $user_id, $provider ),
			'provider' => $provider,
		);
	}

	private static function is_reasoning_model( string $model ): bool {
		$m = strtolower( $model );
		if ( false !== strpos( $m, '-chat' ) ) {
			return false;
		}
		return (bool) preg_match( '/^(gpt-5|o[1-9])/', $m );
	}

	/** La réponse a-t-elle été coupée faute de place ? */
	private static function was_truncated( string $provider, array $body ): bool {
		switch ( $provider ) {
			case 'anthropic':
				return 'max_tokens' === ( $body['stop_reason'] ?? '' );
			case 'gemini':
				return 'MAX_TOKENS' === ( $body['candidates'][0]['finishReason'] ?? '' );
			case 'mistral':
			case 'compatible':
				return 'length' === ( $body['choices'][0]['finish_reason'] ?? '' );
			case 'openai':
			default:
				return 'incomplete' === ( $body['status'] ?? '' ) || 'max_output_tokens' === ( $body['incomplete_details']['reason'] ?? '' );
		}
	}

	private static function extract_text( string $provider, array $body ): string {
		switch ( $provider ) {
			case 'anthropic':
				$parts = array();
				foreach ( (array) ( $body['content'] ?? array() ) as $block ) {
					if ( 'text' === ( $block['type'] ?? '' ) && isset( $block['text'] ) ) {
						$parts[] = (string) $block['text'];
					}
				}
				return trim( implode( "\n", $parts ) );

			case 'gemini':
				$parts = array();
				foreach ( (array) ( $body['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
					if ( isset( $part['text'] ) ) {
						$parts[] = (string) $part['text'];
					}
				}
				return trim( implode( "\n", $parts ) );

			case 'mistral':
			case 'compatible':
				$content = $body['choices'][0]['message']['content'] ?? '';
				if ( is_array( $content ) ) {
					$content = implode( "\n", array_map( static fn( $c ) => is_array( $c ) ? (string) ( $c['text'] ?? '' ) : (string) $c, $content ) );
				}
				return trim( (string) $content );

			case 'openai':
			default:
				if ( isset( $body['output_text'] ) && is_scalar( $body['output_text'] ) ) {
					return (string) $body['output_text'];
				}
				$parts = array();
				foreach ( (array) ( $body['output'] ?? array() ) as $item ) {
					foreach ( (array) ( $item['content'] ?? array() ) as $content ) {
						if ( isset( $content['text'] ) && is_scalar( $content['text'] ) ) {
							$parts[] = (string) $content['text'];
						}
					}
				}
				return trim( implode( "\n", $parts ) );
		}
	}

	private static function error_message( array $body ): string {
		if ( isset( $body['error']['message'] ) ) {
			return (string) $body['error']['message'];
		}
		if ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
			return $body['error'];
		}
		if ( isset( $body['message'] ) && is_string( $body['message'] ) ) {
			return $body['message'];
		}
		if ( isset( $body['detail'] ) ) {
			return is_string( $body['detail'] ) ? $body['detail'] : (string) wp_json_encode( $body['detail'] );
		}
		if ( isset( $body[0]['error']['message'] ) ) {
			return (string) $body[0]['error']['message'];
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Chiffrement de la clé API (les clés ne sont jamais stockées en clair)
	 * ------------------------------------------------------------------- */

	private static function crypto_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|logiexam-ai', true );
	}

	public static function encrypt( string $plain ): string {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 16 );
			$cipher = openssl_encrypt( $plain, 'aes-256-cbc', self::crypto_key(), OPENSSL_RAW_DATA, $iv );
			if ( false !== $cipher ) {
				return 'enc:' . base64_encode( $iv . $cipher );
			}
		}
		return 'b64:' . base64_encode( $plain );
	}

	public static function decrypt( string $stored ): string {
		if ( 0 === strpos( $stored, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, 4 ), true );
			if ( false === $raw || strlen( $raw ) <= 16 ) {
				return '';
			}
			$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::crypto_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
			return false === $plain ? '' : $plain;
		}
		if ( 0 === strpos( $stored, 'b64:' ) ) {
			$plain = base64_decode( substr( $stored, 4 ), true );
			return false === $plain ? '' : $plain;
		}
		return '';
	}
}
