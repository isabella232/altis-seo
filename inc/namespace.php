<?php
/**
 * SEO module functions.
 *
 * @package altis/seo
 */

namespace Altis\SEO;

use Altis;
use Altis\Module;

/**
 * Bootstrap SEO Module.
 *
 * @param Module $module The SEO Module object.
 * @return void
 */
function bootstrap( Module $module ) {
	$settings = $module->get_settings();

	if ( $settings['redirects'] ) {
		add_action( 'muplugins_loaded', __NAMESPACE__ . '\\load_redirects', 0 );
	}

	if ( $settings['metadata'] ) {
		add_action( 'muplugins_loaded', __NAMESPACE__ . '\\load_metadata', 0 );

		// Maybe override Yoast social options.
		add_filter( 'pre_option_wpseo_social', __NAMESPACE__ . '\\override_yoast_social_options', 9999 );

		// Hide the HUGE SEO ISSUE warning and disable admin bar menu.
		add_filter( 'pre_option_wpseo', __NAMESPACE__ . '\\override_yoast_seo_options' );
	}

	if ( $settings['site-verification'] ) {
		add_action( 'muplugins_loaded', __NAMESPACE__ . '\\Site_Verification\\bootstrap' );
	}

	// Load Yoast SEO late in case WP SEO Premium is installed as a plugin or mu-plugin.
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_wpseo', 1 );

	// Remove Yoast SEO dashboard widget.
	add_action( 'admin_init', __NAMESPACE__ . '\\remove_yoast_dashboard_widget' );

	// Remove the Yoast Premium submenu page.
	add_action( 'admin_init', __NAMESPACE__ . '\\remove_yoast_submenu_page' );

	// Remove Helpscout.
	add_filter( 'wpseo_helpscout_show_beacon', '__return_false' );


	// Read config/robots.txt file into robots.txt route handled by WP.
	add_filter( 'robots_txt', __NAMESPACE__ . '\\robots_txt', 10 );

	// Add sitemap to robots.txt.
	add_filter( 'robots_txt', __NAMESPACE__ . '\\add_sitemap_index_to_robots', 11, 2 );

	// CSS overrides.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_yoast_css_overrides', 11 );
	add_action( 'wpseo_configuration_wizard_head', __NAMESPACE__ . '\\override_wizard_styles' );
	add_action( 'admin_head', __NAMESPACE__ . '\\hide_yoast_premium_social_previews' );
}

/**
 * Get a corresponding callable for a boolean value.
 *
 * @param boolean $condition Condition to check.
 * @return callable
 */
function get_bool_callback( bool $condition ) : callable {
	return $condition ? '__return_true' : '__return_false';
}

/**
 * Load the redirects plugin.
 *
 * @return void
 */
function load_redirects() {
	require_once Altis\ROOT_DIR . '/vendor/humanmade/hm-redirects/hm-redirects.php';
}

/**
 * Checks if Yoast SEO Premium is installed.
 *
 * @return bool
 */
function is_yoast_premium() : bool {
	return class_exists( 'WPSEO_Premium' );
}

/**
 * Load Yoast SEO.
 */
function load_wpseo() {
	if ( is_yoast_premium() ) {
		return;
	}

	require_once Altis\ROOT_DIR . '/vendor/yoast/wordpress-seo/wp-seo.php';
}

/**
 * Remove the Yoast SEO dashboard widget.
 */
function remove_yoast_dashboard_widget() {
	remove_meta_box( 'wpseo-dashboard-overview', 'dashboard', 'normal' );

	// This script & style are enqueued by Yoast.
	wp_dequeue_script( 'dashboard-widget' );
	wp_dequeue_style( 'wp-dashboard' );
}

/**
 * Remove the Premium submenu.
 */
function remove_yoast_submenu_page() {
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_licenses' );
}

/**
 * Load the SEO metadata plugin.
 *
 * @return void
 */
function load_metadata() {
	$config = Altis\get_config()['modules']['seo']['metadata'] ?? [];
	$options = get_option( 'wpseo_social' );

	// Only add our custom Opengraph presenters if Opengraph is enabled.
	if ( ( isset( $config['opengraph'] ) && $config['opengraph'] === true ) || $options['opengraph'] ) {
		add_filter( 'wpseo_frontend_presenters', __NAMESPACE__ . '\\opengraph_presenters' );
	}
}

