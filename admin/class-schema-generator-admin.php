<?php
if ( ! defined( 'ABSPATH' ) ) exit;




class Schema_Generator_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Schema Generator', 'schema-generator' ),
			__( 'Schema Generator', 'schema-generator' ),
			'manage_options',
			'schema-generator',
			[ $this, 'render_admin_page' ],
			'dashicons-editor-code',
			80
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'toplevel_page_schema-generator' ) return;
	}

	public function render_admin_page() {

		global $wpdb;
		$schema_table_name =  $wpdb->prefix . 'tcb_schema';

		$global_setting_address = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM $schema_table_name WHERE page = %s AND property = %s",
				'global',
				'single_location'
			)
		);
		
		$tabs = [
			'global-settings'       => 'Global Setting',
			'local-business'        => 'Local Business',
			'service-area'          => 'Service Area Pages',
			'service-general'       => 'Service General Pages',
			'service-capability'    => 'Service Capability Pages',
			'blog-schema'    		=> 'Blog Pages',
			'past-project-schema'   => 'Past Project Pages',
			'review-snippet'        => 'Review Snippet',
			'faq-snippet'           => 'FAQ Snippet',
			'employee-snippet'      => 'Employee Snippet',
		];

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'global-settings';
		?>
		<div id="schema-generator-loading-container" class="loading-overlay" role="status" aria-live="polite" aria-hidden="true">
			<div class="loading-content">
				<div class="spinner" aria-hidden="true"></div>
				<div class="loading-text">Loading...</div>
			</div>
		</div>
		<div class="wrap schema-generator-wrap">
			<h1><?php esc_html_e( 'Schema Generator', 'schema-generator' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<?if($slug == 'service-area' && $global_setting_address == "1"){continue;} else{?>
						<a href="?page=schema-generator&tab=<?php echo esc_attr( $slug ); ?>" class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?}?>
				<?php endforeach; ?>
			</h2>

			<div class="schema-generator-tab-content">
				<?php
				$view_file = SCHEMA_GENERATOR_PATH . 'admin/views/view-' . $current_tab . '.php';
				if ( file_exists( $view_file ) ) {
					include $view_file;
				} else {
					echo '<p>' . esc_html__( 'This section is under construction.', 'schema-generator' ) . '</p>';
				}
				?>
			</div>
		</div>

		<style>
			.loading-overlay {
			position: fixed;
			inset: 0; 
			background: rgba(0, 0, 0, 0.6); 
			z-index: 9999;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: opacity 200ms ease;
			opacity: 1;
			}

			/* hidden */
			.loading-overlay.hidden {
				opacity: 0;
				pointer-events: none;
			}
			.loading-content {
			text-align: center;
			color: #fff;
			font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
			}

			/* 小转圈 spinner */
			.spinner {
			width: 48px;
			height: 48px;
			border-radius: 50%;
			border: 4px solid rgba(255,255,255,0.18);
			border-top-color: rgba(255,255,255,0.95);
			margin: 0 auto 12px;
			animation: spin 0.9s linear infinite;
			}

			.loading-text {
			font-size: 18px;
			letter-spacing: 0.3px;
			}

			@keyframes spin {
			to { transform: rotate(360deg); }
			}

		</style>
		<script>
			function showLoading() {
				jQuery('#schema-generator-loading-container')
				.removeClass('hidden')
				.attr('aria-hidden', 'false');
		}

			function hideLoading() {
				jQuery('#schema-generator-loading-container')
				.addClass('hidden')
				.attr('aria-hidden', 'true');
			}
		</script>
		<?php
	}
}