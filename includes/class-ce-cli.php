<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP-CLI commands for Custom Emails.
 *
 *   wp custom-emails list
 *   wp custom-emails test <id> --to=you@example.com
 *   wp custom-emails export > file.json
 *   wp custom-emails import file.json
 */
class CE_CLI {

    public static function boot() {
        WP_CLI::add_command( 'custom-emails', __CLASS__ );
    }

    public function list_( $args, $assoc ) {
        $rows = [];
        foreach ( CE_Registry::all() as $id => $e ) {
            $o = CE_Store::get( $id );
            $rows[] = [
                'id'       => $id,
                'source'   => $e['source'],
                'label'    => $e['label'],
                'status'   => $o ? 'customized' : 'default',
                'modified' => $o['modified'] ?? '',
            ];
        }
        WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'source', 'label', 'status', 'modified' ] );
    }

    /** Alias list because `list` is a reserved word. */
    public function __call( $name, $args ) {
        if ( $name === 'list' ) return $this->list_( ...$args );
    }

    public function test( $args, $assoc ) {
        [ $id ] = $args;
        $to  = $assoc['to'] ?? get_option( 'admin_email' );
        $tpl = CE_Store::resolve( $id );
        $def = CE_Registry::get( $id );
        if ( ! $tpl || ! $def ) WP_CLI::error( "Unknown email: $id" );

        $fake = [];
        foreach ( array_keys( $def['available_tokens'] ) as $t ) $fake[ $t ] = '[' . trim( $t, '{}' ) . ']';
        $ok = wp_mail( $to,
            '[TEST] ' . CE_Renderer::render( $tpl['subject'], $fake ),
            CE_Renderer::render( $tpl['body'], $fake ) );
        $ok ? WP_CLI::success( "Sent to $to" ) : WP_CLI::error( 'wp_mail returned false' );
    }

    public function export( $args, $assoc ) {
        $data = [
            'version'   => CE_VERSION,
            'exported'  => gmdate( 'c' ),
            'overrides' => CE_Store::all(),
            'wrapper'   => [
                'html'   => (bool) get_option( CE_Wrapper::OPT_HTML ),
                'header' => get_option( CE_Wrapper::OPT_HEADER, '' ),
                'footer' => get_option( CE_Wrapper::OPT_FOOTER, '' ),
            ],
                        'sender'    => [
                'from_name'  => get_option( CE_Settings::OPT_FROM_NAME, '' ),
                'from_email' => get_option( CE_Settings::OPT_FROM_EMAIL, '' ),
            ],
            'logo_url'  => get_option( CE_Settings::OPT_LOGO ),
        ];
        WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
    }

    public function import( $args, $assoc ) {
        [ $file ] = $args;
        if ( ! file_exists( $file ) ) WP_CLI::error( "File not found: $file" );
        $data = json_decode( file_get_contents( $file ), true );
        if ( ! is_array( $data ) || empty( $data['overrides'] ) ) WP_CLI::error( 'Invalid JSON.' );

        CE_Store::replace_all( $data['overrides'] );
        if ( isset( $data['wrapper'] ) ) {
            update_option( CE_Wrapper::OPT_HTML, ! empty( $data['wrapper']['html'] ) );
            update_option( CE_Wrapper::OPT_HEADER, (string) ( $data['wrapper']['header'] ?? '' ) );
            update_option( CE_Wrapper::OPT_FOOTER, (string) ( $data['wrapper']['footer'] ?? '' ) );
        }
                if ( isset( $data['sender'] ) ) {
            update_option( CE_Settings::OPT_FROM_NAME, sanitize_text_field( $data['sender']['from_name'] ?? '' ) );
            update_option( CE_Settings::OPT_FROM_EMAIL, sanitize_email( $data['sender']['from_email'] ?? '' ) );
        }
        if ( isset( $data['logo_url'] ) ) {
            update_option( CE_Settings::OPT_LOGO, esc_url_raw( $data['logo_url'] ) );
        }
        WP_CLI::success( 'Imported ' . count( $data['overrides'] ) . ' overrides.' );
    }

    public function reset( $args, $assoc ) {
        [ $id ] = $args;
        CE_Store::delete( $id );
        WP_CLI::success( "Reset: $id" );
    }
}
