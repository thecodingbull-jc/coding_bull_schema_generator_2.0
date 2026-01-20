<?php
/**
 * Plugin Name: Schema Generator
 * Description: A framework plugin for generating schema markup with multiple configuration tabs.
 * Version: 1.0.19
 * Author: The Coding Bull
 * Text Domain: schema-generator
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Define plugin path constants
define( 'SCHEMA_GENERATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCHEMA_GENERATOR_URL', plugin_dir_url( __FILE__ ) );

// Include admin class
require_once SCHEMA_GENERATOR_PATH . 'admin/class-schema-generator-admin.php';

// Initialize
add_action( 'plugins_loaded', function() {
	new Schema_Generator_Admin();
});

// Create database
function create_tcb_schema_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcb_schema'; // wp_tcb_schema

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        property varchar(255) NOT NULL,
        value longtext NOT NULL,
        page varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_tcb_schema_table');

// Load API handler
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-local-business-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-service-area-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-service-general-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-service-capability-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-blog-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/API/schema-generator-past-project-api.php';

add_action('admin_enqueue_scripts', function($hook) {
    // Only enqueue on your plugin page, optional:
    if ($hook !== 'toplevel_page_schema-generator') return;

    // Make AJAX URL & nonce available in JS
    wp_localize_script('jquery', 'schemaAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('schema_nonce')
    ]);
});

add_action('wp_head', function() {
    if (is_front_page()) {
        $jsonld = get_option('homepage_jsonld_script');
        if ($jsonld) {
            echo '<script type="application/ld+json">' . wp_json_encode(json_decode($jsonld)) . '</script>';
        }
    }
});

add_action('wp_head', function() {
    if (is_singular()) {
        global $post;
        $jsonld = get_post_meta($post->ID, '_injected_script', true);

        if ($jsonld) {
            echo '<script type="application/ld+json">' . wp_json_encode(json_decode($jsonld)) . '</script>';
        }
    }
});

add_action('wp_head', function() {
    if (is_singular()) {
        global $post;
        $jsonld = get_post_meta($post->ID, '_injected_faq_script', true);

        if ($jsonld) {
            echo '<script type="application/ld+json">' . wp_json_encode(json_decode($jsonld)) . '</script>';
        }
    }
});