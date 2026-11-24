<?php
/**
 * Réglages → LogiProjet : IA, clés API, rôles enseignants, taille maximale.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Settings {

	const OPT = 'logiprojet_settings';

	public static function defaults() {
		return array(
			'provider'        => 'gemini',
			'gemini_key'      => '',
			'gemini_model'    => 'gemini-3-flash-preview',
			'openai_key'      => '',
			'openai_model'    => 'gpt-5.1',
			'anthropic_key'   => '',
			'anthropic_model' => 'claude-sonnet-5-5',
			'teacher_roles'   => array( 'administrator', 'editor', 'teacher' ),
			'max_mb'          => 50,
		);
	}

	public static function all() {
		return wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/** Limite réelle : la plus petite entre le réglage et ce que permet le serveur. */
	public static function max_mb() {
		$server = (int) floor( wp_max_upload_size() / 1048576 );
		$mine   = max( 1, (int) self::get( 'max_mb' ) );
		return $server > 0 ? min( $mine, $server ) : $mine;
	}

	public static function menu() {
		add_options_page( 'LogiProjet', 'LogiProjet', 'manage_options', 'logiprojet', array( __CLASS__, 'page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=logiprojet' ) ) . '">Réglages</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			'logiprojet',
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $in ) {
		$old = self::all();
		$in  = is_array( $in ) ? $in : array();
		$out = $old;

		$p               = sanitize_key( $in['provider'] ?? '' );
		$out['provider'] = isset( LogiProjet_AI::PROVIDERS[ $p ] ) ? $p : 'gemini';
		foreach ( array_keys( LogiProjet_AI::PROVIDERS ) as $prov ) {
			$model                     = sanitize_text_field( (string) ( $in[ $prov . '_model' ] ?? '' ) );
			$out[ $prov . '_model' ]   = '' !== $model ? $model : self::defaults()[ $prov . '_model' ];
			$key                       = trim( (string) ( $in[ $prov . '_key' ] ?? '' ) );
			if ( ! empty( $in[ $prov . '_key_clear' ] ) ) {
				$out[ $prov . '_key' ] = '';
			} elseif ( '' !== $key ) {
				$out[ $prov . '_key' ] = sanitize_text_field( $key );
			}
		}
		$roles = array();
		foreach ( (array) ( $in['teacher_roles'] ?? array() ) as $r ) {
			$r = sanitize_key( $r );
			if ( get_role( $r ) ) {
				$roles[] = $r;
			}
		}
		$out['teacher_roles'] = array_values( array_unique( array_merge( array( 'administrator' ), $roles ) ) );
		$out['max_mb']        = max( 1, min( 500, absint( $in['max_mb'] ?? 50 ) ) );
		return $out;
	}

	private static function mask( $key ) {
		return '' === $key ? '' : str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::all();
		LogiProjet_App::enqueue( false );
		?>
		<div class="wrap lpj-admin">
			<h1><img src="<?php echo esc_url( LOGIPROJET_URL . 'assets/logiprojet-logo.svg' ); ?>" alt="LogiProjet by LogiProf" height="48"></h1>
			<p class="lpj-admin-lead">Activités notées ou formatives, seul ou en groupe : les apprentis déposent leur fichier, l’IA propose une note par critère (PowerPoint), vous validez. Placez le code court <code>[logiprojet]</code> dans une page (par exemple dans le portail LogiOne).</p>
			<p><a class="button button-primary" href="<?php echo esc_url( LogiProjet_Skill::url() ); ?>">Compétence « créer une activité » (.zip)</a> <a class="button" href="<?php echo esc_url( LogiProjet_Skill::url( 'logiprojet-correction' ) ); ?>">Compétence « corriger dans Claude » (.zip)</a>
			<span class="description">Dans Claude : Paramètres → Capacités → Compétences → Importer, puis demandez par exemple « Crée une activité LogiProjet sur le M109 ». Claude rend un fichier .json à importer ici et la consigne en Word. Pour corriger : page du travail → Corriger dans Claude.</span></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'logiprojet' ); ?>
				<h2>Intelligence artificielle</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="lpj-provider">IA utilisée pour corriger</label></th>
						<td>
							<select id="lpj-provider" name="<?php echo esc_attr( self::OPT ); ?>[provider]">
								<?php foreach ( LogiProjet_AI::PROVIDERS as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['provider'], $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Les PowerPoint des apprentis sont envoyés à ce service pour la correction. Les noms des apprentis sont remplacés par « [Apprenti 1] » et « [Apprenti 2] » avant l’envoi.</p>
						</td>
					</tr>
					<?php
					foreach ( LogiProjet_AI::PROVIDERS as $k => $label ) :
						$const = 'LOGIPROJET_' . strtoupper( $k ) . '_KEY';
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<p><label>Clé API<br>
								<?php if ( defined( $const ) && constant( $const ) ) : ?>
									<em>Définie dans wp-config.php (<code><?php echo esc_html( $const ); ?></code>).</em>
								<?php else : ?>
									<input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( self::OPT . '[' . $k . '_key]' ); ?>" placeholder="<?php echo esc_attr( $s[ $k . '_key' ] ? self::mask( $s[ $k . '_key' ] ) . ' (laisser vide pour garder)' : 'Coller la clé ici' ); ?>">
									<?php if ( $s[ $k . '_key' ] ) : ?>
										<br><label><input type="checkbox" name="<?php echo esc_attr( self::OPT . '[' . $k . '_key_clear]' ); ?>" value="1"> Supprimer la clé enregistrée</label>
									<?php endif; ?>
								<?php endif; ?>
								</label></p>
								<p><label>Modèle<br><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPT . '[' . $k . '_model]' ); ?>" value="<?php echo esc_attr( $s[ $k . '_model' ] ); ?>"></label></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description">Plus sûr : définissez la clé dans <code>wp-config.php</code>, par exemple <code>define( 'LOGIPROJET_GEMINI_KEY', '…' );</code>. Elle n’est alors jamais enregistrée dans la base de données.</p>

				<h2>Enseignants et dépôts</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Rôles enseignants</th>
						<td>
							<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
								<label style="display:inline-block;margin:0 16px 6px 0"><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[teacher_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['teacher_roles'], true ) ); ?> <?php disabled( 'administrator' === $role ); ?>> <?php echo esc_html( translate_user_role( $name ) ); ?></label>
							<?php endforeach; ?>
							<p class="description">Ces rôles créent des travaux et corrigent. Tous les autres utilisateurs connectés sont des apprentis. Un enseignant ne voit que ses travaux ; un administrateur les voit tous.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lpj-max">Taille maximale d’un PowerPoint</label></th>
						<td><input id="lpj-max" type="number" min="1" max="500" name="<?php echo esc_attr( self::OPT ); ?>[max_mb]" value="<?php echo esc_attr( $s['max_mb'] ); ?>"> Mo
							<p class="description">Votre serveur accepte au maximum <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?> par envoi.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>
		</div>
		<?php
	}
}
