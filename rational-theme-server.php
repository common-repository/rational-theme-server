<?php
/*
Plugin Name:	Rational Theme Server
Plugin URI:		http://jeremyhixon.com/rational-theme-server/
Description:	Plugin for theme developers to preview and serve downloads of their themes.
Version:		1.0.2
Author:			Jeremy Hixon
Author URI:		http://jeremyhixon.com/
License:		GPL2
License URI:	https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:	rational-theme-server
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Rational_Theme_Server {
	/* ==========================================================================
	   Vars
	   ========================================================================== */
	private $add_bar = false;
	private $post_types = array( 'post', 'page' );
	private $theme_current = false;
	private $themes_database = array();
	private $themes_available = array();
	private $edd_active = false;
	private $edd_default = 'on';
	private $edd_integrate = true;
	private $edd_themes_db = array();
	
	/* ==========================================================================
	   Magic
	   ========================================================================== */
	/**
	 * Class construct method
	 * Hooks into WordPress actions and filters
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'generate_rewrite_rules', array( $this, 'generate_rewrite_rules' ) );
		add_action( 'get_header', array( $this, 'get_header' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );
		
		// Check support for EDD
		if ( get_option( 'rts_edd', $this->edd_default ) === 'on' ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_edd' ) );
		} else {
			$this->edd_integrate = false;
		}
		
		// Check for global bar support
		if ( get_option( 'rts_global', 'on' ) !== 'on' ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		}
		
		
		if ( !session_id() ) {
			session_start();
		}
		if ( !empty( $_REQUEST['rts_select'] ) ) {
			$_SESSION['rts_select'] = $_REQUEST['rts_select'];
		}
		if ( !empty( $_SESSION['rts_select'] ) && !is_admin() ) {
			add_filter( 'stylesheet', array( $this, 'stylesheet_template' ) );
			add_filter( 'template', array( $this, 'stylesheet_template' ) );
		}
		
		register_deactivation_hook( __FILE__, array( $this, 'flush_rewrite' ) );
		register_activation_hook( __FILE__, array( $this, 'flush_rewrite' ) );
	}
	
	/* ==========================================================================
	   WordPress action hooks
	   ========================================================================== */
	/**
	 * add_meta_boxes
	 * Add meta box for bar display
	 * If the bar is disbled globally
	 */
	public function add_meta_boxes() {
		foreach ( $this->post_types as $screen ) {
			add_meta_box(
				'rts_meta_box',
				__( 'Theme Server', 'rational-theme-server' ),
				array( $this, 'build_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}
	
	/**
	 * add_meta_boxes
	 * Add meta box for EDD
	 * If EDD is enabled
	 */
	public function add_meta_boxes_edd() {
		add_meta_box(
			'rts_meta_box_edd',
			__( 'Theme Server', 'rational-theme-server' ),
			array( $this, 'build_meta_box_edd' ),
			'download',
			'side',
			'default'
		);
	}
	
	/**
	 * admin_init
	 * Registering settings for option page
	 */
	public function admin_init() {
		register_setting(
			'rts_group',	// used in settings_fields()
			'rts_themes',
			array( $this, 'themes_sanitize' )
		);
		register_setting(
			'rts_group',	// used in settings_fields()
			'rts_label',
			'sanitize_text_field'
		);
		register_setting(
			'rts_group',	// used in settings_fields()
			'rts_edd'
		);
		register_setting(
			'rts_group',	// used in settings_fields()
			'rts_global'
		);
		register_setting(
			'rts_group',	// used in settings_fields()
			'rts_styles'
		);
		register_setting(
			'rts_group',	// used in settings_fields()
			'rts_extra'
		);
		
		add_settings_section(
			'rts_themes',
			__( 'Theme Select', 'rational-theme-server' ),
			array( $this, 'build_themes_section' ),
			'rts_menu'
		);
		
		add_settings_field(
			'rts_select_themes',
			__( 'Available Themes', 'rational-theme-server' ),
			array( $this, 'build_select_themes' ),
			'rts_menu',
			'rts_themes'
		);
		add_settings_field(
			'rts_input_label',
			__( 'Select Label', 'rational-theme-server' ),
			array( $this, 'build_input_label' ),
			'rts_menu',
			'rts_themes'
		);
		if ( $this->edd_active ) {
			add_settings_field(
				'rts_input_edd',
				__( 'Integrate w/Easy Digital Downloads', 'rational-theme-server' ),
				array( $this, 'build_input_edd' ),
				'rts_menu',
				'rts_themes'
			);
		}
		add_settings_field(
			'rts_input_global',
			__( 'Apply bar globally', 'rational-theme-server' ),
			array( $this, 'build_input_global' ),
			'rts_menu',
			'rts_themes'
		);
		add_settings_field(
			'rts_input_styles',
			__( 'Use Default Styles', 'rational-theme-server' ),
			array( $this, 'build_input_styles' ),
			'rts_menu',
			'rts_themes'
		);
		add_settings_field(
			'rts_editor_extra',
			__( 'Additional Content', 'rational-theme-server' ),
			array( $this, 'build_editor_extra' ),
			'rts_menu',
			'rts_themes'
		);
	}
	
	/**
	 * admin_menu
	 * Adds the option page to the menu
	 */
	public function admin_menu() {
		add_theme_page(
			__( 'Rational Theme Server', 'rational-theme-server' ),
			__( 'Theme Server', 'rational-theme-server' ),
			'edit_theme_options',
			'rts_menu',												// used for sections
			array( $this, 'build_option_page' )
		);
	}
	
	/**
	 * generate_rewrite_rules
	 * Adds rewrite rule
	 */
	public function generate_rewrite_rules() {
		global $wp_rewrite;
		/**
		 * I needed info from WordPress to be thorough but I couldn't pull in
		 * wp-blog-header without messing up my PHP headers. So I'm passing
		 * the info through the redirect
		 */
		$theme = wp_get_theme();
		$themes_folder = $this->rts_encode_path( $theme->get_theme_root() );
		$uploads_folder = wp_upload_dir();
		$uploads_folder = $this->rts_encode_path( $uploads_folder['basedir'] . '/' );
		$non_wp_rules = array(
			'theme-download/([^/]*)/?'	=> sprintf(
				'%s/rational-theme-server/download.php?theme=%s&folder=%s&uploads=%s',
				PLUGINDIR,
				'$1',
				$themes_folder,
				$uploads_folder
			),
		);
		$wp_rewrite->non_wp_rules += $non_wp_rules;
	}
	
	/**
	 * get_header
	 * If the them bar is displayed for this page or not
	 */
	public function get_header() {
		if (
			get_option( 'rts_global', 'on' ) === 'on' ||
			get_post_meta( get_the_id(), 'rts_show_page', true ) === 'on'
		) {
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_footer', array( $this, 'wp_footer' ) );
		}
	}
	
	/**
	 * init
	 * Checking to see if EDD is among the active plugins
	 */
	public function init() {
		// Checking to see if EDD is active
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
			$this->edd_active = true;
		}
	}
	
	/**
	 * save_post
	 * Saves the choice of whether or not to show the theme server bar on the page
	 * If the bar is disabled globally
	 */
	public function save_post( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;
			
		if ( ! isset( $_POST['rts_nonce'] ) || ! wp_verify_nonce( $_POST['rts_nonce'], '_rts_nonce' ) )
			return;
		
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;
		
		$post_type = get_post_type( $post_id );
		if ( in_array( $post_type, $this->post_types ) ) {
			if ( isset( $_POST['rts_show_page'] ) )
				update_post_meta( $post_id, 'rts_show_page', 'on' );
			else
				update_post_meta( $post_id, 'rts_show_page', 'off' );
		} elseif ( $post_type === 'download' ) {
			if ( $_POST['rts_theme_edd'] === 'null' )
				update_post_meta( $post_id, 'rts_theme_edd', null );
			else
				update_post_meta( $post_id, 'rts_theme_edd', $_POST['rts_theme_edd'] );
		}
	}
	
	/**
	 * wp_enqueue_scripts
	 * Adds the stylesheet to the queue
	 */
	public function wp_enqueue_scripts() {
		
		$this->get_database_themes();
		if ( !empty( $this->themes_database ) ) {
			foreach( $this->themes_database as $theme_status ) {
				if ( $theme_status === 'download' ||  $theme_status === 'buy' ) {
					if ( get_option( 'rts_styles', 'on' ) === 'on' ) {
						wp_enqueue_style( 'rts-theme', plugin_dir_url( __FILE__ ) . 'css/style.css', false, '1.0' );
					}
					$this->add_bar = true;
					break;
				}
			}		
		}
	}
	
	/**
	 * wp_footer
	 * Adds the theme select bar to the footer
	 */
	public function wp_footer() {
		if ( $this->add_bar ) {
			$this->get_themes_available();
			$this->get_theme_current();
			?><div class="rational-theme-server-bar">
				<form class="rts-form" method="post"><?php
					$rts_extra = get_option( 'rts_extra', false );
					if ( !empty( $rts_extra ) ) {
						?><div class="rts-extra"><?php
							echo apply_filters( 'the_content', $rts_extra );
						?></div><?php
					}
					?><label class="rts-label" for="rts-select"><?php
						echo get_option(
							'rts_label',
							__( 'Current Theme', 'rational-theme-server' )
						);
					?></label>
					<select class="rts-select" id="rts-select" name="rts_select" onchange="this.form.submit()">
						<option value="">Choose a theme&hellip;</option><?php
						foreach ( $this->themes_available as $theme_id => $theme_name ) {
							if ( !empty( $this->themes_database[ $theme_id ] ) && in_array( $this->themes_database[ $theme_id ], array( 'on', 'buy' ) ) ) {
								printf(
									'<option %s value="%s">%s</option>',
									$theme_id === $this->theme_current ? 'selected' : '',
									$theme_id,
									$theme_name
								);
							}
						}
					?></select><?php
					echo "\r\n";
					if (
						empty( $this->themes_database[ $this->theme_current ] ) ||
						$this->themes_database[ $this->theme_current ] !== 'buy'
					) {
						$url = esc_url( site_url() ) . "/theme-download/{$this->theme_current}/";
						$text = 'Download';
					} elseif ( $this->themes_database[ $this->theme_current ] === 'buy' ) {
						$id = 0;
						foreach ( $this->edd_themes_db as $edd_theme_db ) {
							if ( $edd_theme_db->meta_value === $this->theme_current ) {
								$id = $edd_theme_db->post_id;
							}
						}
						$url = get_permalink( $id );
						$text = 'Buy';
					}
					printf(
						'<a class="%s" href="%s">%s</a>',
						'rts-download',
						$url,
						$text
					);
				?></form>
			</div><?php
		}
	}
	
	/**
	 * Cleans up the rewrite rules on activate/deactivate
	 */
	public function flush_rewrite() {
		flush_rewrite_rules();
	}
	
	/* ==========================================================================
	   WordPress filter hooks
	   ========================================================================== */
	/**
	 * stylesheet/template
	 */
	public function stylesheet_template() {
		return $_SESSION['rts_select'];
	}
	
	/* ==========================================================================
	   Public
	   ========================================================================== */
	/**
	 * Builds the options page
	 */
	public function build_option_page() {
		?><div class="wrap">
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<form method="post" action="options.php"><?php
				settings_errors();
				settings_fields( 'rts_group' );	// defined in register_setting()
				do_settings_sections( 'rts_menu' ); // defined in add_theme_page();
				submit_button();
			?></form>
		</div><?php
	}
	
	/**
	 * HTML for the "themes select" section
	 */
	public function build_themes_section() {
		?><p><?php _e( 'Choose the themes you&rsquo;d like to serve.', 'rational-theme-server' ); ?></p><?php
	}
	
	/**
	 * Inputs for the theme select
	 */
	public function build_select_themes() {
		$this->get_themes_available();
		$this->get_database_themes();
		foreach ( $this->themes_available as $theme_id => $theme_name ) {
			printf(
				'<p><label><input %s name="%s" type="checkbox"> %s</label></p>',
				in_array( $this->themes_database[ $theme_id ], array( 'on', 'buy' ) ) ? 'checked' : '',
				"rts_themes[{$theme_id}]",
				$theme_name
			);
		}
	}
	
	/**
	 * Inputs for the label
	 */
	public function build_input_label() {
		printf(
			'<input class="regular-text" name="rts_label" type="text" value="%s">',
			get_option(
				'rts_label',
				__( 'Current Theme', 'rational-theme-server' )
			)
		);
	}
	
	/**
	 * Inputs for the Easy Digital Downloads checkbox
	 */
	public function build_input_edd() {
		printf(
			'<label><input %s name="rts_edd" type="checkbox"> %s</label>',
			checked( 'on', get_option( 'rts_edd', $this->edd_default ), false ),
			__( 'Check to integrate with Easy Digital Downloads.', 'rational-theme-server' )
		);
	}
	
	/**
	 * Inputs for the apply globally checkbox
	 */
	public function build_input_global() {
		printf(
			'<label><input %s name="rts_global" type="checkbox"> %s</label>',
			checked( 'on', get_option( 'rts_global', 'on' ), false ),
			__( 'Check to show the bar on all pages.', 'rational-theme-server' )
		);
	}
	
	/**
	 * Inputs for the default styles checkbox
	 */
	public function build_input_styles() {
		printf(
			'<label><input %s name="rts_styles" type="checkbox"> %s</label>',
			checked( 'on', get_option( 'rts_styles', 'on' ), false ),
			__( 'Check to enable default styling.', 'rational-theme-server' )
		);
	}

	/**
	 * Editor for the extra field
	 */
	public function build_editor_extra() {
		?><p class="description"><?php _e( 'If you use this field you may beed to make some CSS changes to the bar as well to accommodate the bar.', 'rational-theme-server' ); ?></p><hr><?php
		wp_editor(
			get_option( 'rts_extra', false ),
			'rts_extra'
		);
	}
	
	/**
	 * Add meta box callback
	 */
	public function build_meta_box() {
		wp_nonce_field( '_rts_nonce', 'rts_nonce' );
		printf(
			'<label><input %s id="%s" name="%s" type="checkbox" value="%s">&nbsp; Enable theme server bar for this page.</label>',
			get_post_meta( get_the_id(), 'rts_show_page', true ) === 'on' ? 'checked' : '',
			'rts_show_page',
			'rts_show_page',
			'on'
		);
	}
	
	/**
	 * Add meta box callback for EDD
	 */
	public function build_meta_box_edd() {
		wp_nonce_field( '_rts_nonce', 'rts_nonce' );
		?><p><strong>Theme Associated with this Download</strong></p><?php
		$this->get_themes_available();
		$db_value = get_post_meta( get_the_id(), 'rts_theme_edd', true );
		?><select name="rts_theme_edd">
			<option value="null">None</option><?php
			foreach ( $this->themes_available as $value => $text ) {
				printf(
					'<option %s value="%s">%s</option>',
					selected( $db_value, $value, false ),
					$value,
					$text
				);
			}
		?></select><?php
	}

	/**
	 * Sanitize function for the theme select
	 *
	 * @param	array	$input	Input from the page
	 *
	 * @return	array			Sanitized input
	 */
	public function themes_sanitize( $input ) {
		$this->get_themes_available();
		foreach ( $this->themes_available as $theme_id => $theme_name ) {
			if ( empty( $input[ $theme_id ] ) ) {
				$input[ $theme_id ] = 'off';
			}
		}
		return $input;
	}
	
	/* ==========================================================================
	   Helpers
	   ========================================================================== */
	/**
	 * Gets the themes available through WordPress
	 */
	private function get_themes_available() {
		$wp_themes = wp_get_themes( array(
			'allowed'	=> true, 
		) );
		foreach ( $wp_themes as $theme_id => $theme_object ) {
			$this->themes_available[ $theme_id ] = $theme_object->get( 'Name' );
		}
	}
	
	/**
	 * Get the themes selected from the database
	 */
	private function get_database_themes() {
		// get themes selected themes
		$this->themes_database = get_option( 'rts_themes' );
		
		// get themes associated with EDD
		global $wpdb;
		$query = "SELECT * FROM `{$wpdb->postmeta}` WHERE `meta_key` = 'rts_theme_edd' AND `meta_value` IS NOT NULL";
		$this->edd_themes_db = $wpdb->get_results( $query );
		$edd_themes = array();
		foreach ( $this->edd_themes_db as $edd_theme_db ) {
			if ( !in_array( $edd_theme_db->meta_value, $edd_themes ) ) {
				$edd_themes[] = $edd_theme_db->meta_value;
			}
		}
		
		foreach ( $this->themes_database as $key => $value ) {
			if ( $value === 'on' && in_array( $key, $edd_themes ) ) {
				$this->themes_database[ $key ] = 'buy';
			}
		}
	}
	
	/**
	 * Get the current theme in use
	 */
	private function get_theme_current() {
		$this->theme_current = wp_get_theme()->get_template();
	}
	
	/**
	 * Replaces % with ' because the rewrite rules can't handle the %
	 */
	private function rts_encode_path( $path ) {
		return str_replace(
			array( "'", '%' ),
			array( '%27', "'" ),
			urlencode( esc_url( $path . '/' ) )
		);
	}
}
new Rational_Theme_Server;