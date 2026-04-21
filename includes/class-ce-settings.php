<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Settings {

    public static function boot() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
        add_action( 'admin_post_ce_save_settings', [ __CLASS__, 'save' ] );
        add_action( 'admin_post_ce_promote', [ __CLASS__, 'promote' ] );
    }

    public static function menu() {
        add_submenu_page( CE_Admin::SLUG, 'Settings', 'Settings', 'manage_options', 'ce-settings', [ __CLASS__, 'render' ] );
        add_submenu_page( CE_Admin::SLUG, 'Discovery', 'Discovery', 'manage_options', 'ce-discovery', [ __CLASS__, 'render_discovery' ] );
        add_submenu_page( CE_Admin::SLUG, 'Export / Import', 'Export / Import', 'manage_options', 'ce-export', [ 'CE_Exporter', 'render' ] );
    }

    public static function render() {
        ?>
        <div class="wrap"><h1>Custom Emails - Settings</h1>
        <?php if ( isset( $_GET['updated'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>'; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ce_settings' ); ?>
            <input type="hidden" name="action" value="ce_save_settings">
            <table class="form-table">
                <tr><th>Enable discovery logger</th>
                    <td><label><input type="checkbox" name="logger" value="1" <?php checked( get_option( CE_Logger::OPTION_ON ) ); ?>> Capture all outgoing mail for 7 days</label></td></tr>
                <tr><th>Wrap emails in HTML</th>
                    <td><label><input type="checkbox" name="html" value="1" <?php checked( get_option( CE_Wrapper::OPT_HTML ) ); ?>> Apply global header/footer</label></td></tr>
                <tr><th>Global header</th>
                    <td><textarea name="header" rows="6" class="large-text code"><?php echo esc_textarea( get_option( CE_Wrapper::OPT_HEADER ) ); ?></textarea></td></tr>
                <tr><th>Global footer</th>
                    <td><textarea name="footer" rows="6" class="large-text code"><?php echo esc_textarea( get_option( CE_Wrapper::OPT_FOOTER ) ); ?></textarea></td></tr>
            </table>
            <?php submit_button(); ?>
        </form></div>
        <?php
    }

    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'ce_settings' );
        update_option( CE_Logger::OPTION_ON, ! empty( $_POST['logger'] ) );
        update_option( CE_Wrapper::OPT_HTML, ! empty( $_POST['html'] ) );
        update_option( CE_Wrapper::OPT_HEADER, wp_kses_post( wp_unslash( $_POST['header'] ?? '' ) ) );
        update_option( CE_Wrapper::OPT_FOOTER, wp_kses_post( wp_unslash( $_POST['footer'] ?? '' ) ) );
        wp_safe_redirect( add_query_arg( [ 'page' => 'ce-settings', 'updated' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function render_discovery() {
        $rows = CE_Logger::unregistered();
        echo '<div class="wrap"><h1>Email Discovery</h1>';
        if ( ! get_option( CE_Logger::OPTION_ON ) ) {
            echo '<div class="notice notice-warning"><p>Logger is disabled. Enable it in Settings.</p></div>';
        }
        if ( isset( $_GET['promoted'] ) ) echo '<div class="notice notice-success"><p>Promoted to registry.</p></div>';
        echo '<p>Groups of outgoing emails captured by fingerprint. Promote one to create a generic editable override matched by subject.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Subject</th><th>Count</th><th>Last sent</th><th>Origin</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ( (array) $rows as $r ) {
            $status = $r->promoted ? 'Promoted' : 'New';
            $btn = $r->promoted ? '' : sprintf(
                '<form method="post" action="%s" style="display:inline"><input type="hidden" name="action" value="ce_promote"><input type="hidden" name="fingerprint" value="%s">%s<button class="button">Promote</button></form>',
                esc_url( admin_url( 'admin-post.php' ) ),
                esc_attr( $r->fingerprint ),
                wp_nonce_field( 'ce_promote_' . $r->fingerprint, '_wpnonce', true, false )
            );
            printf( '<tr><td>%s</td><td>%d</td><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
                esc_html( $r->subject ), (int) $r->c, esc_html( $r->last ),
                esc_html( mb_strimwidth( $r->backtrace, 0, 120, '...' ) ),
                esc_html( $status ), $btn );
        }
        echo '</tbody></table></div>';
    }

    public static function promote() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $fp = sanitize_text_field( $_POST['fingerprint'] ?? '' );
        check_admin_referer( 'ce_promote_' . $fp );
        $sample = CE_Logger::sample( $fp );
        if ( ! $sample ) wp_die( 'Not found.' );

        $promoted = get_option( 'ce_promoted_emails', [] );
        $promoted[ $fp ] = [
            'subject_pattern' => preg_replace( '/\d+/', '#', $sample->subject ),
            'label'           => 'Promoted: ' . wp_trim_words( $sample->subject, 8 ),
        ];
        update_option( 'ce_promoted_emails', $promoted, false );

        $id = 'promoted_' . substr( $fp, 0, 12 );
        CE_Store::save( $id, $sample->subject, $sample->body, false );
        CE_Logger::mark_promoted( $fp );

        wp_safe_redirect( add_query_arg( [ 'page' => 'ce-discovery', 'promoted' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
