<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Interceptor_BBP_Subscriptions {

    public static function boot() {
        add_filter( 'bbp_subscription_mail_title',         [ __CLASS__, 'topic_title' ],   10, 3 );
        add_filter( 'bbp_subscription_mail_message',       [ __CLASS__, 'topic_body' ],    10, 3 );
        add_filter( 'bbp_forum_subscription_mail_title',   [ __CLASS__, 'forum_title' ],   10, 2 );
        add_filter( 'bbp_forum_subscription_mail_message', [ __CLASS__, 'forum_body' ],    10, 3 );
    }

    protected static function topic_ctx( $reply_id, $topic_id ) {
        if ( ! function_exists( 'bbp_get_topic_title' ) ) return [];
        return [
            '{topic_title}'     => bbp_get_topic_title( $topic_id ),
            '{reply_author}'    => bbp_get_reply_author_display_name( $reply_id ),
            '{reply_content}'   => wp_strip_all_tags( bbp_get_reply_content( $reply_id ) ),
            '{reply_url}'       => bbp_get_reply_url( $reply_id ),
            '{unsubscribe_url}' => function_exists( 'bbp_get_subscriptions_permalink' ) ? bbp_get_subscriptions_permalink( bbp_get_reply_author_id( $reply_id ) ) : '',
        ];
    }

    public static function topic_title( $title, $reply_id, $topic_id ) {
        $tpl = CE_Store::resolve( 'bbp_subscription_topic' );
        return $tpl ? CE_Renderer::render( $tpl['subject'], self::topic_ctx( $reply_id, $topic_id ) ) : $title;
    }

    public static function topic_body( $message, $reply_id, $topic_id ) {
        $tpl = CE_Store::resolve( 'bbp_subscription_topic' );
        return $tpl ? CE_Renderer::render( $tpl['body'], self::topic_ctx( $reply_id, $topic_id ) ) : $message;
    }

    protected static function forum_ctx( $topic_id ) {
        if ( ! function_exists( 'bbp_get_topic_title' ) ) return [];
        return [
            '{topic_title}'   => bbp_get_topic_title( $topic_id ),
            '{topic_author}'  => bbp_get_topic_author_display_name( $topic_id ),
            '{topic_content}' => wp_strip_all_tags( bbp_get_topic_content( $topic_id ) ),
            '{topic_url}'     => bbp_get_topic_permalink( $topic_id ),
        ];
    }

    public static function forum_title( $title, $topic_id ) {
        $tpl = CE_Store::resolve( 'bbp_subscription_forum' );
        return $tpl ? CE_Renderer::render( $tpl['subject'], self::forum_ctx( $topic_id ) ) : $title;
    }

    public static function forum_body( $message, $topic_id, $forum_id ) {
        $tpl = CE_Store::resolve( 'bbp_subscription_forum' );
        return $tpl ? CE_Renderer::render( $tpl['body'], self::forum_ctx( $topic_id ) ) : $message;
    }
}
