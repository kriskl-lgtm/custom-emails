<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Logger {

    const TABLE_SUFFIX = 'ce_mail_log';
    const OPTION_ON    = 'ce_logger_enabled';
    const RETENTION_D  = 7;

    public static function boot() {
        add_filter( 'wp_mail', [ __CLASS__, 'capture' ], 999 );
        add_action( 'ce_logger_gc', [ __CLASS__, 'gc' ] );
        if ( ! wp_next_scheduled( 'ce_logger_gc' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ce_logger_gc' );
        }
    }

    public static function install() {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE $t (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sent_at DATETIME NOT NULL,
            to_addr TEXT NOT NULL,
            subject TEXT NOT NULL,
            fingerprint CHAR(32) NOT NULL,
            body LONGTEXT NOT NULL,
            backtrace TEXT NOT NULL,
            promoted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id), KEY fp (fingerprint), KEY sent_at (sent_at)
        ) $charset;" );
    }

    public static function capture( $atts ) {
        if ( ! get_option( self::OPTION_ON ) ) return $atts;
        global $wpdb;
        $trace   = wp_debug_backtrace_summary( null, 2 );
        $subject = $atts['subject'] ?? '';
        $fp      = md5( preg_replace( '/\d+/', '#', $subject ) . '|' . substr( $trace, 0, 200 ) );
        $wpdb->insert( $wpdb->prefix . self::TABLE_SUFFIX, [
            'sent_at'     => current_time( 'mysql' ),
            'to_addr'     => is_array( $atts['to'] ?? '' ) ? implode( ',', $atts['to'] ) : (string) ( $atts['to'] ?? '' ),
            'subject'     => $subject,
            'fingerprint' => $fp,
            'body'        => (string) ( $atts['message'] ?? '' ),
            'backtrace'   => $trace,
        ] );
        return $atts;
    }

    public static function gc() {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE sent_at < %s",
            gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_D * DAY_IN_SECONDS ) ) );
    }

    public static function unregistered() {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUFFIX;
        return $wpdb->get_results( "SELECT fingerprint, subject, backtrace, MAX(promoted) AS promoted, COUNT(*) c, MAX(sent_at) last
                                    FROM $t GROUP BY fingerprint ORDER BY last DESC" );
    }

    public static function mark_promoted( $fingerprint ) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->update( $t, [ 'promoted' => 1 ], [ 'fingerprint' => $fingerprint ] );
    }

    public static function sample( $fingerprint ) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUFFIX;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE fingerprint=%s ORDER BY id DESC LIMIT 1", $fingerprint ) );
    }
}
