<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BuddyPress bridge: BP has its own 'bp-email' post type editor.
 * We register each existing BP email into our registry and route the Edit button
 * in our list table back to BP's native editor via the ce_edit_link filter.
 */
class CE_Interceptor_BP_Bridge {

    public static function boot() {
        add_action( 'ce_register_emails', [ __CLASS__, 'register_bp_emails' ] );
        add_filter( 'ce_edit_link',       [ __CLASS__, 'edit_link' ], 10, 2 );
    }

    public static function register_bp_emails() {
        if ( ! function_exists( 'bp_is_active' ) || ! function_exists( 'bp_get_email_post_type' ) ) return;

        $posts = get_posts( [
            'post_type'      => bp_get_email_post_type(),
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );

        foreach ( $posts as $p ) {
            CE_Registry::register( 'bp_' . $p->ID, [
                'source'           => 'bp',
                'label'            => 'BuddyPress - ' . $p->post_title,
                'description'      => 'Edited natively via BuddyPress email editor.',
                'default_subject'  => get_post_meta( $p->ID, 'bp_email_subject', true ),
                'default_body'     => $p->post_content,
                'available_tokens' => [ '{{recipient.name}}' => 'Recipient name', '{{site.name}}' => 'Site name' ],
                'bp_post_id'       => $p->ID,
            ] );
        }
    }

    public static function edit_link( $url, $email ) {
        if ( ! empty( $email['bp_post_id'] ) ) {
            $bp_url = get_edit_post_link( $email['bp_post_id'], 'url' );
            if ( $bp_url ) return $bp_url;
        }
        return $url;
    }
}
