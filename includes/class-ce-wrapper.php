<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Wrapper {

    const OPT_HEADER = 'ce_wrapper_header';
    const OPT_FOOTER = 'ce_wrapper_footer';
    const OPT_HTML   = 'ce_wrapper_html';

    public static function boot() {
        add_filter( 'wp_mail', [ __CLASS__, 'wrap' ], 20 );
    }

    public static function wrap( $atts ) {
        if ( ! get_option( self::OPT_HTML ) ) return $atts;

        $header = get_option( self::OPT_HEADER, '' );
        $footer = get_option( self::OPT_FOOTER, '' );
        if ( ! $header && ! $footer ) return $atts;

        $atts['message'] = $header . wpautop( $atts['message'] ) . $footer;

        $headers = (array) ( $atts['headers'] ?? [] );
        $has_ct = false;
        foreach ( $headers as $h ) if ( stripos( $h, 'content-type:' ) === 0 ) $has_ct = true;
        if ( ! $has_ct ) $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $atts['headers'] = $headers;

        return $atts;
    }
}
