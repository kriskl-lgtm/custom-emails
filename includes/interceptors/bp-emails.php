<?php
/**
 * Custom Emails - BuddyPress email interceptor (v2)
 *
 * Registers known BP email types in the CE_Registry via the ce_register_emails
 * hook so they appear in the admin list. At runtime, filters bp_email_set_*
 * to inject overrides stored in CE_Store.
 *
 * IDs use bp_ prefix (not bp:) because sanitize_key() strips colons.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CE_BP_Emails {

  const SOURCE = 'bp';

  private static $types = [
    'core-user-registration' => [
      'label'   => 'BP - Activate account (signup)',
      'desc'    => 'Activation email sent to a new user after signup (contains activation link).',
      'subject' => '[{site_name}] Activate your account',
      'body'    => "Thanks for registering!\n\nTo complete the activation of your account, go to the following link: {activate.url}",
      'tokens'  => [
        '{activate.url}' => 'Activation URL',
        '{user.email}'   => 'User email',
        '{key}'          => 'Activation key',
      ],
    ],
    'core-user-registration-with-blog' => [
      'label'   => 'BP - Activate account + blog',
      'desc'    => 'Activation email for user+blog signups on multisite.',
      'subject' => '[{site_name}] Activate your account and blog',
      'body'    => "Thanks for registering!\n\nTo complete the activation of your account and blog, go to: {activate.url}",
      'tokens'  => [
        '{activate.url}' => 'Activation URL',
        '{user.email}'   => 'User email',
      ],
    ],
    'core-user-activation' => [
      'label'   => 'BP - Account activated (system)',
      'desc'    => 'Confirmation email sent by BuddyPress right after activation.',
      'subject' => '[{site_name}] Your account is now active',
      'body'    => "Your account is now active.\n\nUsername: {user.login}\nLogin at: {login.url}",
      'tokens'  => [
        '{user.login}' => 'Username',
        '{login.url}'  => 'Login URL',
      ],
    ],
    'activity-at-message' => [
      'label'   => 'BP - @mention in activity',
      'desc'    => 'Sent when a user is mentioned in an activity update.',
      'subject' => '{poster.name} mentioned you in an update',
      'body'    => "{poster.name} mentioned you in an update.\n\nView: {mentioned.url}",
      'tokens'  => [
        '{poster.name}'   => 'Who mentioned',
        '{usermessage}'   => 'Message excerpt',
        '{mentioned.url}' => 'Permalink',
      ],
    ],
    'groups-invitation' => [
      'label'   => 'BP - Group invitation',
      'desc'    => 'Sent when a user is invited to a group.',
      'subject' => 'You have an invitation to the group: {group.name}',
      'body'    => "{inviter.name} invited you to {group.name}.\n\n{invite.message}\n\nAccept: {group.url}",
      'tokens'  => [
        '{inviter.name}'   => 'Inviter',
        '{group.name}'     => 'Group',
        '{group.url}'      => 'Group URL',
        '{invite.message}' => 'Message',
      ],
    ],
    'messages-unread' => [
      'label'   => 'BP - New private message',
      'desc'    => 'Notification of an unread private message.',
      'subject' => 'New message from {sender.name}',
      'body'    => "{sender.name} sent you a new message.\n\nView: {message.url}",
      'tokens'  => [
        '{sender.name}'  => 'Sender',
        '{usermessage}'  => 'Message text',
        '{message.url}'  => 'Message URL',
      ],
    ],
    'friends-request' => [
      'label'   => 'BP - Friendship request',
      'desc'    => 'Sent when someone requests to be friends.',
      'subject' => 'New friendship request from {initiator.name}',
      'body'    => "{initiator.name} wants to be friends.\n\nView: {friendship.url}",
      'tokens'  => [
        '{initiator.name}' => 'Requester',
        '{friendship.url}' => 'Respond URL',
      ],
    ],
    'friends-request-accepted' => [
      'label'   => 'BP - Friendship accepted',
      'desc'    => 'Sent when a friendship request is accepted.',
      'subject' => '{friend.name} accepted your friendship request',
      'body'    => '{friend.name} accepted your request.',
      'tokens'  => [ '{friend.name}' => 'Friend name' ],
    ],
    'settings-verify-email-change' => [
      'label'   => 'BP - Verify email change',
      'desc'    => 'Sent when a user changes their email in BP settings.',
      'subject' => '[{site_name}] Verify your new email address',
      'body'    => 'Please click the link to verify your new email: {verify.url}',
      'tokens'  => [
        '{verify.url}'     => 'Verification URL',
        '{old-user.email}' => 'Old email',
        '{user.email}'     => 'New email',
      ],
    ],
  ];

  public static function init() {
    add_action( 'ce_register_emails', [ __CLASS__, 'register' ] );
    add_filter( 'bp_email_set_subject',           [ __CLASS__, 'filter_subject' ], 20, 2 );
    add_filter( 'bp_email_set_content_html',      [ __CLASS__, 'filter_html' ],    20, 2 );
    add_filter( 'bp_email_set_content_plaintext', [ __CLASS__, 'filter_plain' ],   20, 2 );
  }

  public static function register() {
    if ( ! class_exists( 'CE_Registry' ) ) { return; }
    $common_tokens = [
      '{site_name}'        => 'Site name',
      '{site_url}'         => 'Site URL',
      '{recipient.name}'   => 'Recipient name',
      '{recipient.email}'  => 'Recipient email',
    ];
    foreach ( self::$types as $slug => $t ) {
      CE_Registry::register( 'bp_' . $slug, [
        'source'           => self::SOURCE,
        'label'            => $t['label'],
        'description'      => $t['desc'],
        'default_subject'  => $t['subject'],
        'default_body'     => $t['body'],
        'available_tokens' => array_merge( $common_tokens, $t['tokens'] ),
      ] );
    }
  }

  /* ---------- runtime filters ---------- */

  public static function filter_subject( $subject, $email ) {
    $o = self::get_override( $email, 'subject' );
    return $o !== null ? $o : $subject;
  }
  public static function filter_html( $html, $email ) {
    $o = self::get_override( $email, 'body' );
    if ( $o === null ) { return $html; }
    $tokens = self::tokens_from_email( $email );
    return class_exists( 'CE_Renderer' ) ? CE_Renderer::render( $o, $tokens ) : str_replace( array_keys( $tokens ), array_values( $tokens ), $o );
  }
  public static function filter_plain( $plain, $email ) {
    $o = self::get_override( $email, 'body' );
    if ( $o === null ) { return $plain; }
    $tokens = self::tokens_from_email( $email );
    $rendered = class_exists( 'CE_Renderer' ) ? CE_Renderer::render( $o, $tokens ) : str_replace( array_keys( $tokens ), array_values( $tokens ), $o );
    return wp_strip_all_tags( $rendered );
  }

  /* ---------- helpers ---------- */

  private static function get_override( $email, $field ) {
    if ( ! is_object( $email ) || ! method_exists( $email, 'get_type' ) ) { return null; }
    if ( ! class_exists( 'CE_Store' ) ) { return null; }
    $type = $email->get_type();
    if ( ! $type ) { return null; }
    $data = CE_Store::get( 'bp_' . $type );
    if ( empty( $data ) || empty( $data['subject'] ) ) { return null; }
    return isset( $data[ $field ] ) && $data[ $field ] !== '' ? $data[ $field ] : null;
  }

  private static function tokens_from_email( $email ) {
    $t = [];
    if ( method_exists( $email, 'get_tokens' ) ) {
      foreach ( (array) $email->get_tokens() as $k => $v ) {
        $key = '{' . $k . '}';
        if ( is_scalar( $v ) ) { $t[ $key ] = (string) $v; }
      }
    }
    return $t;
  }
}

CE_BP_Emails::init();