/**
 * Add our custom Opengraph presenters to the array of Yoast Opengraph presenters.
 *
 * @param array $presenters The array of presenters.
 *
 * @return array Updated array of presenters.
 */
function opengraph_presenters( array $presenters ) : array {
	require_once __DIR__ . '/opengraph/class-altis-opengraph-author-presenter.php';
	require_once __DIR__ . '/opengraph/class-altis-opengraph-section-presenter.php';
	require_once __DIR__ . '/opengraph/class-altis-opengraph-tag-presenter.php';

	$presenters[] = new Altis_Opengraph_Author_Presenter();
	$presenters[] = new Altis_Opengraph_Section_Presenter();
	$presenters[] = new Altis_Opengraph_Tag_Presenter();

	return $presenters;
}

/**
 * Override SEO Social options from config.
 *
 * @param array|bool $options Any options set by pre_option_* filters.
 *
 * @return array|bool The filtered option values.
 */
function override_yoast_social_options( $options ) {
	$config = Altis\get_config()['modules']['seo']['metadata'] ?? [];

	// Get Opengraph and Twitter card settings. These default to true if the config has been set but no value given to these options.
	$options['opengraph'] = $config['opengraph'] ?? true;
	$options['twitter'] = $config['twitter'] ?? true;

	$options['pinterestverify'] = $config['pinterest-verify'] ?? '';
	$options['facebook_site'] = $config['social-urls']['facebook'] ?? '';
	$options['twitter_site'] = $config['social-urls']['twitter'] ?? '';
	$options['instagram_url'] = $config['social-urls']['instagram'] ?? '';
	$options['linkedin_url'] = $config['social-urls']['linkedin'] ?? '';
	$options['google_url'] = $config['social-urls']['google'] ?? '';
	$options['myspace_url'] = $config['social-urls']['myspace'] ?? '';
	$options['pinterest_url'] = $config['social-urls']['pinterest'] ?? '';
	$options['youtube_url'] = $config['social-urls']['youtube'] ?? '';
	$options['wikipedia_url'] = $config['social-urls']['wikipedia'] ?? '';

	// Set fallback image based on config.
	$options['og_default_image'] = $config['fallback-image'] ?? '';
	$options['og_default_image_id'] = $config['fallback-image-id'] ?? '';

	// If the fallback image ID was not configured, but we do have a fallback image, get the ID from the URL.
	if ( empty( $options['og_default_image_id'] ) && $options['og_default_image'] ) {
		$options['og_default_image_id'] = get_image_id_from_url( $config['fallback-image'] );
	}

	// These options are only used as fallbacks from the default Home and Front Page SEO options, and possibly not even then.
	$options['og_frontpage_title'] = $config['opengraph-fallback']['og-frontpage-title'] ?? '';
	$options['og_frontpage_desc'] = $config['opengraph-fallback']['og-frontpage-desc'] ?? '';
	$options['og_frontpage_image'] = $config['opengraph-fallback']['og-frontpage-image'] ?? '';
	$options['og_frontpage_image_id'] = $config['opengraph-fallback']['og-frontpage-image-id'] ?? '';

	// If the frontpage image ID was not configured but we do have a frontpage image, get the ID from the url.
	if ( empty( $options['og_frontpage_image_id'] ) && $options['og_frontpage_image'] ) {
		$options['og_frontpage_image_id'] = get_image_id_from_url( $config['opengraph-fallback']['og-frontpage-image'] );
	}

	return $options;
}

/**
 * Check if we should override the metadata options.
 *
 * Compares the SEO metadata options saved in the config file with the default values. If anything has been changed, returns true.
 *
 * @return bool True if metadata values have been saved.
 */
function should_override_metadata_options() : bool {
	$config = Altis\get_config()['modules']['seo']['metadata'] ?? [];
	$default_config = apply_filters( 'altis.config.default', [] )['modules']['seo']['metadata'];

	// If the config matches the default, we aren't overriding metadata options.
	if ( empty( array_diff_assoc( $config, $default_config ) ) ) {
		return false;
	}

	// If any changes have been made to the metadata config, they will override the default options.
	return true;
}

/**
 * Get a WordPress image ID by the URL.
 *
 * @param string $url URL of the uploaded image.
 *
 * @return int|string Either the ID of the image or an empty string if no matching ID was found.
 */
