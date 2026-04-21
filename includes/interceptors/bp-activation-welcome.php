<?php
/**
 * Custom Emails - Welcome email after BuddyPress account activation.
 *
 * Hooks bp_core_activated_user (fired when a user clicks the activation link)
 * and sends a customisable welcome email via wp_mail.
 *
 * The email template is stored in CE_Store under id 'bp_welcome_after_activation'
 * and appears in the Custom Emails admin list so you can edit subject + body.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CE_BP_Welcome {

  const ID = 'bp_welcome_after_activation';

  public static function init() {
    // Register in the Custom Emails list.
    add_action( 'ce_register_emails', [ __CLASS__, 'register' ] );
    // Fire after BP activates a user.
    add_action( 'bp_core_activated_user', [ __CLASS__, 'send_welcome' ], 10, 3 );
  }

  public static function register() {
    if ( ! class_exists( 'CE_Registry' ) ) { return; }
    CE_Registry::register( self::ID, [
      'source'      => 'BP',
      'label'       => 'Welcome email (after activation)',
      'description' => 'Sent automatically to the user right after they activate their BuddyPress account.',
      'default_subject' => '{site_name} - Welcome!',
      'default_body'    => "Hi {display_name},\n\nWelcome to {site_name}! Your account is now active.\n\nYour username: {user_login}\nLog in here: {login_url}\n\nEnjoy!\n{site_name}",
      'available_tokens' => [
        '{site_name}'    => 'Site name',
        '{site_url}'     => 'Site URL',
        '{user_login}'   => 'Username',
        '{user_email}'   => 'Email address',
        '{display_name}' => 'Display name',
        '{login_url}'    => 'Login page URL',
        '{profile_url}'  => 'User profile URL',
      ],
    ] );
  }

  /**
   * Fired by bp_core_activated_user( $user_id, $key, $user ).
   */
  public static function send_welcome( $user_id, $key = '', $user = null ) {
    if ( ! $user_id ) { return; }
    $u = get_userdata( $user_id );
    if ( ! $u ) { return; }

    // Build token values.
    $tokens = [
      '{site_name}'    => get_bloginfo( 'name' ),
      '{site_url}'     => home_url( '/' ),
      '{user_login}'   => $u->user_login,
      '{user_email}'   => $u->user_email,
      '{display_name}' => $u->display_name ? $u->display_name : $u->user_login,
      '{login_url}'    => wp_login_url(),
      '{profile_url}'  => function_exists( 'bp_core_get_user_domain' )
                            ? bp_core_get_user_domain( $user_id ) : wp_login_url(),
    ];

    // Get stored override (if admin customised it) or fall back to defaults.
    $override = class_exists( 'CE_Store' ) ? CE_Store::get( self::ID ) : false;
    $def      = class_exists( 'CE_Registry' ) ? CE_Registry::get( self::ID ) : null;

    if ( $override && ! empty( $override['subject'] ) ) {
      $subject = $override['subject'];
    } elseif ( $def ) {
      $subject = $def['default_subject'];
    } else {
      $subject = '{site_name} - Welcome!';
    }

    if ( $override && ! empty( $override['body'] ) ) {
      $body = $override['body'];
    } elseif ( $def ) {
      $body = $def['default_body'];
    } else {
      $body = "Welcome to {site_name}, {display_name}!";
    }

    // Replace tokens.
    $subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject );
    $body    = str_replace( array_keys( $tokens ), array_values( $tokens ), $body );

    // Use CE_Renderer if available for advanced rendering.
    if ( class_exists( 'CE_Renderer' ) ) {
      $body = CE_Renderer::render( $body, $tokens );
    }

    wp_mail( $u->user_email, $subject, $body );
  }
}

CE_BP_Welcome::init();
