<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Interceptor_New_User {

    public static function boot() {
        add_filter( 'wp_new_user_notification_email',       [ __CLASS__, 'to_user' ],  10, 3 );
        add_filter( 'wp_new_user_notification_email_admin', [ __CLASS__, 'to_admin' ], 10, 3 );
    }

    public static function to_user( $email, $user, $blogname ) {
        $tpl = CE_Store::resolve( 'core_new_user_user' );
        if ( ! $tpl ) return $email;
        $ctx = [
            '{user_login}' => $user->user_login,
            '{user_email}' => $user->user_email,
            '{login_url}'  => wp_login_url(),
        ];
        $email['subject'] = CE_Renderer::render( $tpl['subject'], $ctx );
        $email['message'] = CE_Renderer::render( $tpl['body'],    $ctx );
        return $email;
    }

    public static function to_admin( $email, $user, $blogname ) {
        $tpl = CE_Store::resolve( 'core_new_user_admin' );
        if ( ! $tpl ) return $email;
        $ctx = [
            '{user_login}' => $user->user_login,
            '{user_email}' => $user->user_email,
        ];
        $email['subject'] = CE_Renderer::render( $tpl['subject'], $ctx );
        $email['message'] = CE_Renderer::render( $tpl['body'],    $ctx );
        return $email;
    }
}
