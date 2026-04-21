<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Store {

    const OPTION = 'ce_overrides';

    public static function install() {
        if ( get_option( self::OPTION ) === false ) {
            add_option( self::OPTION, [], '', false );
        }
    }

    public static function get( $id ) {
        $all = get_option( self::OPTION, [] );
        return $all[ $id ] ?? null;
    }

    public static function all() {
        return get_option( self::OPTION, [] );
    }

    public static function save( $id, $subject, $body, $enabled = true ) {
        $all = get_option( self::OPTION, [] );
        $all[ $id ] = [
            'subject'  => wp_kses_post( $subject ),
            'body'     => wp_kses_post( $body ),
            'enabled'  => (bool) $enabled,
            'modified' => current_time( 'mysql' ),
        ];
        update_option( self::OPTION, $all, false );
    }

    public static function replace_all( array $data ) {
        update_option( self::OPTION, $data, false );
    }

    public static function delete( $id ) {
        $all = get_option( self::OPTION, [] );
        unset( $all[ $id ] );
        update_option( self::OPTION, $all, false );
    }

    public static function resolve( $id ) {
        $override = self::get( $id );
        $default  = CE_Registry::get( $id );
        if ( ! $default ) return null;

        if ( $override && ! empty( $override['enabled'] ) ) {
            return [ 'subject' => $override['subject'], 'body' => $override['body'] ];
        }
        return [ 'subject' => $default['default_subject'], 'body' => $default['default_body'] ];
    }
}
