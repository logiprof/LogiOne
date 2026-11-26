<?php
/**
 * LogiInfo – contrôle automatique des exercices Excel.
 * build()   : compare le fichier de départ et le résultat attendu, et en déduit la liste des points à vérifier.
 * evaluate(): vérifie ces points dans le classeur rendu par l'apprenti.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'LI_CLI' ) ) { exit; }

class LI_Xlsx_Check {

	/* ================================================================ génération des critères */
	public static function build( LI_Xlsx_Model $raw, LI_Xlsx_Model $res, $consignes = '' ) {
		$checks = array();
		$ask = array(
			'landscape' => (bool) preg_match( '/paysage/iu', $consignes ),
			'fit'       => (bool) preg_match( '/(sur|à) (1|une) page|1 page en largeur/iu', $consignes ),
			'titles'    => (bool) preg_match( '/titres répétés|lignes? à répéter/iu', $consignes ),
			'pivot'     => (bool) preg_match( '/Tableau croisé dynamique/u', $consignes ),
		);
		foreach ( $res->order as $si => $name ) {
			$R = $res->sheets[ $name ];
			$rawname = isset( $raw->sheets[ $name ] ) ? $name : null;
			$A = $rawname ? $raw->sheets[ $rawname ] : null;
			if ( ! $A ) {
				$checks[] = array( 'type' => 'sheet', 'sheet' => $name, 'label' => 'Feuille « ' . $name . ' » présente', 'pts' => 1 );
			}
			if ( ! $A && $ask['pivot'] ) {
				// feuille de tableau croisé dynamique : la disposition exacte dépend d'Excel, on cherche les résultats n'importe où sur la feuille
				$nums = array(); $txts = array();
				foreach ( $R['cells'] as $ref => $c ) {
					if ( 1 === LI_Xlsx_Model::split_ref( $ref )[1] || null === $c['v'] || '' === $c['v'] ) { continue; }
					if ( is_float( $c['v'] ) ) { $nums[] = $c['v']; } elseif ( is_string( $c['v'] ) && ! preg_match( '/^(Étiquettes|Total|Somme|Nombre|%|Row|Grand)/iu', $c['v'] ) ) { $txts[] = $c['v']; }
				}
				if ( $txts ) { $checks[] = array( 'type' => 'anywhere', 'sheet' => $name, 'values' => array_values( array_unique( $txts ) ), 'pts' => 1, 'label' => '« ' . $name . ' » : bonnes lignes et colonnes' ); }
				if ( $nums ) { $checks[] = array( 'type' => 'anywhere', 'sheet' => $name, 'values' => $nums, 'pts' => 2, 'label' => '« ' . $name . ' » : bons résultats calculés' ); }
				$pre = '« ' . $name . ' » : ';
				$ch0 = array();
				foreach ( $R['charts'] as $t ) { $checks[] = array( 'type' => 'chart', 'sheet' => $name, 'chart' => $t, 'pts' => 2, 'label' => $pre . 'graphique ' . self::chart_label( $t ) ); }
				continue;
			}
			// 1. cellules à remplir ou à modifier
			$changed = array();
			foreach ( $R['cells'] as $ref => $c ) {
				$o = $A['cells'][ $ref ] ?? null;
				$has = null !== $c['v'] || null !== $c['f'];
				if ( ! $has ) { continue; }
				$same = $o && self::same_val( $o['v'], $c['v'] ) && ( null === $o['f'] ) === ( null === $c['f'] ) && ( null === $c['f'] || self::nf( $o['f'] ) === self::nf( $c['f'] ) );
				if ( ! $same ) { $changed[ $ref ] = $c; }
			}
			$frag = array();
			foreach ( array_keys( $changed ) as $ref ) { if ( null === $changed[ $ref ]['f'] ) { list( $cl, $rw ) = LI_Xlsx_Model::split_ref( $ref ); $frag[ $cl ][] = $rw; } }
			foreach ( $frag as $cl => $rws ) {
				sort( $rws ); $breaks = 0;
				for ( $i = 1; $i < count( $rws ); $i++ ) { if ( $rws[ $i ] !== $rws[ $i - 1 ] + 1 ) { $breaks++; } }
				if ( $breaks < 1 || count( $rws ) < 10 ) { continue; }
				for ( $rw = $rws[0]; $rw <= end( $rws ); $rw++ ) { // données réordonnées (tri) : toute la colonne est vérifiée
					$ref = $cl . $rw;
					if ( isset( $R['cells'][ $ref ] ) && null === $R['cells'][ $ref ]['f'] && null !== $R['cells'][ $ref ]['v'] ) { $changed[ $ref ] = $R['cells'][ $ref ]; }
				}
			}
			foreach ( self::groups( array_keys( $changed ), function ( $ref ) use ( $changed ) { return null !== $changed[ $ref ]['f'] ? 'f' : 'v'; } ) as $g ) {
				$cells = array();
				foreach ( $g['refs'] as $ref ) {
					$c = $changed[ $ref ];
					$cells[ $ref ] = array( 'v' => $c['v'] instanceof DateTime ? null : $c['v'], 'f' => null !== $c['f'] );
				}
				$isf = 'f' === $g['kind'];
				$checks[] = array( 'type' => 'cells', 'sheet' => $name, 'cells' => $cells, 'formula' => $isf, 'pts' => $isf ? 2 : 1,
					'label' => self::where( $res, $name, $g ) . ( $isf ? ' : formules et résultats' : ' : contenu' ) );
			}
			// 2. formats de nombres et mise en forme des cellules (comparées à la feuille de départ)
			$fmt = array(); $sty = array();
			$intable = array(); // la mise en forme d'un tableau Excel vient de son style de tableau, pas des cellules
			foreach ( $R['table_refs'] as $tr ) { foreach ( LI_Xlsx_Model::cells_of( $tr ) as $x ) { $intable[ $x ] = true; } }
			foreach ( $R['cells'] as $ref => $c ) {
				$s1 = $res->style( $name, $ref );
				$s0 = $A ? $raw->style( $rawname, $ref ) : $raw->style( '', '' );
				if ( null !== $c['v'] && is_float( $c['v'] ) || ( null !== $c['f'] ) ) {
					$k1 = LI_Xlsx_Model::fmt_class( $s1['fmt'] );
					if ( $k1 !== LI_Xlsx_Model::fmt_class( $s0['fmt'] ) ) { $fmt[ $k1 ][] = $ref; }
				}
				if ( isset( $intable[ $ref ] ) ) { continue; }
				foreach ( array( 'bold', 'fill', 'border', 'halign', 'wrap', 'italic' ) as $p ) {
					if ( $s1[ $p ] !== $s0[ $p ] ) { $sty[ $p . '=' . json_encode( $s1[ $p ] ) ][] = $ref; }
				}
			}
			foreach ( $fmt as $k => $refs ) {
				foreach ( self::groups( $refs, null ) as $g ) {
					$checks[] = array( 'type' => 'numfmt', 'sheet' => $name, 'cells' => $g['refs'], 'fmt' => $k, 'pts' => 1,
						'label' => self::where( $res, $name, $g ) . ' : format ' . self::fmt_label( $k ) );
				}
			}
			$stymap = array();
			$lab = array( 'bold' => 'gras', 'italic' => 'italique', 'fill' => 'couleur de remplissage', 'border' => 'bordures', 'halign' => 'alignement', 'wrap' => 'renvoi à la ligne' );
			foreach ( $sty as $k => $refs ) {
				list( $p, $val ) = explode( '=', $k, 2 );
				$val = json_decode( $val, true );
				if ( false === $val || '' === $val ) { continue; } // on ne vérifie que ce qui a été ajouté
				foreach ( self::groups( $refs, null ) as $g ) {
					$key = $name . '|' . implode( ',', $g['refs'] );
					$txt = $lab[ $p ] . ( 'halign' === $p ? ' ' . self::align_label( $val ) : '' );
					if ( isset( $stymap[ $key ] ) ) {
						$checks[ $stymap[ $key ] ]['props'][ $p ] = $val;
						$checks[ $stymap[ $key ] ]['label'] .= ', ' . $txt;
					} else {
						$stymap[ $key ] = count( $checks );
						$checks[] = array( 'type' => 'style', 'sheet' => $name, 'cells' => $g['refs'], 'props' => array( $p => $val ), 'pts' => 1, 'label' => ucfirst( self::range_label( $g ) ) . ' : ' . $txt );
					}
				}
			}
			// 3. objets et réglages de la feuille
			$pre = count( $res->order ) > 1 ? '« ' . $name . ' » : ' : '';
			$new = function ( $key ) use ( $A, $R ) { return $R[ $key ] && ( ! $A || $R[ $key ] !== $A[ $key ] ); };
			foreach ( array_diff( $R['merges'], $A ? $A['merges'] : array() ) as $mg ) { $checks[] = array( 'type' => 'merge', 'sheet' => $name, 'ref' => $mg, 'pts' => 1, 'label' => $pre . 'cellules ' . $mg . ' fusionnées' ); }
			if ( $new( 'frozen' ) ) { $checks[] = array( 'type' => 'frozen', 'sheet' => $name, 'ref' => $R['frozen'], 'pts' => 1, 'label' => $pre . 'volets figés (en ' . $R['frozen'] . ')' ); }
			if ( $new( 'autofilter' ) && ! $R['tables'] ) { $checks[] = array( 'type' => 'autofilter', 'sheet' => $name, 'pts' => 1, 'label' => $pre . 'filtre automatique activé' ); }
			if ( $R['tables'] > ( $A ? $A['tables'] : 0 ) ) { $checks[] = array( 'type' => 'tables', 'sheet' => $name, 'n' => $R['tables'], 'pts' => 1, 'label' => $pre . 'données mises sous forme de tableau' ); }
			$cf0 = $A ? array_map( 'json_encode', $A['cf'] ) : array();
			foreach ( $R['cf'] as $cf ) {
				if ( in_array( json_encode( $cf ), $cf0, true ) ) { continue; }
				$checks[] = array( 'type' => 'cf', 'sheet' => $name, 'sqref' => $cf['sqref'], 'cf' => $cf['type'], 'pts' => 1, 'label' => $pre . 'mise en forme conditionnelle ' . self::cf_label( $cf['type'] ) . ' sur ' . $cf['sqref'] );
			}
			$dv0 = $A ? array_map( 'json_encode', $A['dv'] ) : array();
			foreach ( $R['dv'] as $dv ) {
				if ( in_array( json_encode( $dv ), $dv0, true ) ) { continue; }
				$checks[] = array( 'type' => 'dv', 'sheet' => $name, 'sqref' => $dv['sqref'], 'dv' => $dv['type'], 'pts' => 1, 'label' => $pre . ( 'list' === $dv['type'] ? 'liste déroulante' : 'validation des données' ) . ' en ' . $dv['sqref'] );
			}
			$ch0 = $A ? $A['charts'] : array();
			foreach ( $R['charts'] as $i => $t ) {
				if ( isset( $ch0[ $i ] ) && $ch0[ $i ] === $t ) { continue; }
				$checks[] = array( 'type' => 'chart', 'sheet' => $name, 'chart' => $t, 'pts' => 2, 'label' => $pre . 'graphique ' . self::chart_label( $t ) );
			}
			if ( $R['pivots'] > ( $A ? $A['pivots'] : 0 ) ) { $checks[] = array( 'type' => 'pivot', 'sheet' => $name, 'pts' => 2, 'label' => $pre . 'tableau croisé dynamique' ); }
			if ( $ask['landscape'] && $new( 'orientation' ) && 'landscape' === $R['orientation'] ) { $checks[] = array( 'type' => 'landscape', 'sheet' => $name, 'pts' => 1, 'label' => $pre . 'impression en paysage' ); }
			if ( $ask['fit'] && $R['fit'] && ( ! $A || ! $A['fit'] ) ) { $checks[] = array( 'type' => 'fit', 'sheet' => $name, 'pts' => 1, 'label' => $pre . 'impression ajustée à la page' ); }
			if ( $ask['titles'] && $R['print_titles'] && ( ! $A || $A['print_titles'] !== $R['print_titles'] ) ) { $checks[] = array( 'type' => 'titles', 'sheet' => $name, 'pts' => 1, 'label' => $pre . 'lignes à répéter à l\'impression' ); }
		}
		// réglages d'impression demandés une fois : on les vérifie sur la feuille principale de l'exercice
		$print = array( 'landscape', 'fit', 'titles' );
		$work = array();
		foreach ( $checks as $c ) { if ( ! in_array( $c['type'], $print, true ) ) { $work[ $c['sheet'] ] = ( $work[ $c['sheet'] ] ?? 0 ) + $c['pts']; } }
		if ( $work ) {
			arsort( $work ); $main = key( $work );
			$checks = array_values( array_filter( $checks, function ( $c ) use ( $print, $main ) { return ! in_array( $c['type'], $print, true ) || $c['sheet'] === $main; } ) );
		}
		if ( $ask['pivot'] && ! in_array( 'pivot', array_column( $checks, 'type' ), true ) ) {
			$checks[] = array( 'type' => 'pivot', 'sheet' => $res->order[0], 'pts' => 2, 'label' => 'Tableau croisé dynamique créé' );
		}
		return $checks;
	}

	/* ================================================================ vérification d'une copie */
	public static function evaluate( $checks, LI_Xlsx_Model $m ) {
		$out = array( 'items' => array(), 'score' => 0, 'max' => 0 );
		foreach ( $checks as $c ) {
			$sheet = self::find_sheet( $m, $c['sheet'] );
			$v = $sheet ? self::check( $c, $m, $sheet ) : 0;
			$got = round( $c['pts'] * max( 0, min( 1, $v ) ) * 2 ) / 2;
			$out['items'][] = array( 'label' => $c['label'], 'max' => $c['pts'], 'pts' => $got, 'ok' => $got >= $c['pts'] );
			$out['score'] += $got; $out['max'] += $c['pts'];
		}
		$out['pct'] = $out['max'] ? round( 100 * $out['score'] / $out['max'] ) : 0;
		return $out;
	}

	private static function find_sheet( LI_Xlsx_Model $m, $name ) {
		if ( isset( $m->sheets[ $name ] ) ) { return $name; }
		foreach ( $m->order as $n ) { if ( 0 === strcasecmp( trim( $n ), trim( $name ) ) ) { return $n; } }
		return null;
	}

	private static function check( $c, LI_Xlsx_Model $m, $s ) {
		$S = $m->sheets[ $s ];
		switch ( $c['type'] ) {
			case 'sheet': return 1;
			case 'cells':
				$n = count( $c['cells'] ); $okv = 0; $okf = 0;
				foreach ( $c['cells'] as $ref => $exp ) {
					$cell = $S['cells'][ $ref ] ?? null;
					if ( $cell && ( null === $exp['v'] || self::same_val( $cell['v'], $exp['v'] ) ) ) { $okv++; }
					if ( $cell && null !== $cell['f'] ) { $okf++; }
				}
				return $c['formula'] ? ( $okv + $okf ) / ( 2 * $n ) : $okv / $n;
			case 'numfmt':
				$ok = 0;
				foreach ( $c['cells'] as $ref ) { if ( LI_Xlsx_Model::fmt_class( $m->style( $s, $ref )['fmt'] ) === $c['fmt'] ) { $ok++; } }
				return $ok / count( $c['cells'] );
			case 'style':
				$ok = 0; $n = 0;
				foreach ( $c['cells'] as $ref ) {
					$st = $m->style( $s, $ref );
					foreach ( $c['props'] as $p => $val ) { $n++; if ( $st[ $p ] === $val ) { $ok++; } }
				}
				return $n ? $ok / $n : 0;
			case 'anywhere':
				$have = array();
				foreach ( $S['cells'] as $cell ) { if ( null !== $cell['v'] && '' !== $cell['v'] ) { $have[] = $cell['v']; } }
				$ok = 0;
				foreach ( $c['values'] as $want ) { foreach ( $have as $i => $h ) { if ( self::same_val( $h, $want ) ) { $ok++; unset( $have[ $i ] ); break; } } }
				return $ok / max( 1, count( $c['values'] ) );
			case 'merge': return in_array( $c['ref'], $S['merges'], true ) ? 1 : 0;
			case 'frozen': return '' === $S['frozen'] ? 0 : ( $S['frozen'] === $c['ref'] ? 1 : 0.5 );
			case 'autofilter': return ( $S['autofilter'] || $S['tables'] ) ? 1 : 0;
			case 'tables': return $S['tables'] >= $c['n'] ? 1 : 0;
			case 'cf':
				$want = LI_Xlsx_Model::cells_of( $c['sqref'] ); $best = 0;
				foreach ( $S['cf'] as $cf ) {
					$cov = count( array_intersect( $want, LI_Xlsx_Model::cells_of( $cf['sqref'] ) ) ) / max( 1, count( $want ) );
					$best = max( $best, $cov * ( $cf['type'] === $c['cf'] ? 1 : 0.5 ) );
				}
				return $best >= 0.9 ? 1 : $best;
			case 'dv':
				$want = LI_Xlsx_Model::cells_of( $c['sqref'] ); $best = 0;
				foreach ( $S['dv'] as $dv ) {
					$cov = count( array_intersect( $want, LI_Xlsx_Model::cells_of( $dv['sqref'] ) ) ) / max( 1, count( $want ) );
					$best = max( $best, $cov * ( $dv['type'] === $c['dv'] ? 1 : 0.5 ) );
				}
				return $best >= 0.9 ? 1 : $best;
			case 'chart':
				$all = array();
				foreach ( $m->sheets as $x ) { $all = array_merge( $all, $x['charts'] ); }
				if ( in_array( $c['chart'], $all, true ) ) { return 1; }
				$fam = explode( ':', $c['chart'] )[0];
				foreach ( $all as $t ) { if ( explode( ':', $t )[0] === $fam ) { return 0.75; } }
				return $all ? 0.5 : 0;
			case 'pivot': return $m->pivots ? 1 : 0;
			case 'landscape': return 'landscape' === $S['orientation'] ? 1 : 0;
			case 'fit': return $S['fit'] ? 1 : 0;
			case 'titles': return $S['print_titles'] ? 1 : 0;
		}
		return 0;
	}

	/* ================================================================ outils */
	public static function same_val( $a, $b ) {
		if ( null === $a || '' === $a ) { return null === $b || '' === $b; }
		if ( null === $b || '' === $b ) { return false; }
		if ( is_bool( $a ) || is_bool( $b ) ) { return (bool) $a === (bool) $b; }
		if ( is_numeric( $a ) && is_numeric( $b ) ) { $a = (float) $a; $b = (float) $b; return abs( $a - $b ) <= max( 0.005, 1e-6 * abs( $b ) ); }
		return 0 === strcasecmp( preg_replace( '/\s+/u', ' ', trim( (string) $a ) ), preg_replace( '/\s+/u', ' ', trim( (string) $b ) ) );
	}
	private static function nf( $f ) { return strtoupper( preg_replace( '/\s+|\$/', '', (string) $f ) ); }

	/** Regroupe des cellules par colonne et par blocs de lignes consécutives. */
	private static function groups( $refs, $kindfn ) {
		$by = array();
		foreach ( $refs as $ref ) {
			list( $col, $row ) = LI_Xlsx_Model::split_ref( $ref );
			$k = $col . '|' . ( $kindfn ? $kindfn( $ref ) : '' );
			$by[ $k ][ $row ] = $ref;
		}
		$out = array();
		foreach ( $by as $k => $rows ) {
			ksort( $rows );
			list( $col, $kind ) = explode( '|', $k );
			$cur = array(); $prev = null;
			foreach ( $rows as $r => $ref ) {
				if ( null !== $prev && $r !== $prev + 1 ) { $out[] = array( 'col' => $col, 'kind' => $kind, 'refs' => $cur ); $cur = array(); }
				$cur[] = $ref; $prev = $r;
			}
			if ( $cur ) { $out[] = array( 'col' => $col, 'kind' => $kind, 'refs' => $cur ); }
		}
		// colonnes voisines de même nature sur les mêmes lignes : un seul critère (ex. B5:D5 ou A5:F40)
		$rows = function ( $x ) { return array_map( function ( $r ) { return LI_Xlsx_Model::split_ref( $r )[1]; }, $x ); };
		usort( $out, function ( $a, $b ) { return array( LI_Xlsx_Model::col_num( $a['col'] ), LI_Xlsx_Model::split_ref( $a['refs'][0] )[1] ) <=> array( LI_Xlsx_Model::col_num( $b['col'] ), LI_Xlsx_Model::split_ref( $b['refs'][0] )[1] ); } );
		$merged = array();
		foreach ( $out as $g ) {
			$done = false;
			foreach ( $merged as $i => $p ) {
				if ( $p['kind'] === $g['kind'] && $p['rows'] === $rows( $g['refs'] ) && LI_Xlsx_Model::col_num( $g['col'] ) === $p['lastcol'] + 1 ) {
					$merged[ $i ]['refs'] = array_merge( $p['refs'], $g['refs'] );
					$merged[ $i ]['lastcol']++; $merged[ $i ]['multi'] = true; $done = true; break;
				}
			}
			if ( ! $done ) { $merged[] = $g + array( 'rows' => $rows( $g['refs'] ), 'lastcol' => LI_Xlsx_Model::col_num( $g['col'] ), 'multi' => false ); }
		}
		foreach ( $merged as $i => $g ) { // première et dernière cellule du bloc
			$r = $g['rows'];
			$merged[ $i ]['first'] = $g['col'] . $r[0];
			$merged[ $i ]['last']  = LI_Xlsx_Model::col_letter( $g['lastcol'] ) . end( $r );
		}
		return $merged;
	}

	private static function range_label( $g ) {
		return 1 === count( $g['refs'] ) ? 'cellule ' . $g['first'] : 'cellules ' . $g['first'] . ':' . $g['last'];
	}
	/** « Colonne « Quantité » (E5:E12) » ou « Cellule E14 (Total) ». */
	private static function where( LI_Xlsx_Model $m, $sheet, $g ) {
		$r = $g['refs'];
		list( $col, $row ) = LI_Xlsx_Model::split_ref( $r[0] );
		$S = $m->sheets[ $sheet ]['cells'];
		$txt = function ( $ref ) use ( $S ) { $v = $S[ $ref ]['v'] ?? null; return is_string( $v ) && '' !== trim( $v ) && null === ( $S[ $ref ]['f'] ?? null ) ? trim( $v ) : ''; };
		$head = ''; for ( $y = $row - 1; $y >= max( 1, $row - 8 ); $y-- ) { if ( $t = $txt( $col . $y ) ) { $head = $t; break; } }
		$left = ''; for ( $x = LI_Xlsx_Model::col_num( $col ) - 1; $x >= 1; $x-- ) { if ( $t = $txt( LI_Xlsx_Model::col_letter( $x ) . $row ) ) { $left = $t; break; } }
		$short = function ( $s ) { return mb_strlen( $s ) > 40 ? mb_substr( $s, 0, 38 ) . '…' : $s; };
		if ( 1 === count( $r ) ) { return 'Cellule ' . $r[0] . ( $left ? ' (« ' . $short( $left ) . ' »)' : ( $head ? ' (« ' . $short( $head ) . ' »)' : '' ) ); }
		if ( ! empty( $g['multi'] ) && 1 === count( $g['rows'] ) ) { return 'Ligne ' . $row . ' (' . $g['first'] . ':' . $g['last'] . ( $left ? ', « ' . $short( $left ) . ' »' : '' ) . ')'; }
		if ( ! empty( $g['multi'] ) ) { return 'Plage ' . $g['first'] . ':' . $g['last'] . ( $head ? ' (sous « ' . $short( $head ) . ' »)' : '' ); }
		return ( $head ? 'Colonne « ' . $short( $head ) . ' »' : 'Colonne ' . $col ) . ' (' . $g['first'] . ':' . $g['last'] . ')';
	}
	private static function fmt_label( $k ) {
		$p = explode( ':', $k );
		$n = array( 'pourcent' => 'pourcentage', 'date' => 'date', 'heure' => 'heure', 'monnaie' => 'monétaire', 'nombre' => 'nombre', 'texte' => 'texte', 'standard' => 'standard' )[ $p[0] ] ?? $p[0];
		return $n . ( isset( $p[1] ) ? ' (' . $p[1] . ' décimale' . ( $p[1] > 1 ? 's' : '' ) . ')' : '' );
	}
	private static function align_label( $v ) { return array( 'center' => 'centré', 'right' => 'à droite', 'left' => 'à gauche', 'centerContinuous' => 'centré sur plusieurs colonnes' )[ $v ] ?? $v; }
	private static function cf_label( $t ) { return array( 'cellIs' => '(règle de valeur)', 'dataBar' => '(barres de données)', 'colorScale' => '(nuances de couleurs)', 'iconSet' => '(jeu d\'icônes)', 'expression' => '(formule)', 'top10' => '(premiers / derniers)', 'containsText' => '(texte contenant)', 'aboveAverage' => '(au-dessus de la moyenne)', 'duplicateValues' => '(doublons)' )[ $t ] ?? '(' . $t . ')'; }
	private static function chart_label( $t ) {
		return array( 'barChart:col' => 'en colonnes (histogramme)', 'barChart:bar' => 'en barres', 'pieChart' => 'en secteurs', 'pie3DChart' => 'en secteurs 3D', 'doughnutChart' => 'en anneau', 'lineChart' => 'en courbes', 'areaChart' => 'en aires', 'scatterChart' => 'en nuage de points' )[ $t ] ?? $t;
	}
}
