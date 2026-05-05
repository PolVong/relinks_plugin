<?php
/**
 * Plugin Name: Relinks
 * Description: Система внутрішньої перелінковки. ACF Block + автодобір анкорів з JSON + рівномірний розподіл.
 * Version:     2.4.1
 * Author:      Todo
 * Text Domain: relinks
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RELINKS_VERSION', '2.4.1' );
define( 'RELINKS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'RELINKS_URL',     plugin_dir_url( __FILE__ ) );

// Create data dir on activation
register_activation_hook( __FILE__, function() {
    $dir = RELINKS_DIR . 'data/';
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
} );

// Check ACF PRO
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'acf_register_block_type' ) ) {
        echo '<div class="notice notice-error"><p><strong>Relinks:</strong> Потрібен плагін ACF PRO.</p></div>';
    }
} );

// Auto-update from GitHub
require_once RELINKS_DIR . 'lib/plugin-update-checker/load-v5p6.php';
$update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/PolVong/relinks_plugin/',
    __FILE__,
    'relinks'
);
$update_checker->setBranch( 'main' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=relinks-options' ) . '">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );

// Load includes after all plugins loaded
add_action( 'plugins_loaded', function() {
    require_once RELINKS_DIR . 'includes/relinking-service.php';
    require_once RELINKS_DIR . 'includes/acf-setup.php';
    require_once RELINKS_DIR . 'includes/admin-tools.php';
} );
