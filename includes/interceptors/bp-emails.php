<?php
/**
 * Custom Emails - BuddyPress interceptor (safe)
 *
 * Guards every BP function with function_exists() and runs registration on
 * 'init' priority 99 so BP has had a chance to register its post type and
 * taxonomy. Does nothing if BP is not active.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CE_BP_Emails {

  const SOURCE = 'BP';

  public static function init() {
    add_action( 'init', [ __CLASS__, 'register_all' ], 99 );
    add_filter( 'bp_email_set_subject',           [ __CLASS__, 'filter_subject' ], 20, 2 );
    add_filter( 'bp_email_set_content_html',      [ __CLASS__, 'filter_html' ],    20, 2 );
    add_filter( 'bp_email_set_content_plaintext', [ __CLASS__, 'filter_plain' ],   20, 2 );
  }

  /** Discover every bp-email post and register it. */
  public static function register_all() {
    if ( ! function_exists( 'buddypress' ) ) { return; }
    if ( ! function_exists( 'bp_get_email_post_type' ) ) { return; }
    if ( ! function_exists( 'bp_get_email_type_tax_name' ) ) { return; }
    if ( ! class_exists( 'CE_Registry' ) ) { return; }

    $post_type = bp_get_email_post_type();
    $tax_name  = bp_get_email_type_tax_name();
    if ( ! $post_type || ! post_type_exists( $post_type ) ) { return; }
    if ( ! $tax_name  || ! taxonomy_exists( $tax_name ) )   { return; }

    $posts = get_posts( [
      'post_type'        => $post_type,
      'post_status'      => 'publish',
      'posts_per_page'   => -1,
      'no_found_rows'    => true,
      'suppress_filters' => true,
    ] );
    if ( ! $posts ) { return; }

    foreach ( $posts as $p ) {
      $terms = wp_get_object_terms( $p->ID, $tax_name, [ 'fields' => 'slugs' ] );
      if ( is_wp_error( $terms ) || empty( $terms ) ) { continue; }
      $type = $terms[0];

      CE_Registry::register( [
        'id'          => 'bp:' . $type,
        'source'      => self::SOURCE,
        'label'       => self::friendly_label( $type, $p->post_title ),
        'description' => self::friendly_desc( $type, $p->post_excerpt ),
        'tokens'      => self::tokens_for( $type ),
        'bp_type'     => $type,
        'bp_post_id'  => $p->ID,
        'edit_link'   => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
      ] );
    }
  }

  /* ---------- filters ---------- */

  public static function filter_subject( $subject, $email ) {
    $o = self::get_override( $email, 'subject' );
    return $o !== null ? $o : $subject;
  }
  public static function filter_html( $html, $email ) {
    $o = self::get_override( $email, 'html' );
    return $o !== null && class_exists( 'CE_Renderer' )
      ? CE_Renderer::render( $o, self::tokens_from_email( $email ) ) : $html;
  }
  public static function filter_plain( $plain, $email ) {
    $o = self::get_override( $email, 'plain' );
    return $o !== null && class_exists( 'CE_Renderer' )
      ? CE_Renderer::render( $o, self::tokens_from_email( $email ) ) : $plain;
  }

  /* ---------- helpers ---------- */

  private static function get_override( $email, $field ) {
    if ( ! is_object( $email ) || ! method_exists( $email, 'get_type' ) ) { return null; }
    if ( ! class_exists( 'CE_Store' ) ) { return null; }
    $type = $email->get_type();
    if ( ! $type ) { return null; }
    $data = CE_Store::get( 'bp:' . $type );
    if ( empty( $data ) || empty( $data['enabled'] ) ) { return null; }
    return isset( $data[ $field ] ) && $data[ $field ] !== '' ? $data[ $field ] : null;
  }

  private static function tokens_from_email( $email ) {
    $t = [];
    if ( is_object( $email ) && method_exists( $email, 'get_tokens' ) ) {
      foreach ( (array) $email->get_tokens() as $k => $v ) {
        if ( is_scalar( $v ) ) { $t[ $k ] = (string) $v; }
      }
    }
    return $t;
  }

  private static function friendly_label( $type, $fallback ) {
    $map = [
      'core-user-registration'              => 'BuddyPress - Activate account (signup)',
      'core-user-registration-with-blog'    => 'BuddyPress - Activate account + blog',
      'activity-at-message'                 => 'BuddyPress - @mention in activity',
      'activity-comment'                    => 'BuddyPress - Reply to activity',
      'activity-comment-author'             => 'BuddyPress - Reply to your activity comment',
      'groups-at-message'                   => 'BuddyPress - @mention in group',
      'groups-details-updated'              => 'BuddyPress - Group details updated',
      'groups-invitation'                   => 'BuddyPress - Group invitation',
      'groups-member-promoted'              => 'BuddyPress - Promoted in group',
      'groups-membership-request'           => 'BuddyPress - Membership request',
      'groups-membership-request-accepted'  => 'BuddyPress - Membership accepted',
      'groups-membership-request-rejected'  => 'BuddyPress - Membership rejected',
      'friends-request'                     => 'BuddyPress - Friendship request',
      'friends-request-accepted'            => 'BuddyPress - Friendship accepted',
      'messages-unread'                     => 'BuddyPress - New private message',
      'settings-verify-email-change'        => 'BuddyPress - Verify email change',
      'core-user-activation'                => 'BuddyPress - Welcome (post-activation)',
    ];
    return isset( $map[ $type ] ) ? $map[ $type ] : ( $fallback ? $fallback : 'BuddyPress - ' . $type );
  }

  private static function friendly_desc( $type, $fallback ) {
    if ( $type === 'core-user-registration' ) {
      return 'Activation email sent to a new user after signup (contains activation link).';
    }
    return $fallback ? wp_strip_all_tags( $fallback ) : 'BuddyPress email of type ' . $type;
  }

  private static function tokens_for( $type ) {
    $common = [ 'recipient.name', 'recipient.email', 'recipient.username', 'site.name', 'site.url', 'site.admin-email', 'site.description', 'email.subject' ];
    $extra = [
      'core-user-registration'           => [ 'activate.url', 'key', 'user.email' ],
      'core-user-registration-with-blog' => [ 'activate.url', 'key', 'user.email', 'domain', 'path', 'title' ],
      'settings-verify-email-change'     => [ 'verify.url', 'old-user.email', 'user.email' ],
      'activity-at-message'              => [ 'poster.name', 'mentioned.url', 'usermessage' ],
      'groups-invitation'                => [ 'inviter.name', 'inviter.url', 'group.name', 'group.url', 'invite.message' ],
      'messages-unread'                  => [ 'sender.name', 'usermessage', 'message.url' ],
      'friends-request'                  => [ 'initiator.name', 'initiator.url', 'friendship.url' ],
    ];
    return array_values( array_unique( array_merge( $common, isset( $extra[ $type ] ) ? $extra[ $type ] : [] ) ) );
  }
}

CE_BP_Emails::init();
