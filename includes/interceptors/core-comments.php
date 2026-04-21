<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Overrides WP core comment notification and moderation emails.
 * Uses filters: comment_notification_subject/text and comment_moderation_subject/text.
 */
class CE_Interceptor_Comments {

    public static function boot() {
        add_filter( 'comment_notification_subject', [ __CLASS__, 'notify_subject' ], 10, 2 );
        add_filter( 'comment_notification_text',    [ __CLASS__, 'notify_body' ],    10, 2 );
        add_filter( 'comment_moderation_subject',   [ __CLASS__, 'mod_subject' ],    10, 2 );
        add_filter( 'comment_moderation_text',      [ __CLASS__, 'mod_body' ],       10, 2 );
    }

    protected static function ctx( $comment_id ) {
        $c = get_comment( $comment_id );
        if ( ! $c ) return [];
        $post = get_post( $c->comment_post_ID );
        return [
            '{post_title}'      => $post ? $post->post_title : '',
            '{comment_author}'  => $c->comment_author,
            '{comment_content}' => wp_strip_all_tags( $c->comment_content ),
            '{comment_url}'     => get_comment_link( $c ),
            '{moderation_url}'  => admin_url( 'edit-comments.php?comment_status=moderated' ),
        ];
    }

    public static function notify_subject( $subject, $comment_id ) {
        $tpl = CE_Store::resolve( 'core_comment_notification' );
        return $tpl ? CE_Renderer::render( $tpl['subject'], self::ctx( $comment_id ) ) : $subject;
    }

    public static function notify_body( $message, $comment_id ) {
        $tpl = CE_Store::resolve( 'core_comment_notification' );
        return $tpl ? CE_Renderer::render( $tpl['body'], self::ctx( $comment_id ) ) : $message;
    }

    public static function mod_subject( $subject, $comment_id ) {
        $tpl = CE_Store::resolve( 'core_comment_moderation' );
        return $tpl ? CE_Renderer::render( $tpl['subject'], self::ctx( $comment_id ) ) : $subject;
    }

    public static function mod_body( $message, $comment_id ) {
        $tpl = CE_Store::resolve( 'core_comment_moderation' );
        return $tpl ? CE_Renderer::render( $tpl['body'], self::ctx( $comment_id ) ) : $message;
    }
}
