<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LOGIEVAL_Auth
 *
 * Uses a signed WordPress cookie instead of PHP sessions.
 * This works reliably on all devices (mobile, iOS Safari, etc.)
 * and on all hosting configurations, including those that
 * disable or misconfigure PHP sessions.
 *
 * Cookie name : logieval_auth
 * Cookie value: base64( json( [ens_id, expiry, hmac] ) )
 * HMAC key    : AUTH_KEY + SECURE_AUTH_KEY (WordPress secrets)
 */
class LOGIEVAL_Auth {

    const COOKIE_NAME   = 'logieval_auth';
    const COOKIE_DAYS   = 60;   // LogiOne 1.28 : 60 jours (auparavant ~10 ans) ; « ordinateur partagé » : 12 h, cookie de session
    const SHARED_HOURS  = 12;

    /* Brute-force protection: failed logins are counted per IP address,
       whatever identifier is tried (counting per identifier let an attacker
       try a new identifier at every request without ever being slowed down). */
    // LogiOne 1.53.0 : moins rigide pour une école entière derrière la même adresse internet
    // (60 essais faux en 5 minutes ; avec des identifiants de 8 caractères ou plus, deviner reste impossible).
    const MAX_FAILED_PER_IP = 60;
    const LOCKOUT_MINUTES   = 5;
    const MIN_ID_LENGTH     = 8;

