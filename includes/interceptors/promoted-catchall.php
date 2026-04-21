<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Catch-all for "promoted" emails discovered via the logger.
 * Hooks into wp_mail and, for any outgoing message whose subject matches
 * a stored promoted pattern (digits normalised to #), substitutes the
 * admin-edited subject/body.
 */
class CE_Interceptor_Promoted {

    public static function boot() {
        add_action( 'ce_register_emails', [ __CLASS__, 'register' ] );
        add_filter( 'wp_mail', [ __CLASS__, 'apply' ], 15 );
    }

    public static function register() {
        $promoted = get_option( 'ce_promoted_emails', [] );
        foreach ( $promoted as $fp => $meta ) {
            $id = 'promoted_' . substr( $fp, 0, 12 );
            CE_Registry::register( $id, [
                'source'           => 'custom',
                'label'            => $meta['label'] ?? 'Promoted email',
                'description'      => 'Matched by subject pattern: ' . ( $meta['subject_pattern'] ?? '' ),
                'default_subject'  => $meta['subject_pattern'] ?? '',
                'default_body'     => '',
                'available_tokens' => [ '{site_name}' => 'Site name', '{site_url}' => 'Site URL' ],
                'fingerprint'      => $fp,
                'subject_pattern'  => $meta['subject_pattern'] ?? '',
            ] );
        }
    }

    public static function apply( $atts ) {
        $promoted = get_option( 'ce_promoted_emails', [] );
        if ( empty( $promoted ) ) return $atts;

        $normalised = preg_replace( '/\d+/', '#', $atts['subject'] ?? '' );
        foreach ( $promoted as $fp => $meta ) {
            if ( ( $meta['subject_pattern'] ?? '' ) === $normalised ) {
                $id  = 'promoted_' . substr( $fp, 0, 12 );
                $tpl = CE_Store::resolve( $id );
                if ( ! $tpl ) continue;
                $override = CE_Store::get( $id );
                if ( empty( $override['enabled'] ) ) continue;

                $atts['subject'] = CE_Renderer::render( $tpl['subject'], [] );
                $atts['message'] = CE_Renderer::render( $tpl['body'], [] );
                break;
            }
        }
        return $atts;
    }
}
