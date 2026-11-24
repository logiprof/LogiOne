<?php
/**
 * Formats de fichier que l'enseignant peut accepter pour un travail.
 * Chaque format est vérifié sur son contenu, pas seulement sur son extension.
 * L'IA ne sait corriger que les PowerPoint (.pptx).
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Formats {

	/** clé => [libellé, extensions, types MIME acceptés]. */
	public static function catalogue() {
		$zip = array( 'application/zip', 'application/x-zip-compressed', 'application/octet-stream' );
		return array(
			'pptx' => array( 'PowerPoint (.pptx)', array( 'pptx' ), array_merge( array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ), $zip ) ),
			'pdf'  => array( 'PDF (.pdf)', array( 'pdf' ), array( 'application/pdf', 'application/octet-stream' ) ),
			'docx' => array( 'Word (.docx)', array( 'docx' ), array_merge( array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ), $zip ) ),
			'xlsx' => array( 'Excel (.xlsx)', array( 'xlsx' ), array_merge( array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ), $zip ) ),
			'odp'  => array( 'LibreOffice Impress (.odp)', array( 'odp' ), array_merge( array( 'application/vnd.oasis.opendocument.presentation' ), $zip ) ),
			'key'  => array( 'Keynote (.key)', array( 'key' ), array_merge( array( 'application/x-iwork-keynote-sffkey', 'application/vnd.apple.keynote' ), $zip ) ),
			'img'  => array( 'Image (.jpg, .png)', array( 'jpg', 'jpeg', 'png' ), array( 'image/jpeg', 'image/png' ) ),
			'mp4'  => array( 'Vidéo (.mp4)', array( 'mp4' ), array( 'video/mp4', 'application/octet-stream' ) ),
			'zip'  => array( 'Archive (.zip)', array( 'zip' ), $zip ),
		);
	}

	public static function clean( $keys ) {
		$cat = self::catalogue();
		$out = array();
		foreach ( (array) $keys as $k ) {
			$k = sanitize_key( (string) $k );
			if ( isset( $cat[ $k ] ) && ! in_array( $k, $out, true ) ) {
				$out[] = $k;
			}
		}
		return $out ? $out : array( 'pptx' );
	}

	public static function extensions( $keys ) {
		$cat = self::catalogue();
		$ext = array();
		foreach ( self::clean( $keys ) as $k ) {
			$ext = array_merge( $ext, $cat[ $k ][1] );
		}
		return $ext;
	}

	public static function all_extensions() {
		return self::extensions( array_keys( self::catalogue() ) );
	}

	public static function label( $keys ) {
		$cat = self::catalogue();
		return implode( ', ', array_map( function ( $k ) use ( $cat ) { return $cat[ $k ][0]; }, self::clean( $keys ) ) );
	}

	public static function key_for_ext( $ext ) {
		foreach ( self::catalogue() as $k => $f ) {
			if ( in_array( strtolower( $ext ), $f[1], true ) ) {
				return $k;
			}
		}
		return '';
	}

	public static function mime_for_ext( $ext ) {
		$map = array(
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'odp'  => 'application/vnd.oasis.opendocument.presentation',
			'key'  => 'application/octet-stream',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'mp4'  => 'video/mp4',
			'zip'  => 'application/zip',
		);
		return $map[ strtolower( $ext ) ] ?? 'application/octet-stream';
	}

	/** Vérifie que le contenu correspond bien au format annoncé. */
	public static function check_content( $path, $ext ) {
		$ext  = strtolower( $ext );
		$head = (string) file_get_contents( $path, false, null, 0, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		switch ( $ext ) {
			case 'pptx':
				return LogiProjet_Pptx::is_valid( $path );
			case 'pdf':
				return 0 === strpos( $head, '%PDF' );
			case 'png':
				return 0 === strpos( $head, "\x89PNG" );
			case 'jpg':
			case 'jpeg':
				return 0 === strpos( $head, "\xFF\xD8\xFF" );
			case 'mp4':
				return 'ftyp' === substr( $head, 4, 4 );
			case 'docx':
			case 'xlsx':
			case 'odp':
			case 'key':
			case 'zip':
				if ( 0 !== strpos( $head, 'PK' ) ) {
					return false;
				}
				if ( ! class_exists( 'ZipArchive' ) ) {
					return true;
				}
				$z = new ZipArchive();
				if ( true !== $z->open( $path ) ) {
					return false;
				}
				$ok = true;
				if ( 'docx' === $ext ) {
					$ok = false !== $z->locateName( 'word/document.xml' );
				} elseif ( 'xlsx' === $ext ) {
					$ok = false !== $z->locateName( 'xl/workbook.xml' );
				} elseif ( 'odp' === $ext ) {
					$ok = false !== $z->locateName( 'content.xml' );
				}
				$z->close();
				return $ok;
		}
		return false;
	}
}
