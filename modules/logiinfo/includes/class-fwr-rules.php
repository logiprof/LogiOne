<?php
/**
 * Critères de contrôle automatique pour les 15 exercices de la formation Word.
 * Chaque critère : array( libellé, points, fonction( $D, $docs ) -> bool|float 0..1 ).
 * $D = structure du fichier principal (LIR_Docx->d), $docs = toutes les structures déposées.
 */
if ( ! defined( 'LIR_STANDALONE' ) && ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Rules {

	/* ================================================================ utilitaires */
	public static function n( $s ) {
		$s = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF", '’', '‘', '–', '—' ), array( ' ', ' ', "'", "'", '-', '-' ), (string) $s );
		$s = preg_replace( '/[ \t\n\r]+/u', ' ', $s );
		return mb_strtolower( trim( $s ), 'UTF-8' );
	}
	public static function body( $D ) { return array_values( array_filter( $D['paragraphs'], function ( $p ) { return ! $p['in_table'] && ! $p['toc']; } ) ); }
	public static function P( $D, $needle, $k = 0 ) {
		$all = self::PS( $D, $needle );
		return isset( $all[ $k ] ) ? $all[ $k ] : null;
	}
	public static function PS( $D, $needle ) {
		$n = self::n( $needle );
		$out = array();
		foreach ( self::body( $D ) as $p ) { if ( '' !== $n && false !== mb_strpos( self::n( $p['text'] ), $n ) ) { $out[] = $p; } }
		return $out;
	}
	public static function idx( $D, $needle ) { $p = self::P( $D, $needle ); return $p ? $p['i'] : -1; }
	public static function count( $D, $needle ) { return count( self::PS( $D, $needle ) ); }
	public static function sn( $p ) { return $p ? $p['sname'] : ''; }
	public static function is_style( $p, $name ) { return $p && self::sn( $p ) === $name; }
	public static function styled( $D, $name, $needles ) {
		$ok = 0;
		foreach ( $needles as $t ) { $p = self::heading( $D, $t ); if ( self::is_style( $p, $name ) ) { $ok++; } }
		return $ok / max( 1, count( $needles ) );
	}
	/** paragraphe dont le texte ÉGALE la chaîne (utile pour les titres courts) */
	public static function heading( $D, $t ) {
		$n = self::n( $t );
		foreach ( self::body( $D ) as $p ) { if ( self::n( $p['text'] ) === $n ) { return $p; } }
		return self::P( $D, $t );
	}
	public static function eff( $D, $p, $key ) {
		if ( ! $p ) { return null; }
		if ( isset( $p['ppr'][ $key ] ) ) { return $p['ppr'][ $key ]; }
		$st = isset( $D['styles'][ $p['sname'] ] ) ? $D['styles'][ $p['sname'] ] : null;
		if ( $st && isset( $st[ $key ] ) && null !== $st[ $key ] ) { return $st[ $key ]; }
		if ( 'after' === $key || 'before' === $key ) { return isset( $D['defaults'][ $key ] ) ? $D['defaults'][ $key ] : 0; }
		if ( 'jc' === $key ) { return 'left'; }
		return 0;
	}
	public static function jc( $D, $p ) { $j = self::eff( $D, $p, 'jc' ); return ( 'both' === $j || 'distribute' === $j ) ? 'both' : ( $j ? $j : 'left' ); }
	public static function ind( $p, $k ) { return ( $p && isset( $p['ppr'][ 'ind_' . $k ] ) ) ? $p['ppr'][ 'ind_' . $k ] / 567 : 0; }
	public static function near( $a, $b, $tol ) { return null !== $a && abs( $a - $b ) <= $tol; }
	public static function pt( $D, $p, $key ) { $v = self::eff( $D, $p, $key ); return null === $v ? null : $v / 20; }

	/** propriété de caractère sur les runs couvrant $needle dans $p */
	public static function runs_of( $p, $needle = null ) {
		if ( ! $p ) { return array(); }
		if ( null === $needle ) { return array_values( array_filter( $p['runs'], function ( $r ) { return '' !== trim( $r['t'] ); } ) ); }
		$full = ''; $map = array();
		foreach ( $p['runs'] as $k => $r ) { $start = mb_strlen( $full ); $full .= $r['t']; $map[] = array( $start, mb_strlen( $full ), $k ); }
		$pos = mb_stripos( self::n2( $full ), self::n2( $needle ) );
		if ( false === $pos ) { return array(); }
		$end = $pos + mb_strlen( self::n2( $needle ) );
		$out = array();
		foreach ( $map as $m ) { if ( $m[1] > $pos && $m[0] < $end && '' !== trim( $p['runs'][ $m[2] ]['t'] ) ) { $out[] = $p['runs'][ $m[2] ]; } }
		return $out;
	}
	private static function n2( $s ) { return str_replace( array( "\xC2\xA0", '’', '–' ), array( ' ', "'", '-' ), $s ); }
	public static function rp( $D, $p, $needle, $prop, $val = true ) {
		$runs = self::runs_of( $p, $needle );
		if ( ! $runs ) { return false; }
		foreach ( $runs as $r ) {
			$v = isset( $r[ $prop ] ) ? $r[ $prop ] : self::style_run_default( $D, $p, $prop );
			if ( 'font' === $prop ) { if ( false === stripos( (string) $v, $val ) ) { return false; } continue; }
			if ( 'color' === $prop || 'shd' === $prop ) { if ( strtoupper( (string) $v ) !== strtoupper( $val ) ) { return false; } continue; }
			if ( true === $val ) { if ( empty( $v ) ) { return false; } continue; }
			if ( false === $val ) { if ( ! empty( $v ) ) { return false; } continue; }
			if ( (string) $v !== (string) $val ) { return false; }
		}
		return true;
	}
	public static function style_run_default( $D, $p, $prop ) {
		$st = isset( $D['styles'][ $p['sname'] ] ) ? $D['styles'][ $p['sname'] ] : array();
		if ( isset( $st[ $prop ] ) && null !== $st[ $prop ] ) { return $st[ $prop ]; }
		if ( 'sz' === $prop ) { return isset( $D['defaults']['sz'] ) ? $D['defaults']['sz'] : 24; }
		if ( 'font' === $prop ) { return isset( $D['defaults']['font'] ) ? $D['defaults']['font'] : 'Aptos'; }
		return null;
	}
	public static function tabs_ok( $p, $want ) {
		// $want : liste de [type, cm, leader|null]
		if ( ! $p || empty( $p['ppr']['tabs'] ) ) { return false; }
		foreach ( $want as $w ) {
			$found = false;
			foreach ( $p['ppr']['tabs'] as $t ) {
				$type = ( 'start' === $t['val'] ) ? 'left' : ( ( 'end' === $t['val'] ) ? 'right' : $t['val'] );
				if ( 'clear' === $type ) { continue; }
				if ( $type === $w[0] && abs( $t['pos'] / 567 - $w[1] ) <= 0.2 ) {
					if ( null === $w[2] || ( $t['leader'] && $t['leader'] === $w[2] ) ) { $found = true; }
				}
			}
			if ( ! $found ) { return false; }
		}
		return true;
	}
	public static function ratio( $items, $fn ) {
		$items = array_values( array_filter( $items ) );
		if ( ! $items ) { return 0; }
		$ok = 0; foreach ( $items as $it ) { if ( $fn( $it ) ) { $ok++; } }
		return $ok / count( $items );
	}
	public static function each( $D, $needles, $fn ) {
		$ps = array(); foreach ( $needles as $t ) { $ps[] = self::P( $D, $t ); }
		$ok = 0; foreach ( $ps as $p ) { if ( $p && $fn( $p ) ) { $ok++; } }
		return $ok / max( 1, count( $needles ) );
	}
	public static function empty_pars( $D ) {
		$n = 0;
		foreach ( self::body( $D ) as $k => $p ) {
			if ( $p['empty'] && ! $p['images'] && empty( $p['ppr']['pageBreakInside'] ) && ! self::has_obj( $D, $p ) ) { $n++; }
		}
		// le dernier ¶ du document est toujours présent
		$b = self::body( $D );
		if ( $b && end( $b )['empty'] ) { $n = max( 0, $n - 1 ); }
		return $n;
	}
	public static function has_obj( $D, $p ) {
		foreach ( array( 'charts', 'smartart' ) as $k ) { foreach ( $D[ $k ] as $c ) { if ( $c['p'] === $p['i'] ) { return true; } } }
		return false;
	}
	public static function page_break_before( $D, $p ) {
		if ( ! $p ) { return false; }
		if ( ! empty( $p['ppr']['pageBreakBefore'] ) || ! empty( $p['ppr']['pageBreakInside'] ) ) { return true; }
		// saut de page dans un ¶ précédent (sans texte entre les deux) ou saut de section page suivante
		$all = $D['paragraphs'];
		for ( $k = $p['i'] - 1; $k >= 0; $k-- ) {
			$q = $all[ $k ];
			if ( ! empty( $q['ppr']['pageBreakInside'] ) ) { return true; }
			if ( $q['sect'] !== $p['sect'] ) {
				$s = $D['sections'][ $p['sect'] ];
				return in_array( $s['type'], array( 'nextPage', 'oddPage', 'evenPage' ), true );
			}
			if ( ! $q['empty'] || $q['images'] ) { return false; }
		}
		return false;
	}
	public static function sect_of( $D, $p ) { return ( $p && isset( $D['sections'][ $p['sect'] ] ) ) ? $D['sections'][ $p['sect'] ] : null; }
	public static function img_in( $D, $needle ) {
		$p = self::P( $D, $needle );
		if ( ! $p ) { return null; }
		foreach ( $D['images'] as $im ) { if ( $im['p'] === $p['i'] ) { return $im; } }
		// tolérance : image dans le ¶ précédent
		foreach ( $D['images'] as $im ) { if ( $im['p'] === $p['i'] - 1 ) { return $im; } }
		return null;
	}
	public static function find_table( $D, $needle ) {
		$n = self::n( $needle );
		foreach ( $D['tables'] as $t ) {
			foreach ( $t['rows'] as $r ) { foreach ( $r as $c ) { if ( false !== mb_strpos( self::n( $c['text'] ), $n ) ) { return $t; } } }
		}
		return null;
	}
	public static function col_vals( $t, $c, $from = 1, $to = null ) {
		$out = array(); $to = null === $to ? count( $t['rows'] ) : $to;
		for ( $r = $from; $r < $to; $r++ ) { if ( isset( $t['rows'][ $r ][ $c ] ) ) { $out[] = $t['rows'][ $r ][ $c ]; } }
		return $out;
	}
	public static function header_main( $D ) { foreach ( $D['headers'] as $h ) { if ( 'default' === $h['type'] ) { return $h; } } return $D['headers'] ? $D['headers'][0] : null; }
	public static function footer_main( $D ) { foreach ( $D['footers'] as $h ) { if ( 'default' === $h['type'] ) { return $h; } } return $D['footers'] ? $D['footers'][0] : null; }
	public static function no_tabbed_lines( $D, $needles ) {
		foreach ( $needles as $t ) { foreach ( self::PS( $D, $t ) as $p ) { if ( false !== strpos( $p['text'], "\t" ) ) { return false; } } }
		return true;
	}
	public static function style_is( $D, $name, $prop, $val ) {
		$s = isset( $D['styles'][ $name ] ) ? $D['styles'][ $name ] : null;
		if ( ! $s ) { return false; }
		$v = $s[ $prop ];
		if ( 'font' === $prop ) { return false !== stripos( (string) $v, $val ); }
		if ( 'color' === $prop ) { return strtoupper( (string) $v ) === $val; }
		return $v == $val; // phpcs:ignore
	}

	/* ================================================================ définitions */
	public static function get( $lecon ) {
		$m = 'L' . sprintf( '%02d', $lecon );
		return method_exists( __CLASS__, $m ) ? self::$m() : array();
	}

	public static function evaluate( $lecon, $docs ) {
		$rules = self::get( $lecon );
		$D = self::main_doc( $lecon, $docs );
		$out = array( 'items' => array(), 'score' => 0, 'max' => 0 );
		foreach ( $rules as $r ) {
			list( $label, $pts, $fn ) = $r;
			try { $v = $D ? $fn( $D, $docs ) : 0; } catch ( Throwable $e ) { $v = 0; }
			$v = is_bool( $v ) ? ( $v ? 1 : 0 ) : max( 0, min( 1, (float) $v ) );
			$got = round( $pts * $v * 2 ) / 2;
			$out['items'][] = array( 'label' => $label, 'max' => $pts, 'pts' => $got, 'ok' => $got >= $pts );
			$out['score'] += $got; $out['max'] += $pts;
		}
		$out['pct'] = $out['max'] ? round( 100 * $out['score'] / $out['max'] ) : 0;
		return $out;
	}

	public static function main_doc( $lecon, $docs ) {
		if ( ! $docs ) { return null; }
		if ( 14 === (int) $lecon ) {
			$best = null; $bm = -1;
			foreach ( $docs as $d ) { $c = count( $d['mergefields'] ); if ( $c > $bm ) { $bm = $c; $best = $d; } }
			return $best;
		}
		return $docs[0];
	}

	/* ---------------------------------------------------------------- L01 */
	private static function L01() {
		$order = array( 'Bienvenue chez Évasion Colibri', 'Depuis 2009', 'Notre philosophie tient', 'Dans ce catalogue', 'Chaque séjour peut être adapté', 'Léa Morand, directrice' );
		return array(
			array( 'Les 6 paragraphes sont présents (aucun texte perdu)', 2, function ( $D ) use ( $order ) { return self::each( $D, $order, function () { return true; } ); } ),
			array( 'Aucune ligne vide (¶ isolé)', 3, function ( $D ) { $e = self::empty_pars( $D ); return $e === 0 ? 1 : max( 0, 1 - $e / 4 ); } ),
			array( 'Le paragraphe en double a été supprimé', 2, function ( $D ) { return self::count( $D, 'Notre philosophie tient' ) === 1; } ),
			array( 'Les paragraphes sont dans le bon ordre', 3, function ( $D ) use ( $order ) {
				$ix = array(); foreach ( $order as $t ) { $ix[] = self::idx( $D, $t ); }
				if ( in_array( -1, $ix, true ) ) { return 0; }
				$ok = 0; for ( $k = 1; $k < count( $ix ); $k++ ) { if ( $ix[ $k ] > $ix[ $k - 1 ] ) { $ok++; } }
				return $ok / ( count( $ix ) - 1 );
			} ),
		);
	}

	/* ---------------------------------------------------------------- L02 */
	private static function L02() {
		$inter = array( 'Pourquoi y aller ?', 'À ne pas manquer', 'Infos pratiques' );
		return array(
			array( 'Titre en MAJUSCULES (sans retaper)', 2, function ( $D ) { $p = self::P( $D, 'Islande, terre de feu' ); return $p && ( false !== strpos( $p['text'], 'ISLANDE, TERRE DE FEU' ) || self::rp( $D, $p, 'Islande', 'caps' ) ); } ),
			array( 'Titre : Aptos Display, 26 pt, gras, Bleu foncé', 3, function ( $D ) { $p = self::P( $D, 'Islande, terre de feu' ); $t = 'terre de feu';
				return ( self::rp( $D, $p, $t, 'font', 'Aptos Display' ) + self::rp( $D, $p, $t, 'sz', 52 ) + self::rp( $D, $p, $t, 'b' ) + self::rp( $D, $p, $t, 'color', '002060' ) ) / 4; } ),
			array( 'Sous-titre : italique, 14 pt, Bleu', 2, function ( $D ) { $p = self::P( $D, 'Circuit accompagné de 10 jours' ); $t = 'Circuit accompagné';
				return ( self::rp( $D, $p, $t, 'i' ) + self::rp( $D, $p, $t, 'sz', 28 ) + self::rp( $D, $p, $t, 'color', '0070C0' ) ) / 3; } ),
			array( 'Les 3 intertitres : Aptos Display 16 pt, gras, Rouge foncé', 3, function ( $D ) use ( $inter ) {
				return self::each( $D, $inter, function ( $p ) use ( $D ) { $t = mb_substr( $p['text'], 0, 6 ); return self::rp( $D, $p, $t, 'b' ) && self::rp( $D, $p, $t, 'sz', 32 ) && self::rp( $D, $p, $t, 'color', 'C00000' ) && self::rp( $D, $p, $t, 'font', 'Aptos Display' ); } ); } ),
			array( 'Gras sur « des paysages que l\'on ne voit nulle part ailleurs »', 1, function ( $D ) { return self::rp( $D, self::P( $D, 'Volcans actifs' ), "des paysages que l'on ne voit nulle part ailleurs", 'b' ); } ),
			array( 'Les 4 lignes « À ne pas manquer » en italique', 1, function ( $D ) {
				return self::each( $D, array( 'lagon glaciaire', 'cascade de Skógafoss', "Cercle d'or", 'source chaude naturelle' ), function ( $p ) use ( $D ) { return self::rp( $D, $p, mb_substr( $p['text'], 0, 10 ), 'i' ); } ); } ),
			array( 'Prix « dès CHF 3 890.– » en gras rouge', 1, function ( $D ) { $p = self::P( $D, 'Prix :' ); return self::rp( $D, $p, 'CHF 3', 'b' ) && self::rp( $D, $p, 'CHF 3', 'color', 'FF0000' ); } ),
			array( '« Nouveau ! » en gras surligné jaune', 1, function ( $D ) { $p = self::P( $D, 'Nouveau !' ); return self::rp( $D, $p, 'Nouveau', 'b' ) && self::rp( $D, $p, 'Nouveau', 'highlight', 'yellow' ); } ),
			array( 'm² en exposant', 1, function ( $D ) { $p = self::P( $D, 'Hébergement :' ); foreach ( $p ? $p['runs'] : array() as $r ) { if ( '2' === trim( $r['t'] ) && isset( $r['vert'] ) && 'superscript' === $r['vert'] ) { return true; } } return false; } ),
			array( 'CO₂ en indice', 1, function ( $D ) { $p = self::P( $D, 'de vos vols est compensé' ); foreach ( $p ? $p['runs'] : array() as $r ) { if ( '2' === trim( $r['t'] ) && isset( $r['vert'] ) && 'subscript' === $r['vert'] ) { return true; } } return false; } ),
			array( 'Ligne « Formalités » nettoyée (mise en forme effacée)', 2, function ( $D ) { $p = self::P( $D, 'Formalités' );
				if ( ! $p ) { return false; } foreach ( $p['runs'] as $r ) { if ( ! empty( $r['i'] ) || ! empty( $r['u'] ) || ( isset( $r['font'] ) && false !== stripos( $r['font'], 'Comic' ) ) || ( isset( $r['color'] ) && 'FF0000' === $r['color'] ) ) { return false; } } return true; } ),
			array( 'Ligne de contact : 9 pt et petites majuscules', 2, function ( $D ) { $p = self::P( $D, 'rue du Midi 14' ); return ( self::rp( $D, $p, 'Évasion Colibri', 'sz', 18 ) + self::rp( $D, $p, 'Évasion Colibri', 'smallCaps' ) ) / 2; } ),
		);
	}

	/* ---------------------------------------------------------------- L03 */
	private static function L03() {
		$corps = array( 'Vous avez voyagé avec nous', 'Pour 2027, notre équipe', 'Comme toujours, chaque programme', 'Le saviez-vous', 'Nous nous réjouissons' );
		return array(
			array( 'Aucune ligne vide ajoutée', 3, function ( $D ) { return self::empty_pars( $D ) === 0; } ),
			array( 'Expéditeur : « Évasion Colibri » en gras, espacement après 0 pt', 2, function ( $D ) {
				$a = self::P( $D, 'Évasion Colibri' ); $b = self::P( $D, 'Rue du Midi 14' );
				return ( self::rp( $D, $a, 'Évasion Colibri', 'b' ) + ( self::pt( $D, $a, 'after' ) == 0 && self::pt( $D, $b, 'after' ) == 0 ) ) / 2; } ),
			array( 'Destinataire : retrait gauche de 10 cm', 2, function ( $D ) { return self::each( $D, array( 'Madame Claire Dubois', 'Chemin des Vignes 7', '1095 Lutry' ), function ( $p ) { return self::near( self::ind( $p, 'left' ), 10, 0.3 ); } ); } ),
			array( 'Destinataire : 24 pt avant la 1re ligne, 0 pt après', 1, function ( $D ) { $p = self::P( $D, 'Madame Claire Dubois' ); return self::pt( $D, $p, 'before' ) >= 22 && self::pt( $D, $p, 'after' ) == 0; } ),
			array( 'Date alignée à droite, 24 pt avant', 2, function ( $D ) { $p = self::P( $D, 'Lausanne, le 19 novembre' ); return ( ( 'right' === self::jc( $D, $p ) || self::ind( $p, 'left' ) >= 9 ) + ( self::pt( $D, $p, 'before' ) >= 22 ) ) / 2; } ),
			array( 'Objet en gras, 18 pt avant et 12 pt après', 2, function ( $D ) { $p = self::P( $D, 'Concerne' ); return ( self::rp( $D, $p, 'Concerne', 'b' ) + ( self::near( self::pt( $D, $p, 'before' ), 18, 1 ) && self::near( self::pt( $D, $p, 'after' ), 12, 1 ) ) ) / 2; } ),
			array( 'Corps du texte justifié', 2, function ( $D ) use ( $corps ) { return self::each( $D, $corps, function ( $p ) use ( $D ) { return 'both' === self::jc( $D, $p ); } ); } ),
			array( 'Citation : centrée, italique, Bleu, retraits de 2 cm', 3, function ( $D ) { $p = self::P( $D, 'Partir, c\'est se donner' );
				return ( ( 'center' === self::jc( $D, $p ) ) + self::rp( $D, $p, 'Partir', 'i' ) + self::rp( $D, $p, 'Partir', 'color', '0070C0' ) + ( self::near( self::ind( $p, 'left' ), 2, 0.2 ) && self::near( self::ind( $p, 'right' ), 2, 0.2 ) ) ) / 4; } ),
			array( 'Encadré : bordure de paragraphe', 2, function ( $D ) { $p = self::P( $D, 'Le saviez-vous' ); return $p && ! empty( $p['ppr']['border'] ) && count( $p['ppr']['border'] ) >= 4; } ),
			array( 'Encadré : trame de fond', 1, function ( $D ) { $p = self::P( $D, 'Le saviez-vous' ); return $p && ! empty( $p['ppr']['shd'] ); } ),
			array( 'Encadré : retraits 1 cm, « Le saviez-vous ? » en gras', 2, function ( $D ) { $p = self::P( $D, 'Le saviez-vous' );
				return ( ( self::near( self::ind( $p, 'left' ), 1, 0.2 ) && self::near( self::ind( $p, 'right' ), 1, 0.2 ) ) + self::rp( $D, $p, 'Le saviez-vous', 'b' ) ) / 2; } ),
			array( 'Signature : retrait gauche 10 cm, 24 pt avant', 2, function ( $D ) { $p = self::heading( $D, 'Léa Morand' ); $q = self::heading( $D, 'Directrice' );
				return ( ( self::near( self::ind( $p, 'left' ), 10, 0.3 ) && self::near( self::ind( $q, 'left' ), 10, 0.3 ) ) + ( self::pt( $D, $p, 'before' ) >= 22 ) ) / 2; } ),
		);
	}

	/* ---------------------------------------------------------------- L04 */
	private static function L04() {
		$docs  = array( "Passeport ou carte d'identité", "Billets d'avion", "Carte d'assurance maladie", 'Permis de conduire' );
		$cat   = array( 'Vêtements', 'Trousse de toilette', 'Électronique' );
		$items = array( 'Pull chaud', 'Veste imperméable', 'Chaussures de marche', 'Maillot de bain', 'Crème solaire', 'Brosse à dents', 'Médicaments personnels', 'Chargeur de téléphone', 'Adaptateur de prise' );
		$resa  = array( 'Choisissez votre destination', 'Prenez rendez-vous', 'Recevez votre programme', 'Versez un acompte', 'Recevez vos documents' );
		$dep   = array( "Vérifiez l'heure", 'Présentez-vous à l', 'Gardez vos documents' );
		$inter = array( 'Documents à emporter', 'Dans la valise', 'Votre réservation en 5 étapes', 'Le jour du départ' );
		return array(
			array( 'Titre 24 pt gras Bleu foncé centré, introduction italique centrée', 2, function ( $D ) { $p = self::P( $D, 'Check-list du voyageur' ); $q = self::P( $D, 'Préparez votre départ' );
				return ( self::rp( $D, $p, 'Check', 'sz', 48 ) + self::rp( $D, $p, 'Check', 'b' ) + self::rp( $D, $p, 'Check', 'color', '002060' ) + ( 'center' === self::jc( $D, $p ) ) + self::rp( $D, $q, 'Préparez', 'i' ) + ( 'center' === self::jc( $D, $q ) ) ) / 6; } ),
			array( 'Les 4 intertitres : 14 pt, gras, Rouge foncé', 2, function ( $D ) use ( $inter ) { return self::each( $D, $inter, function ( $p ) use ( $D ) { $t = mb_substr( $p['text'], 0, 5 ); return self::rp( $D, $p, $t, 'b' ) && self::rp( $D, $p, $t, 'sz', 28 ) && self::rp( $D, $p, $t, 'color', 'C00000' ); } ); } ),
			array( 'Documents : liste à puces ✓', 3, function ( $D ) use ( $docs ) { return self::each( $D, $docs, function ( $p ) { return $p['num'] && 'bullet' === $p['num']['fmt'] && false !== mb_strpos( $p['num']['text'], '✓' ) || ( $p['num'] && 'bullet' === $p['num']['fmt'] && in_array( $p['num']['text'], array( "\xEF\x83\xBC", 'ü', "\xEF\x81\x8C" ), true ) ); } ); } ),
			array( 'Valise : liste à puces à plusieurs niveaux', 2, function ( $D ) use ( $cat, $items ) { return self::each( $D, array_merge( $cat, $items ), function ( $p ) { return $p['num'] && 'bullet' === $p['num']['fmt']; } ); } ),
			array( 'Valise : catégories au niveau 1, objets au niveau 2', 3, function ( $D ) use ( $cat, $items ) {
				return ( self::each( $D, $cat, function ( $p ) { return $p['num'] && 0 === $p['num']['ilvl']; } ) + self::each( $D, $items, function ( $p ) { return $p['num'] && 1 === $p['num']['ilvl']; } ) ) / 2; } ),
			array( 'Réservation : numérotation 1. à 5.', 2, function ( $D ) use ( $resa ) { $k = 0; return self::each( $D, $resa, function ( $p ) use ( &$k ) { $k++; return $p['num'] && 'decimal' === $p['num']['fmt'] && $p['num']['value'] === $k; } ); } ),
			array( 'Jour du départ : numérotation a) b) c) qui recommence à a)', 2, function ( $D ) use ( $dep ) { $k = 0; return self::each( $D, $dep, function ( $p ) use ( &$k ) { $k++; return $p['num'] && 'lowerLetter' === $p['num']['fmt'] && $p['num']['value'] === $k; } ); } ),
			array( 'Aucun tiret ni numéro tapé à la main', 1, function ( $D ) use ( $docs, $items, $resa, $dep ) {
				foreach ( array_merge( $docs, $items, $resa, $dep ) as $t ) { $p = self::P( $D, $t ); if ( $p && preg_match( '/^\s*([-–•✓]|\d+[.)]|[a-c]\))\s/u', $p['text'] ) ) { return false; } } return true; } ),
		);
	}

	/* ---------------------------------------------------------------- L05 */
	private static function L05() {
		$prog = array( 'Jour 1', 'Jour 2', 'Jour 3', 'Jour 4', 'Jour 5', 'Jour 6', 'Jour 7', 'Jour 8' );
		$tar  = array( 'Forfait en chambre double', 'Supplément chambre individuelle', 'Option cours de cuisine', 'Assurance annulation', 'Réduction enfant' );
		$tr   = array( 'Trajet', 'Lausanne – Milan', 'Milan – Florence', 'Florence – Pise' );
		return array(
			array( 'Titre et intertitres mis en forme (Vert, gras)', 1, function ( $D ) { return self::each( $D, array( 'Toscane gourmande', 'Programme jour par jour', 'Tarifs par personne', 'Horaires des trains' ), function ( $p ) use ( $D ) { $t = mb_substr( $p['text'], 0, 5 ); return self::rp( $D, $p, $t, 'b' ) && self::rp( $D, $p, $t, 'color', '00B050' ); } ); } ),
			array( 'Programme : taquet gauche à 2,5 cm', 2, function ( $D ) use ( $prog ) { return self::each( $D, $prog, function ( $p ) { return self::tabs_ok( $p, array( array( 'left', 2.5, null ) ) ); } ); } ),
			array( 'Programme : taquet droit à 16 cm avec points de suite', 3, function ( $D ) use ( $prog ) { return self::each( $D, $prog, function ( $p ) { return self::tabs_ok( $p, array( array( 'right', 16, 'dot' ) ) ); } ); } ),
			array( 'Programme : « Jour n » en gras, 4 pt après', 1, function ( $D ) use ( $prog ) { return self::each( $D, $prog, function ( $p ) use ( $D ) { return self::rp( $D, $p, 'Jour', 'b' ) && self::near( self::pt( $D, $p, 'after' ), 4, 1 ); } ); } ),
			array( 'Tarifs : taquet décimal à 15 cm avec points de suite', 3, function ( $D ) use ( $tar ) { return self::each( $D, $tar, function ( $p ) { return self::tabs_ok( $p, array( array( 'decimal', 15, 'dot' ) ) ); } ); } ),
			array( 'Réduction enfant en italique Vert', 1, function ( $D ) { $p = self::P( $D, 'Réduction enfant' ); return self::rp( $D, $p, 'Réduction', 'i' ) && self::rp( $D, $p, 'Réduction', 'color', '00B050' ); } ),
			array( 'Horaires : taquets centrés 8 et 11 cm, droit 15 cm', 3, function ( $D ) use ( $tr ) { return self::each( $D, $tr, function ( $p ) { return self::tabs_ok( $p, array( array( 'center', 8, null ), array( 'center', 11, null ), array( 'right', 15, null ) ) ); } ); } ),
			array( 'Horaires : en-tête en gras avec bordure inférieure', 2, function ( $D ) { $p = self::P( $D, 'Trajet' ); return ( self::rp( $D, $p, 'Trajet', 'b' ) + ( $p && isset( $p['ppr']['border']['bottom'] ) ) ) / 2; } ),
			array( 'Note finale 9 pt italique', 1, function ( $D ) { $p = self::P( $D, 'Programme sous réserve' ); return self::rp( $D, $p, 'Programme', 'sz', 18 ) && self::rp( $D, $p, 'Programme', 'i' ); } ),
			array( 'Aucun espace utilisé pour aligner', 1, function ( $D ) { foreach ( self::body( $D ) as $p ) { if ( preg_match( '/ {3,}/', $p['text'] ) ) { return false; } } return true; } ),
		);
	}

	/* ---------------------------------------------------------------- L06 */
	private static function L06() {
		$h1 = array( 'Avant de partir', 'Hébergement', 'Gastronomie', 'Se déplacer' );
		$h2 = array( 'Formalités', 'Meilleure saison', 'Budget', 'Les ryokans', 'Les hôtels', 'Les incontournables', 'Les bonnes manières', 'Le Japan Rail Pass', 'Les transports urbains' );
		$h3 = array( 'À table', 'Le pourboire' );
		return array(
			array( 'Styles Titre et Sous-titre', 2, function ( $D ) { return ( self::is_style( self::heading( $D, 'Le Japon en 12 jours' ), 'title' ) + self::is_style( self::P( $D, 'Guide pratique du voyageur' ), 'subtitle' ) ) / 2; } ),
			array( 'Titre 1 sur les 4 chapitres', 3, function ( $D ) use ( $h1 ) { return self::styled( $D, 'heading 1', $h1 ); } ),
			array( 'Titre 2 sur les 9 sous-chapitres', 3, function ( $D ) use ( $h2 ) { return self::styled( $D, 'heading 2', $h2 ); } ),
			array( 'Titre 3 sur « À table » et « Le pourboire »', 2, function ( $D ) use ( $h3 ) { return self::styled( $D, 'heading 3', $h3 ); } ),
			array( 'Style Titre 1 modifié : 18 pt, gras, Rouge foncé', 3, function ( $D ) { return ( self::style_is( $D, 'heading 1', 'sz', 36 ) + self::style_is( $D, 'heading 1', 'b', true ) + self::style_is( $D, 'heading 1', 'color', 'C00000' ) ) / 3; } ),
			array( 'Style Titre 1 : 24 pt avant et bordure inférieure', 2, function ( $D ) { $s = isset( $D['styles']['heading 1'] ) ? $D['styles']['heading 1'] : array();
				return ( ( isset( $s['before'] ) && self::near( $s['before'] / 20, 24, 1 ) ) + ! empty( $s['border']['bottom'] ) ) / 2; } ),
			array( 'Style Titre 2 mis à jour : Rouge, 14 pt', 2, function ( $D ) { return ( self::style_is( $D, 'heading 2', 'color', 'FF0000' ) + self::style_is( $D, 'heading 2', 'sz', 28 ) ) / 2; } ),
			array( 'Style Normal : justifié, 6 pt après', 2, function ( $D ) { $s = $D['styles']['normal']; return ( ( 'both' === $s['jc'] ) + ( null !== $s['after'] && self::near( $s['after'] / 20, 6, 1 ) ) ) / 2; } ),
			array( 'Chapitre « Gastronomie » déplacé avant « Hébergement » (avec son contenu)', 3, function ( $D ) {
				$g = self::idx( $D, 'Gastronomie' ); $h = self::heading( $D, 'Hébergement' ); $h = $h ? $h['i'] : -1; $s = self::idx( $D, 'Sushis, ramen' ); $r = self::idx( $D, 'Ces auberges traditionnelles' );
				return ( ( $g > -1 && $g < $h ) + ( $s > $g && $s < $h ) + ( $r > $h ) ) / 3; } ),
		);
	}

	/* ---------------------------------------------------------------- L07 */
	private static function L07() {
		$dest = array( 'Norvège –', 'Cyclades –', 'Marrakech –', 'Islande –', 'Toscane –', 'Écosse –' );
		$cal  = array( 'Destination', 'Norvège', 'Cyclades', 'Marrakech', 'Islande', 'Toscane', 'Écosse' );
		return array(
			array( 'Styles : Titre, Sous-titre et 4 Titres 1', 2, function ( $D ) { return ( self::is_style( self::heading( $D, 'Escapades en Europe' ), 'title' ) + self::styled( $D, 'heading 1', array( 'Une saison pour prendre le temps', 'Nos destinations en bref', 'Calendrier des départs', 'Conditions générales' ) ) ) / 2; } ),
			array( 'Marges de 2 cm', 2, function ( $D ) { return self::ratio( $D['sections'], function ( $s ) { $m = $s['margins']; return $m && self::near( $m['left'] / 567, 2, 0.1 ) && self::near( $m['right'] / 567, 2, 0.1 ) && self::near( $m['top'] / 567, 2, 0.1 ) && self::near( $m['bottom'] / 567, 2, 0.1 ); } ); } ),
			array( 'Page de titre : saut de page avant « Une saison… »', 2, function ( $D ) { return self::page_break_before( $D, self::heading( $D, 'Une saison pour prendre le temps' ) ); } ),
			array( 'Aucune ligne vide pour pousser le texte', 1, function ( $D ) { return self::empty_pars( $D ) <= 2; } ),
			array( 'Destinations sur 2 colonnes avec ligne séparatrice', 3, function ( $D ) use ( $dest ) { return self::each( $D, $dest, function ( $p ) use ( $D ) { $s = self::sect_of( $D, $p ); return $s && 2 === $s['cols'] && $s['sep']; } ); } ),
			array( 'Le reste du texte reste sur 1 colonne', 1, function ( $D ) { $s = self::sect_of( $D, self::P( $D, 'Après une année riche' ) ); $t = self::sect_of( $D, self::P( $D, 'Les prix s\'entendent' ) ); return $s && $t && 1 === $s['cols'] && 1 === $t['cols']; } ),
			array( 'Noms des pays en gras', 1, function ( $D ) use ( $dest ) { return self::each( $D, $dest, function ( $p ) use ( $D ) { return self::rp( $D, $p, mb_substr( $p['text'], 0, 4 ), 'b' ); } ); } ),
			array( 'Calendrier seul en paysage', 3, function ( $D ) { $c = self::sect_of( $D, self::heading( $D, 'Calendrier des départs' ) ); $a = self::sect_of( $D, self::heading( $D, 'Nos destinations en bref' ) ); $g = self::sect_of( $D, self::heading( $D, 'Conditions générales' ) );
				return ( ( $c && 'landscape' === $c['orient'] ) * 2 + ( $a && 'portrait' === $a['orient'] && $g && 'portrait' === $g['orient'] ) ) / 3; } ),
			array( 'Calendrier : taquets centrés à 7, 10, 13, 16 et 19 cm', 2, function ( $D ) use ( $cal ) {
				$ok = 0; $n = 0;
				foreach ( self::body( $D ) as $p ) { if ( false !== strpos( $p['text'], "\t" ) && self::sect_of( $D, $p )['orient'] === 'landscape' ) { $n++; if ( self::tabs_ok( $p, array( array( 'center', 7, null ), array( 'center', 10, null ), array( 'center', 13, null ), array( 'center', 16, null ), array( 'center', 19, null ) ) ) ) { $ok++; } } }
				return $n ? $ok / $n : 0; } ),
			array( 'Calendrier : en-tête gras avec bordure inférieure', 1, function ( $D ) { foreach ( self::PS( $D, 'Destination' ) as $p ) { if ( false !== strpos( $p['text'], "\t" ) ) { return self::rp( $D, $p, 'Destination', 'b' ) && isset( $p['ppr']['border']['bottom'] ); } } return false; } ),
			array( 'Conditions générales sur une nouvelle page', 1, function ( $D ) { return self::page_break_before( $D, self::heading( $D, 'Conditions générales' ) ); } ),
		);
	}

	/* ---------------------------------------------------------------- L08 */
	private static function L08() {
		$h1 = array( 'Une année record', 'Nos destinations phares', 'Notre équipe', 'Engagements durables', 'Perspectives 2027' );
		return array(
			array( 'Titre 1 sur les 5 chapitres, Titre 2 sur les 2 sous-chapitres', 3, function ( $D ) use ( $h1 ) { return ( self::styled( $D, 'heading 1', $h1 ) * 2 + self::styled( $D, 'heading 2', array( 'Chiffres clés', 'Nos canaux de réservation' ) ) ) / 3; } ),
			array( 'Texte courant justifié', 1, function ( $D ) { return 'both' === self::jc( $D, self::P( $D, 'Avec 1 248 voyageurs' ) ); } ),
			array( 'Page de garde insérée', 3, function ( $D ) { if ( $D['cover'] ) { return 1; } $t = self::P( $D, "Rapport d'activité 2026" ); $h = self::heading( $D, 'Une année record' ); return ( $t && $h && $t['i'] < $h['i'] && self::page_break_before( $D, $h ) ) ? 0.5 : 0; } ),
			array( 'Textes de la couverture déplacés (pas retapés en double)', 1, function ( $D ) { return self::count( $D, 'Évasion Colibri – agence de voyages' ) <= 1 && self::count( $D, 'Présenté aux associés' ) <= 1; } ),
			array( 'En-tête : logo', 2, function ( $D ) { $h = self::header_main( $D ); return $h && $h['images'] > 0; } ),
			array( 'En-tête : « Rapport d\'activité 2026 »', 2, function ( $D ) { $h = self::header_main( $D ); return $h && false !== mb_strpos( self::n( $h['text'] ), self::n( "Rapport d'activité 2026" ) ); } ),
			array( 'En-tête : bordure inférieure', 1, function ( $D ) { $h = self::header_main( $D ); return $h && $h['border']; } ),
			array( 'Ligne « Rapport d\'activité 2026 » retirée de la fin du document', 1, function ( $D ) { $b = self::body( $D ); $last = ''; foreach ( array_reverse( $b ) as $p ) { if ( ! $p['empty'] ) { $last = $p['text']; break; } } return false === mb_strpos( self::n( $last ), 'rapport d\'activit' ); } ),
			array( 'Pied de page « Page X sur Y » (champs PAGE et NUMPAGES)', 3, function ( $D ) { $f = self::footer_main( $D ); return $f ? ( in_array( 'PAGE', $f['fields'], true ) + in_array( 'NUMPAGES', $f['fields'], true ) ) / 2 : 0; } ),
			array( 'Première page différente', 2, function ( $D ) { return ! empty( $D['sections'][0]['titlePg'] ); } ),
			array( 'Sauts de page avant « Nos destinations phares » et « Engagements durables »', 1, function ( $D ) { return ( self::page_break_before( $D, self::heading( $D, 'Nos destinations phares' ) ) + self::page_break_before( $D, self::heading( $D, 'Engagements durables' ) ) ) / 2; } ),
		);
	}

	/* ---------------------------------------------------------------- L09 */
	private static function L09() {
		$h1 = array( 'Préparer son voyage', 'Sur place', 'Réduire ses déchets', 'Notre charte', 'Contacts utiles' );
		$h2 = array( 'Choisir sa destination', 'Privilégier le train', 'Partir moins souvent', 'Respecter les habitants', 'Consommer local', 'Préserver la nature', 'La trousse zéro déchet', "L'eau", 'Bilan carbone : nos engagements' );
		return array(
			array( 'Styles Titre / Sous-titre', 1, function ( $D ) { return ( self::is_style( self::heading( $D, 'Voyager autrement' ), 'title' ) + self::is_style( self::P( $D, 'Guide du voyageur responsable' ), 'subtitle' ) ) / 2; } ),
			array( 'Titre 1 sur les 5 chapitres', 2, function ( $D ) use ( $h1 ) { return self::styled( $D, 'heading 1', $h1 ); } ),
			array( 'Titre 2 sur les sous-chapitres (dont « Bilan carbone »)', 3, function ( $D ) use ( $h2 ) { return self::styled( $D, 'heading 2', $h2 ); } ),
			array( 'Titre 3 sur « Dans la salle de bain » et « Pour les repas »', 1, function ( $D ) { return self::styled( $D, 'heading 3', array( 'Dans la salle de bain', 'Pour les repas' ) ); } ),
			array( 'Table des matières automatique insérée', 3, function ( $D ) { foreach ( $D['fields'] as $f ) { if ( 'TOC' === $f['code'] ) { return true; } } return count( $D['toc'] ) > 3; } ),
			array( 'Table placée entre la page de titre et le 1er chapitre', 2, function ( $D ) { $h = self::heading( $D, 'Préparer son voyage' ); $s = self::P( $D, 'Guide du voyageur responsable' ); $first = null;
				foreach ( $D['paragraphs'] as $p ) { if ( $p['toc'] ) { $first = $p['i']; break; } }
				return ( null !== $first && $s && $h && $first > $s['i'] && $first < $h['i'] ) * 0.5 + 0.5 * self::page_break_before( $D, $h ); } ),
			array( 'Table limitée à 2 niveaux (pas de Titre 3)', 2, function ( $D ) { foreach ( $D['fields'] as $f ) { if ( 'TOC' === $f['code'] && preg_match( '/\\\\o\s*"1-2"/', $f['instr'] ) ) { return true; } } $l = array_column( $D['toc'], 'level' ); return $l && max( $l ) <= 2 && ! self::toc_has( $D, 'Pour les repas' ); } ),
			array( 'Table à jour : contient « Bilan carbone »', 2, function ( $D ) { return self::toc_has( $D, 'Bilan carbone' ); } ),
			array( 'Saut de page avant « Réduire ses déchets »', 1, function ( $D ) { return self::page_break_before( $D, self::heading( $D, 'Réduire ses déchets' ) ); } ),
			array( 'Numéros de page en bas', 2, function ( $D ) { $f = self::footer_main( $D ); return $f && in_array( 'PAGE', $f['fields'], true ); } ),
		);
	}
	public static function toc_has( $D, $t ) { foreach ( $D['toc'] as $e ) { if ( false !== mb_strpos( self::n( $e['text'] ), self::n( $t ) ) ) { return true; } } return false; }

	/* ---------------------------------------------------------------- L10 */
	private static function L10() {
		return array(
			array( 'Styles Titre / Titre 1, texte justifié', 1, function ( $D ) { return ( self::is_style( self::heading( $D, 'Nos coups de cœur 2027' ), 'title' ) + self::styled( $D, 'heading 1', array( 'Les fjords de Norvège', 'Santorin, la perle des Cyclades', 'Marrakech, la ville rouge' ) ) + ( 'both' === self::jc( $D, self::P( $D, 'Chaque année' ) ) ) ) / 3; } ),
			array( 'Bandeau IMG-05 : aligné sur le texte, 16 cm', 2, function ( $D ) { $t = self::heading( $D, 'Nos coups de cœur 2027' ); $i = self::P( $D, 'Chaque année' );
				foreach ( $D['images'] as $im ) { if ( $t && $i && $im['p'] >= $t['i'] && $im['p'] <= $i['i'] ) { return ( $im['inline'] ) * 0.5 + ( self::near( $im['cx'], 16, 0.4 ) ) * 0.5; } } return 0; } ),
			array( 'IMG-02 Norvège : 7 cm, carré, à droite, ombre', 4, function ( $D ) { $im = self::img_in( $D, 'Entre parois vertigineuses' ); if ( ! $im ) { return 0; }
				return ( self::near( $im['cx'], 7, 0.3 ) + ( 'square' === $im['wrap'] ) + ( 'right' === $im['halign'] ) + $im['shadow'] ) / 4; } ),
			array( 'IMG-03 Santorin : cercle (rognage + ellipse), 5 cm, rapproché, à gauche', 4, function ( $D ) { $im = self::img_in( $D, 'Ses maisons blanches' ); if ( ! $im ) { return 0; }
				return ( ( 'ellipse' === $im['geom'] ) + ( $im['crop'] && self::near( $im['cx'], $im['cy'], 0.2 ) ) + self::near( $im['cx'], 5, 0.3 ) + in_array( $im['wrap'], array( 'tight', 'through' ), true ) + ( 'left' === $im['halign'] ) ) / 5; } ),
			array( 'IMG-04 Marrakech : 6 cm, carré, à droite, cadre blanc', 3, function ( $D ) { $im = self::img_in( $D, 'Derrière les remparts' ); if ( ! $im ) { return 0; }
				return ( self::near( $im['cx'], 6, 0.3 ) + ( 'square' === $im['wrap'] ) + ( 'right' === $im['halign'] ) + ( $im['border'] && 'FFFFFF' === $im['border_color'] ) ) / 4; } ),
			array( 'Texte de remplacement sur les 4 images', 2, function ( $D ) { $n = 0; foreach ( $D['images'] as $im ) { if ( '' !== $im['alt'] ) { $n++; } } return min( 1, $n / 4 ); } ),
			array( 'Lignes « Texte de remplacement… » supprimées', 1, function ( $D ) { return 0 === self::count( $D, 'Texte de remplacement IMG' ); } ),
			array( 'Offre spéciale dans une zone de texte', 3, function ( $D ) { foreach ( $D['textboxes'] as $t ) { if ( false !== mb_stripos( $t['text'], 'Offre spéciale' ) ) { return true; } } return false; } ),
			array( 'Zone de texte colorée, ligne d\'origine supprimée du corps', 2, function ( $D ) { $f = false; foreach ( $D['textboxes'] as $t ) { if ( false !== mb_stripos( $t['text'], 'Offre spéciale' ) && $t['fill'] ) { $f = true; } } return ( $f + ( 0 === self::count( $D, 'Offre spéciale' ) ) ) / 2; } ),
		);
	}

	/* ---------------------------------------------------------------- L11 */
	private static function L11() {
		return array(
			array( 'Styles Titre / Titre 1', 1, function ( $D ) { return ( self::is_style( self::heading( $D, 'Grille tarifaire 2027' ), 'title' ) + self::is_style( self::heading( $D, 'Demande de devis' ), 'heading 1' ) ) / 2; } ),
			array( 'Texte converti en tableau de 5 colonnes', 3, function ( $D ) { $t = self::find_table( $D, 'Basse saison' ); return $t && 5 === $t['ncols']; } ),
			array( 'Ligne « Tahiti » supprimée', 2, function ( $D ) { return false === mb_stripos( json_encode( $D['tables'], JSON_UNESCAPED_UNICODE ) . $D['text'], 'Tahiti' ); } ),
			array( 'Ligne de titre fusionnée contenant « Tarifs des circuits accompagnés 2027 »', 3, function ( $D ) { $t = self::find_table( $D, 'Basse saison' ); if ( ! $t ) { return 0; } $c = $t['rows'][0][0];
				return ( ( count( $t['rows'][0] ) === 1 || $c['span'] >= 5 ) + ( false !== mb_stripos( $c['text'], 'Tarifs des circuits' ) ) + ( 0 === self::count( $D, 'Tarifs des circuits accompagnés' ) ) ) / 3; } ),
			array( 'Titre du tableau gras et centré, en-têtes de colonnes en gras', 2, function ( $D ) { $t = self::find_table( $D, 'Basse saison' ); if ( ! $t ) { return 0; } $c = $t['rows'][0][0];
				$h = isset( $t['rows'][1] ) ? self::ratio( $t['rows'][1], function ( $x ) { return $x['b']; } ) : 0; return ( $c['b'] + ( 'center' === $c['jc'] ) + $h ) / 3; } ),
			array( 'Prix alignés à droite', 2, function ( $D ) { $t = self::find_table( $D, 'Basse saison' ); if ( ! $t ) { return 0; } return ( self::ratio( self::col_vals( $t, 2, 2 ), function ( $c ) { return in_array( $c['jc'], array( 'right', 'end' ), true ); } ) + self::ratio( self::col_vals( $t, 3, 2 ), function ( $c ) { return in_array( $c['jc'], array( 'right', 'end' ), true ); } ) ) / 2; } ),
			array( 'Formulaire : tableau de 6 lignes × 2 colonnes', 3, function ( $D ) { $t = self::find_table( $D, 'Nom et prénom' ); return $t && 6 === $t['nrows'] && 2 === $t['ncols']; } ),
			array( 'Formulaire : colonnes de 5 cm et 11 cm', 2, function ( $D ) { $t = self::find_table( $D, 'Nom et prénom' ); if ( ! $t || count( $t['grid'] ) < 2 ) { return 0; } return ( self::near( $t['grid'][0] / 567, 5, 0.3 ) + self::near( $t['grid'][1] / 567, 11, 0.4 ) ) / 2; } ),
			array( 'Formulaire : lignes de 1 cm, 1re colonne en gras', 2, function ( $D ) { $t = self::find_table( $D, 'Nom et prénom' ); if ( ! $t ) { return 0; }
				return ( self::ratio( $t['heights'], function ( $h ) { return self::near( $h / 567, 1, 0.1 ); } ) + self::ratio( self::col_vals( $t, 0, 0 ), function ( $c ) { return $c['b']; } ) ) / 2; } ),
		);
	}

	/* ---------------------------------------------------------------- L12 */
	private static function L12() {
		$tbl = function ( $D ) { return self::find_table( $D, 'Participants' ); };
		return array(
			array( 'Tableau de 5 colonnes avec les 40 réservations et la ligne Total', 2, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); return $t && 5 === $t['ncols'] && 42 === $t['nrows']; } ),
			array( 'Tri par destination', 3, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); if ( ! $t ) { return 0; } $v = array_map( function ( $c ) { return self::ascii( $c['text'] ); }, self::col_vals( $t, 2, 1, $t['nrows'] - 1 ) );
				$s = $v; sort( $s, SORT_STRING ); return $v === $s; } ),
			array( 'Puis par date de départ', 2, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); if ( ! $t ) { return 0; } $prev = null; $ok = true;
				for ( $r = 1; $r < $t['nrows'] - 1; $r++ ) { $d = $t['rows'][ $r ][2]['text']; $dt = DateTime::createFromFormat( 'd.m.Y', $t['rows'][ $r ][0]['text'] ); if ( ! $dt ) { return 0; }
					if ( $prev && $prev[0] === $d && $dt < $prev[1] ) { $ok = false; } $prev = array( $d, $dt ); } return $ok; } ),
			array( 'Ligne Total restée en bas', 1, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); return $t && false !== mb_stripos( end( $t['rows'] )[0]['text'], 'Total' ); } ),
			array( 'Totaux calculés par formule (=SUM(ABOVE))', 3, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); if ( ! $t ) { return 0; } $l = end( $t['rows'] ); return ( ! empty( $l[3]['formula'] ) + ! empty( $l[4]['formula'] ) ) / 2; } ),
			array( 'Style « Tableau Grille 4 - Accentuation 1 »', 2, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); return $t && ( false !== strpos( $t['style_name'], 'grid table 4' ) && false !== strpos( $t['style_name'], 'accent 1' ) || false !== stripos( $t['style'], 'Grille4' ) && false !== stripos( $t['style'], 'ccentuation1' ) ); } ),
			array( 'Options : ligne d\'en-tête, ligne Total, lignes à bandes, sans 1re colonne', 2, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); if ( ! $t || ! $t['look'] ) { return 0; } $l = $t['look'];
				return ( $l['firstRow'] + $l['lastRow'] + ! $l['noHBand'] + ! $l['firstColumn'] ) / 4; } ),
			array( 'Ligne d\'en-tête répétée sur chaque page', 2, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); return $t && $t['header']; } ),
			array( 'Tableau centré sur la page', 2, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); return $t && 'center' === $t['jc']; } ),
			array( 'Participants et montants alignés à droite', 1, function ( $D ) use ( $tbl ) { $t = $tbl( $D ); if ( ! $t ) { return 0; } return self::ratio( self::col_vals( $t, 4, 1 ), function ( $c ) { return in_array( $c['jc'], array( 'right', 'end' ), true ); } ); } ),
		);
	}
	public static function ascii( $s ) { $t = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s ); return strtolower( false === $t ? $s : $t ); }

	/* ---------------------------------------------------------------- L13 */
	private static function L13() {
		$bar = function ( $D ) { foreach ( $D['charts'] as $c ) { if ( false !== strpos( $c['type'], 'bar' ) ) { return $c; } } return null; };
		$pie = function ( $D ) { foreach ( $D['charts'] as $c ) { if ( false !== stripos( $c['type'], 'pie' ) || false !== stripos( $c['type'], 'doughnut' ) ) { return $c; } } return null; };
		return array(
			array( 'Styles Titre / Titre 1', 1, function ( $D ) { return ( self::is_style( self::heading( $D, 'Fréquentation 2026' ), 'title' ) + self::styled( $D, 'heading 1', array( 'Évolution trimestrielle', 'Répartition par destination', 'Réserver en quatre étapes' ) ) ) / 2; } ),
			array( 'Histogramme groupé inséré', 3, function ( $D ) use ( $bar ) { $c = $bar( $D ); return $c && ( ! isset( $c['barDir'] ) || 'col' === $c['barDir'] ); } ),
			array( 'Histogramme : 2 séries (2025, 2026) × 4 trimestres', 2, function ( $D ) use ( $bar ) { $c = $bar( $D ); return $c && 2 === $c['series'] && 4 === $c['points']; } ),
			array( 'Histogramme : titre et étiquettes de données', 2, function ( $D ) use ( $bar ) { $c = $bar( $D ); return $c ? ( ( false !== mb_stripos( $c['title'], 'trimestre' ) ) + $c['labels'] ) / 2 : 0; } ),
			array( 'Graphique en secteurs avec 6 destinations', 3, function ( $D ) use ( $pie ) { $c = $pie( $D ); return $c ? 0.5 + 0.5 * ( 6 === $c['points'] ) : 0; } ),
			array( 'Secteurs : titre, noms et pourcentages affichés', 2, function ( $D ) use ( $pie ) { $c = $pie( $D ); return $c ? ( ( false !== mb_stripos( $c['title'], 'Répartition' ) ) + ( $c['labels'] && $c['catlabels'] ) ) / 2 : 0; } ),
			array( 'Lignes de données et lignes « Titre du graphique » supprimées', 2, function ( $D ) { return ( self::no_tabbed_lines( $D, array( 'T1', 'T4', 'Norvège', 'Écosse', 'Destination' ) ) + ( 0 === self::count( $D, 'Titre du graphique' ) ) ) / 2; } ),
			array( 'SmartArt inséré avec les 4 étapes', 4, function ( $D ) { if ( ! $D['smartart'] ) { return 0; } $t = self::n( $D['smartart'][0]['text'] ); $ok = 0;
				foreach ( array( 'Choisir sa destination', 'Rencontrer un conseiller', 'Recevoir son programme', "Partir l'esprit léger" ) as $s ) { if ( false !== mb_strpos( $t, self::n( $s ) ) ) { $ok++; } } return 0.5 + 0.5 * $ok / 4; } ),
			array( 'Lignes des étapes retirées du corps du texte', 1, function ( $D ) { return 0 === self::count( $D, 'Rencontrer un conseiller' ); } ),
		);
	}

	/* ---------------------------------------------------------------- L14 */
	private static function L14() {
		$fields = array( 'Civilité', 'Prénom', 'Nom', 'Adresse', 'NPA', 'Localité', 'Destination', 'Date_départ', 'Nuits', 'Montant' );
		return array(
			array( 'Les 10 champs de fusion sont insérés', 4, function ( $D ) use ( $fields ) { $mf = array_map( array( __CLASS__, 'n' ), $D['mergefields'] ); $ok = 0; foreach ( $fields as $f ) { if ( in_array( self::n( $f ), $mf, true ) ) { $ok++; } } return $ok / count( $fields ); } ),
			array( 'Champs répétés : Civilité ×3, Destination ×2', 2, function ( $D ) { $c = array_count_values( array_map( array( __CLASS__, 'n' ), $D['mergefields'] ) ); return ( ( isset( $c['civilité'] ) && $c['civilité'] >= 3 ) + ( isset( $c['destination'] ) && $c['destination'] >= 2 ) ) / 2; } ),
			array( 'Plus aucun crochet [ ] dans la lettre', 2, function ( $D ) { foreach ( self::body( $D ) as $p ) { if ( preg_match( '/\[[^\]]{2,}\]/u', $p['text'] ) ) { return false; } } return true; } ),
			array( 'Lettre reliée à la liste Excel (publipostage démarré)', 2, function ( $D ) { return $D['mailmerge']; } ),
			array( 'Mise en forme : destinataire à 10 cm, date à droite', 2, function ( $D ) { $p = null; foreach ( self::body( $D ) as $q ) { if ( preg_match( '/«?Civilité/u', $q['text'] ) || false !== mb_stripos( $q['text'], 'Civilit' ) ) { $p = $q; break; } }
				$d = self::P( $D, 'Lausanne, le 19 novembre' ); return ( ( $p && self::near( self::ind( $p, 'left' ), 10, 0.3 ) ) + ( 'right' === self::jc( $D, $d ) || self::ind( $d, 'left' ) >= 9 ) ) / 2; } ),
			array( 'Mise en forme : objet en gras, corps justifié', 2, function ( $D ) { $o = self::P( $D, 'Confirmation de votre réservation' ); $c = self::P( $D, 'Nous avons le plaisir' ); return ( self::rp( $D, $o, 'Confirmation', 'b' ) + ( 'both' === self::jc( $D, $c ) ) ) / 2; } ),
			array( 'Document fusionné déposé : 8 lettres personnalisées', 6, function ( $D, $docs ) {
				foreach ( $docs as $d ) { if ( $d === $D && count( $docs ) > 1 ) { continue; }
					$n = self::count( $d, 'Confirmation de votre réservation' );
					if ( $n >= 8 && empty( $d['mergefields'] ) ) { return ( false !== mb_stripos( $d['text'], 'Dubois' ) && false !== mb_stripos( $d['text'], 'Rossier' ) ) ? 1 : 0.7; } }
				return 0; } ),
		);
	}

	/* ---------------------------------------------------------------- L15 (barème /50) */
	private static function L15() {
		$h1 = array( 'Bienvenue en Islande', 'Le programme jour par jour', 'Les étapes du circuit', 'Le climat', 'Infos pratiques', 'Petit lexique islandais' );
		$h2 = array( 'Les cascades du Sud', 'La lagune glaciaire', 'Le Nord et le lac Mývatn', 'À emporter', 'Sur place' );
		return array(
			array( 'Styles appliqués (6 Titres 1, 5 Titres 2)', 3, function ( $D ) use ( $h1, $h2 ) { return ( self::styled( $D, 'heading 1', $h1 ) + self::styled( $D, 'heading 2', $h2 ) ) / 2; } ),
			array( 'Styles modifiés (Titre 1 Bleu foncé 18 pt gras bordure, Normal justifié)', 3, function ( $D ) { $s = isset( $D['styles']['heading 1'] ) ? $D['styles']['heading 1'] : array();
				return ( self::style_is( $D, 'heading 1', 'color', '002060' ) + self::style_is( $D, 'heading 1', 'sz', 36 ) + ! empty( $s['border']['bottom'] ) + ( 'both' === $D['styles']['normal']['jc'] ) ) / 4; } ),
			array( 'Encadré « Le saviez-vous ? » (retraits, bordure, trame)', 4, function ( $D ) { $p = self::P( $D, 'Le saviez-vous' ); if ( ! $p ) { return 0; }
				return ( ! empty( $p['ppr']['border'] ) + ! empty( $p['ppr']['shd'] ) + self::near( self::ind( $p, 'left' ), 1, 0.2 ) + self::rp( $D, $p, 'Le saviez-vous', 'b' ) ) / 4; } ),
			array( 'Tableau du programme (conversion, style, en-tête répété)', 6, function ( $D ) { $t = self::find_table( $D, 'Nuit à' ); if ( ! $t ) { return 0; }
				return ( ( 3 === $t['ncols'] && 11 === $t['nrows'] ) * 2 + ( false !== strpos( $t['style_name'], 'grid table 4' ) || false !== stripos( $t['style'], 'Grille4' ) ) + $t['header'] + ( $t['look'] && ! $t['look']['firstColumn'] ) + self::ratio( self::col_vals( $t, 0, 1 ), function ( $c ) { return 'center' === $c['jc']; } ) ) / 6; } ),
			array( 'Listes (puce ✓, numérotation)', 4, function ( $D ) {
				$a = self::each( $D, array( 'Passeport ou carte', 'Veste imperméable et pull', 'Chaussures de marche', 'Maillot de bain', 'Masque de sommeil' ), function ( $p ) { return $p['num'] && 'bullet' === $p['num']['fmt']; } );
				$b = self::each( $D, array( 'Consultez la météo', 'Ne quittez jamais', 'Faites le plein', 'Gardez le numéro' ), function ( $p ) { return $p['num'] && 'decimal' === $p['num']['fmt']; } );
				return ( $a + $b ) / 2; } ),
			array( 'Graphique du climat (type, données, titre, étiquettes)', 6, function ( $D ) { $c = null; foreach ( $D['charts'] as $x ) { if ( false !== strpos( $x['type'], 'bar' ) ) { $c = $x; } } if ( ! $c ) { return 0; }
				return ( 2 + ( 2 === $c['series'] && 4 === $c['points'] ) + ( false !== mb_stripos( $c['title'], 'Reykjavik' ) ) + $c['labels'] + self::no_tabbed_lines( $D, array( 'Juillet', 'Septembre', 'Minimum' ) ) ) / 6; } ),
			array( 'Images (taille, habillage, style, texte de remplacement)', 8, function ( $D ) {
				$s = 0; $map = null; foreach ( $D['images'] as $im ) { if ( self::near( $im['cx'], 14, 0.5 ) ) { $map = $im; } }
				$s += $map ? 1 : 0;
				$c = self::img_in( $D, 'Seljalandsfoss se laisse admirer' ); $s += $c ? ( self::near( $c['cx'], 5.5, 0.3 ) + ( 'square' === $c['wrap'] ) + ( 'right' === $c['halign'] ) ) / 1.5 : 0;
				$l = self::img_in( $D, "À Jökulsárlón, d'immenses blocs" ); $s += $l ? ( self::near( $l['cx'], 5.5, 0.3 ) + ( 'square' === $l['wrap'] ) + ( 'left' === $l['halign'] ) + $l['border'] ) / 2 : 0;
				$n = 0; foreach ( $D['images'] as $im ) { if ( '' !== $im['alt'] ) { $n++; } } $s += min( 2, $n / 2 );
				$s += ( $D['images'] && $D['images'][0]['p'] < self::idx( $D, 'Islande, terre de feu' ) + 2 ) ? 1 : 0;
				return $s / 8; } ),
			array( 'Couverture (image, titre 36 pt Bleu foncé) et sauts de page', 4, function ( $D ) { $t = self::heading( $D, 'Islande, terre de feu et de glace' );
				$tdm = false; foreach ( $D['paragraphs'] as $p ) { if ( $p['toc'] ) { $tdm = true; } }
				return ( self::rp( $D, $t, 'Islande', 'sz', 72 ) + self::rp( $D, $t, 'Islande', 'color', '002060' ) + self::page_break_before( $D, self::heading( $D, 'Bienvenue en Islande' ) ) * 2 ) / 4; } ),
			array( 'Lexique en deux colonnes avec ligne séparatrice', 3, function ( $D ) { $p = self::P( $D, 'Takk' ); $s = self::sect_of( $D, $p ); return ( ( $s && 2 === $s['cols'] && $s['sep'] ) * 2 + self::rp( $D, $p, 'Takk', 'b' ) ) / 3; } ),
			array( 'En-tête (logo + texte), pied « Page X sur Y », première page différente', 5, function ( $D ) { $h = self::header_main( $D ); $f = self::footer_main( $D );
				return ( ( $h && $h['images'] > 0 ) + ( $h && false !== mb_stripos( $h['text'], 'Carnet de voyage' ) ) + ( $f && in_array( 'PAGE', $f['fields'], true ) ) + ( $f && in_array( 'NUMPAGES', $f['fields'], true ) ) + ! empty( $D['sections'][0]['titlePg'] ) ) / 5; } ),
			array( 'Table des matières (position, contenu à jour)', 4, function ( $D ) use ( $h1, $h2 ) { if ( ! $D['toc'] ) { return 0; } $ok = 0; foreach ( array_merge( $h1, $h2 ) as $t ) { if ( self::toc_has( $D, $t ) ) { $ok++; } }
				$first = null; foreach ( $D['paragraphs'] as $p ) { if ( $p['toc'] ) { $first = $p['i']; break; } } $b = self::heading( $D, 'Bienvenue en Islande' );
				return ( 2 * $ok / 11 + 2 * ( $b && $first < $b['i'] ) ) / 4; } ),
		);
	}
}
