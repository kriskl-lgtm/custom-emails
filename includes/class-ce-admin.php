<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CE_Admin {

    const SLUG = 'custom-emails';

    public static function boot() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_post_ce_save', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_ce_reset', [ __CLASS__, 'handle_reset' ] );
        add_action( 'admin_post_ce_send_test', [ __CLASS__, 'handle_test' ] );
    }

    public static function menu() {
        add_menu_page( 'Custom Emails', 'Custom Emails', 'manage_options', self::SLUG,
            [ __CLASS__, 'render' ], 'dashicons-email-alt', 30 );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $id = isset( $_GET['email'] ) ? sanitize_key( $_GET['email'] ) : '';
        $id ? self::render_edit( $id ) : self::render_list();
    }

    protected static function render_list() {
        $emails = CE_Registry::all();
        echo '<div class="wrap"><h1>Custom Emails</h1>';
        if ( isset( $_GET['updated'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
        if ( isset( $_GET['reset'] ) )   echo '<div class="notice notice-success is-dismissible"><p>Restored default.</p></div>';
        if ( isset( $_GET['tested'] ) )  echo '<div class="notice notice-success is-dismissible"><p>Test email sent.</p></div>';
        echo '<table class="widefat striped"><thead><tr><th>Source</th><th>Email</th><th>Status</th><th>Last edited</th><th></th></tr></thead><tbody>';
        foreach ( $emails as $id => $e ) {
            $override = CE_Store::get( $id );
            $status   = $override ? 'Customized' : 'Default';
            $modified = $override['modified'] ?? '-';
            $default_url = add_query_arg( [ 'page' => self::SLUG, 'email' => $id ], admin_url( 'admin.php' ) );
            $url = apply_filters( 'ce_edit_link', $default_url, $e );
            printf( '<tr><td>%s</td><td><strong>%s</strong><br><span class="description">%s</span></td><td>%s</td><td>%s</td><td><a href="%s" class="button">Edit</a></td></tr>',
                esc_html( strtoupper( $e['source'] ) ), esc_html( $e['label'] ), esc_html( $e['description'] ),
                esc_html( $status ), esc_html( $modified ), esc_url( $url ) );
        }
        echo '</tbody></table></div>';
    }

    protected static function render_edit( $id ) {
        $def = CE_Registry::get( $id );
        if ( ! $def ) { echo '<div class="wrap"><p>Unknown email.</p></div>'; return; }
        $cur = CE_Store::resolve( $id );
        $has_override = (bool) CE_Store::get( $id );
        ?>
        <div class="wrap">
            <h1>Edit: <?php echo esc_html( $def['label'] ); ?></h1>
            <p class="description"><?php echo esc_html( $def['description'] ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ce_save_' . $id ); ?>
                <input type="hidden" name="action" value="ce_save">
                <input type="hidden" name="email_id" value="<?php echo esc_attr( $id ); ?>">
                <table class="form-table">
                    <tr><th><label>Subject</label></th>
                        <td><input type="text" name="subject" class="large-text" value="<?php echo esc_attr( $cur['subject'] ); ?>"></td></tr>
                    <tr><th><label>Body</label></th>
                        <td>
                            <?php wp_editor( $cur['body'], 'ce_body', [ 'textarea_name' => 'body', 'textarea_rows' => 15 ] ); ?>
                            <p><strong>Available tokens:</strong></p>
                            <ul>
                                <?php foreach ( $def['available_tokens'] as $tok => $desc ) : ?>
                                    <li><code><?php echo esc_html( $tok ); ?></code> - <?php echo esc_html( $desc ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td></tr>
                </table>
                <?php submit_button( 'Save' ); ?>
            </form>

            <?php if ( $has_override ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Restore default?');">
                    <?php wp_nonce_field( 'ce_reset_' . $id ); ?>
                    <input type="hidden" name="action" value="ce_reset">
                    <input type="hidden" name="email_id" value="<?php echo esc_attr( $id ); ?>">
                    <?php submit_button( 'Restore default', 'delete' ); ?>
                </form>
            <?php endif; ?>

            <hr>
            <h2>Send test email</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ce_test_' . $id ); ?>
                <input type="hidden" name="action" value="ce_send_test">
                <input type="hidden" name="email_id" value="<?php echo esc_attr( $id ); ?>">
                <input type="email" name="to" required class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
                <?php submit_button( 'Send test', 'secondary', 'submit', false ); ?>
                <p class="description">Tokens are replaced with placeholder values.</p>
            </form>
        </div>
        <?php
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $id = sanitize_key( $_POST['email_id'] ?? '' );
        check_admin_referer( 'ce_save_' . $id );
        CE_Store::save( $id,
            sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
            wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ), true );
        wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'email' => $id, 'updated' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_reset() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $id = sanitize_key( $_POST['email_id'] ?? '' );
        check_admin_referer( 'ce_reset_' . $id );
        CE_Store::delete( $id );
        wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'email' => $id, 'reset' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_test() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $id = sanitize_key( $_POST['email_id'] ?? '' );
        check_admin_referer( 'ce_test_' . $id );
        $to  = sanitize_email( $_POST['to'] ?? '' );
        $tpl = CE_Store::resolve( $id );
        $def = CE_Registry::get( $id );
        if ( ! $tpl || ! $to ) wp_die( 'Missing data.' );
        $fake = [];
        foreach ( array_keys( $def['available_tokens'] ) as $t ) $fake[ $t ] = '[' . trim( $t, '{}' ) . ']';
        wp_mail( $to,
            '[TEST] ' . CE_Renderer::render( $tpl['subject'], $fake ),
            CE_Renderer::render( $tpl['body'], $fake ) );
        wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'email' => $id, 'tested' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
