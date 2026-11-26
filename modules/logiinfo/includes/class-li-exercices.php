<?php
/**
 * LogiInfo – remise d'exercices dans n'importe quel cours ou leçon :
 * [logiinfo_remise titre="Rapport de visite" types="pdf,docx" fichiers="1"]
 * Chaque exercice est enregistré une fois (table logiinfo_exercices) et reçoit le code EX001, EX002…
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LI_Exercices {
	const BASE  = 1000; // clé interne = 1000 + id
	const TYPES = array( 'docx', 'xlsx', 'pptx', 'pdf', 'jpg', 'jpeg', 'png', 'zip', 'txt', 'odt', 'ods', 'odp' );

	public static function table() { global $wpdb; return $wpdb->prefix . 'logiinfo_exercices'; }

	public static function init() {
		add_shortcode( 'logiinfo_remise', array( __CLASS__, 'shortcode' ) );
	}

	public static function install( $c ) {
		$t = self::table();
		dbDelta( "CREATE TABLE $t (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			titre varchar(190) NOT NULL,
			types varchar(120) NOT NULL DEFAULT 'docx,xlsx,pptx,pdf',
			fichiers tinyint(3) unsigned NOT NULL DEFAULT 1,
			consignes text NULL,
			cree datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_titre (post_id,titre)
		) $c;" );
	}

	/** Exercices libres au format du registre des leçons (clé 1000 + id). */
	public static function as_lecons() {
		global $wpdb;
		$out = array();
		$t = self::table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return $out; }
		foreach ( $wpdb->get_results( "SELECT * FROM $t ORDER BY id", ARRAY_A ) as $e ) { // phpcs:ignore
			$types = array_values( array_intersect( array_map( 'trim', explode( ',', strtolower( $e['types'] ) ) ), self::TYPES ) );
			$post = $e['post_id'] ? get_the_title( $e['post_id'] ) : '';
			$out[ self::BASE + (int) $e['id'] ] = array(
				'titre' => $post ? html_entity_decode( $post, ENT_QUOTES, 'UTF-8' ) : $e['titre'], 'exercice' => $e['titre'], 'fichiers' => max( 1, (int) $e['fichiers'] ),
				'type' => 'libre', 'types' => $types ? $types : array( 'pdf' ), 'formation' => 'libre', 'num' => (int) $e['id'], 'post_id' => (int) $e['post_id'],
				'mission' => (string) $e['consignes'], 'consignes' => (string) $e['consignes'], 'grille' => '[]',
			);
		}
		return $out;
	}

	public static function shortcode( $atts ) {
		global $wpdb;
		$post_id = (int) get_the_ID();
		$a = shortcode_atts( array( 'titre' => '', 'types' => 'docx,xlsx,pptx,pdf', 'fichiers' => 1, 'consignes' => '' ), $atts );
		$titre = sanitize_text_field( $a['titre'] ? $a['titre'] : ( $post_id ? get_the_title( $post_id ) : 'Exercice' ) );
		$types = implode( ',', array_intersect( array_map( 'trim', explode( ',', strtolower( $a['types'] ) ) ), self::TYPES ) );
		$t = self::table();
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE post_id = %d AND titre = %s", $post_id, $titre ) ); // phpcs:ignore
		$row = array( 'types' => $types ? $types : 'pdf', 'fichiers' => max( 1, min( 10, (int) $a['fichiers'] ) ), 'consignes' => sanitize_textarea_field( $a['consignes'] ) );
		if ( ! $id ) {
			$wpdb->insert( $t, $row + array( 'post_id' => $post_id, 'titre' => $titre, 'cree' => current_time( 'mysql' ) ) );
			$id = (int) $wpdb->insert_id;
			LIR_Core::reset_lecons();
		} else {
			$wpdb->update( $t, $row, array( 'id' => $id ) ); // réglages du code court modifiés dans la leçon
			LIR_Core::reset_lecons();
		}
		return LIR_Front::sc_remise( array( 'cle' => self::BASE + $id ) );
	}

	/** Vérifie qu'un fichier correspond bien à son extension (contenu réel, pas seulement le nom). */
	public static function valid_file( $tmp, $ext ) {
		$office = array( 'docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml', 'odt' => 'content.xml', 'ods' => 'content.xml', 'odp' => 'content.xml' );
		if ( isset( $office[ $ext ] ) || 'zip' === $ext ) {
			$z = new ZipArchive();
			if ( true !== $z->open( $tmp ) ) { return false; }
			$ok = 'zip' === $ext || false !== $z->locateName( $office[ $ext ] );
			$z->close();
			return $ok;
		}
		$head = (string) file_get_contents( $tmp, false, null, 0, 8 );
		if ( 'pdf' === $ext ) { return 0 === strpos( $head, '%PDF' ); }
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) { $i = @getimagesize( $tmp ); return $i && in_array( $i[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG ), true ); }
		if ( 'txt' === $ext ) { return false === strpos( (string) file_get_contents( $tmp, false, null, 0, 4096 ), '<?' ); }
		return false;
	}
}
LI_Exercices::init();
