<?php
/**
 * Lecture d'un PowerPoint (.pptx) sans bibliothèque externe :
 * texte, titres, images, tableaux, graphiques et notes de chaque slide.
 *
 * @package LogiProjet
 */

defined( 'ABSPATH' ) || exit;

class LogiProjet_Pptx {

	const MAX_XML    = 4194304; // 4 Mo par fichier XML.
	const MAX_SLIDES = 150;

	/** Vérifie qu'un fichier est bien un PowerPoint .pptx lisible. */
	public static function is_valid( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return true; // Impossible de vérifier : l'analyse échouera proprement plus tard.
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return false;
		}
		$ok = false !== $zip->locateName( 'ppt/presentation.xml' ) && false !== $zip->locateName( '[Content_Types].xml' );
		$zip->close();
		return $ok;
	}

	public static function extract( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'logiprojet_zip', 'L’extension PHP « zip » est nécessaire pour lire les PowerPoint. Demandez à votre hébergeur de l’activer.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'logiprojet_pptx', 'Le fichier ne peut pas être ouvert comme PowerPoint (.pptx).' );
		}
		$slides_paths = self::slide_order( $zip );
		$slides       = array();
		$n            = 0;
		foreach ( $slides_paths as $sp ) {
			if ( ++$n > self::MAX_SLIDES ) {
				break;
			}
			$xml = self::read( $zip, $sp );
			if ( null === $xml ) {
				continue;
			}
			$slide          = self::parse_slide( $xml );
			$slide['n']     = $n;
			$slide['notes'] = self::notes_for( $zip, $sp );
			$slides[]       = $slide;
		}
		$zip->close();

		$words = 0;
		$imgs  = 0;
		foreach ( $slides as $s ) {
			$words += $s['words'];
			$imgs  += $s['images'] + $s['charts'] + $s['diagrams'];
		}
		return array(
			'slides'       => $slides,
			'slide_count'  => count( $slides ),
			'words'        => $words,
			'visuals'      => $imgs,
			'notes_count'  => count(
				array_filter(
					$slides,
					function ( $s ) {
						return '' !== $s['notes'];
					}
				)
			),
			'analysed_at'  => gmdate( 'c' ),
		);
	}

	private static function read( $zip, $name ) {
		$stat = $zip->statName( $name );
		if ( ! $stat || $stat['size'] > self::MAX_XML ) {
			return null;
		}
		$data = $zip->getFromName( $name );
		return false === $data ? null : $data;
	}

	private static function dom( $xml ) {
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $ok ? $doc : null;
	}

	private static function xpath( $doc ) {
		$xp = new DOMXPath( $doc );
		$xp->registerNamespace( 'p', 'http://schemas.openxmlformats.org/presentationml/2006/main' );
		$xp->registerNamespace( 'a', 'http://schemas.openxmlformats.org/drawingml/2006/main' );
		$xp->registerNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
		$xp->registerNamespace( 'rel', 'http://schemas.openxmlformats.org/package/2006/relationships' );
		return $xp;
	}

	/** Ordre réel des slides (celui de presentation.xml), slides masquées comprises. */
	private static function slide_order( $zip ) {
		$pres = self::read( $zip, 'ppt/presentation.xml' );
		$rels = self::read( $zip, 'ppt/_rels/presentation.xml.rels' );
		$out  = array();
		if ( $pres && $rels ) {
			$pd = self::dom( $pres );
			$rd = self::dom( $rels );
			if ( $pd && $rd ) {
				$map = array();
				foreach ( self::xpath( $rd )->query( '//rel:Relationship' ) as $r ) {
					$map[ $r->getAttribute( 'Id' ) ] = $r->getAttribute( 'Target' );
				}
				foreach ( self::xpath( $pd )->query( '//p:sldIdLst/p:sldId' ) as $s ) {
					$rid = $s->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id' );
					if ( isset( $map[ $rid ] ) ) {
						$out[] = self::resolve( 'ppt/', $map[ $rid ] );
					}
				}
			}
		}
		if ( ! $out ) {
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $zip->getNameIndex( $i );
				if ( preg_match( '#^ppt/slides/slide(\d+)\.xml$#', $name, $m ) ) {
					$out[ (int) $m[1] ] = $name;
				}
			}
			ksort( $out );
			$out = array_values( $out );
		}
		return $out;
	}

	private static function resolve( $base, $target ) {
		if ( 0 === strpos( $target, '/' ) ) {
			return ltrim( $target, '/' );
		}
		$parts = explode( '/', rtrim( $base, '/' ) . '/' . $target );
		$stack = array();
		foreach ( $parts as $p ) {
			if ( '..' === $p ) {
				array_pop( $stack );
			} elseif ( '.' !== $p && '' !== $p ) {
				$stack[] = $p;
			}
		}
		return implode( '/', $stack );
	}

	private static function paragraphs( $xp, $context ) {
		$lines = array();
		foreach ( $xp->query( './/a:p', $context ) as $p ) {
			$t = '';
			foreach ( $xp->query( './/a:t', $p ) as $node ) {
				$t .= $node->textContent;
			}
			$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
			if ( '' !== $t ) {
				$lines[] = $t;
			}
		}
		return $lines;
	}

	private static function parse_slide( $xml ) {
		$doc   = self::dom( $xml );
		$slide = array(
			'title'    => '',
			'lines'    => array(),
			'words'    => 0,
			'images'   => 0,
			'tables'   => 0,
			'charts'   => 0,
			'diagrams' => 0,
			'hidden'   => false,
		);
		if ( ! $doc ) {
			return $slide;
		}
		$xp              = self::xpath( $doc );
		$root            = $doc->documentElement;
		$slide['hidden'] = $root && '0' === $root->getAttribute( 'show' );

		foreach ( $xp->query( '//p:sp' ) as $sp ) {
			$ph    = $xp->query( './/p:nvPr/p:ph', $sp )->item( 0 );
			$type  = $ph ? $ph->getAttribute( 'type' ) : '';
			$lines = self::paragraphs( $xp, $sp );
			if ( in_array( $type, array( 'sldNum', 'dt', 'ftr' ), true ) ) {
				continue;
			}
			if ( in_array( $type, array( 'title', 'ctrTitle' ), true ) && '' === $slide['title'] ) {
				$slide['title'] = implode( ' ', $lines );
				continue;
			}
			$slide['lines'] = array_merge( $slide['lines'], $lines );
		}
		foreach ( $xp->query( '//a:tbl' ) as $tbl ) {
			++$slide['tables'];
			foreach ( $xp->query( './/a:tr', $tbl ) as $tr ) {
				$cells = array();
				foreach ( $xp->query( './a:tc', $tr ) as $tc ) {
					$cells[] = implode( ' ', self::paragraphs( $xp, $tc ) );
				}
				$slide['lines'][] = '| ' . implode( ' | ', $cells ) . ' |';
			}
		}
		$slide['images']   = $xp->query( '//p:pic' )->length;
		$slide['charts']   = $doc->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/drawingml/2006/chart', 'chart' )->length;
		$slide['diagrams'] = $doc->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/drawingml/2006/diagram', 'relIds' )->length;

		$all            = trim( $slide['title'] . ' ' . implode( ' ', $slide['lines'] ) );
		$slide['words'] = '' === $all ? 0 : count( preg_split( '/\s+/u', $all ) );
		return $slide;
	}

	private static function notes_for( $zip, $slide_path ) {
		$rels_path = dirname( $slide_path ) . '/_rels/' . basename( $slide_path ) . '.rels';
		$rels      = self::read( $zip, $rels_path );
		if ( ! $rels ) {
			return '';
		}
		$rd = self::dom( $rels );
		if ( ! $rd ) {
			return '';
		}
		foreach ( self::xpath( $rd )->query( '//rel:Relationship' ) as $r ) {
			if ( false !== strpos( $r->getAttribute( 'Type' ), '/notesSlide' ) ) {
				$xml = self::read( $zip, self::resolve( dirname( $slide_path ), $r->getAttribute( 'Target' ) ) );
				$doc = $xml ? self::dom( $xml ) : null;
				if ( ! $doc ) {
					return '';
				}
				$xp    = self::xpath( $doc );
				$lines = array();
				foreach ( $xp->query( '//p:sp' ) as $sp ) {
					$ph = $xp->query( './/p:nvPr/p:ph', $sp )->item( 0 );
					if ( $ph && in_array( $ph->getAttribute( 'type' ), array( 'sldNum', 'sldImg', 'hdr', 'ftr', 'dt' ), true ) ) {
						continue;
					}
					$lines = array_merge( $lines, self::paragraphs( $xp, $sp ) );
				}
				return trim( implode( "\n", $lines ) );
			}
		}
		return '';
	}
}
