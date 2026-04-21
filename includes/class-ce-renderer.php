<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Renderer {

    public static function render( $template, array $context ) {
        $context = array_merge( self::global_tokens(), $context );
        return str_replace( array_keys( $context ), array_values( $context ), $template );
    }

    public static function global_tokens() {
        return [
            '{site_name}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            '{site_url}'  => home_url( '/' ),
        ];
    }
}
