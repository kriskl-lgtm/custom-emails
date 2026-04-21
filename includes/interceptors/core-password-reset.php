<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Interceptor_Password_Reset {

    public static function boot() {
        add_filter( 'retrieve_password_title',   [ __CLASS__, 'subject' ], 10, 3 );
        add_filter( 'retrieve_password_message', [ __CLASS__, 'body' ],    10, 4 );
    }

    public static function subject( $title, $user_login, $user_data ) {
        $tpl = CE_Store::resolve( 'core_password_reset' );
        if ( ! $tpl ) return $title;
        return CE_Renderer::render( $tpl['subject'], self::context( $user_login, $user_data, '' ) );
    }

    public static function body( $message, $key, $user_login, $user_data ) {
        $tpl = CE_Store::resolve( 'core_password_reset' );
        if ( ! $tpl ) return $message;
        $reset_url = network_site_url( "wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user_login ), 'login' );
        return CE_Renderer::render( $tpl['body'], self::context( $user_login, $user_data, $reset_url ) );
    }

    protected static function context( $user_login, $user_data, $reset_url ) {
        return [
            '{user_login}' => $user_login,
            '{user_email}' => is_object( $user_data ) ? $user_data->user_email : '',
            '{reset_url}'  => $reset_url,
        ];
    }
}
