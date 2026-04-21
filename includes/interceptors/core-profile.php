<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Overrides password_change_email and email_change_email.
 * Both filters pass $email (array with to/subject/message/headers) and the user data.
 */
class CE_Interceptor_Profile {

    public static function boot() {
        add_filter( 'password_change_email', [ __CLASS__, 'password_change' ], 10, 3 );
        add_filter( 'email_change_email',    [ __CLASS__, 'email_change' ],    10, 3 );
    }

    public static function password_change( $email, $user, $userdata ) {
        $tpl = CE_Store::resolve( 'core_password_change' );
        if ( ! $tpl ) return $email;
        $ctx = [
            '{user_login}' => $user['user_login'] ?? '',
            '{user_email}' => $user['user_email'] ?? '',
        ];
        $email['subject'] = CE_Renderer::render( $tpl['subject'], $ctx );
        $email['message'] = CE_Renderer::render( $tpl['body'],    $ctx );
        return $email;
    }

    public static function email_change( $email, $user, $userdata ) {
        $tpl = CE_Store::resolve( 'core_email_change' );
        if ( ! $tpl ) return $email;
        $ctx = [
            '{user_login}' => $user['user_login'] ?? '',
            '{user_email}' => $user['user_email'] ?? '',
        ];
        $email['subject'] = CE_Renderer::render( $tpl['subject'], $ctx );
        $email['message'] = CE_Renderer::render( $tpl['body'],    $ctx );
        return $email;
    }
}
