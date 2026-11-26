<?php
/**
 * LogiInfo – lecture d'un classeur .xlsx (valeurs, formules, formats, mises en forme, objets)
 * pour le contrôle automatique des exercices Excel. Aucune dépendance : ZipArchive + expressions régulières.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'LI_CLI' ) ) { exit; }

class LI_Xlsx_Model {
	public $ok = false;
	public $error = '';
	public $sheets = array();   // nom => tableau de la feuille
	public $order = array();    // noms dans l'ordre
	public $pivots = 0;
	private $z;
	private $xf = array();
	private $ss = array();

	const BUILTIN_FMT = array( 0 => 'General', 1 => '0', 2 => '0.00', 3 => '#,##0', 4 => '#,##0.00', 9 => '0%', 10 => '0.00%', 11 => '0.00E+00', 12 => '# ?/?', 13 => '# ??/??',
		14 => 'dd.mm.yyyy', 15 => 'd-mmm-yy', 16 => 'd-mmm', 17 => 'mmm-yy', 18 => 'h:mm AM/PM', 19 => 'h:mm:ss AM/PM', 20 => 'h:mm', 21 => 'h:mm:ss', 22 => 'dd.mm.yyyy h:mm',
		37 => '#,##0 ;(#,##0)', 38 => '#,##0 ;[Red](#,##0)', 39 => '#,##0.00;(#,##0.00)', 40 => '#,##0.00;[Red](#,##0.00)', 45 => 'mm:ss', 46 => '[h]:mm:ss', 47 => 'mmss.0', 48 => '##0.0E+0', 49 => '@' );

	public function __construct( $path ) {
		$this->z = new ZipArchive();
		if ( true !== $this->z->open( $path ) || false === $this->z->locateName( 'xl/workbook.xml' ) ) { $this->error = 'classeur Excel illisible'; return; }
		$this->strings();
		$this->styles();
		$this->workbook();
		$this->z->close();
		$this->ok = true;
	}

	private function get( $p ) { $s = $this->z->getFromName( $p ); return false === $s ? '' : $s; }
	private static function attr( $tag, $name ) { return preg_match( '/\b' . $name . '="([^"]*)"/', $tag, $m ) ? html_entity_decode( $m[1], ENT_QUOTES | ENT_XML1, 'UTF-8' ) : null; }
	private static function dec( $s ) { return html_entity_decode( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' ); }

	private function rels( $part ) {
		$dir = dirname( $part ); $rp = $dir . '/_rels/' . basename( $part ) . '.rels';
		$out = array();
		if ( preg_match_all( '/<Relationship\b[^>]*>/', $this->get( $rp ), $m ) ) {
			foreach ( $m[0] as $t ) {
				$target = self::attr( $t, 'Target' );
				$path = '/' === $target[0] ? ltrim( $target, '/' ) : self::norm( $dir . '/' . $target );
				$out[ self::attr( $t, 'Id' ) ] = array( 'type' => basename( (string) self::attr( $t, 'Type' ) ), 'path' => $path );
			}
		}
		return $out;
	}
	private static function norm( $p ) {
		$o = array();
		foreach ( explode( '/', $p ) as $s ) { if ( '..' === $s ) { array_pop( $o ); } elseif ( '' !== $s && '.' !== $s ) { $o[] = $s; } }
		return implode( '/', $o );
	}

	private function strings() {
		if ( preg_match_all( '#<(?:\w+:)?si\b[^>]*>(.*?)</(?:\w+:)?si>#s', $this->get( 'xl/sharedStrings.xml' ), $m ) ) {
			foreach ( $m[1] as $si ) {
				$si = preg_replace( '#<(?:\w+:)?(rPh|phoneticPr)\b.*?(</(?:\w+:)?\1>|/>)#s', '', $si );
				preg_match_all( '#<(?:\w+:)?t\b[^>]*>(.*?)</(?:\w+:)?t>#s', $si, $t );
				$this->ss[] = self::dec( implode( '', $t[1] ) );
			}
		}
	}

	private function styles() {
		$x = $this->get( 'xl/styles.xml' );
		$fmts = self::BUILTIN_FMT;
		if ( preg_match_all( '/<(?:\w+:)?numFmt\b[^>]*>/', $x, $m ) ) { foreach ( $m[0] as $t ) { $fmts[ (int) self::attr( $t, 'numFmtId' ) ] = self::attr( $t, 'formatCode' ); } }
		$fonts = array(); $fills = array(); $borders = array();
		if ( preg_match( '#<(?:\w+:)?fonts\b.*?</(?:\w+:)?fonts>#s', $x, $b ) && preg_match_all( '#<(?:\w+:)?font\b[^>]*?(?:/>|>(.*?)</(?:\w+:)?font>)#s', $b[0], $m ) ) {
			foreach ( $m[1] as $f ) {
				$off = function ( $tag ) use ( $f ) { return preg_match( '#<(?:\w+:)?' . $tag . '\b([^>]*)/?>#', $f, $mm ) && ! preg_match( '/val="(0|false)"/', $mm[1] ); };
				$fonts[] = array( 'bold' => $off( 'b' ), 'italic' => $off( 'i' ) );
			}
		}
		if ( preg_match( '#<(?:\w+:)?fills\b.*?</(?:\w+:)?fills>#s', $x, $b ) && preg_match_all( '#<(?:\w+:)?fill\b[^>]*?(?:/>|>(.*?)</(?:\w+:)?fill>)#s', $b[0], $m ) ) {
			foreach ( $m[1] as $f ) { $fills[] = (bool) preg_match( '/patternType="(?!none|gray125)[^"]+"|<(?:\w+:)?gradientFill/', $f ); }
		}
		if ( preg_match( '#<(?:\w+:)?borders\b.*?</(?:\w+:)?borders>#s', $x, $b ) && preg_match_all( '#<(?:\w+:)?border\b[^>]*?(?:/>|>(.*?)</(?:\w+:)?border>)#s', $b[0], $m ) ) {
			foreach ( $m[1] as $f ) { $borders[] = (bool) preg_match( '/<(?:\w+:)?(left|right|top|bottom)\b[^>]*style="(?!none)[^"]+"/', $f ); }
		}
		if ( preg_match( '#<(?:\w+:)?cellXfs\b.*?</(?:\w+:)?cellXfs>#s', $x, $b ) && preg_match_all( '#<(?:\w+:)?xf\b([^>]*?)(?:/>|>(.*?)</(?:\w+:)?xf>)#s', $b[0], $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $xf ) {
				$a = $xf[1]; $in = isset( $xf[2] ) ? $xf[2] : '';
				$nf = (int) self::attr( $a, 'numFmtId' );
				$fo = $fonts[ (int) self::attr( $a, 'fontId' ) ] ?? array( 'bold' => false, 'italic' => false );
				$al = preg_match( '#<(?:\w+:)?alignment\b[^>]*>#', $in, $am ) ? $am[0] : '';
				$this->xf[] = array(
					'fmt'    => $fmts[ $nf ] ?? 'General',
					'bold'   => $fo['bold'],
					'italic' => $fo['italic'],
					'fill'   => $fills[ (int) self::attr( $a, 'fillId' ) ] ?? false,
					'border' => $borders[ (int) self::attr( $a, 'borderId' ) ] ?? false,
					'halign' => $al ? (string) self::attr( $al, 'horizontal' ) : '',
					'wrap'   => $al && in_array( self::attr( $al, 'wrapText' ), array( '1', 'true' ), true ),
				);
			}
		}
	}

	private function workbook() {
		$wb = $this->get( 'xl/workbook.xml' );
		$rels = $this->rels( 'xl/workbook.xml' );
		$titles = array();
		if ( preg_match_all( '#<(?:\w+:)?definedName\b([^>]*)>(.*?)</(?:\w+:)?definedName>#s', $wb, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $d ) { if ( '_xlnm.Print_Titles' === self::attr( $d[1], 'name' ) ) { $titles[ (int) self::attr( $d[1], 'localSheetId' ) ] = self::dec( $d[2] ); } }
		}
		preg_match_all( '#<(?:\w+:)?sheet\b[^>]*>#', $wb, $m );
		foreach ( $m[0] as $i => $t ) {
			$name = self::attr( $t, 'name' );
			$rid = preg_match( '/\br:id="([^"]+)"|\bid="([^"]+)"/', $t, $mm ) ? ( $mm[1] ?: $mm[2] ) : '';
			if ( ! isset( $rels[ $rid ] ) || 'worksheet' !== $rels[ $rid ]['type'] ) { continue; }
			$sh = $this->sheet( $rels[ $rid ]['path'] );
			$sh['state'] = (string) self::attr( $t, 'state' );
			$sh['print_titles'] = $titles[ $i ] ?? '';
			$this->sheets[ $name ] = $sh;
			$this->order[] = $name;
		}
	}

	private function value( $c, $inner ) {
		$t = (string) self::attr( $c, 't' );
		$v = preg_match( '#<(?:\w+:)?v>(.*?)</(?:\w+:)?v>#s', $inner, $m ) ? self::dec( $m[1] ) : null;
		if ( 'inlineStr' === $t ) { preg_match_all( '#<(?:\w+:)?t\b[^>]*>(.*?)</(?:\w+:)?t>#s', $inner, $tt ); return self::dec( implode( '', $tt[1] ) ); }
		if ( null === $v ) { return null; }
		if ( 's' === $t ) { return $this->ss[ (int) $v ] ?? ''; }
		if ( 'b' === $t ) { return (bool) $v; }
		if ( 'str' === $t || 'e' === $t ) { return $v; }
		return is_numeric( $v ) ? (float) $v : $v;
	}

	private function sheet( $part ) {
		$x = $this->get( $part );
		$sh = array( 'cells' => array(), 'merges' => array(), 'frozen' => '', 'autofilter' => '', 'cf' => array(), 'dv' => array(), 'tables' => 0, 'charts' => array(),
			'pivots' => 0, 'table_refs' => array(), 'orientation' => '', 'fit' => false, 'hidden_rows' => 0 );
		if ( preg_match( '#<(?:\w+:)?sheetData\b[^>]*>(.*)</(?:\w+:)?sheetData>#s', $x, $sd ) ) {
			preg_match_all( '#<(?:\w+:)?row\b([^>]*)>#', $sd[1], $rows );
			foreach ( $rows[1] as $ra ) { if ( '1' === self::attr( $ra, 'hidden' ) ) { $sh['hidden_rows']++; } }
			if ( preg_match_all( '#<(?:\w+:)?c\b([^>]*?)(?:/>|>(.*?)</(?:\w+:)?c>)#s', $sd[1], $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $c ) {
					$ref = self::attr( $c[1], 'r' );
					if ( ! $ref ) { continue; }
					$in = isset( $c[2] ) ? $c[2] : '';
					$f = preg_match( '#<(?:\w+:)?f\b[^>]*?(?:/>|>(.*?)</(?:\w+:)?f>)#s', $in, $fm ) ? ( isset( $fm[1] ) ? self::dec( $fm[1] ) : '' ) : null;
					$sh['cells'][ $ref ] = array( 'v' => $this->value( $c[1], $in ), 'f' => $f, 's' => (int) self::attr( $c[1], 's' ) );
				}
			}
		}
		if ( preg_match_all( '#<(?:\w+:)?mergeCell\b[^>]*>#', $x, $m ) ) { foreach ( $m[0] as $t ) { $sh['merges'][] = self::attr( $t, 'ref' ); } }
		if ( preg_match( '#<(?:\w+:)?pane\b[^>]*>#', $x, $m ) && preg_match( '/state="frozen(Split)?"/', $m[0] ) ) { $sh['frozen'] = (string) self::attr( $m[0], 'topLeftCell' ); }
		if ( preg_match( '#<(?:\w+:)?autoFilter\b[^>]*>#', $x, $m ) ) { $sh['autofilter'] = (string) self::attr( $m[0], 'ref' ); }
		if ( preg_match_all( '#<(?:\w+:)?conditionalFormatting\b([^>]*)>(.*?)</(?:\w+:)?conditionalFormatting>#s', $x, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $cf ) {
				$sq = self::attr( $cf[1], 'sqref' );
				if ( ! $sq && preg_match( '#<xm:sqref>(.*?)</xm:sqref>#', $cf[2], $q ) ) { $sq = $q[1]; }
				preg_match_all( '/<(?:\w+:)?cfRule\b[^>]*\btype="([^"]+)"/', $cf[2], $ty );
				foreach ( $ty[1] as $type ) { $sh['cf'][] = array( 'sqref' => (string) $sq, 'type' => $type ); }
			}
		}
		if ( preg_match_all( '#<(?:\w+:)?dataValidation\b([^>]*)>(.*?)</(?:\w+:)?dataValidation>#s', $x, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $dv ) {
				$sq = self::attr( $dv[1], 'sqref' );
				if ( ! $sq && preg_match( '#<xm:sqref>(.*?)</xm:sqref>#', $dv[2], $q ) ) { $sq = $q[1]; }
				$sh['dv'][] = array( 'sqref' => (string) $sq, 'type' => (string) self::attr( $dv[1], 'type' ) );
			}
		}
		if ( preg_match( '#<(?:\w+:)?pageSetup\b[^>]*>#', $x, $m ) ) { $sh['orientation'] = (string) self::attr( $m[0], 'orientation' ); }
		$sh['fit'] = (bool) preg_match( '/fitToPage="(1|true)"/', $x );
		preg_match_all( '#<(?:\w+:)?tablePart\b#', $x, $m ); $sh['tables'] = count( $m[0] );
		foreach ( $this->rels( $part ) as $r ) {
			if ( 'pivotTable' === $r['type'] ) { $sh['pivots']++; $this->pivots++; }
			if ( 'table' === $r['type'] && preg_match( '#<(?:\w+:)?table\b[^>]*>#', $this->get( $r['path'] ), $tm ) ) { $sh['table_refs'][] = (string) self::attr( $tm[0], 'ref' ); }
			if ( 'drawing' === $r['type'] ) {
				foreach ( $this->rels( $r['path'] ) as $d ) {
					if ( 'chart' !== $d['type'] ) { continue; }
					$cx = $this->get( $d['path'] );
					if ( preg_match( '#<(?:\w+:)?plotArea\b.*?<(?:\w+:)?(\w+Chart)\b#s', $cx, $ct ) ) {
						$type = $ct[1];
						if ( 'barChart' === $type && preg_match( '/<(?:\w+:)?barDir val="(\w+)"/', $cx, $bd ) ) { $type .= ':' . $bd[1]; }
						$sh['charts'][] = $type;
					}
				}
			}
		}
		return $sh;
	}

	/* ------------------------------------------------------------ utilitaires communs */
	public function style( $sheet, $ref ) {
		$c = $this->sheets[ $sheet ]['cells'][ $ref ] ?? null;
		return $this->xf[ $c ? $c['s'] : 0 ] ?? array( 'fmt' => 'General', 'bold' => false, 'italic' => false, 'fill' => false, 'border' => false, 'halign' => '', 'wrap' => false );
	}
	public function cell( $sheet, $ref ) { return $this->sheets[ $sheet ]['cells'][ $ref ] ?? null; }

	/** Catégorie d'un format de nombre : pourcentage, date, heure, monétaire, texte, nombre ; décimales. */
	public static function fmt_class( $code ) {
		$c = preg_replace( '/"[^"]*"|\\\\.|\[(?!\$)[^\]]*\]/', '', (string) $code );
		$first = explode( ';', $c )[0];
		$dec = preg_match( '/\.(0+)/', $first, $m ) ? strlen( $m[1] ) : 0;
		if ( '@' === trim( $first ) ) { return 'texte'; }
		if ( false !== strpos( $first, '%' ) ) { return 'pourcent:' . $dec; }
		if ( preg_match( '/[dy]|m(?![^\[]*\])/i', preg_replace( '/\[\$[^\]]*\]/', '', $first ) ) && ! preg_match( '/^[#0,.\' ]+$/', $first ) ) {
			return preg_match( '/[hs]/i', $first ) && ! preg_match( '/[dy]/i', $first ) ? 'heure' : 'date';
		}
		if ( preg_match( '/[hs]:|:[ms]/i', $first ) ) { return 'heure'; }
		$cur = preg_match( '/CHF|€|\$|\[\$/', (string) $code ) ? 'monnaie' : 'nombre';
		if ( 'General' === $first || 'Standard' === $first ) { return 'standard'; }
		return $cur . ':' . $dec;
	}

	public static function split_ref( $ref ) { preg_match( '/^\$?([A-Z]+)\$?(\d+)$/', $ref, $m ); return array( $m[1], (int) $m[2] ); }
	public static function col_num( $col ) { $n = 0; foreach ( str_split( $col ) as $ch ) { $n = $n * 26 + ord( $ch ) - 64; } return $n; }
	/** Plage A1:B3 → liste de cellules (limité à 5000). */
	public static function cells_of( $sqref ) {
		$out = array();
		foreach ( preg_split( '/\s+/', trim( (string) $sqref ) ) as $rg ) {
			$p = explode( ':', str_replace( '$', '', $rg ) );
			if ( ! preg_match( '/^[A-Z]+\d+$/', $p[0] ) ) { continue; }
			list( $c1, $r1 ) = self::split_ref( $p[0] ); list( $c2, $r2 ) = isset( $p[1] ) && preg_match( '/^[A-Z]+\d+$/', $p[1] ) ? self::split_ref( $p[1] ) : array( $c1, $r1 );
			for ( $c = self::col_num( $c1 ); $c <= self::col_num( $c2 ); $c++ ) {
				for ( $r = $r1; $r <= $r2; $r++ ) { $out[] = self::col_letter( $c ) . $r; if ( count( $out ) > 5000 ) { return $out; } }
			}
		}
		return $out;
	}
	public static function col_letter( $n ) { $s = ''; while ( $n > 0 ) { $m = ( $n - 1 ) % 26; $s = chr( 65 + $m ) . $s; $n = intdiv( $n - 1, 26 ); } return $s; }
}
