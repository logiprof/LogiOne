<?php
// Test isolé (données fictives, sans WordPress ni base réelle) de start_attempt() — LogiOne 1.50.0.
// Lancer : php tests/exam-code-entry-test.php modules/exam/includes/evaluations/trait-student-session.php
define( 'ABSPATH', __DIR__ ); define( 'ARRAY_A', 'ARRAY_A' );
function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
function __( $m, $d ) { return $m; }
function wp_check_password( $plain, $hash ) { ++$GLOBALS['hash_checks']; return $hash === 'H_' . $plain; }
class LogiEval_Secure_Database { public static function tables() { return array( 'codes' => 'codes' ); } }
class FakeDb {
	public $queries = array(); public $rows = array();
	public function prepare( $q, ...$a ) { return vsprintf( str_replace( '%d', '%s', $q ), $a ); }
	public function get_results( $q, $f ) { $this->queries[] = $q; return strpos( $q, 'user_id<>' ) !== false ? array() : $this->rows; }
	public function update( ...$a ) { throw new RuntimeException( 'write' ); }
	public function insert( ...$a ) { throw new RuntimeException( 'write' ); }
}
require $argv[1];
class Probe {
	use LogiEval_Secure_Evaluations_Student_Session;
	public static $open = array();
	public static function get_evaluation( $id ) { return array( 'id' => $id, 'status' => 'published' ); }
	public static function evaluation_is_open( $e ) { return ! empty( self::$open[ $e['id'] ] ); }
	public static function user_can_access_evaluation( $u, $e ) { return false; } // refus final : pas d'écriture en base
}
$cases = array(
	// nom => [code saisi, codes de l'élève (eval => code), évaluations ouvertes, comparaisons attendues]
	'30 anciens codes + code faux' => array( 'XXX', array_fill_keys( range( 1, 30 ), 'OLD' ), array(), 0 ),
	'30 anciens + 1 ouvert, bon code' => array( 'GOOD', array_fill_keys( range( 1, 30 ), 'OLD' ) + array( 99 => 'GOOD' ), array( 99 => 1 ), 1 ),
	'30 anciens + 1 ouvert, code faux' => array( 'BAD', array_fill_keys( range( 1, 30 ), 'OLD' ) + array( 99 => 'GOOD' ), array( 99 => 1 ), 1 ),
	'aucun code' => array( 'BAD', array(), array(), 0 ),
	'code d une evaluation fermee' => array( 'OLD', array( 5 => 'OLD' ), array(), 0 ),
);
foreach ( $cases as $name => $c ) {
	$wpdb = new FakeDb();
	foreach ( $c[1] as $eid => $code ) { $wpdb->rows[] = array( 'id' => $eid, 'evaluation_id' => $eid, 'code_hash' => 'H_' . $code ); }
	Probe::$open = $c[2]; $GLOBALS['hash_checks'] = 0;
	$r = Probe::start_attempt( 42, $c[0] );
	$ok = false === $r['success'] && $GLOBALS['hash_checks'] === $c[3] && count( $wpdb->queries ) === 1;
	printf( "%-36s comparaisons=%d attendu=%d %s | %s\n", $name, $GLOBALS['hash_checks'], $c[3], $ok ? 'OK' : 'ECHEC', $r['message'] );
	if ( ! $ok ) { exit( 1 ); }
}
