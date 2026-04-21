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
    $view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '';
    if ( $view === 'tokens' ) { self::render_tokens(); return; }
    $id = isset( $_GET['email'] ) ? sanitize_key( $_GET['email'] ) : '';
    $id ? self::render_edit( $id ) : self::render_list();
  }

  protected static function render_nav( $active = 'list' ) {
    $list_url   = admin_url( 'admin.php?page=' . self::SLUG );
    $tokens_url = add_query_arg( 'view', 'tokens', $list_url );
    echo '<div style="margin:12px 0 16px">';
    echo '<a href="' . esc_url( $list_url ) . '" class="button' . ( $active === 'list' ? ' button-primary' : '' ) . '">All Emails</a> ';
    echo '<a href="' . esc_url( $tokens_url ) . '" class="button' . ( $active === 'tokens' ? ' button-primary' : '' ) . '">Token Reference</a>';
    echo '</div>';
  }

  protected static function render_list() {
    $emails = CE_Registry::all();
    echo '<div class="wrap"><h1>Custom Emails</h1>';
    self::render_nav( 'list' );
    if ( isset( $_GET['updated'] ) )  echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
    if ( isset( $_GET['reset'] ) )    echo '<div class="notice notice-success is-dismissible"><p>Restored default.</p></div>';
    if ( isset( $_GET['tested'] ) )   echo '<div class="notice notice-success is-dismissible"><p>Test email sent.</p></div>';
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

  protected static function render_tokens() {
    $emails = CE_Registry::all();
    echo '<div class="wrap"><h1>Token Reference</h1>';
    self::render_nav( 'tokens' );
    echo '<p>Below is a complete list of every available token for each email, grouped by source. Use these in Subject and Body fields.</p>';

    // Group by source.
    $grouped = [];
    foreach ( $emails as $id => $e ) {
      $src = strtoupper( $e['source'] ?? 'OTHER' );
      $grouped[ $src ][] = $e;
    }

    foreach ( $grouped as $source => $items ) {
      echo '<h2>' . esc_html( $source ) . '</h2>';
      echo '<table class="widefat striped"><thead><tr><th style="width:25%">Email</th><th style="width:25%">Token</th><th>Description</th></tr></thead><tbody>';
      foreach ( $items as $e ) {
        $tokens = $e['available_tokens'] ?? [];
        if ( empty( $tokens ) ) {
          echo '<tr><td>' . esc_html( $e['label'] ) . '</td><td colspan="2"><em>No tokens registered</em></td></tr>';
          continue;
        }
        $first = true;
        foreach ( $tokens as $tok => $desc ) {
          echo '<tr>';
          if ( $first ) {
            echo '<td rowspan="' . count( $tokens ) . '" style="vertical-align:top;font-weight:600">' . esc_html( $e['label'] ) . '</td>';
            $first = false;
          }
          echo '<td><code>' . esc_html( $tok ) . '</code></td>';
          echo '<td>' . esc_html( $desc ) . '</td>';
          echo '</tr>';
        }
      }
      echo '</tbody></table>';
    }

    // Global summary - unique tokens across all emails.
    echo '<h2>All Unique Tokens (quick copy)</h2>';
    $all_tokens = [];
    foreach ( $emails as $e ) {
      foreach ( ( $e['available_tokens'] ?? [] ) as $tok => $desc ) {
        if ( ! isset( $all_tokens[ $tok ] ) ) { $all_tokens[ $tok ] = $desc; }
      }
    }
    ksort( $all_tokens );
    echo '<table class="widefat striped"><thead><tr><th>Token</th><th>Description</th></tr></thead><tbody>';
    foreach ( $all_tokens as $tok => $desc ) {
      echo '<tr><td><code>' . esc_html( $tok ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
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
      <?php self::render_nav( '' ); ?>
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
                  <li><code><?php echo esc_html( $tok ); ?></code> &ndash; <?php echo esc_html( $desc ); ?></li>
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

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'ce_test_' . $id ); ?>
        <input type="hidden" name="action" value="ce_send_test">
        <input type="hidden" name="email_id" value="<?php echo esc_attr( $id ); ?>">
        <p><label>Send test to: <input type="email" name="test_to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"></label>
        <?php submit_button( 'Send Test', 'secondary', 'submit', false ); ?></p>
      </form>
    </div>
    <?php
  }

  /* ---- handlers (unchanged) ---- */

  public static function handle_save() {
    $id = sanitize_key( $_POST['email_id'] ?? '' );
    check_admin_referer( 'ce_save_' . $id );
    CE_Store::put( $id, [
      'subject'  => sanitize_text_field( $_POST['subject'] ?? '' ),
      'body'     => wp_kses_post( $_POST['body'] ?? '' ),
      'modified' => current_time( 'Y-m-d H:i' ),
    ] );
    wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'email' => $id, 'updated' => 1 ], admin_url( 'admin.php' ) ) );
    exit;
  }

  public static function handle_reset() {
    $id = sanitize_key( $_POST['email_id'] ?? '' );
    check_admin_referer( 'ce_reset_' . $id );
    CE_Store::delete( $id );
    wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'email' => $id, 'reset' => 1 ], admin_url( 'admin.php' ) ) );
    exit;
  }

  public static function handle_test() {
    $id = sanitize_key( $_POST['email_id'] ?? '' );
    check_admin_referer( 'ce_test_' . $id );
    $to  = sanitize_email( $_POST['test_to'] ?? '' );
    $def = CE_Registry::get( $id );
    $cur = CE_Store::resolve( $id );
    if ( $def && $to ) {
      $subject = $cur['subject'] ?: $def['default_subject'];
      $body    = $cur['body']    ?: $def['default_body'];
      wp_mail( $to, '[TEST] ' . $subject, $body );
    }
    wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'email' => $id, 'tested' => 1 ], admin_url( 'admin.php' ) ) );
    exit;
  }
}
