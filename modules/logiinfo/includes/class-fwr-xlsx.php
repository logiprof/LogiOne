<?php
/** Lecture légère d'un classeur .xlsx : qui l'a créé et qui l'a enregistré en dernier (docProps/core.xml). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Xlsx {
	/** Créateurs des fichiers de départ fournis par la formation. */
	const CREATEURS_DEPART = array( 'openpyxl', 'LogiProf' );
	public $ok = false;
	public $error = '';
	public $d = array();

	public function __construct( $path ) {
		$z = new ZipArchive();
		if ( true !== $z->open( $path ) || false === $z->locateName( 'xl/workbook.xml' ) ) { $this->error = 'fichier Excel illisible'; return; }
		$core = (string) $z->getFromName( 'docProps/core.xml' );
		$app  = (string) $z->getFromName( 'docProps/app.xml' );
		$wb   = (string) $z->getFromName( 'xl/workbook.xml' );
		$z->close();
		$tag = function ( $xml, $name ) { return preg_match( '#<(?:\w+:)?' . $name . '\b[^>]*>(.*?)</(?:\w+:)?' . $name . '>#s', $xml, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) : ''; };
		$creator = $tag( $core, 'creator' );
		$last    = $tag( $core, 'lastModifiedBy' );
		preg_match_all( '#<(?:\w+:)?sheet\b[^>]*\bname="([^"]*)"#', $wb, $sh );
		$authors = array();
		if ( $last ) { $authors[ $last ] = 1; }
		$depart = in_array( $creator, self::CREATEURS_DEPART, true );
		if ( $creator && ! $depart ) { $authors[ $creator ] = ( $authors[ $creator ] ?? 0 ) + 1; }
		$this->d = array(
			'trace' => array(
				'kind'       => 'xlsx',
				'creator'    => $creator,
				'depart'     => $depart,
				'protection' => 'xlsx',
				'tracking'   => true,
				'n'          => $last ? 1 : 0,
				'authors'    => $authors,
				'first'      => $tag( $core, 'created' ),
				'last'       => $tag( $core, 'modified' ),
				'last_by'    => $last,
				'app'        => $tag( $app, 'Application' ),
			),
			'sheets' => array_map( function ( $s ) { return html_entity_decode( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' ); }, $sh[1] ),
		);
		$this->ok = true;
	}

	public function summary() {
		$t = $this->d['trace'];
		return 'Créé par : ' . ( $t['creator'] ?: '?' ) . ( $t['depart'] ? ' (fichier de départ de la formation)' : '' ) . "\n"
			. 'Dernier enregistrement par : ' . ( $t['last_by'] ?: '?' ) . ' (' . $t['last'] . ")\n"
			. 'Application : ' . ( $t['app'] ?: '?' ) . "\n"
			. 'Feuilles : ' . implode( ', ', $this->d['sheets'] );
	}
}