function get_image_id_from_url( string $url ) {
	global $wpdb;
	$url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $url );

	$image = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM $wpdb->posts WHERE guid='%s';",
		$url
	) );

	if ( ! empty( $image ) ) {
		return $image[0];
	}

	return '';
}

/**
 * Override the Yoast SEO options.
 *
 * Disables the Search Engines Discouraged warning on non-production environments and the admin bar menu.
 *
 * @param mixed $options The option to retrieve.
 *
 * @return array The updated WPSEO options.
 */
function override_yoast_seo_options( $options ) : ?array {
	$options['enable_admin_bar_menu'] = false;

	if ( Altis\get_environment_type() === 'production' ) {
		return $options;
	}

	$options['ignore_search_engines_discouraged_notice'] = true;

	return $options;
}

/**
 * Render a notice on the Yoast SEO Dashboard page that social settings have been configured in the Altis config.
 */
function social_options_overridden_notice() {
	$screen = get_current_screen();

	if ( $screen->base !== 'toplevel_page_wpseo_dashboard' ) {
		return;
	}

	$classes = 'notice notice-warning is-dismissable';
	$message = __( 'Social metadata has been saved in the Altis configuration file. Use the Altis configuration file to make changes to the Social SEO settings.', 'altis-seo' );

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $classes ), esc_html( $message ) );
}

/**
 * Add robots.txt content if file is present.
 *
 * @param string $output robots.txt file content generated by WP.
 *
 * @return string robots.txt file content including custom configuration if any.
 */
function robots_txt( string $output ) : string {
	$robots_file = Altis\ROOT_DIR . '/.config/robots.txt';

	// Legacy file will be in the `/config` dir instead of `/.config`.
	$legacy_file = Altis\ROOT_DIR . '/config/robots.txt';

	if ( file_exists( $robots_file ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$output .= "\n" . file_get_contents( $robots_file ) . "\n";
	} elseif ( file_exists( $legacy_file ) ) {
		// If the legacy-style file exists, load it, but warn.
		trigger_error( 'The "config/robots.txt" file is deprecated as of Altis 2.0. Use ".config/robots.txt" instead.', E_USER_DEPRECATED );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$output .= "\n" . file_get_contents( $legacy_file ) . "\n";
	}

	return $output;
}

/**
 * Add the Yoast SEO sitemap index to the robots.txt file.
 *
 * @param string $output The original robots.txt content.
 * @param bool $public Whether the site is public.
 *
 * @return string The filtered robots.txt content.
 */
function add_sitemap_index_to_robots( string $output, bool $public ) : string {
	if ( $public ) {
		$output .= sprintf( "Sitemap: %s\n", site_url( '/sitemap_index.xml' ) );
	}

	return $output;
}

/**
 * Enqueue CSS.
 */
function enqueue_yoast_css_overrides() {
	wp_enqueue_style( 'altis-seo', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/altis-seo.css', [], '2021-06-04-5' );
}

/**
 * Override the Yoast wizard styles.
 *
 * The Yoast setup wizard bails early, before our styles are loaded, but we can
 * hook into their action to load in our style overrides.
 */
function override_wizard_styles() {
	wp_register_style( 'altis-seo', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/global-styles.css', [], '2021-06-04-5' );
	wp_print_styles( 'altis-seo' );
}

/**
 * Hide the social previews if Yoast Premium is not active.
 */
function hide_yoast_premium_social_previews() {
	$screen = get_current_screen();

	// Bail early if Yoast Premium is active or if we aren't on a post edit screen.
	if ( is_yoast_premium() || $screen->base !== 'post' ) {
		return;
	}

	/**
	 * This targets the 6th and 7th components panel in the Yoast
	 * sidebar, which corresponds to the Facebook and Twitter social
	 * preview buttons. If Yoast ever adds more panels to this sidebar,
	 * this will need to be updated.
	 */
	$styles = 'div.components-panel div:nth-child(6n) div.yoast.components-panel__body, div.components-panel div:nth-child(7n) div.yoast.components-panel__body {
		display: none;
	}';

	/**
	 * Hide the Social tab in the Yoast Metabox.
	 *
	 * The Google preview is in the basic SEO tab and social previews
	 * are only available for Yoast SEO Premium.
	 */
	$styles .= '.wpseo-metabox-menu .yoast-aria-tabs li:last-of-type {
		display:none;
	}';

	echo "<style>$styles</style>"; // phpcs:ignore HM.Security.EscapeOutput.OutputNotEscaped
}
