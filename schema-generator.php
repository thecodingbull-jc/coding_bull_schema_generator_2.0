<?php
/**
 * Plugin Name: Schema Generator
 * Description: A framework plugin for generating schema markup with multiple configuration tabs.
 * Version: 1.0.23
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

// The front-page WebSite/creator credit that used to print here now lives in the
// TCB Elementor Widgets plugin (productivity/agency-attribution-schema.php). It
// runs on every page instead of only the front page, and gives the agency a
// stable @id so the same entity resolves across every client site. The
// homepage_website_jsonld_script option is left in place but no longer read.

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

// --- Regenerate All Schemas utility ---

function tcb_run_all_schema_generators() {
    homepage_generate_schema( true );
    service_area_generate_schema( true );
    service_general_generate_schema( true );
    service_capability_generate_schema( true );
    blog_generate_schema( true );
    past_project_generate_schema( true );
}

// Nightly cron
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'tcb_nightly_schema_regeneration' ) ) {
        wp_schedule_event( strtotime( 'midnight' ), 'daily', 'tcb_nightly_schema_regeneration' );
    }
});
add_action( 'tcb_nightly_schema_regeneration', 'tcb_run_all_schema_generators' );

// Admin bar button
add_action( 'admin_bar_menu', function( WP_Admin_Bar $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $wp_admin_bar->add_node([
        'id'    => 'tcb-regenerate-schema',
        'title' => 'Regenerate Schema',
        'href'  => '#',
    ]);
}, 100 );

// Inline script + style for the admin bar button (frontend + admin)
function tcb_regenerate_schema_bar_script() {
    if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) return;
    $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
    $nonce    = wp_create_nonce( 'tcb_regenerate_all' );
    ?>
    <style>
        #wp-admin-bar-tcb-regenerate-schema > .ab-item { cursor: pointer; }
        #wp-admin-bar-tcb-regenerate-schema.tcb-running > .ab-item { opacity: 0.6; pointer-events: none; }
    </style>
    <script>
    (function($) {
        $(document).on('click', '#wp-admin-bar-tcb-regenerate-schema > .ab-item', function(e) {
            e.preventDefault();
            var $node = $('#wp-admin-bar-tcb-regenerate-schema');
            if ($node.hasClass('tcb-running')) return;
            var $link = $node.find('.ab-item').first();
            var originalText = $link.text();
            $node.addClass('tcb-running');
            $link.text('Regenerating...');
            $.post('<?php echo $ajax_url; ?>', {
                action: 'tcb_regenerate_all_schemas',
                nonce:  '<?php echo $nonce; ?>'
            })
            .done(function(res) {
                $link.text(res.success ? 'Done!' : 'Error');
            })
            .fail(function() {
                $link.text('Error');
            })
            .always(function() {
                $node.removeClass('tcb-running');
                setTimeout(function() { $link.text(originalText); }, 3000);
            });
        });
    })(jQuery);
    </script>
    <?php
}
add_action( 'wp_footer',    'tcb_regenerate_schema_bar_script' );
add_action( 'admin_footer', 'tcb_regenerate_schema_bar_script' );

// AJAX handler for the admin bar button
add_action( 'wp_ajax_tcb_regenerate_all_schemas', function() {
    check_ajax_referer( 'tcb_regenerate_all', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ] );
    }
    tcb_run_all_schema_generators();
    wp_send_json_success( [ 'message' => 'All schemas regenerated.' ] );
} );