    /* ── HMAC helpers ───────────────────────────────────────────── */
    private static function secret(): string {
        return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY . 'logieval_v2' );
    }

    private static function sign( int $ens_id, int $expiry ): string {
        return hash_hmac( 'sha256', $ens_id . '|' . $expiry, self::secret() );
    }

    private static function session_version( array $ens ): string {
        return hash_hmac(
            'sha256',
            (int) $ens['id'] . '|' . (string) ( $ens['identifiant_unique'] ?? '' ),
            self::secret()
        );
    }

    private static function client_ip(): string {
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
    }

    private static function cookie_paths(): array {
        return array_values(array_unique(array_filter([
            '/',
            defined('COOKIEPATH') ? COOKIEPATH : '',
            defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '',
        ])));
    }

    private static function cookie_domains(): array {
        return array_values(array_unique([
            '',
            defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '',
        ]));
    }

    /* ── Set cookie ─────────────────────────────────────────────── */
    private static function set_cookie( int $ens_id, array $ens = [], bool $shared = false ): void {
        $expiry  = $shared ? time() + self::SHARED_HOURS * HOUR_IN_SECONDS : time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS );
        if ( empty( $ens ) ) {
            $ens = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['id' => $ens_id] ) ?: [];
        }
        $payload = base64_encode( json_encode([
            'id'  => $ens_id,
            'exp' => $expiry,
            'sig' => self::sign( $ens_id, $expiry ),
            'ver' => self::session_version( $ens ),
        ]));

        // setcookie with SameSite=Lax works on iOS Safari 12+
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( self::COOKIE_NAME, $payload, [
                'expires'  => $shared ? 0 : $expiry, // ordinateur partagé : supprimé à la fermeture du navigateur
                'path'     => '/',
                'domain'   => COOKIE_DOMAIN ?: '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            // Fallback for PHP < 7.3
            setcookie(
                self::COOKIE_NAME,
                $payload,
                $shared ? 0 : $expiry,
                '/; SameSite=Lax',
                COOKIE_DOMAIN ?: '',
                is_ssl(),
                true
            );
        }

        // Also set in $_COOKIE immediately so current request can read it
        $_COOKIE[ self::COOKIE_NAME ] = $payload;
    }

    /* ── Clear cookie ───────────────────────────────────────────── */
    private static function clear_cookie(): void {
        $past = time() - 3600;
        foreach ( self::cookie_paths() as $path ) {
            foreach ( self::cookie_domains() as $domain ) {
                if ( PHP_VERSION_ID >= 70300 ) {
                    setcookie( self::COOKIE_NAME, '', [
                        'expires'  => $past,
                        'path'     => $path,
                        'domain'   => $domain,
                        'secure'   => is_ssl(),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie( self::COOKIE_NAME, '', $past,
                        $path . '; SameSite=Lax',
                        $domain, is_ssl(), true
                    );
                }
            }
        }
        unset( $_COOKIE[ self::COOKIE_NAME ] );
    }

    /* ── Read & validate cookie ─────────────────────────────────── */
    private static function read_cookie(): int {
        $raw = $_COOKIE[ self::COOKIE_NAME ] ?? '';
        if ( ! $raw ) return 0;

        $data = json_decode( base64_decode( $raw ), true );
        if ( ! is_array($data) || empty($data['id']) || empty($data['exp']) || empty($data['sig']) ) {
            return 0;
        }

        // Check expiry
        if ( (int)$data['exp'] < time() ) return 0;

        // Check signature
        $expected = self::sign( (int)$data['id'], (int)$data['exp'] );
        if ( ! hash_equals( $expected, $data['sig'] ) ) return 0;

        $ens = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['id' => (int) $data['id']] );
        if ( ! $ens || empty( $ens['actif'] ) || empty( $data['ver'] ) || ! hash_equals( self::session_version( $ens ), (string) $data['ver'] ) ) {
            self::clear_cookie();
            return 0;
        }

        return (int) $data['id'];
    }

    /* ── Public API ─────────────────────────────────────────────── */

    private static function rate_key(): string {
        return 'logieval_login_ip_' . md5( self::client_ip() );
    }

    /** True when this IP address has made too many failed attempts recently. */
    public static function is_rate_limited(): bool {
        return (int) get_transient( self::rate_key() ) >= self::MAX_FAILED_PER_IP;
    }

    public static function login( string $identifier, bool $shared = false ): bool {
        $identifier = strtoupper( trim( sanitize_text_field( $identifier ) ) );
        if ( self::is_rate_limited() ) {
            return false;
        }

        $ens = ( $identifier !== '' && strlen( $identifier ) <= 40 )
            ? LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['identifiant_unique' => $identifier] )
            : null;
        if ( ! $ens || empty( $ens['actif'] ) ) {
            $key = self::rate_key();
            set_transient( $key, (int) get_transient( $key ) + 1, self::LOCKOUT_MINUTES * MINUTE_IN_SECONDS );
            return false;
        }

        delete_transient( self::rate_key() );
        self::set_cookie( (int) $ens['id'], $ens, $shared );
        // LogiOne : l'identifiant est retenu pour ce compte ; la prochaine fois, l'enseignant est reconnu sans le saisir.
        if ( is_user_logged_in() && current_user_can( 'lp_eval_use' ) ) {
            update_user_meta( get_current_user_id(), 'logieval_enseignant_id', (int) $ens['id'] );
        }
        return true;
    }

    public static function logout(): void {
        self::clear_cookie();
    }

    public static function current_enseignant_id(): int {
        $id = self::read_cookie();
        return $id ? $id : self::linked_enseignant_id();
    }

    /**
     * LogiOne : enseignant reconnu par son compte WordPress, sans identifiant à saisir.
     * Réservé aux comptes qui ont la permission LogiOne « lp_eval_use » (un élève qui s'inscrirait
     * avec l'adresse d'un enseignant n'obtient rien). D'abord le lien retenu à la première connexion
     * avec l'identifiant, sinon la même adresse e-mail. L'enseignant doit être actif dans LogiEval.
     */
    public static function linked_enseignant_id(): int {
        static $cache = array();
        if ( ! is_user_logged_in() || ! current_user_can( 'lp_eval_use' ) ) {
            return 0;
        }
        $user = wp_get_current_user();
        if ( isset( $cache[ $user->ID ] ) ) {
            return $cache[ $user->ID ];
        }
        $table  = LOGIEVAL_Database::t( 'enseignants' );
        $linked = (int) get_user_meta( $user->ID, 'logieval_enseignant_id', true );
        $ens    = $linked ? LOGIEVAL_Database::get_row( $table, [ 'id' => $linked ] ) : null;
        if ( ( ! $ens || empty( $ens['actif'] ) ) && is_email( $user->user_email ) ) {
            $ens = LOGIEVAL_Database::get_row( $table, [ 'email' => $user->user_email ] );
        }
        return $cache[ $user->ID ] = ( $ens && ! empty( $ens['actif'] ) ) ? (int) $ens['id'] : 0;
    }

    /** Vrai quand l'enseignant est reconnu par son compte LogiOne (et non par le cookie de l'identifiant). */
    public static function is_linked(): bool {
        return ! self::read_cookie() && self::linked_enseignant_id() > 0;
    }

    public static function is_logged_in(): bool {
        return self::current_enseignant_id() > 0;
    }

    public static function current_enseignant(): ?array {
        $id = self::current_enseignant_id();
        if ( ! $id ) return null;
        return LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['id' => $id] );
    }

    public static function is_maitre( int $enseignant_id, int $classe_id ): bool {
        $row = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('classe_enseignant'), [
            'enseignant_id' => $enseignant_id,
            'classe_id'     => $classe_id,
        ]);
        return $row && (bool) $row['est_maitre'];
    }

    public static function generate_identifier(): string {
        $prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) get_option( 'logieval_id_prefix', 'ENS' ) ) );
        if ( $prefix === '' ) $prefix = 'ENS';
        $prefix = substr( $prefix, 0, 6 );
        $length = min( 16, max( self::MIN_ID_LENGTH, (int) get_option( 'logieval_id_length', 8 ) ) );
        // La colonne fait 20 caractères : préfixe + tiret + partie aléatoire ne doit jamais dépasser.
        $length = min( $length, 20 - strlen( $prefix ) - 1 );
        do {
            $rand  = strtoupper( substr( bin2hex( random_bytes(8) ), 0, $length ) );
            $ident = $prefix . '-' . $rand;
            $exists = LOGIEVAL_Database::get_row( LOGIEVAL_Database::t('enseignants'), ['identifiant_unique' => $ident] );
        } while ( $exists );
        return $ident;
    }

    public static function regenerate( int $enseignant_id ): string {
        $new = self::generate_identifier();
        LOGIEVAL_Database::update(
            LOGIEVAL_Database::t('enseignants'),
            ['identifiant_unique' => $new],
            ['id' => $enseignant_id]
        );
        return $new;
    }

    // Kept for backward compatibility — no longer needed but harmless
    public static function maybe_start_session(): void {}
}
