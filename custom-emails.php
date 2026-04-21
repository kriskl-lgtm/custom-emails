<?php
/**
 * Plugin Name: Custom Emails
 * Description: Capture and edit system emails from WP core, BuddyPress, and bbPress.
 * Version: 0.3.0
 * Author: kriskl-lgtm
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CE_VERSION', '0.3.0' );
define( 'CE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CE_URL',  plugin_dir_url( __FILE__ ) );

foreach ( [
    'includes/class-ce-registry.php',
    'includes/class-ce-store.php',
    'includes/class-ce-renderer.php',
    'includes/class-ce-wrapper.php',
    'includes/class-ce-logger.php',
    'includes/class-ce-settings.php',
    'includes/class-ce-admin.php',
    'includes/class-ce-exporter.php',
    'includes/class-ce-cli.php',
    'includes/interceptors/core-password-reset.php',
    'includes/interceptors/core-new-user.php',
    'includes/interceptors/core-profile.php',
    'includes/interceptors/core-comments.php',
    'includes/interceptors/bbp-subscriptions.php',
    'includes/interceptors/bp-bridge.php',
    'includes/interceptors/promoted-catchall.php',
] as $f ) {
    $p = CE_PATH . $f;
    if ( file_exists( $p ) ) require_once $p;
}

add_action( 'plugins_loaded', function () {
    CE_Registry::boot();
    CE_Admin::boot();
    CE_Settings::boot();
    CE_Logger::boot();
    CE_Wrapper::boot();
    CE_Exporter::boot();
    CE_Interceptor_Password_Reset::boot();
    CE_Interceptor_New_User::boot();
    CE_Interceptor_Profile::boot();
    CE_Interceptor_Comments::boot();
    CE_Interceptor_BBP_Subscriptions::boot();
    CE_Interceptor_BP_Bridge::boot();
    CE_Interceptor_Promoted::boot();
    if ( defined( 'WP_CLI' ) && WP_CLI ) { CE_CLI::boot(); }
} );

register_activation_hook( __FILE__, function () {
    CE_Store::install();
    CE_Logger::install();
} );
