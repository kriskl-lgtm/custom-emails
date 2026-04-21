<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Settings {

    const OPT_LOGO = 'ce_email_logo_url';

    public static function boot() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
        add_action( 'admin_post_ce_save_settings', [ __CLASS__, 'save' ] );
        add_action( 'admin_post_ce_promote', [ __CLASS__, 'promote' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_media' ] );
    }

    public static function enqueue_media( $hook ) {
        if ( strpos( $hook, 'ce-settings' ) === false ) return;
        wp_enqueue_media();
    }

    public static function menu() {
        add_submenu_page( CE_Admin::SLUG, 'Settings', 'Settings', 'manage_options', 'ce-settings', [ __CLASS__, 'render' ] );
        add_submenu_page( CE_Admin::SLUG, 'Discovery', 'Discovery', 'manage_options', 'ce-discovery', [ __CLASS__, 'render_discovery' ] );
        add_submenu_page( CE_Admin::SLUG, 'Export / Import', 'Export / Import', 'manage_options', 'ce-export', [ 'CE_Exporter', 'render' ] );
    }

    public static function render() {
        $logo_url = get_option( self::OPT_LOGO, '' );
        ?>
        <div class="wrap"><h1>Custom Emails – Settings</h1>
        <?php if ( isset( $_GET['updated'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>'; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ce_settings' ); ?>
            <input type="hidden" name="action" value="ce_save_settings">
            <table class="form-table">
                <tr><th>Enable discovery logger</th>
                    <td><label><input type="checkbox" name="logger" value="1" <?php checked( get_option( CE_Logger::OPTION_ON ) ); ?>> Capture all outgoing mail for 7 days</label></td></tr>
                <tr><th>Wrap emails in HTML</th>
                    <td><label><input type="checkbox" name="html" value="1" <?php checked( get_option( CE_Wrapper::OPT_HTML ) ); ?>> Apply global header/footer</label></td></tr>
                <tr><th>Email logo</th>
                    <td>
                        <div id="ce-logo-preview" style="margin-bottom:10px;">
                            <?php if ( $logo_url ) : ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:300px;max-height:100px;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="logo_url" id="ce-logo-url" value="<?php echo esc_attr( $logo_url ); ?>">
                        <button type="button" class="button" id="ce-logo-upload">Select / Upload Logo</button>
                        <button type="button" class="button" id="ce-logo-remove" <?php echo $logo_url ? '' : 'style="display:none"'; ?>>Remove</button>
                        <p class="description">Choose a logo image from the Media Library. It will appear at the top of HTML emails.</p>
                    </td></tr>
                <tr><th>Global header</th>
                    <td>
                        <textarea name="header" rows="6" class="large-text code"><?php echo esc_textarea( get_option( CE_Wrapper::OPT_HEADER ) ); ?></textarea>
                        <p class="description">
                            HTML that appears <strong>after the logo</strong> and <strong>before</strong> the email body.<br>
                            Typically used to open a centered container, e.g.:<br>
                            <code>&lt;div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#333;padding:20px;"&gt;</code>
                        </p>
                    </td></tr>
                <tr><th>Global footer</th>
                    <td>
                        <textarea name="footer" rows="6" class="large-text code"><?php echo esc_textarea( get_option( CE_Wrapper::OPT_FOOTER ) ); ?></textarea>
                        <p class="description">
                            HTML that appears <strong>after</strong> the email body. Close any container opened in the header.<br>
                            Example: a separator, site name, link, then close the div:<br>
                            <code>&lt;hr style="border:none;border-top:1px solid #ddd;margin:30px 0 15px;"&gt;</code><br>
                            <code>&lt;p style="font-size:12px;color:#999;text-align:center;"&gt;OpenTuition &amp;ndash; Free ACCA &amp;amp; CIMA online courses&lt;br&gt;&lt;a href="https://opentuition.com"&gt;opentuition.com&lt;/a&gt;&lt;/p&gt;</code><br>
                            <code>&lt;/div&gt;</code>
                        </p>
                        <p class="description" style="margin-top:10px;">
                            <strong>Visual order of HTML emails:</strong> Logo &rarr; Header &rarr; Email body &rarr; Footer.<br>
                            Emails set to "text" format skip all of this.
                        </p>
                    </td></tr>
            </table>
            <?php submit_button(); ?>
        </form></div>
        <script>
        jQuery(function($){
            var frame;
            $('#ce-logo-upload').on('click', function(e){
                e.preventDefault();
                if ( frame ) { frame.open(); return; }
                frame = wp.media({
                    title: 'Select Email Logo',
                    button: { text: 'Use as Logo' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    $('#ce-logo-url').val( att.url );
                    $('#ce-logo-preview').html('<img src="' + att.url + '" style="max-width:300px;max-height:100px;">');
                    $('#ce-logo-remove').show();
                });
                frame.open();
            });
            $('#ce-logo-remove').on('click', function(e){
                e.preventDefault();
                $('#ce-logo-url').val('');
                $('#ce-logo-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'ce_settings' );
        update_option( CE_Logger::OPTION_ON, ! empty( $_POST['logger'] ) );
        update_option( CE_Wrapper::OPT_HTML, ! empty( $_POST['html'] ) );
        update_option( self::OPT_LOGO, esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ) );
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
        if ( isset( $_GET['promoted'] ) ) {
            echo '<div class="notice notice-success"><p>Promoted to registry.</p></div>';
        }
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
