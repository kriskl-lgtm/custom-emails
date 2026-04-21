<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Exporter {

    public static function boot() {
        add_action( 'admin_post_ce_export', [ __CLASS__, 'download' ] );
        add_action( 'admin_post_ce_import', [ __CLASS__, 'upload' ] );
    }

    public static function render() {
        ?>
        <div class="wrap"><h1>Export / Import</h1>
        <?php if ( isset( $_GET['imported'] ) ) echo '<div class="notice notice-success"><p>Import complete.</p></div>'; ?>

        <h2>Export</h2>
        <p>Download a JSON file of all overrides (for staging -> production migration).</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ce_export' ); ?>
            <input type="hidden" name="action" value="ce_export">
            <?php submit_button( 'Download JSON' ); ?>
        </form>

        <h2>Import</h2>
        <p><strong>Warning:</strong> this replaces all current overrides.</p>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ce_import' ); ?>
            <input type="hidden" name="action" value="ce_import">
            <input type="file" name="ce_file" accept="application/json" required>
            <?php submit_button( 'Upload & Replace', 'primary', 'submit', false ); ?>
        </form>
        </div>
        <?php
    }

    public static function download() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'ce_export' );
        $data = [
            'version'   => CE_VERSION,
            'exported'  => gmdate( 'c' ),
            'overrides' => CE_Store::all(),
            'wrapper'   => [
                'html'   => (bool) get_option( CE_Wrapper::OPT_HTML ),
                'header' => get_option( CE_Wrapper::OPT_HEADER, '' ),
                'footer' => get_option( CE_Wrapper::OPT_FOOTER, '' ),
            ],
        ];
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="custom-emails-' . gmdate( 'Ymd-His' ) . '.json"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public static function upload() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'ce_import' );
        if ( empty( $_FILES['ce_file']['tmp_name'] ) ) wp_die( 'No file.' );
        $json = file_get_contents( $_FILES['ce_file']['tmp_name'] );
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) || empty( $data['overrides'] ) ) wp_die( 'Invalid file.' );

        CE_Store::replace_all( $data['overrides'] );
        if ( isset( $data['wrapper'] ) ) {
            update_option( CE_Wrapper::OPT_HTML, ! empty( $data['wrapper']['html'] ) );
            update_option( CE_Wrapper::OPT_HEADER, (string) ( $data['wrapper']['header'] ?? '' ) );
            update_option( CE_Wrapper::OPT_FOOTER, (string) ( $data['wrapper']['footer'] ?? '' ) );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'ce-export', 'imported' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
