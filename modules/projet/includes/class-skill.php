<?php
/**
 * Téléchargement des compétences Claude « logiprojet-activite » et « logiprojet-correction » (.zip),
 * réservé aux enseignants et aux administrateurs.
 * Le .zip est construit à la volée : il contient toujours le modèle de consigne
 * et le format d'import de cette version de LogiProjet.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Skill {

	const NAME  = 'logiprojet-activite';
	const NAMES = array( 'logiprojet-activite', 'logiprojet-correction' );

	public static function url( $name = self::NAME ) {
		return add_query_arg(
			array(
				'action'      => 'logiprojet_competence',
				'competence'  => in_array( $name, self::NAMES, true ) ? $name : self::NAME,
				'_wpnonce' => wp_create_nonce( 'logiprojet_competence' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function download() {
		check_admin_referer( 'logiprojet_competence' );
		if ( ! LogiProjet_Access::is_teacher() ) {
			wp_die( 'Réservé aux enseignants.', 403 );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'L’extension PHP « zip » est nécessaire pour préparer la compétence. Demandez à votre hébergeur de l’activer.' );
		}
		$name = isset( $_GET['competence'] ) ? sanitize_key( wp_unslash( $_GET['competence'] ) ) : self::NAME; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $name, self::NAMES, true ) ) {
			$name = self::NAME;
		}
		$src = LOGIPROJET_DIR . 'skill/' . $name;
		$tmp = wp_tempnam( 'logiprojet-skill' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_die( 'La compétence n’a pas pu être préparée.' );
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$rel  = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $src ) + 1 ) );
			$data = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( 'SKILL.md' === $rel ) {
				$data = strtr(
					$data,
					array(
						'{{SITE_URL}}'  => home_url( '/' ),
						'{{SITE_NAME}}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
						'{{VERSION}}'   => LOGIPROJET_VERSION,
					)
				);
			}
			$zip->addFromString( $name . '/' . $rel, $data );
		}
		// Modèle de consigne de cette version, pour que Claude reparte toujours du bon texte.
		if ( self::NAME === $name ) {
		$zip->addFromString(
			self::NAME . '/references/modele-consigne.json',
			wp_json_encode(
				array(
					'note'     => 'Modèle de LogiProjet ' . LOGIPROJET_VERSION . ' pour une présentation de 5 leçons en duo. Les clés et leur usage sont décrits dans format-logiprojet.md.',
					'note_sur_6'  => LogiProjet_Consigne::modele( 5, 2, array( 'pptx' ), true ),
					'non_notee'   => LogiProjet_Consigne::modele( 5, 2, array( 'pptx' ), false ),
					'titres_par_defaut' => LogiProjet_Consigne::default_titres( true ),
					'formats'  => array_map(
						function ( $f ) {
							return $f[0];
						},
						LogiProjet_Formats::catalogue()
					),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
		}
		$zip->close();
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		exit;
	}
}
