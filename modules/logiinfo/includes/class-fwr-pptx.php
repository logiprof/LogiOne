<?php
/** Lecture légère d'une présentation .pptx : qui l'a créée et enregistrée (docProps/core.xml) et résumé des diapositives. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Pptx {
	/** Créateur des présentations de départ fournies par la formation. */
	const CREATEURS_DEPART = array( 'LogiProf' );
	public $ok = false;
	public $error = '';
	public $d = array();
	private $m = null;

	public function __construct( $path ) {
		$z = new ZipArchive();
		if ( true !== $z->open( $path ) || false === $z->locateName( 'ppt/presentation.xml' ) ) { $this->error = 'présentation PowerPoint illisible'; return; }
		$core = (string) $z->getFromName( 'docProps/core.xml' );
		$app  = (string) $z->getFromName( 'docProps/app.xml' );
		$z->close();
		$tag = function ( $xml, $name ) { return preg_match( '#<(?:\w+:)?' . $name . '\b[^>]*>(.*?)</(?:\w+:)?' . $name . '>#s', $xml, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) : ''; };
		$creator = $tag( $core, 'creator' );
		$last    = $tag( $core, 'lastModifiedBy' );
		$authors = array();
		if ( $last && ! in_array( $last, self::CREATEURS_DEPART, true ) ) { $authors[ $last ] = 1; }
		$depart = in_array( $creator, self::CREATEURS_DEPART, true );
		if ( $creator && ! $depart ) { $authors[ $creator ] = ( $authors[ $creator ] ?? 0 ) + 1; }
		$this->m = new LI_Pptx_Model( $path );
		$this->d = array(
			'trace' => array(
				'kind'       => 'pptx',
				'creator'    => $creator,
				'depart'     => $depart,
				'protection' => 'pptx',
				'tracking'   => true,
				'n'          => $last ? 1 : 0,
				'authors'    => $authors,
				'first'      => $tag( $core, 'created' ),
				'last'       => $tag( $core, 'modified' ),
				'last_by'    => in_array( $last, self::CREATEURS_DEPART, true ) ? '' : $last,
				'app'        => $tag( $app, 'Application' ),
			),
		);
		$this->ok = true;
	}

	public function summary() {
		$t = $this->d['trace'];
		$s = 'Créée par : ' . ( $t['creator'] ?: '?' ) . ( $t['depart'] ? ' (présentation de départ de la formation)' : '' ) . "\n"
			. 'Dernier enregistrement par : ' . ( $t['last_by'] ?: '?' ) . ' (' . $t['last'] . ")\n"
			. 'Application : ' . ( $t['app'] ?: '?' ) . "\n";
		if ( ! $this->m || ! $this->m->ok ) { return $s; }
		$M = $this->m;
		$s .= sprintf( "Format : %s · thème « %s » (couleurs « %s », polices %s / %s)\n", abs( $M->ratio - 16 / 9 ) < 0.02 ? '16:9' : ( abs( $M->ratio - 4 / 3 ) < 0.02 ? '4:3' : round( $M->ratio, 2 ) ), $M->theme['name'] ?? '', $M->theme['colors'] ?? '', $M->theme['major'] ?? '', $M->theme['minor'] ?? '' );
		if ( $M->sections ) { $s .= 'Sections : ' . implode( ', ', array_map( function ( $x ) { return $x['name']; }, $M->sections ) ) . "\n"; }
		$s .= 'Images du masque : ' . (int) $M->master_pics . ' · poids des images : ' . round( $M->media_bytes / 1024 ) . ' Ko · commentaires : ' . (int) $M->comments . "\n\n";
		foreach ( $M->slides as $i => $sl ) {
			$obj = array();
			foreach ( array( 'pic' => 'image', 'shape' => 'forme', 'conn' => 'connecteur', 'video' => 'vidéo', 'smartart' => 'SmartArt' ) as $k => $lab ) { if ( ! empty( $sl[ $k ] ) ) { $obj[] = $sl[ $k ] . ' ' . $lab . ( $sl[ $k ] > 1 ? 's' : '' ); } }
			if ( $sl['table'] ) { $obj[] = count( $sl['table'] ) . ' tableau'; }
			if ( $sl['chart'] ) { $obj[] = 'graphique ' . implode( ', ', $sl['chart'] ); }
			$s .= sprintf( "%d. %s%s [%s]%s\n", $i + 1, $sl['hidden'] ? '(masquée) ' : '', $sl['title'] ?: '(sans titre)', $sl['layout_name'] ?: $sl['layout'], $obj ? ' – ' . implode( ', ', $obj ) : '' );
			$extra = array();
			if ( '' !== $sl['transition'] ) { $extra[] = 'transition ' . $sl['transition']; }
			if ( $sl['anim'] ) { $extra[] = $sl['anim'] . ' animation(s)'; }
			if ( '' !== $sl['notes'] ) { $extra[] = 'notes : « ' . mb_substr( $sl['notes'], 0, 80 ) . ( mb_strlen( $sl['notes'] ) > 80 ? '…' : '' ) . ' »'; }
			if ( $sl['sldnum'] ) { $extra[] = 'numéro'; }
			if ( '' !== $sl['footer'] ) { $extra[] = 'pied de page « ' . $sl['footer'] . ' »'; }
			if ( $sl['links_int'] || $sl['links_ext'] ) { $extra[] = 'liens : ' . $sl['links_int'] . ' interne(s), ' . $sl['links_ext'] . ' externe(s)'; }
			if ( $extra ) { $s .= '   ' . implode( ' · ', $extra ) . "\n"; }
			$txt = array_slice( array_filter( $sl['texts'], function ( $t ) use ( $sl ) { return $t !== $sl['title']; } ), 0, 8 );
			if ( $txt ) { $s .= '   ' . implode( ' | ', array_map( function ( $t ) { return mb_substr( $t, 0, 70 ); }, $txt ) ) . "\n"; }
		}
		return $s;
	}
}
