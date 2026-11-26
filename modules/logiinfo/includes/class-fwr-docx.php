<?php
/**
 * Analyseur de fichiers .docx : extrait la structure utile à la correction
 * (paragraphes, styles, listes, tabulations, tableaux, sections, champs, images, graphiques…).
 * Indépendant de WordPress.
 */
if ( ! defined( 'LIR_STANDALONE' ) && ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Docx {
	const W   = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	const NS  = array(
		'w'   => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
		'r'   => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
		'wp'  => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
		'a'   => 'http://schemas.openxmlformats.org/drawingml/2006/main',
		'pic' => 'http://schemas.openxmlformats.org/drawingml/2006/picture',
		'c'   => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
		'wps' => 'http://schemas.microsoft.com/office/word/2010/wordprocessingShape',
		'mc'  => 'http://schemas.openxmlformats.org/markup-compatibility/2006',
		'v'   => 'urn:schemas-microsoft-com:vml',
		'dgm' => 'http://schemas.openxmlformats.org/drawingml/2006/diagram',
		'rel' => 'http://schemas.openxmlformats.org/package/2006/relationships',
	);

	public $ok = false;
	public $error = '';
	public $d = array();          // structure extraite
	private $zip;
	private $styles = array();    // styleId => info
	private $theme_fonts = array( 'major' => 'Aptos Display', 'minor' => 'Aptos' );
	private $defaults = array( 'after' => 0, 'line' => 240, 'sz' => 20, 'font' => 'Times New Roman' );
	private $rels = array();

	public function __construct( $path ) {
		$this->zip = new ZipArchive();
		if ( true !== $this->zip->open( $path ) ) { $this->error = 'Fichier illisible (ce n\'est pas un .docx valide).'; return; }
		$doc = $this->zip->getFromName( 'word/document.xml' );
		if ( ! $doc ) { $this->error = 'document.xml introuvable : le fichier n\'est pas un document Word.'; return; }
		try {
			$this->load_theme();
			$this->load_styles();
			$this->rels = $this->load_rels( 'word/_rels/document.xml.rels' );
			$trace = $this->trace( $doc );
			$this->parse_document( $doc );
			$this->d['trace'] = $trace;
			$this->ok = true;
		} catch ( Exception $e ) {
			$this->error = 'Analyse impossible : ' . $e->getMessage();
		}
		$this->zip->close();
	}

	/* ------------------------------------------------------------ utilitaires XML */
	private function xp( $xml ) {
		$dom = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$dom->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
		libxml_use_internal_errors( $prev );
		$x = new DOMXPath( $dom );
		foreach ( self::NS as $k => $v ) { $x->registerNamespace( $k, $v ); }
		self::accept_revisions( $x );
		return $x;
	}

	/**
	 * Le fichier de départ est en « suivi des modifications » : on analyse la version finale,
	 * comme si toutes les modifications étaient acceptées (texte supprimé et anciennes mises en forme retirés).
	 */
	private static function accept_revisions( $x ) {
		$drop = array( 'del', 'moveFrom', 'rPrChange', 'pPrChange', 'sectPrChange', 'tblPrChange', 'tblPrExChange', 'tblGridChange', 'trPrChange', 'tcPrChange', 'numberingChange',
			'moveFromRangeStart', 'moveFromRangeEnd', 'moveToRangeStart', 'moveToRangeEnd', 'customXmlDelRangeStart', 'customXmlDelRangeEnd', 'customXmlInsRangeStart', 'customXmlInsRangeEnd',
			'customXmlMoveFromRangeStart', 'customXmlMoveFromRangeEnd', 'customXmlMoveToRangeStart', 'customXmlMoveToRangeEnd' );
		foreach ( $drop as $t ) {
			foreach ( iterator_to_array( $x->query( '//w:' . $t ) ) as $n ) { if ( $n->parentNode ) { $n->parentNode->removeChild( $n ); } }
		}
		foreach ( array( 'ins', 'moveTo' ) as $t ) {
			foreach ( iterator_to_array( $x->query( '//w:' . $t ) ) as $n ) {
				if ( ! $n->parentNode ) { continue; }
				while ( $n->firstChild ) { $n->parentNode->insertBefore( $n->firstChild, $n ); }
				$n->parentNode->removeChild( $n );
			}
		}
	}

	/* ------------------------------------------------------------ traçabilité (suivi des modifications) */
	const REV_TAGS = 'ins|del|moveFrom|moveTo|rPrChange|pPrChange|sectPrChange|tblPrChange|tblPrExChange|tblGridChange|trPrChange|tcPrChange|numberingChange|cellIns|cellDel|cellMerge';

	private function trace( $doc ) {
		$t = array( 'tracking' => false, 'protection' => 'absente', 'authors' => array(), 'n' => 0, 'first' => '', 'last' => '', 'creator' => '', 'last_by' => '', 'modified' => '' );
		$parts = array( $doc );
		for ( $i = 0; $i < $this->zip->numFiles; $i++ ) {
			$name = $this->zip->getNameIndex( $i );
			if ( preg_match( '#^word/(header|footer|footnotes|endnotes)\d*\.xml$#', $name ) ) { $parts[] = (string) $this->zip->getFromName( $name ); }
		}
		foreach ( $parts as $xml ) {
			if ( ! preg_match_all( '#<w:(' . self::REV_TAGS . ')\b([^>]*)>#', $xml, $m, PREG_SET_ORDER ) ) { continue; }
			foreach ( $m as $e ) {
				if ( ! preg_match( '#\bw:author="([^"]*)"#', $e[2], $a ) ) { continue; }
				$who = html_entity_decode( $a[1], ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$t['authors'][ $who ] = ( $t['authors'][ $who ] ?? 0 ) + 1;
				$t['n']++;
				if ( preg_match( '#\bw:date="([^"]+)"#', $e[2], $d ) ) {
					if ( '' === $t['first'] || $d[1] < $t['first'] ) { $t['first'] = $d[1]; }
					if ( $d[1] > $t['last'] ) { $t['last'] = $d[1]; }
				}
			}
		}
		arsort( $t['authors'] );
		$set = (string) $this->zip->getFromName( 'word/settings.xml' );
		$t['tracking'] = (bool) preg_match( '#<w:trackRevisions(?![^>]*w:val="(0|false|off)")[^>]*/?>#', $set );
		if ( preg_match( '#<w:documentProtection\b([^>]*)/?>#', $set, $p ) ) {
			$at = array();
			preg_match_all( '#w:(\w+)="([^"]*)"#', $p[1], $mm, PREG_SET_ORDER );
			foreach ( $mm as $kv ) { $at[ $kv[1] ] = $kv[2]; }
			$on = in_array( strtolower( $at['enforcement'] ?? '' ), array( '1', 'true', 'on' ), true );
			if ( ! $on ) { $t['protection'] = 'absente'; }
			elseif ( 'trackedChanges' !== ( $at['edit'] ?? '' ) ) { $t['protection'] = 'autre'; }
			else {
				$hash = $at['hash'] ?? ( $at['hashValue'] ?? '' );
				$salt = $at['salt'] ?? ( $at['saltValue'] ?? '' );
				$spin = (int) ( $at['cryptSpinCount'] ?? ( $at['spinCount'] ?? 0 ) );
				$algo = isset( $at['algorithmName'] ) ? strtolower( str_replace( '-', '', $at['algorithmName'] ) ) : array( '4' => 'sha1', '12' => 'sha256', '13' => 'sha384', '14' => 'sha512' )[ $at['cryptAlgorithmSid'] ?? '4' ] ?? 'sha1';
				$pw = defined( 'LIR_MDP_EXERCICES' ) ? LIR_MDP_EXERCICES : 'Logipro-info';
				$t['protection'] = ( $hash && $salt && self::check_word_password( $pw, $hash, $salt, $spin, $algo ) ) ? 'verrouillee' : 'autre_mdp';
			}
		}
		$core = (string) $this->zip->getFromName( 'docProps/core.xml' );
		foreach ( array( 'creator' => 'dc:creator', 'last_by' => 'cp:lastModifiedBy', 'modified' => 'dcterms:modified' ) as $k => $tag ) {
			if ( preg_match( '#<' . $tag . '\b[^>]*>([^<]*)</' . $tag . '>#', $core, $c ) ) { $t[ $k ] = html_entity_decode( $c[1], ENT_QUOTES | ENT_XML1, 'UTF-8' ); }
		}
		return $t;
	}

	/** Hachage « legacy » des mots de passe de Word (ECMA-376, partie 4). */
	private static function word_legacy_key( $pw ) {
		static $init = array( 0xE1F0, 0x1D0F, 0xCC9C, 0x84C0, 0x110C, 0x0E10, 0xF1CE, 0x313E, 0x1872, 0xE139, 0xD40F, 0x84F9, 0x280C, 0xA96A, 0x4EC3 );
		static $mx = array( array( 0xAEFC, 0x4DD9, 0x9BB2, 0x2745, 0x4E8A, 0x9D14, 0x2A09 ), array( 0x7B61, 0xF6C2, 0xFDA5, 0xEB6B, 0xC6F7, 0x9DCF, 0x2BBF ), array( 0x4563, 0x8AC6, 0x05AD, 0x0B5A, 0x16B4, 0x2D68, 0x5AD0 ),
			array( 0x0375, 0x06EA, 0x0DD4, 0x1BA8, 0x3750, 0x6EA0, 0xDD40 ), array( 0xD849, 0xA0B3, 0x5147, 0xA28E, 0x553D, 0xAA7A, 0x44D5 ), array( 0x6F45, 0xDE8A, 0xAD35, 0x4A4B, 0x9496, 0x390D, 0x721A ),
			array( 0xEB23, 0xC667, 0x9CEF, 0x29FF, 0x53FE, 0xA7FC, 0x5FD9 ), array( 0x47D3, 0x8FA6, 0x0F6D, 0x1EDA, 0x3DB4, 0x7B68, 0xF6D0 ), array( 0xB861, 0x60E3, 0xC1C6, 0x93AD, 0x377B, 0x6EF6, 0xDDEC ),
			array( 0x45A0, 0x8B40, 0x06A1, 0x0D42, 0x1A84, 0x3508, 0x6A10 ), array( 0xAA51, 0x4483, 0x8906, 0x022D, 0x045A, 0x08B4, 0x1168 ), array( 0x76B4, 0xED68, 0xCAF1, 0x85C3, 0x1BA7, 0x374E, 0x6E9C ),
			array( 0x3730, 0x6E60, 0xDCC0, 0xA9A1, 0x4363, 0x86C6, 0x1DAD ), array( 0x3331, 0x6662, 0xCCC4, 0x89A9, 0x0373, 0x06E6, 0x0DCC ), array( 0x1021, 0x2042, 0x4084, 0x8108, 0x1231, 0x2462, 0x48C4 ) );
		$chars = array();
		foreach ( preg_split( '//u', mb_substr( $pw, 0, 15 ), -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
			$c = mb_ord( $ch ); $lo = $c & 0xFF;
			$chars[] = $lo ? $lo : ( ( $c >> 8 ) & 0xFF );
		}
		$n = count( $chars );
		if ( ! $n ) { return 0; }
		$high = $init[ $n - 1 ];
		foreach ( $chars as $i => $c ) { for ( $j = 0; $j < 7; $j++ ) { if ( $c & ( 1 << $j ) ) { $high ^= $mx[ 15 - $n + $i ][ $j ]; } } }
		$low = 0;
		foreach ( array_reverse( $chars ) as $c ) { $low = ( ( ( $low >> 14 ) & 1 ) | ( ( $low << 1 ) & 0x7FFF ) ) ^ $c; }
		$low = ( ( ( $low >> 14 ) & 1 ) | ( ( $low << 1 ) & 0x7FFF ) ) ^ $n ^ 0xCE4B;
		return ( $high << 16 ) | $low;
	}

	public static function check_word_password( $pw, $hash_b64, $salt_b64, $spin, $algo = 'sha512' ) {
		if ( ! in_array( $algo, hash_algos(), true ) ) { return false; }
		$key = self::word_legacy_key( $pw );
		$hex = strtoupper( bin2hex( pack( 'V', $key ) ) );                 // octets inversés, en hexadécimal
		$h = hash( $algo, base64_decode( $salt_b64 ) . mb_convert_encoding( $hex, 'UTF-16LE', 'UTF-8' ), true );
		for ( $i = 0; $i < $spin; $i++ ) { $h = hash( $algo, $h . pack( 'V', $i ), true ); }
		return hash_equals( base64_decode( $hash_b64 ), $h );
	}
	private static function wa( $el, $name ) {
		if ( ! $el ) { return null; }
		$v = $el->getAttributeNS( self::W, $name );
		return ( '' === $v && ! $el->hasAttributeNS( self::W, $name ) ) ? null : $v;
	}
	private function child( $x, $ctx, $q ) { $n = $x->query( $q, $ctx ); return $n->length ? $n->item( 0 ) : null; }
	private static function onoff( $el ) {
		if ( ! $el ) { return null; }
		$v = self::wa( $el, 'val' );
		return ! ( '0' === $v || 'false' === $v || 'none' === $v );
	}
	private function load_rels( $name ) {
		$out = array();
		$xml = $this->zip->getFromName( $name );
		if ( ! $xml ) { return $out; }
		$x = $this->xp( $xml );
		foreach ( $x->query( '//rel:Relationship' ) as $r ) {
			$t = $r->getAttribute( 'Target' );
			$base = dirname( str_replace( '_rels/', '', $name ) );
			$base = preg_replace( '#/\.rels$#', '', $base );
			$path = ( 0 === strpos( $t, '/' ) ) ? ltrim( $t, '/' ) : self::norm_path( dirname( $name, 2 ) . '/' . $t );
			$out[ $r->getAttribute( 'Id' ) ] = array( 'type' => basename( $r->getAttribute( 'Type' ) ), 'target' => $path, 'mode' => $r->getAttribute( 'TargetMode' ) );
		}
		return $out;
	}
	private static function norm_path( $p ) {
		$parts = array();
		foreach ( explode( '/', $p ) as $s ) {
			if ( '' === $s || '.' === $s ) { continue; }
			if ( '..' === $s ) { array_pop( $parts ); continue; }
			$parts[] = $s;
		}
		return implode( '/', $parts );
	}

	/* ------------------------------------------------------------ thème & styles */
	private function load_theme() {
		$t = $this->zip->getFromName( 'word/theme/theme1.xml' );
		if ( ! $t ) { return; }
		$x = $this->xp( $t );
		$maj = $x->query( '//a:majorFont/a:latin' );
		$min = $x->query( '//a:minorFont/a:latin' );
		if ( $maj->length ) { $this->theme_fonts['major'] = $maj->item( 0 )->getAttribute( 'typeface' ); }
		if ( $min->length ) { $this->theme_fonts['minor'] = $min->item( 0 )->getAttribute( 'typeface' ); }
	}

	private function load_styles() {
		$xml = $this->zip->getFromName( 'word/styles.xml' );
		if ( ! $xml ) { return; }
		$x = $this->xp( $xml );
		$dd = $this->child( $x, null, '//w:docDefaults' );
		if ( $dd ) {
			$r = $this->child( $x, $dd, './/w:rPrDefault/w:rPr' );
			$p = $this->child( $x, $dd, './/w:pPrDefault/w:pPr' );
			if ( $r ) { $ri = $this->rpr( $x, $r ); foreach ( array( 'sz', 'font' ) as $k ) { if ( isset( $ri[ $k ] ) ) { $this->defaults[ $k ] = $ri[ $k ]; } } }
			if ( $p ) { $pi = $this->ppr( $x, $p ); foreach ( array( 'after', 'line', 'before' ) as $k ) { if ( isset( $pi[ $k ] ) ) { $this->defaults[ $k ] = $pi[ $k ]; } } }
		}
		foreach ( $x->query( '//w:styles/w:style' ) as $s ) {
			$id = self::wa( $s, 'styleId' );
			$nm = $this->child( $x, $s, 'w:name' );
			$bo = $this->child( $x, $s, 'w:basedOn' );
			$p  = $this->child( $x, $s, 'w:pPr' );
			$r  = $this->child( $x, $s, 'w:rPr' );
			$tb = $this->child( $x, $s, 'w:tblPr' );
			$this->styles[ $id ] = array(
				'id'      => $id,
				'name'    => strtolower( $nm ? self::wa( $nm, 'val' ) : $id ),
				'type'    => self::wa( $s, 'type' ),
				'basedOn' => $bo ? self::wa( $bo, 'val' ) : null,
				'ppr'     => $p ? $this->ppr( $x, $p ) : array(),
				'rpr'     => $r ? $this->rpr( $x, $r ) : array(),
				'default' => '1' === self::wa( $s, 'default' ),
			);
		}
	}

	/** propriété de style résolue en remontant la chaîne basedOn */
	public function style_prop( $id, $kind, $key ) {
		$guard = 0;
		while ( $id && isset( $this->styles[ $id ] ) && $guard++ < 15 ) {
			if ( isset( $this->styles[ $id ][ $kind ][ $key ] ) ) { return $this->styles[ $id ][ $kind ][ $key ]; }
			$id = $this->styles[ $id ]['basedOn'];
		}
		return null;
	}
	public function style_name( $id ) { return isset( $this->styles[ $id ] ) ? $this->styles[ $id ]['name'] : ( $id ? strtolower( $id ) : 'normal' ); }
	public function default_par_style() { foreach ( $this->styles as $s ) { if ( 'paragraph' === $s['type'] && $s['default'] ) { return $s['id']; } } return 'Normal'; }

	private function font_of( $rf ) {
		if ( ! $rf ) { return null; }
		$a = self::wa( $rf, 'ascii' );
		if ( $a ) { return $a; }
		$t = self::wa( $rf, 'asciiTheme' );
		if ( $t ) { return ( false !== strpos( $t, 'major' ) ) ? $this->theme_fonts['major'] : $this->theme_fonts['minor']; }
		$h = self::wa( $rf, 'hAnsi' );
		return $h ? $h : null;
	}

	private function rpr( $x, $r ) {
		$o = array();
		foreach ( array( 'b' => 'b', 'i' => 'i', 'caps' => 'caps', 'smallCaps' => 'smallCaps', 'strike' => 'strike' ) as $tag => $k ) {
			$e = $this->child( $x, $r, 'w:' . $tag );
			if ( $e ) { $o[ $k ] = self::onoff( $e ); }
		}
		$u = $this->child( $x, $r, 'w:u' );
		if ( $u ) { $o['u'] = self::wa( $u, 'val' ) ? self::wa( $u, 'val' ) : 'single'; if ( 'none' === $o['u'] ) { $o['u'] = false; } }
		$c = $this->child( $x, $r, 'w:color' );
		if ( $c ) { $o['color'] = strtoupper( (string) self::wa( $c, 'val' ) ); }
		$s = $this->child( $x, $r, 'w:sz' );
		if ( $s ) { $o['sz'] = (int) self::wa( $s, 'val' ); }
		$h = $this->child( $x, $r, 'w:highlight' );
		if ( $h ) { $o['highlight'] = self::wa( $h, 'val' ); }
		$shd = $this->child( $x, $r, 'w:shd' );
		if ( $shd && self::wa( $shd, 'fill' ) && 'auto' !== self::wa( $shd, 'fill' ) ) { $o['shd'] = strtoupper( self::wa( $shd, 'fill' ) ); }
		$v = $this->child( $x, $r, 'w:vertAlign' );
		if ( $v ) { $o['vert'] = self::wa( $v, 'val' ); }
		$f = $this->font_of( $this->child( $x, $r, 'w:rFonts' ) );
		if ( $f ) { $o['font'] = $f; }
		$rs = $this->child( $x, $r, 'w:rStyle' );
		if ( $rs ) { $o['rstyle'] = self::wa( $rs, 'val' ); }
		return $o;
	}

	private function ppr( $x, $p ) {
		$o = array();
		$e = $this->child( $x, $p, 'w:pStyle' );
		if ( $e ) { $o['style'] = self::wa( $e, 'val' ); }
		$e = $this->child( $x, $p, 'w:jc' );
		if ( $e ) { $v = self::wa( $e, 'val' ); $o['jc'] = ( 'start' === $v ) ? 'left' : ( ( 'end' === $v ) ? 'right' : $v ); }
		$e = $this->child( $x, $p, 'w:ind' );
		if ( $e ) {
			foreach ( array( 'left', 'start', 'right', 'end', 'firstLine', 'hanging' ) as $k ) {
				$v = self::wa( $e, $k );
				if ( null !== $v ) { $kk = ( 'start' === $k ) ? 'left' : ( ( 'end' === $k ) ? 'right' : $k ); $o[ 'ind_' . $kk ] = (int) $v; }
			}
		}
		$e = $this->child( $x, $p, 'w:spacing' );
		if ( $e ) {
			foreach ( array( 'before', 'after', 'line' ) as $k ) { $v = self::wa( $e, $k ); if ( null !== $v ) { $o[ $k ] = (int) $v; } }
			if ( '1' === self::wa( $e, 'beforeAutospacing' ) ) { $o['before'] = 280; }
			if ( '1' === self::wa( $e, 'afterAutospacing' ) ) { $o['after'] = 280; }
		}
		$e = $this->child( $x, $p, 'w:numPr' );
		if ( $e ) {
			$id = $this->child( $x, $e, 'w:numId' ); $lv = $this->child( $x, $e, 'w:ilvl' );
			$o['numId'] = $id ? (int) self::wa( $id, 'val' ) : 0;
			$o['ilvl']  = $lv ? (int) self::wa( $lv, 'val' ) : 0;
		}
		$tabs = array();
		foreach ( $x->query( 'w:tabs/w:tab', $p ) as $t ) {
			$tabs[] = array( 'val' => self::wa( $t, 'val' ), 'pos' => (int) self::wa( $t, 'pos' ), 'leader' => self::wa( $t, 'leader' ) );
		}
		if ( $tabs ) { $o['tabs'] = $tabs; }
		$b = $this->child( $x, $p, 'w:pBdr' );
		if ( $b ) {
			$sides = array();
			foreach ( $x->query( 'w:*', $b ) as $s ) {
				$v = self::wa( $s, 'val' );
				if ( $v && 'nil' !== $v && 'none' !== $v ) { $sides[ $s->localName ] = array( 'val' => $v, 'sz' => (int) self::wa( $s, 'sz' ), 'color' => strtoupper( (string) self::wa( $s, 'color' ) ) ); }
			}
			if ( $sides ) { $o['border'] = $sides; }
		}
		$e = $this->child( $x, $p, 'w:shd' );
		if ( $e ) { $f = self::wa( $e, 'fill' ); if ( $f && 'auto' !== $f && 'FFFFFF' !== strtoupper( $f ) ) { $o['shd'] = strtoupper( $f ); } }
		$e = $this->child( $x, $p, 'w:pageBreakBefore' );
		if ( $e && self::onoff( $e ) ) { $o['pageBreakBefore'] = true; }
		$e = $this->child( $x, $p, 'w:outlineLvl' );
		if ( $e ) { $o['outline'] = (int) self::wa( $e, 'val' ); }
		return $o;
	}

	/* ------------------------------------------------------------ numérotation */
	private $num = null;
	private function load_numbering() {
		$this->num = array( 'nums' => array(), 'abs' => array() );
		$xml = $this->zip->getFromName( 'word/numbering.xml' );
		if ( ! $xml ) { return; }
		$x = $this->xp( $xml );
		foreach ( $x->query( '//w:abstractNum' ) as $a ) {
			$lv = array();
			foreach ( $x->query( 'w:lvl', $a ) as $l ) {
				$f = $this->child( $x, $l, 'w:numFmt' ); $t = $this->child( $x, $l, 'w:lvlText' ); $s = $this->child( $x, $l, 'w:start' );
				$lv[ (int) self::wa( $l, 'ilvl' ) ] = array( 'fmt' => $f ? self::wa( $f, 'val' ) : 'decimal', 'text' => $t ? self::wa( $t, 'val' ) : '', 'start' => $s ? (int) self::wa( $s, 'val' ) : 1 );
			}
			$this->num['abs'][ (int) self::wa( $a, 'abstractNumId' ) ] = $lv;
		}
		foreach ( $x->query( '//w:num' ) as $n ) {
			$aid = $this->child( $x, $n, 'w:abstractNumId' );
			$ov = array();
			foreach ( $x->query( 'w:lvlOverride', $n ) as $o ) {
				$so = $this->child( $x, $o, 'w:startOverride' );
				if ( $so ) { $ov[ (int) self::wa( $o, 'ilvl' ) ] = (int) self::wa( $so, 'val' ); }
			}
			$this->num['nums'][ (int) self::wa( $n, 'numId' ) ] = array( 'abs' => $aid ? (int) self::wa( $aid, 'val' ) : -1, 'override' => $ov );
		}
	}
	private function list_level( $numId, $ilvl ) {
		if ( ! isset( $this->num['nums'][ $numId ] ) ) { return null; }
		$a = $this->num['nums'][ $numId ]['abs'];
		return isset( $this->num['abs'][ $a ][ $ilvl ] ) ? $this->num['abs'][ $a ][ $ilvl ] : null;
	}

	/* ------------------------------------------------------------ document */
	private function parse_document( $xml ) {
		$this->load_numbering();
		$x = $this->xp( $xml );
		$body = $this->child( $x, null, '/w:document/w:body' );
		$D = array(
			'paragraphs' => array(), 'tables' => array(), 'sections' => array(), 'fields' => array(), 'mergefields' => array(),
			'images' => array(), 'charts' => array(), 'smartart' => array(), 'textboxes' => array(), 'headers' => array(), 'footers' => array(),
			'styles' => array(), 'toc' => array(), 'cover' => false, 'mailmerge' => false, 'text' => '',
		);
		// paragraphes du corps (hors zones de texte et hors contenus de repli)
		$paras = $x->query( '//w:body//w:p[not(ancestor::w:txbxContent) and not(ancestor::mc:Fallback)]' );
		$sect = 0;
		$counters = array();
		$in_toc = false;
		$all_text = array();
		foreach ( $paras as $i => $p ) {
			$pp = $this->child( $x, $p, 'w:pPr' );
			$pi = $pp ? $this->ppr( $x, $pp ) : array();
			$style = isset( $pi['style'] ) ? $pi['style'] : $this->default_par_style();
			$runs = array();
			$text = '';
			foreach ( $x->query( './/w:r[not(ancestor::w:del) and not(ancestor::mc:Fallback) and not(ancestor::w:txbxContent)]', $p ) as $r ) {
				// ignorer les runs d'un paragraphe imbriqué (ne devrait pas arriver hors zone de texte)
				$rp = $this->child( $x, $r, 'w:rPr' );
				$ri = $rp ? $this->rpr( $x, $rp ) : array();
				$t = '';
				foreach ( $x->query( 'w:t|w:tab|w:br|w:noBreakHyphen|w:sym', $r ) as $c ) {
					if ( 't' === $c->localName ) { $t .= $c->textContent; }
					elseif ( 'tab' === $c->localName ) { $t .= "\t"; }
					elseif ( 'br' === $c->localName ) {
						$type = self::wa( $c, 'type' );
						if ( 'page' === $type ) { $pi['pageBreakInside'] = true; } elseif ( 'column' === $type ) { $pi['colBreak'] = true; } else { $t .= "\n"; }
					} elseif ( 'noBreakHyphen' === $c->localName ) { $t .= '-'; }
				}
				foreach ( $x->query( 'w:instrText', $r ) as $it ) { $this->add_field( $D, $it->textContent, $in_toc ); }
				foreach ( $x->query( 'w:fldChar', $r ) as $fc ) {
					if ( 'end' === self::wa( $fc, 'fldCharType' ) && $in_toc ) { /* fin éventuelle du champ TdM */ }
				}
				$draw = $x->query( 'w:drawing|w:pict|mc:AlternateContent/mc:Choice//w:drawing', $r );
				foreach ( $draw as $dr ) { $this->add_drawing( $x, $dr, $D, $i ); }
				if ( '' !== $t ) { $ri['t'] = $t; $runs[] = $ri; $text .= $t; }
			}
			foreach ( $x->query( './/w:fldSimple', $p ) as $fs ) { $this->add_field( $D, $fs->getAttributeNS( self::W, 'instr' ), $in_toc ); }
			$in_table = $x->query( 'ancestor::w:tbl', $p )->length > 0;
			$sname = $this->style_name( $style );
			$num = null;
			$numId = isset( $pi['numId'] ) ? $pi['numId'] : $this->style_prop( $style, 'ppr', 'numId' );
			if ( $numId ) {
				$ilvl = isset( $pi['ilvl'] ) ? $pi['ilvl'] : (int) $this->style_prop( $style, 'ppr', 'ilvl' );
				$lv = $this->list_level( $numId, $ilvl );
				if ( $lv ) {
					$abs = $this->num['nums'][ $numId ]['abs'];
					$key = $abs . ':' . $ilvl;
					if ( ! isset( $counters['seen'][ $numId . ':' . $ilvl ] ) && isset( $this->num['nums'][ $numId ]['override'][ $ilvl ] ) ) {
						$counters[ $key ] = $this->num['nums'][ $numId ]['override'][ $ilvl ] - 1;
					}
					$counters['seen'][ $numId . ':' . $ilvl ] = true;
					$counters[ $key ] = isset( $counters[ $key ] ) ? $counters[ $key ] + 1 : $lv['start'];
					// remise à zéro des niveaux inférieurs
					foreach ( $counters as $k => $v ) { if ( 'seen' !== $k && 0 === strpos( $k, $abs . ':' ) && (int) substr( $k, strlen( $abs ) + 1 ) > $ilvl ) { unset( $counters[ $k ] ); } }
					$num = array( 'numId' => $numId, 'ilvl' => $ilvl, 'fmt' => $lv['fmt'], 'text' => $lv['text'], 'value' => $counters[ $key ] );
				}
			}
			$sectPr = $this->child( $x, $p, 'w:pPr/w:sectPr' );
			$is_toc = (bool) preg_match( '/^toc \d$/', $sname );
			$par = array(
				'i' => $i, 'text' => $text, 'style' => $style, 'sname' => $sname, 'ppr' => $pi, 'runs' => $runs, 'num' => $num,
				'in_table' => $in_table, 'sect' => $sect, 'images' => array(), 'toc' => $is_toc,
				'empty' => ( '' === trim( $text ) ),
			);
			$D['paragraphs'][] = $par;
			if ( ! $in_table ) { $all_text[] = $text; }
			if ( $is_toc ) { $D['toc'][] = array( 'text' => trim( preg_replace( '/\t.*$/s', '', $text ) ), 'level' => (int) substr( $sname, 4 ) ); }
			if ( $sectPr ) { $D['sections'][] = $this->sect( $x, $sectPr ); $sect++; }
		}
		$last = $this->child( $x, $body, 'w:sectPr' );
		if ( $last ) { $D['sections'][] = $this->sect( $x, $last ); }
		// images rattachées aux paragraphes
		foreach ( $D['images'] as $k => $im ) { if ( isset( $D['paragraphs'][ $im['p'] ] ) ) { $D['paragraphs'][ $im['p'] ]['images'][] = $k; } }
		// tableaux
		foreach ( $x->query( '//w:body//w:tbl[not(ancestor::w:tbl)]' ) as $t ) { $D['tables'][] = $this->table( $x, $t ); }
		// zones de texte
		foreach ( $x->query( '//w:txbxContent[not(ancestor::mc:Fallback)]' ) as $tb ) {
			$shape = $x->query( 'ancestor::wps:wsp', $tb );
			$fill = false;
			if ( $shape->length ) { $fill = $x->query( 'wps:spPr/a:solidFill', $shape->item( 0 ) )->length > 0; }
			$D['textboxes'][] = array( 'text' => trim( $tb->textContent ), 'fill' => $fill );
		}
		// page de garde (bloc de construction)
		$D['cover'] = $x->query( '//w:sdt/w:sdtPr/w:docPartObj/w:docPartGallery[@w:val="Cover Pages"]' )->length > 0;
		$D['text'] = implode( "\n", $all_text );
		// en-têtes et pieds de page
		foreach ( $D['sections'] as $si => $s ) {
			foreach ( array( 'headers', 'footers' ) as $kind ) {
				foreach ( $s[ $kind ] as $type => $rid ) {
					if ( isset( $this->rels[ $rid ] ) ) {
						$part = $this->part_info( $this->rels[ $rid ]['target'] );
						$part['section'] = $si; $part['type'] = $type;
						$D[ $kind ][] = $part;
					}
				}
			}
		}
		// styles principaux (formatage effectif du style)
		foreach ( $this->styles as $id => $s ) {
			if ( 'paragraph' !== $s['type'] && 'table' !== $s['type'] ) { continue; }
			$D['styles'][ $s['name'] ] = array(
				'id' => $id, 'type' => $s['type'],
				'font' => $this->style_prop( $id, 'rpr', 'font' ), 'sz' => $this->style_prop( $id, 'rpr', 'sz' ), 'b' => $this->style_prop( $id, 'rpr', 'b' ),
				'i' => $this->style_prop( $id, 'rpr', 'i' ), 'color' => $this->style_prop( $id, 'rpr', 'color' ), 'jc' => $this->style_prop( $id, 'ppr', 'jc' ),
				'before' => $this->style_prop( $id, 'ppr', 'before' ), 'after' => $this->style_prop( $id, 'ppr', 'after' ), 'border' => $this->style_prop( $id, 'ppr', 'border' ),
			);
		}
		$set = $this->zip->getFromName( 'word/settings.xml' );
		if ( $set ) { $D['mailmerge'] = false !== strpos( $set, 'mailMerge' ); }
		$D['defaults'] = $this->defaults;
		$this->d = $D;
	}

	private function add_field( &$D, $instr, &$in_toc ) {
		$instr = trim( $instr );
		if ( '' === $instr ) { return; }
		if ( ! preg_match( '/^([A-Za-z=]+)/', $instr, $m ) ) { return; }
		$code = strtoupper( $m[1] );
		if ( '=' === substr( $instr, 0, 1 ) ) { $code = 'FORMULA'; }
		$D['fields'][] = array( 'code' => $code, 'instr' => $instr );
		if ( 'MERGEFIELD' === $code && preg_match( '/MERGEFIELD\s+"?([^"\\\\]+?)"?(\s|\\\\|$)/u', $instr, $mm ) ) { $D['mergefields'][] = trim( $mm[1] ); }
		if ( 'TOC' === $code ) { $in_toc = true; }
	}

	private function sect( $x, $s ) {
		$pg = $this->child( $x, $s, 'w:pgSz' ); $mar = $this->child( $x, $s, 'w:pgMar' ); $cols = $this->child( $x, $s, 'w:cols' ); $type = $this->child( $x, $s, 'w:type' );
		$w = $pg ? (int) self::wa( $pg, 'w' ) : 11906; $h = $pg ? (int) self::wa( $pg, 'h' ) : 16838;
		$o = array(
			'orient' => ( $pg && 'landscape' === self::wa( $pg, 'orient' ) ) || $w > $h ? 'landscape' : 'portrait',
			'cols' => $cols && self::wa( $cols, 'num' ) ? (int) self::wa( $cols, 'num' ) : 1,
			'sep' => $cols && '1' === self::wa( $cols, 'sep' ) || ( $cols && 'true' === self::wa( $cols, 'sep' ) ),
			'type' => $type ? self::wa( $type, 'val' ) : 'nextPage',
			'titlePg' => (bool) $this->child( $x, $s, 'w:titlePg' ) && false !== self::onoff( $this->child( $x, $s, 'w:titlePg' ) ),
			'margins' => $mar ? array( 'top' => (int) self::wa( $mar, 'top' ), 'bottom' => (int) self::wa( $mar, 'bottom' ), 'left' => (int) self::wa( $mar, 'left' ), 'right' => (int) self::wa( $mar, 'right' ) ) : array(),
			'headers' => array(), 'footers' => array(),
		);
		foreach ( $x->query( 'w:headerReference', $s ) as $r ) { $o['headers'][ self::wa( $r, 'type' ) ] = $r->getAttributeNS( self::NS['r'], 'id' ); }
		foreach ( $x->query( 'w:footerReference', $s ) as $r ) { $o['footers'][ self::wa( $r, 'type' ) ] = $r->getAttributeNS( self::NS['r'], 'id' ); }
		return $o;
	}

	private function part_info( $path ) {
		$xml = $this->zip->getFromName( $path );
		$o = array( 'text' => '', 'images' => 0, 'fields' => array(), 'jc' => array(), 'border' => false );
		if ( ! $xml ) { return $o; }
		$x = $this->xp( $xml );
		$o['text'] = trim( preg_replace( '/\s+/u', ' ', $x->query( '/*' )->item( 0 )->textContent ) );
		$o['images'] = $x->query( '//pic:pic|//v:imagedata' )->length;
		foreach ( $x->query( '//w:instrText|//w:fldSimple/@w:instr' ) as $f ) {
			if ( preg_match( '/^\s*([A-Za-z]+)/', $f->textContent, $m ) ) { $o['fields'][] = strtoupper( $m[1] ); }
		}
		// champs PAGE dans des contrôles de contenu (galerie Numéro de page)
		foreach ( $x->query( '//w:p' ) as $p ) {
			$jc = $this->child( $x, $p, 'w:pPr/w:jc' );
			$ps = $this->child( $x, $p, 'w:pPr/w:pStyle' );
			$j = $jc ? self::wa( $jc, 'val' ) : ( $ps ? $this->style_prop( self::wa( $ps, 'val' ), 'ppr', 'jc' ) : null );
			if ( trim( $p->textContent ) !== '' || $x->query( './/w:instrText|.//w:fldSimple', $p )->length ) { $o['jc'][] = $j ? $j : 'left'; }
			if ( $this->child( $x, $p, 'w:pPr/w:pBdr' ) ) { $o['border'] = true; }
		}
		return $o;
	}

	private function add_drawing( $x, $dr, &$D, $pidx ) {
		$anchor = $this->child( $x, $dr, './/wp:anchor' );
		$inline = $this->child( $x, $dr, './/wp:inline' );
		$box = $anchor ? $anchor : $inline;
		if ( ! $box ) { return; }
		$gd = $this->child( $x, $box, 'a:graphic/a:graphicData' );
		$uri = $gd ? $gd->getAttribute( 'uri' ) : '';
		$ext = $this->child( $x, $box, 'wp:extent' );
		$cx = $ext ? (int) $ext->getAttribute( 'cx' ) / 360000 : 0;
		$cy = $ext ? (int) $ext->getAttribute( 'cy' ) / 360000 : 0;
		$docpr = $this->child( $x, $box, 'wp:docPr' );
		$alt = $docpr ? trim( $docpr->getAttribute( 'descr' ) ) : '';
		if ( false !== strpos( $uri, '/chart' ) ) {
			$c = $this->child( $x, $gd, 'c:chart' );
			$rid = $c ? $c->getAttributeNS( self::NS['r'], 'id' ) : '';
			$info = array( 'type' => '', 'series' => 0, 'points' => 0, 'title' => '', 'labels' => false, 'p' => $pidx, 'cx' => $cx, 'inline' => (bool) $inline );
			if ( $rid && isset( $this->rels[ $rid ] ) ) {
				$cxml = $this->zip->getFromName( $this->rels[ $rid ]['target'] );
				if ( $cxml ) {
					$cx2 = $this->xp( $cxml );
					foreach ( array( 'barChart', 'bar3DChart', 'pieChart', 'pie3DChart', 'doughnutChart', 'lineChart', 'areaChart' ) as $t ) {
						if ( $cx2->query( '//c:plotArea/c:' . $t )->length ) { $info['type'] = $t; break; }
					}
					$bd = $cx2->query( '//c:barDir' );
					if ( $bd->length ) { $info['barDir'] = $bd->item( 0 )->getAttribute( 'val' ); }
					$info['series'] = $cx2->query( '//c:plotArea//c:ser' )->length;
					$pc = $cx2->query( '//c:plotArea//c:ser[1]/c:val//c:ptCount' );
					$info['points'] = $pc->length ? (int) $pc->item( 0 )->getAttribute( 'val' ) : 0;
					$tt = $cx2->query( '//c:chart/c:title//a:t' );
					$tx = ''; foreach ( $tt as $n ) { $tx .= $n->textContent; }
					$info['title'] = $tx;
					$info['title_deleted'] = $cx2->query( '//c:autoTitleDeleted[@val="1"]' )->length > 0;
					$info['labels'] = $cx2->query( '//c:dLbls/c:showVal[@val="1"]|//c:dLbls/c:showPercent[@val="1"]' )->length > 0;
					$info['catlabels'] = $cx2->query( '//c:dLbls/c:showCatName[@val="1"]' )->length > 0;
					$info['legend'] = $cx2->query( '//c:chart/c:legend' )->length > 0;
				}
			}
			$D['charts'][] = $info;
			return;
		}
		if ( false !== strpos( $uri, '/diagram' ) ) {
			$rel = $this->child( $x, $gd, 'dgm:relIds' );
			$txt = '';
			if ( $rel ) {
				$dm = $rel->getAttributeNS( self::NS['r'], 'dm' );
				if ( $dm && isset( $this->rels[ $dm ] ) ) {
					$dxml = $this->zip->getFromName( $this->rels[ $dm ]['target'] );
					if ( $dxml ) { $dx = $this->xp( $dxml ); foreach ( $dx->query( '//a:t' ) as $n ) { $txt .= $n->textContent . ' | '; } }
				}
			}
			$D['smartart'][] = array( 'text' => $txt, 'p' => $pidx );
			return;
		}
		if ( false !== strpos( $uri, 'wordprocessingShape' ) ) { return; } // zones de texte : traitées à part
		if ( false === strpos( $uri, '/picture' ) ) { return; }
		$sp = $this->child( $x, $gd, './/pic:spPr' );
		$geom = $sp ? $this->child( $x, $sp, 'a:prstGeom' ) : null;
		$ln = $sp ? $this->child( $x, $sp, 'a:ln' ) : null;
		$ln_fill = $ln ? $this->child( $x, $ln, 'a:solidFill/a:srgbClr|a:solidFill/a:schemeClr' ) : null;
		$src = $this->child( $x, $gd, './/a:srcRect' );
		$crop = false;
		if ( $src ) { foreach ( array( 'l', 't', 'r', 'b' ) as $k ) { if ( (int) $src->getAttribute( $k ) > 0 ) { $crop = true; } } }
		$wrap = 'inline';
		$halign = null;
		$behind = false;
		if ( $anchor ) {
			foreach ( array( 'wrapSquare' => 'square', 'wrapTight' => 'tight', 'wrapThrough' => 'through', 'wrapTopAndBottom' => 'topbottom', 'wrapNone' => 'none' ) as $t => $v ) {
				if ( $this->child( $x, $anchor, 'wp:' . $t ) ) { $wrap = $v; }
			}
			$behind = '1' === $anchor->getAttribute( 'behindDoc' );
			$al = $this->child( $x, $anchor, 'wp:positionH/wp:align' );
			if ( $al ) { $halign = $al->textContent; }
			else {
				$off = $this->child( $x, $anchor, 'wp:positionH/wp:posOffset' );
				$rel = $this->child( $x, $anchor, 'wp:positionH' );
				if ( $off ) {
					$o = (int) $off->textContent / 360000;
					$relf = $rel ? $rel->getAttribute( 'relativeFrom' ) : 'column';
					$base = in_array( $relf, array( 'page' ), true ) ? 2.5 : 0;
					$o -= $base;
					$halign = ( $o + $cx / 2 > 8 ) ? 'right' : 'left';
				}
			}
		}
		$D['images'][] = array(
			'p' => $pidx, 'inline' => ! $anchor, 'wrap' => $wrap, 'behind' => $behind, 'cx' => round( $cx, 2 ), 'cy' => round( $cy, 2 ), 'alt' => $alt,
			'geom' => $geom ? $geom->getAttribute( 'prst' ) : 'rect', 'crop' => $crop,
			'border' => (bool) $ln_fill, 'border_color' => $ln_fill ? strtoupper( (string) $ln_fill->getAttribute( 'val' ) ) : '',
			'shadow' => $sp ? $x->query( './/a:outerShdw', $sp )->length > 0 : false, 'halign' => $halign,
		);
	}

	private function table( $x, $t ) {
		$rows = array();
		$merged = false; $header = false; $heights = array(); $jcs = array(); $bolds = array();
		foreach ( $x->query( 'w:tr', $t ) as $ri => $tr ) {
			$cells = array(); $cjc = array(); $cb = array();
			if ( $x->query( 'w:trPr/w:tblHeader', $tr )->length && 0 === $ri ) { $header = true; }
			$h = $this->child( $x, $tr, 'w:trPr/w:trHeight' );
			$heights[] = $h ? (int) self::wa( $h, 'val' ) : 0;
			foreach ( $x->query( 'w:tc', $tr ) as $tc ) {
				$gs = $this->child( $x, $tc, 'w:tcPr/w:gridSpan' );
				$span = $gs ? (int) self::wa( $gs, 'val' ) : 1;
				if ( $span > 1 ) { $merged = true; }
				$vm = $this->child( $x, $tc, 'w:tcPr/w:vMerge' );
				if ( $vm ) { $merged = true; }
				$txt = '';
				$ps = $x->query( './/w:p', $tc );
				$pj = array();
				$allb = null;
				foreach ( $ps as $p ) {
					$tx = '';
					foreach ( $x->query( './/w:t', $p ) as $n ) { $tx .= $n->textContent; }
					$txt .= ( '' !== $txt ? "\n" : '' ) . $tx;
					$jc = $this->child( $x, $p, 'w:pPr/w:jc' );
					if ( '' !== trim( $tx ) ) { $pj[] = $jc ? self::wa( $jc, 'val' ) : null; }
					foreach ( $x->query( './/w:r[w:t]', $p ) as $r ) {
						if ( '' === trim( $r->textContent ) ) { continue; }
						$b = $this->child( $x, $r, 'w:rPr/w:b' );
						$isb = $b ? self::onoff( $b ) : false;
						$allb = ( null === $allb ) ? $isb : ( $allb && $isb );
					}
				}
				$w = $this->child( $x, $tc, 'w:tcPr/w:tcW' );
				$cells[] = array( 'text' => trim( $txt ), 'span' => $span, 'w' => $w ? (int) self::wa( $w, 'w' ) : 0, 'wtype' => $w ? self::wa( $w, 'type' ) : '', 'jc' => $pj ? $pj[0] : null, 'b' => (bool) $allb,
					'valign' => ( $va = $this->child( $x, $tc, 'w:tcPr/w:vAlign' ) ) ? self::wa( $va, 'val' ) : 'top', 'formula' => false );
				foreach ( $x->query( './/w:instrText|.//w:fldSimple', $tc ) as $f ) {
					$s = 'fldSimple' === $f->localName ? $f->getAttributeNS( self::W, 'instr' ) : $f->textContent;
					if ( false !== stripos( $s, 'SUM' ) || 0 === strpos( trim( $s ), '=' ) ) { $cells[ count( $cells ) - 1 ]['formula'] = trim( $s ); }
				}
			}
			$rows[] = $cells;
		}
		$grid = array();
		foreach ( $x->query( 'w:tblGrid/w:gridCol', $t ) as $g ) { $grid[] = (int) self::wa( $g, 'w' ); }
		$st = $this->child( $x, $t, 'w:tblPr/w:tblStyle' );
		$jc = $this->child( $x, $t, 'w:tblPr/w:jc' );
		$lk = $this->child( $x, $t, 'w:tblPr/w:tblLook' );
		$look = array();
		if ( $lk ) {
			$hex = self::wa( $lk, 'val' );
			$h = $hex ? hexdec( $hex ) : null;
			foreach ( array( 'firstRow' => 0x20, 'lastRow' => 0x40, 'firstColumn' => 0x80, 'lastColumn' => 0x100, 'noHBand' => 0x200, 'noVBand' => 0x400 ) as $k => $bit ) {
				$v = self::wa( $lk, $k );
				$look[ $k ] = ( null !== $v ) ? ( '1' === $v || 'true' === $v ) : ( null !== $h ? (bool) ( $h & $bit ) : false );
			}
		}
		$tw = $this->child( $x, $t, 'w:tblPr/w:tblW' );
		$sid = $st ? self::wa( $st, 'val' ) : '';
		return array(
			'rows' => $rows, 'nrows' => count( $rows ), 'ncols' => count( $grid ) ? count( $grid ) : ( $rows ? count( $rows[0] ) : 0 ), 'grid' => $grid,
			'style' => $sid, 'style_name' => $this->style_name( $sid ), 'jc' => $jc ? self::wa( $jc, 'val' ) : 'left', 'header' => $header, 'merged' => $merged,
			'look' => $look, 'heights' => $heights, 'tblW' => $tw ? array( 'w' => (int) self::wa( $tw, 'w' ), 'type' => self::wa( $tw, 'type' ) ) : null,
		);
	}

	/* ------------------------------------------------------------ résumé texte (pour l'export IA) */
	public function summary() {
		$D = $this->d;
		$out = array();
		foreach ( $D['paragraphs'] as $p ) {
			if ( $p['in_table'] ) { continue; }
			$t = $p['text'];
			if ( '' === trim( $t ) && ! $p['images'] ) { $out[] = '[¶ vide]'; continue; }
			$attrs = array( 'style=' . $p['sname'] );
			if ( isset( $p['ppr']['jc'] ) ) { $attrs[] = 'align=' . $p['ppr']['jc']; }
			foreach ( array( 'ind_left' => 'retrait_g', 'ind_right' => 'retrait_d', 'ind_firstLine' => 'retrait_1re', 'ind_hanging' => 'retrait_susp' ) as $k => $l ) { if ( ! empty( $p['ppr'][ $k ] ) ) { $attrs[] = $l . '=' . round( $p['ppr'][ $k ] / 567, 2 ) . 'cm'; } }
			foreach ( array( 'before' => 'avant', 'after' => 'apres' ) as $k => $l ) { if ( isset( $p['ppr'][ $k ] ) ) { $attrs[] = $l . '=' . round( $p['ppr'][ $k ] / 20, 1 ) . 'pt'; } }
			if ( ! empty( $p['ppr']['tabs'] ) ) { $attrs[] = 'tabs=' . implode( ',', array_map( function ( $t ) { return $t['val'] . '@' . round( $t['pos'] / 567, 2 ) . 'cm' . ( $t['leader'] && 'none' !== $t['leader'] ? '+' . $t['leader'] : '' ); }, $p['ppr']['tabs'] ) ); }
			if ( ! empty( $p['ppr']['border'] ) ) { $attrs[] = 'bordure=' . implode( '/', array_keys( $p['ppr']['border'] ) ); }
			if ( ! empty( $p['ppr']['shd'] ) ) { $attrs[] = 'trame=' . $p['ppr']['shd']; }
			if ( ! empty( $p['ppr']['pageBreakBefore'] ) || ! empty( $p['ppr']['pageBreakInside'] ) ) { $attrs[] = 'saut_de_page'; }
			if ( $p['num'] ) { $attrs[] = 'liste=' . $p['num']['fmt'] . '(' . $p['num']['text'] . ') niv' . $p['num']['ilvl'] . ' n°' . $p['num']['value']; }
			if ( $p['images'] ) { $attrs[] = 'images=' . count( $p['images'] ); }
			$fmts = array();
			foreach ( $p['runs'] as $r ) {
				$f = array();
				foreach ( array( 'b' => 'gras', 'i' => 'ital', 'caps' => 'MAJ', 'smallCaps' => 'pt-maj' ) as $k => $l ) { if ( ! empty( $r[ $k ] ) ) { $f[] = $l; } }
				foreach ( array( 'font', 'color', 'highlight', 'vert' ) as $k ) { if ( ! empty( $r[ $k ] ) ) { $f[] = $r[ $k ]; } }
				if ( ! empty( $r['sz'] ) ) { $f[] = ( $r['sz'] / 2 ) . 'pt'; }
				if ( ! empty( $r['u'] ) ) { $f[] = 'soul'; }
				if ( $f ) { $fmts[] = '«' . mb_substr( $r['t'], 0, 40 ) . '»:' . implode( ' ', $f ); }
			}
			$line = '[' . implode( ' ', $attrs ) . '] ' . str_replace( "\t", '→', $t );
			if ( $fmts ) { $line .= '   {' . implode( ' ; ', array_slice( $fmts, 0, 8 ) ) . '}'; }
			$out[] = $line;
		}
		return implode( "\n", $out );
	}

	public function to_json() {
		$D = $this->d;
		unset( $D['text'] );
		foreach ( $D['paragraphs'] as &$p ) { unset( $p['i'] ); }
		return $D;
	}
}
