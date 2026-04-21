<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Wrapper {
    const OPT_HEADER = 'ce_wrapper_header';
    const OPT_FOOTER = 'ce_wrapper_footer';
    const OPT_HTML   = 'ce_wrapper_html';

    /**
     * Interceptors can set this before calling wp_mail()
     * so the wrapper knows which email ID is being sent.
     * Reset to null after each wrap() call.
     */
    public static $current_email_id = null;

    public static function boot() {
        add_filter( 'wp_mail', [ __CLASS__, 'wrap' ], 20 );
    }

    /**
     * Determine the effective format for the current email.
     * Priority: per-email override > global setting > 'text'.
     */
    public static function get_effective_format( $email_id = null ) {
        // 1. Check per-email override.
        if ( $email_id && class_exists( 'CE_Store' ) ) {
            $fmt = CE_Store::get_format( $email_id );
            if ( $fmt === 'html' ) return 'html';
            if ( $fmt === 'text' ) return 'text';
            // 'default' falls through to global.
        }
        // 2. Global setting.
        return get_option( self::OPT_HTML ) ? 'html' : 'text';
    }

    /**
     * Build the logo HTML block if a logo URL is configured.
     */
    public static function get_logo_html() {
        $logo_url = get_option( CE_Settings::OPT_LOGO, '' );
        if ( ! $logo_url ) return '';
        return '<div style="text-align:center;padding:20px 0 10px;"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="max-width:300px;max-height:100px;height:auto;"></div>';
    }

    public static function wrap( $atts ) {
        $email_id = self::$current_email_id;
        self::$current_email_id = null; // Reset for next call.

        // If no email_id was set, try to identify by matching subject.
        if ( ! $email_id && class_exists( 'CE_Store' ) ) {
            $all = CE_Store::all();
            foreach ( $all as $id => $data ) {
                if ( ! empty( $data['subject'] ) && $atts['subject'] === $data['subject'] ) {
                    $email_id = $id;
                    break;
                }
            }
        }

        $format = self::get_effective_format( $email_id );
        if ( $format === 'text' ) {
            // Ensure plain text - strip any HTML tags.
            $atts['message'] = wp_strip_all_tags( $atts['message'] );
            return $atts;
        }

        // HTML mode: apply logo + header/footer wrapper and set Content-Type.
        $logo   = self::get_logo_html();
        $header = get_option( self::OPT_HEADER, '' );
        $footer = get_option( self::OPT_FOOTER, '' );
        if ( $logo || $header || $footer ) {
            $atts['message'] = $logo . $header . wpautop( $atts['message'] ) . $footer;
        } else {
            // No wrapper defined but format is HTML - just wpautop.
            $atts['message'] = wpautop( $atts['message'] );
        }

        // Add Content-Type: text/html header if not already present.
        $headers = (array) ( $atts['headers'] ?? [] );
        $has_ct = false;
        foreach ( $headers as $h )
            if ( stripos( $h, 'content-type:' ) === 0 )
                $has_ct = true;
        if ( ! $has_ct )
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $atts['headers'] = $headers;

        return $atts;
    }
}
