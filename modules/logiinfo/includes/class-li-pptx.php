<?php
/**
 * LogiInfo – lecture d'une présentation .pptx et contrôle automatique des exercices PowerPoint.
 * LI_Pptx_Model : diapositives, dispositions, textes, objets, transitions, animations, notes, masque, thème, propriétés.
 * LI_Pptx_Check : vérifie dans la copie de l'élève les points décrits pour chaque exercice (data/powerpoint-checks.json).
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'LI_CLI' ) ) { exit; }

class LI_Pptx_Model {
	public $ok = false;
	public $error = '';
	public $slides = array();
	public $sections = array();
	public $ratio = 0;
	public $theme = array();
	public $props = array();
	public $master_pics = 0;
	public $media_bytes = 0;
	public $comments = 0;
	/** Lisibilité : par diapositive, tailles et polices effectives du texte, nombre de lignes, images déformées. */
	public $typo = array( 'slides' => array(), 'fonts' => array() );
	private $z;
	private $dom_cache = array();

	public function __construct( $path ) {
		$this->z = new ZipArchive();
		if ( true !== $this->z->open( $path ) || false === $this->z->locateName( 'ppt/presentation.xml' ) ) { $this->error = 'présentation PowerPoint illisible'; return; }
		$this->read();
		try { $this->typo(); } catch ( \Throwable $e ) { $this->typo['error'] = $e->getMessage(); }
		$this->z->close();
		$this->ok = true;
	}

	private function get( $p ) { $s = $this->z->getFromName( $p ); return false === $s ? '' : $s; }
	private static function attr( $tag, $name ) { return preg_match( '/\b' . $name . '="([^"]*)"/', $tag, $m ) ? html_entity_decode( $m[1], ENT_QUOTES | ENT_XML1, 'UTF-8' ) : null; }
	private static function norm( $p ) {
		$o = array();
		foreach ( explode( '/', $p ) as $s ) { if ( '..' === $s ) { array_pop( $o ); } elseif ( '' !== $s && '.' !== $s ) { $o[] = $s; } }
		return implode( '/', $o );
	}
	private function rels( $part ) {
		$out = array();
		$rp = dirname( $part ) . '/_rels/' . basename( $part ) . '.rels';
		if ( preg_match_all( '/<Relationship\b[^>]*>/', $this->get( $rp ), $m ) ) {
			foreach ( $m[0] as $t ) {
				$target = (string) self::attr( $t, 'Target' );
				$ext = 'External' === self::attr( $t, 'TargetMode' );
				$out[ self::attr( $t, 'Id' ) ] = array( 'type' => basename( (string) self::attr( $t, 'Type' ) ), 'path' => $ext ? $target : ( '/' === substr( $target, 0, 1 ) ? ltrim( $target, '/' ) : self::norm( dirname( $part ) . '/' . $target ) ), 'ext' => $ext );
			}
		}
		return $out;
	}
	public static function texts_of( $xml ) {
		$out = array();
		if ( preg_match_all( '#<a:p\b[^>]*>(.*?)</a:p>#s', $xml, $m ) ) {
			foreach ( $m[1] as $p ) {
				preg_match_all( '#<a:t>(.*?)</a:t>#s', $p, $t );
				$s = html_entity_decode( implode( '', $t[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				if ( '' !== trim( $s ) ) { $out[] = $s; }
			}
		}
		return $out;
	}

	/* ---------- Lisibilité (tailles et polices effectives, lignes, images déformées) ---------- */
	private function dom( $part ) {
		$cache = &$this->dom_cache;
		if ( array_key_exists( $part, $cache ) ) { return $cache[ $part ]; }
		$x = $this->get( $part );
		if ( '' === $x ) { return $cache[ $part ] = null; }
		$d = new DOMDocument();
		if ( ! @$d->loadXML( $x, LIBXML_NONET ) ) { return $cache[ $part ] = null; }
		$xp = new DOMXPath( $d );
		$xp->registerNamespace( 'a', 'http://schemas.openxmlformats.org/drawingml/2006/main' );
		$xp->registerNamespace( 'p', 'http://schemas.openxmlformats.org/presentationml/2006/main' );
		$xp->registerNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
		return $cache[ $part ] = $xp;
	}
	private static function ph_of( $xp, $sp ) {
		$ph = $xp->query( './p:nvSpPr/p:nvPr/p:ph', $sp )->item( 0 );
		if ( ! $ph ) { return null; }
		return array( 'type' => $ph->getAttribute( 'type' ) ?: 'body', 'idx' => $ph->getAttribute( 'idx' ) );
	}
	/** Forme d'espace réservé correspondante dans une disposition ou un masque. */
	private static function find_ph( $xp, $ph ) {
		if ( ! $xp || ! $ph ) { return null; }
		$title = in_array( $ph['type'], array( 'title', 'ctrTitle' ), true );
		foreach ( $xp->query( '//p:sp' ) as $sp ) {
			$q = self::ph_of( $xp, $sp );
			if ( ! $q ) { continue; }
			if ( '' !== $ph['idx'] && $q['idx'] === $ph['idx'] && ! $title ) { return $sp; }
		}
		foreach ( $xp->query( '//p:sp' ) as $sp ) {
			$q = self::ph_of( $xp, $sp );
			if ( ! $q ) { continue; }
			$qt = in_array( $q['type'], array( 'title', 'ctrTitle' ), true );
			if ( $title ? $qt : ( ! $qt && ( $q['type'] === $ph['type'] || ( 'body' === $q['type'] && in_array( $ph['type'], array( 'obj', 'subTitle', 'body' ), true ) ) ) ) ) { return $sp; }
		}
		return null;
	}
	private static function lvl_attr( $xp, $ctx, $lvl, $what ) {
		if ( ! $ctx ) { return null; }
		$n = $xp->query( './/a:lstStyle/a:lvl' . ( $lvl + 1 ) . 'pPr/a:defRPr', $ctx )->item( 0 );
		if ( ! $n ) { return null; }
		if ( 'sz' === $what ) { return $n->hasAttribute( 'sz' ) ? (int) $n->getAttribute( 'sz' ) : null; }
		$l = $xp->query( './a:latin', $n )->item( 0 );
		return $l ? $l->getAttribute( 'typeface' ) : null;
	}
	private static function style_attr( $xp, $style, $lvl, $what ) {
		if ( ! $xp ) { return null; }
		$n = $xp->query( '//p:txStyles/p:' . $style . '/a:lvl' . ( $lvl + 1 ) . 'pPr/a:defRPr' )->item( 0 );
		if ( ! $n ) { return null; }
		if ( 'sz' === $what ) { return $n->hasAttribute( 'sz' ) ? (int) $n->getAttribute( 'sz' ) : null; }
		$l = $xp->query( './a:latin', $n )->item( 0 );
		return $l ? $l->getAttribute( 'typeface' ) : null;
	}
	private function typo() {
		$pres = $this->dom( 'ppt/presentation.xml' );
		$prels = $this->rels( 'ppt/presentation.xml' );
		$fonts_all = array();
		$font_name = function ( $f, $title ) {
			if ( null === $f || '' === $f ) { $f = $title ? '+mj-lt' : '+mn-lt'; }
			if ( '+mj-lt' === $f ) { return $this->theme['major'] ?: 'Calibri Light'; }
			if ( '+mn-lt' === $f ) { return $this->theme['minor'] ?: 'Calibri'; }
			return $f;
		};
		preg_match_all( '/<p:sldId\b[^>]*>/', $this->get( 'ppt/presentation.xml' ), $m );
		foreach ( $m[0] as $t ) {
			$rid = self::attr( $t, 'r:id' );
			if ( ! isset( $prels[ $rid ] ) ) { continue; }
			$path = $prels[ $rid ]['path'];
			$xp = $this->dom( $path );
			$S = array( 'runs' => array(), 'lines' => 0, 'has_title' => false, 'distort' => 0, 'min_sz' => null, 'fonts' => array() );
			if ( ! $xp ) { $this->typo['slides'][] = $S; continue; }
			$lay = null; $mas = null; $lay_path = ''; $mas_path = '';
			$rels = $this->rels( $path );
			foreach ( $rels as $r ) { if ( 'slideLayout' === $r['type'] ) { $lay_path = $r['path']; } }
			if ( $lay_path ) {
				$lay = $this->dom( $lay_path );
				foreach ( $this->rels( $lay_path ) as $r ) { if ( 'slideMaster' === $r['type'] ) { $mas_path = $r['path']; } }
				$mas = $mas_path ? $this->dom( $mas_path ) : null;
			}
			foreach ( $xp->query( '//p:cSld/p:spTree//p:sp' ) as $sp ) {
				$ph = self::ph_of( $xp, $sp );
				if ( $ph && in_array( $ph['type'], array( 'ftr', 'sldNum', 'dt' ), true ) ) { continue; }
				$title = $ph && in_array( $ph['type'], array( 'title', 'ctrTitle' ), true );
				$lsp = self::find_ph( $lay, $ph ); $msp = self::find_ph( $mas, $ph );
				$style = $title ? 'titleStyle' : ( $ph ? 'bodyStyle' : 'otherStyle' );
				$fit = $xp->query( './p:txBody/a:bodyPr/a:normAutofit', $sp )->item( 0 );
				$scale = $fit && $fit->hasAttribute( 'fontScale' ) ? (int) $fit->getAttribute( 'fontScale' ) / 100000 : 1;
				$plines = 0; $ttl = '';
				foreach ( $xp->query( './p:txBody/a:p', $sp ) as $p ) {
					$pPr = $xp->query( './a:pPr', $p )->item( 0 );
					$lvl = $pPr && $pPr->hasAttribute( 'lvl' ) ? (int) $pPr->getAttribute( 'lvl' ) : 0;
					$txt = '';
					foreach ( $xp->query( './a:r|./a:fld', $p ) as $r ) {
						$tx = (string) $xp->evaluate( 'string(./a:t)', $r );
						$txt .= $tx;
						if ( '' === trim( $tx ) ) { continue; }
						$rPr = $xp->query( './a:rPr', $r )->item( 0 );
						$sz = $rPr && $rPr->hasAttribute( 'sz' ) ? (int) $rPr->getAttribute( 'sz' ) : null;
						$ft = null;
						if ( $rPr ) { $l = $xp->query( './a:latin', $rPr )->item( 0 ); $ft = $l ? $l->getAttribute( 'typeface' ) : null; }
						foreach ( array( array( $xp, $sp ), array( $lay, $lsp ), array( $mas, $msp ) ) as $c ) {
							if ( null === $sz ) { $sz = self::lvl_attr( $c[0], $c[1], $lvl, 'sz' ); }
							if ( null === $ft ) { $ft = self::lvl_attr( $c[0], $c[1], $lvl, 'font' ); }
						}
						if ( null === $sz ) { $sz = self::style_attr( $mas, $style, $lvl, 'sz' ); }
						if ( null === $ft ) { $ft = self::style_attr( $mas, $style, $lvl, 'font' ); }
						if ( null === $sz && $pres ) { $n = $pres->query( '//p:defaultTextStyle/a:lvl' . ( $lvl + 1 ) . 'pPr/a:defRPr' )->item( 0 ); $sz = $n && $n->hasAttribute( 'sz' ) ? (int) $n->getAttribute( 'sz' ) : null; }
						$pt = round( ( $sz ?: 1800 ) * $scale / 100, 1 );
						$fn = $font_name( $ft, $title );
						$S['runs'][] = array( $pt, $fn, $title );
						$S['fonts'][ $fn ] = 1; $fonts_all[ $fn ] = 1;
						$S['min_sz'] = null === $S['min_sz'] ? $pt : min( $S['min_sz'], $pt );
					}
					if ( '' !== trim( $txt ) ) { if ( $title ) { $ttl .= $txt; } else { $plines++; } }
				}
				if ( $title && '' !== trim( $ttl ) ) { $S['has_title'] = true; }
				$S['lines'] += $plines;
			}
			foreach ( $xp->query( '//p:cSld/p:spTree//a:tbl' ) as $tb ) {
				$S['lines'] += $xp->query( './a:tr', $tb )->length;
				foreach ( $xp->query( './/a:tc//a:r', $tb ) as $r ) {
					if ( '' === trim( (string) $xp->evaluate( 'string(./a:t)', $r ) ) ) { continue; }
					$rPr = $xp->query( './a:rPr', $r )->item( 0 );
					$pt = round( ( $rPr && $rPr->hasAttribute( 'sz' ) ? (int) $rPr->getAttribute( 'sz' ) : 1800 ) / 100, 1 );
					$l = $rPr ? $xp->query( './a:latin', $rPr )->item( 0 ) : null;
					$fn = $font_name( $l ? $l->getAttribute( 'typeface' ) : null, false );
					$S['runs'][] = array( $pt, $fn, false );
					$S['fonts'][ $fn ] = 1; $fonts_all[ $fn ] = 1;
					$S['min_sz'] = null === $S['min_sz'] ? $pt : min( $S['min_sz'], $pt );
				}
			}
			// images déformées : proportions affichées ≠ proportions de l'image (après rognage)
			foreach ( $xp->query( '//p:cSld/p:spTree//p:pic' ) as $pic ) {
				if ( $xp->query( './/a:videoFile|.//a:audioFile', $pic )->length ) { continue; }
				$bl = $xp->query( './p:blipFill/a:blip', $pic )->item( 0 );
				$ext = $xp->query( './p:spPr/a:xfrm/a:ext', $pic )->item( 0 );
				if ( ! $bl || ! $ext ) { continue; }
				$emb = $bl->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed' );
				if ( ! isset( $rels[ $emb ] ) ) { continue; }
				$info = @getimagesizefromstring( $this->get( $rels[ $emb ]['path'] ) );
				if ( ! $info || ! $info[1] ) { continue; }
				$sr = $xp->query( './p:blipFill/a:srcRect', $pic )->item( 0 );
				$c = function ( $a ) use ( $sr ) { return $sr && $sr->hasAttribute( $a ) ? (int) $sr->getAttribute( $a ) / 100000 : 0; };
				$w = $info[0] * ( 1 - $c( 'l' ) - $c( 'r' ) ); $h = $info[1] * ( 1 - $c( 't' ) - $c( 'b' ) );
				$cy = (int) $ext->getAttribute( 'cy' );
				if ( $w <= 0 || $h <= 0 || ! $cy ) { continue; }
				$q = ( (int) $ext->getAttribute( 'cx' ) / $cy ) / ( $w / $h );
				if ( abs( $q - 1 ) > 0.06 ) { $S['distort']++; }
			}
			$this->typo['slides'][] = $S;
		}
		$this->typo['fonts'] = array_keys( $fonts_all );
	}

	private function read() {
		$pres = $this->get( 'ppt/presentation.xml' );
		$prels = $this->rels( 'ppt/presentation.xml' );
		if ( preg_match( '/<p:sldSz\b[^>]*>/', $pres, $m ) ) { $this->ratio = (int) self::attr( $m[0], 'cx' ) / max( 1, (int) self::attr( $m[0], 'cy' ) ); }
		$ids = array();
		preg_match_all( '/<p:sldId\b[^>]*>/', $pres, $m );
		foreach ( $m[0] as $t ) {
			$rid = self::attr( $t, 'r:id' );
			if ( isset( $prels[ $rid ] ) ) { $ids[ self::attr( $t, 'id' ) ] = count( $this->slides ); $this->slides[] = $this->slide( $prels[ $rid ]['path'] ); }
		}
		if ( preg_match_all( '#<p14:section\b([^>]*)>(.*?)</p14:section>#s', $pres, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $s ) {
				preg_match_all( '/<p14:sldId id="(\d+)"/', $s[2], $sl );
				$this->sections[] = array( 'name' => html_entity_decode( (string) self::attr( $s[1], 'name' ), ENT_QUOTES | ENT_XML1, 'UTF-8' ), 'slides' => array_values( array_intersect_key( $ids, array_flip( $sl[1] ) ) ) );
			}
		}
		// masque, dispositions et thème
		foreach ( $prels as $r ) {
			if ( 'slideMaster' !== $r['type'] ) { continue; }
			$mx = $this->get( $r['path'] );
			$this->master_pics += preg_match_all( '/<p:pic\b/', $mx );
			$this->theme['title_color'] = preg_match( '#<p:titleStyle>.*?<a:lvl1pPr\b.*?<a:defRPr\b[^>]*>(.*?)</a:defRPr>#s', $mx, $tm ) && preg_match( '/<a:(?:srgbClr|schemeClr) val="([^"]+)"/', $tm[1], $cm ) ? $cm[1] : '';
			foreach ( $this->rels( $r['path'] ) as $mr ) {
				if ( 'theme' === $mr['type'] ) {
					$th = $this->get( $mr['path'] );
					$this->theme['name'] = preg_match( '/<a:theme\b[^>]*name="([^"]*)"/', $th, $x ) ? $x[1] : '';
					$this->theme['colors'] = preg_match( '/<a:clrScheme name="([^"]*)"/', $th, $x ) ? $x[1] : '';
					$this->theme['accent1'] = preg_match( '#<a:accent1>\s*<a:srgbClr val="([0-9A-Fa-f]{6})"#', $th, $x ) ? strtoupper( $x[1] ) : '';
					$this->theme['accent2'] = preg_match( '#<a:accent2>\s*<a:srgbClr val="([0-9A-Fa-f]{6})"#', $th, $x ) ? strtoupper( $x[1] ) : '';
					$this->theme['major'] = preg_match( '#<a:majorFont>\s*<a:latin typeface="([^"]*)"#', $th, $x ) ? $x[1] : '';
					$this->theme['minor'] = preg_match( '#<a:minorFont>\s*<a:latin typeface="([^"]*)"#', $th, $x ) ? $x[1] : '';
				}
				if ( 'slideLayout' === $mr['type'] ) { $this->master_pics += preg_match_all( '/<p:pic\b/', $this->get( $mr['path'] ) ) > 0 ? 0 : 0; }
			}
		}
		$core = $this->get( 'docProps/core.xml' );
		foreach ( array( 'title' => 'dc:title', 'subject' => 'dc:subject', 'keywords' => 'cp:keywords', 'creator' => 'dc:creator', 'last' => 'cp:lastModifiedBy', 'description' => 'dc:description' ) as $k => $tag ) {
			$this->props[ $k ] = preg_match( '#<' . $tag . '>(.*?)</' . $tag . '>#s', $core, $x ) ? html_entity_decode( trim( $x[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) : '';
		}
		for ( $i = 0; $i < $this->z->numFiles; $i++ ) {
			$st = $this->z->statIndex( $i );
			if ( preg_match( '#^ppt/media/.*\.(png|jpe?g|gif|bmp|tiff?)$#i', $st['name'] ) ) { $this->media_bytes += $st['size']; }
			if ( preg_match( '#^ppt/(comments/|modernComments?/).+\.xml$#', $st['name'] ) ) { $this->comments++; }
		}
	}

	private function slide( $path ) {
		$x = $this->get( $path );
		$rels = $this->rels( $path );
		$s = array( 'hidden' => (bool) preg_match( '/<p:sld\b[^>]*\bshow="(0|false)"/', $x ), 'layout' => '', 'layout_name' => '', 'title' => '', 'texts' => array(), 'body_levels' => 0,
			'numbered' => (bool) preg_match( '/<a:buAutoNum\b/', $x ), 'pic' => 0, 'pic_alt' => 0, 'table' => array(), 'chart' => array(), 'smartart' => 0, 'shape' => 0, 'conn' => 0, 'video' => 0,
			'transition' => '', 'anim' => 0, 'notes' => '', 'sldnum' => false, 'footer' => '', 'links_int' => 0, 'links_ext' => 0, 'bg' => '', 'icon' => 0 );
		foreach ( $rels as $rid => $r ) {
			if ( 'slideLayout' === $r['type'] ) {
				$lx = $this->get( $r['path'] );
				$s['layout'] = preg_match( '/<p:sldLayout\b[^>]*\btype="([^"]+)"/', $lx, $m ) ? $m[1] : 'cust';
				$s['layout_name'] = preg_match( '/<p:cSld\b[^>]*name="([^"]*)"/', $lx, $m ) ? $m[1] : '';
			}
			if ( 'notesSlide' === $r['type'] ) {
				$nx = $this->get( $r['path'] );
				$nt = '';
				if ( preg_match_all( '#<p:sp>.*?</p:sp>#s', $nx, $sps ) ) {
					foreach ( $sps[0] as $sp ) { if ( preg_match( '/<p:ph\b[^>]*type="body"/', $sp ) ) { $nt .= implode( "\n", self::texts_of( $sp ) ) . "\n"; } }
				}
				$s['notes'] = trim( $nt );
			}
		}
		if ( preg_match( '#<p:transition\b[^>]*>(.*?)</p:transition>#s', $x, $m ) || preg_match( '#<p:transition\b[^>]*/>#', $x, $m ) ) {
			$s['transition'] = isset( $m[1] ) && preg_match( '/<(?:p|p14|p15):(\w+)/', $m[1], $t ) ? $t[1] : 'cut';
		}
		if ( preg_match_all( '/presetClass="(entr|emph|exit|path)"/', $x, $m ) ) {
			preg_match_all( '/<p:spTgt spid="(\d+)"/', $x, $tg );
			$s['anim'] = max( count( $m[0] ), 0 );
			$s['anim_shapes'] = count( array_unique( $tg[1] ) );
		}
		if ( preg_match( '#<p:bg>(.*?)</p:bg>#s', $x, $m ) ) { $s['bg'] = false !== strpos( $m[1], 'blipFill' ) ? 'pic' : 'fill'; }
		// objets
		if ( preg_match_all( '#<p:(sp|pic|graphicFrame|cxnSp)\b.*?</p:\1>#s', $x, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $o ) {
				$kind = $o[1]; $ox = $o[0];
				if ( preg_match( '/<a:hlinkClick\b[^>]*>/', $ox, $hl ) || preg_match( '/<a:hlinkClick\b[^>]*\/>/', $ox, $hl ) ) {
					preg_match_all( '/<a:hlinkClick\b[^>]*\/?>/', $ox, $all );
					foreach ( $all[0] as $h ) {
						$act = (string) self::attr( $h, 'action' );
						$rid = self::attr( $h, 'r:id' );
						if ( 0 === strpos( $act, 'ppaction://hlinksldjump' ) || 0 === strpos( $act, 'ppaction://hlinkshowjump' ) ) { $s['links_int']++; }
						elseif ( $rid && isset( $rels[ $rid ] ) && $rels[ $rid ]['ext'] ) { $s['links_ext']++; }
					}
				}
				if ( 'sp' === $kind ) {
					$ph = preg_match( '/<p:ph\b([^>]*)\/?>/', $ox, $pm ) ? ( self::attr( $pm[1], 'type' ) ?: 'body' ) : '';
					$tx = self::texts_of( $ox );
					if ( 'sldNum' === $ph ) { $s['sldnum'] = true; continue; }
					if ( 'ftr' === $ph ) { $s['footer'] = implode( ' ', $tx ); continue; }
					if ( 'dt' === $ph ) { continue; }
					if ( in_array( $ph, array( 'title', 'ctrTitle' ), true ) ) { $s['title'] = implode( ' ', $tx ); }
					if ( in_array( $ph, array( 'body', 'obj', 'subTitle' ), true ) ) { $s['body_levels'] += preg_match_all( '/<a:pPr\b[^>]*\blvl="[1-8]"/', $ox ); }
					if ( '' === $ph ) { $s['shape']++; }
					$s['texts'] = array_merge( $s['texts'], $tx );
				} elseif ( 'pic' === $kind ) {
					if ( preg_match( '/<a:videoFile\b|<p14:media\b|<a:audioFile\b/', $ox ) ) { $s['video']++; continue; }
					$s['pic']++;
					if ( false !== strpos( $ox, 'svgBlip' ) ) { $s['icon']++; }
					if ( preg_match( '/<p:cNvPr\b[^>]*\bdescr="([^"]+)"/', $ox ) ) { $s['pic_alt']++; }
				} elseif ( 'cxnSp' === $kind ) {
					$s['conn']++;
				} else {
					if ( false !== strpos( $ox, 'drawingml/2006/table' ) ) {
						$rows = preg_match_all( '#<a:tr\b#', $ox );
						preg_match( '#<a:tr\b.*?</a:tr>#s', $ox, $fr );
						$cols = $fr ? preg_match_all( '#<a:tc\b#', $fr[0] ) : 0;
						$s['table'][] = array( 'rows' => $rows, 'cols' => $cols );
						$s['texts'] = array_merge( $s['texts'], self::texts_of( $ox ) );
					} elseif ( false !== strpos( $ox, 'drawingml/2006/chart' ) && preg_match( '/r:id="([^"]+)"/', $ox, $cr ) && isset( $rels[ $cr[1] ] ) ) {
						$cx = $this->get( $rels[ $cr[1] ]['path'] );
						$s['chart'][] = preg_match( '#<c:plotArea>.*?<c:(\w+Chart)>#s', $cx, $ct ) ? $ct[1] : 'chart';
						$s['texts'] = array_merge( $s['texts'], self::texts_of( $cx ) );
						if ( preg_match_all( '#<c:v>([^<]*)</c:v>#', $cx, $cv ) ) { // catégories et noms de séries (cache)
							foreach ( $cv[1] as $v ) { if ( ! is_numeric( $v ) ) { $s['texts'][] = html_entity_decode( $v, ENT_QUOTES | ENT_XML1, 'UTF-8' ); } }
						}
					} elseif ( false !== strpos( $ox, 'drawingml/2006/diagram' ) ) {
						$s['smartart']++;
						if ( preg_match( '/r:dm="([^"]+)"/', $ox, $dr ) && isset( $rels[ $dr[1] ] ) ) {
							$s['texts'] = array_merge( $s['texts'], self::texts_of( $this->get( $rels[ $dr[1] ]['path'] ) ) );
						}
					}
				}
			}
		}
		// formes dans les groupes : déjà comptées par l'expression ci-dessus (recherche non imbriquée)
		return $s;
	}
}

class LI_Pptx_Check {
	public static function n( $s ) {
		$s = remove_accents_li( mb_strtolower( (string) $s, 'UTF-8' ) );
		return trim( preg_replace( '/[^a-z0-9%]+/', ' ', $s ) );
	}
	private static function has( $hay, $needle ) { return '' === self::n( $needle ) || false !== strpos( ' ' . self::n( $hay ) . ' ', self::n( $needle ) ); }
	private static function slide( $m, $k ) { return isset( $m->slides[ $k - 1 ] ) ? $m->slides[ $k - 1 ] : null; }
	private static function frac( $ok, $n ) { return $n ? $ok / $n : 0; }

	public static function evaluate( $checks, LI_Pptx_Model $m ) {
		$out = array( 'items' => array(), 'score' => 0, 'max' => 0 );
		foreach ( $checks as $c ) {
			$v = max( 0, min( 1, self::check( $c, $m ) ) );
			$got = round( $c['pts'] * $v * 2 ) / 2;
			$out['items'][] = array( 'label' => $c['label'], 'max' => $c['pts'], 'pts' => $got, 'ok' => $got >= $c['pts'] );
			$out['score'] += $got; $out['max'] += $c['pts'];
		}
		$out['pct'] = $out['max'] ? round( 100 * $out['score'] / $out['max'] ) : 0;
		return $out;
	}

	private static function check( $c, LI_Pptx_Model $m ) {
		$S = isset( $c['slide'] ) ? self::slide( $m, $c['slide'] ) : null;
		$all = $m->slides;
		switch ( $c['type'] ) {
			case 'count':
				$n = count( $all );
				return ( $n >= ( $c['min'] ?? 0 ) && $n <= ( $c['max'] ?? 999 ) ) ? 1 : 0;
			case 'titles':
				$ok = 0;
				foreach ( $c['titles'] as $i => $t ) { if ( isset( $all[ $i ] ) && self::has( $all[ $i ]['title'], $t ) && '' !== $all[ $i ]['title'] ) { $ok++; } }
				return self::frac( $ok, count( $c['titles'] ) );
			case 'text':
				$pool = $S ? array_merge( array( $S['title'] ), $S['texts'] ) : array();
				if ( ! isset( $c['slide'] ) ) { foreach ( $all as $s ) { $pool = array_merge( $pool, array( $s['title'] ), $s['texts'] ); } }
				$hay = implode( ' | ', $pool ); $ok = 0;
				foreach ( $c['texts'] as $t ) { if ( self::has( $hay, $t ) ) { $ok++; } }
				return self::frac( $ok, count( $c['texts'] ) );
			case 'find': // un texte doit se trouver sur une diapositive (n'importe laquelle) : on vérifie aussi ce qui l'accompagne
				foreach ( $all as $s ) {
					$hay = $s['title'] . ' | ' . implode( ' | ', $s['texts'] );
					if ( ! self::has( $hay, $c['key'] ) ) { continue; }
					$ok = 0; foreach ( $c['texts'] as $t ) { if ( self::has( $hay, $t ) ) { $ok++; } }
					return self::frac( $ok, count( $c['texts'] ) );
				}
				return 0;
			case 'layout':
				return $S && in_array( $S['layout'], (array) $c['layouts'], true ) ? 1 : 0;
			case 'layouts':
				$ok = 0;
				foreach ( $c['layouts'] as $i => $l ) { if ( isset( $all[ $i ] ) && in_array( $all[ $i ]['layout'], (array) $l, true ) ) { $ok++; } }
				return self::frac( $ok, count( $c['layouts'] ) );
			case 'hidden':
				if ( isset( $c['key'] ) ) { foreach ( $all as $s ) { if ( self::has( $s['title'], $c['key'] ) ) { return $s['hidden'] === (bool) ( $c['val'] ?? true ) ? 1 : 0; } } return 0; }
				return $S && $S['hidden'] === (bool) ( $c['val'] ?? true ) ? 1 : 0;
			case 'nb_hidden':
				$n = 0; foreach ( $all as $s ) { if ( $s['hidden'] ) { $n++; } }
				return $n === (int) $c['n'] ? 1 : 0;
			case 'objects':
				$pool = $S ? array( $S ) : $all; $n = 0;
				foreach ( $pool as $s ) {
					$k = $c['kind'];
					if ( 'table' === $k || 'chart' === $k ) { $n += count( $s[ $k ] ); }
					elseif ( 'visual' === $k ) { $n += $s['smartart'] ? 99 : $s['shape']; }
					else { $n += $s[ $k ]; }
				}
				return min( 1, $n / max( 1, $c['min'] ) );
			case 'chart':
				$pool = $S ? array( $S ) : $all;
				foreach ( $pool as $s ) { if ( in_array( $c['chart'], $s['chart'], true ) ) { return 1; } }
				foreach ( $pool as $s ) { if ( $s['chart'] ) { return 0.5; } }
				return 0;
			case 'table':
				$pool = $S ? array( $S ) : $all;
				foreach ( $pool as $s ) {
					foreach ( $s['table'] as $t ) {
						$v = ( $t['rows'] >= ( $c['rows'] ?? 0 ) ? .5 : 0 ) + ( $t['cols'] >= ( $c['cols'] ?? 0 ) ? .5 : 0 );
						if ( $v ) { return $v; }
					}
				}
				return 0;
			case 'paras':
				$n = $S ? count( $S['texts'] ) : 0;
				return ( $n >= ( $c['min'] ?? 0 ) && $n <= ( $c['max'] ?? 999 ) ) ? 1 : 0;
			case 'min_font': // taille de texte minimale (en points), part des diapositives conformes
				$T = $m->typo['slides']; if ( ! $T ) { return 0; }
				$ok = 0; foreach ( $T as $t ) { if ( null === $t['min_sz'] || $t['min_sz'] >= ( $c['min'] ?? 20 ) - 0.05 ) { $ok++; } }
				return $ok / count( $T );
			case 'max_lines': // lignes de texte par diapositive (hors titre ; une ligne de tableau = une ligne)
				$T = $m->typo['slides']; if ( ! $T ) { return 0; }
				$ok = 0; foreach ( $T as $t ) { if ( $t['lines'] <= ( $c['max'] ?? 6 ) ) { $ok++; } }
				return $ok / count( $T );
			case 'max_fonts':
				return count( $m->typo['fonts'] ) <= ( $c['max'] ?? 2 ) ? 1 : 0;
			case 'all_titles':
				$T = $m->typo['slides']; if ( ! $T ) { return 0; }
				$ok = 0; foreach ( $T as $t ) { if ( $t['has_title'] ) { $ok++; } }
				return $ok / count( $T );
			case 'no_distort':
				foreach ( $m->typo['slides'] as $t ) { if ( $t['distort'] ) { return 0; } }
				return 1;
			case 'absent': // aucun paragraphe de la diapositive ne contient ce texte brut (ex. « ; » d'une ancienne liste)
				if ( ! $S ) { return 0; }
				foreach ( $S['texts'] as $t ) { if ( false !== mb_stripos( $t, $c['text'] ) ) { return 0; } }
				return 1;
			case 'levels':
				return $S ? min( 1, $S['body_levels'] / max( 1, $c['min'] ) ) : 0;
			case 'numbered':
				return $S && $S['numbered'] ? 1 : 0;
			case 'alt':
				$pool = $S ? array( $S ) : $all; $p = 0; $a = 0;
				foreach ( $pool as $s ) { $p += $s['pic']; $a += $s['pic_alt']; }
				return $p ? $a / $p : 0;
			case 'transition':
				$pool = 'all' === ( $c['slides'] ?? 'all' ) ? array_keys( $all ) : array_map( function ( $k ) { return $k - 1; }, $c['slides'] );
				$ok = 0;
				foreach ( $pool as $i ) { if ( isset( $all[ $i ] ) && '' !== $all[ $i ]['transition'] && ( empty( $c['kind'] ) || $all[ $i ]['transition'] === $c['kind'] ) ) { $ok++; } }
				return self::frac( $ok, count( $pool ) );
			case 'anim':
				$pool = $S ? array( $S ) : $all; $best = 0;
				foreach ( $pool as $s ) { $best = max( $best, $s['anim'] ); }
				return min( 1, $best / max( 1, $c['min'] ) );
			case 'notes':
				$pool = 'all' === ( $c['slides'] ?? 'all' ) ? array_keys( $all ) : array_map( function ( $k ) { return $k - 1; }, $c['slides'] );
				$ok = 0;
				foreach ( $pool as $i ) { if ( isset( $all[ $i ] ) && mb_strlen( $all[ $i ]['notes'] ) >= ( $c['min_chars'] ?? 15 ) ) { $ok++; } }
				return self::frac( $ok, count( $pool ) );
			case 'sections':
				$ok = 0;
				foreach ( $c['names'] as $nm ) { foreach ( $m->sections as $sec ) { if ( self::n( $sec['name'] ) === self::n( $nm ) ) { $ok++; break; } } }
				return self::frac( $ok, count( $c['names'] ) );
			case 'links':
				$i = 0; $e = 0;
				foreach ( $all as $s ) { $i += $s['links_int']; $e += $s['links_ext']; }
				$v = 0; $parts = 0;
				if ( ! empty( $c['internal'] ) ) { $parts++; $v += min( 1, $i / $c['internal'] ); }
				if ( ! empty( $c['external'] ) ) { $parts++; $v += min( 1, $e / $c['external'] ); }
				return $parts ? $v / $parts : 0;
			case 'master_pic':
				return $m->master_pics > 0 ? 1 : 0;
			case 'slidenum':
				$n = 0; $ok = 0;
				foreach ( $all as $k => $s ) { if ( 0 === $k && ! empty( $c['exclude_title'] ) ) { continue; } $n++; if ( $s['sldnum'] ) { $ok++; } }
				return self::frac( $ok, $n );
			case 'footer':
				$n = 0; $ok = 0;
				foreach ( $all as $k => $s ) { if ( 0 === $k && ! empty( $c['exclude_title'] ) ) { continue; } $n++; if ( self::has( $s['footer'], $c['text'] ) && '' !== $s['footer'] ) { $ok++; } }
				return self::frac( $ok, $n );
			case 'no_number_on_title':
				return isset( $all[0] ) && ! $all[0]['sldnum'] ? 1 : 0;
			case 'theme':
				$ok = 0; $n = 0;
				foreach ( $c['props'] as $k => $want ) {
					$n++;
					$have = (string) ( $m->theme[ $k ] ?? '' );
					if ( '!' === substr( $want, 0, 1 ) ) { if ( '' !== $have && self::n( $have ) !== self::n( substr( $want, 1 ) ) ) { $ok++; } }
					elseif ( self::n( $have ) === self::n( $want ) ) { $ok++; }
				}
				return self::frac( $ok, $n );
			case 'ratio':
				return abs( $m->ratio - 16 / 9 ) < 0.02 ? 1 : 0;
			case 'background':
				return $S && ( 'any' === ( $c['kind'] ?? 'any' ) ? '' !== $S['bg'] : $S['bg'] === $c['kind'] ) ? 1 : 0;
			case 'props':
				$ok = 0;
				foreach ( $c['fields'] as $k => $want ) { $have = $m->props[ $k ] ?? ''; if ( '' !== trim( $have ) && ( '' === $want || self::has( $have, $want ) ) ) { $ok++; } }
				return self::frac( $ok, count( $c['fields'] ) );
			case 'media_max':
				return $m->media_bytes <= $c['kb'] * 1024 ? 1 : 0;
			case 'no_comments':
				return 0 === $m->comments ? 1 : 0;
		}
		return 0;
	}
}

if ( ! function_exists( 'remove_accents_li' ) ) {
	function remove_accents_li( $s ) {
		if ( function_exists( 'remove_accents' ) ) { return remove_accents( $s ); }
		return strtr( $s, array( 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', '’' => "'" ) );
	}
}
