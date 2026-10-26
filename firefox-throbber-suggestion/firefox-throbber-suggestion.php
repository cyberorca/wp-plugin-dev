<?php
/**
 * Plugin Name: Firefox Throbber Suggestion
 * Description: A small, dismissible bottom-right chip suggesting a custom Firefox tab-loading icon. "Yes" downloads a bundled install.sh and shows a one-line Terminal command to run. Nothing is installed automatically — the visitor always runs the script themselves. Wording and on/off are configurable under Settings.
 * Version: 2.9.1
 * Author: Your Site
 * License: GPL v2 or later
 * Text Domain: firefox-throbber-suggestion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'FTS_VERSION', '2.9.1' );
define( 'FTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FTS_OPTION_KEY', 'fts_settings' );

/**
 * Deliberately NOT a define()'d constant like FTS_PLUGIN_PATH above.
 * plugin_dir_url() reflects the current request's host (and is correctly
 * dynamic as long as nothing upstream caches it into a constant — see the
 * dynamic-site-url mu-plugin for why that matters when this site is
 * accessed through a tunnel). A constant computed once here would freeze
 * to whichever host happened to hit this PHP worker first and leak across
 * requests on persistent-worker runtimes. Called fresh at each use site
 * instead.
 */
function fts_plugin_url() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Default settings, used both as the fallback when no option is saved yet
 * and to pre-fill the settings form.
 */
function fts_default_settings() {
	return array(
		'enabled'         => 1,
		'question'        => __( 'Want a cleaner tab-loading icon in Firefox?', 'firefox-throbber-suggestion' ),
		'subtext'         => __( 'One-time, optional, only touches Firefox.', 'firefox-throbber-suggestion' ),
		'yes_label'       => __( 'Yes', 'firefox-throbber-suggestion' ),
		'no_label'        => __( 'No', 'firefox-throbber-suggestion' ),
		'dont_show_label' => __( "Don't show again", 'firefox-throbber-suggestion' ),
	);
}

/**
 * Reads the saved settings merged over the defaults, so a partially-saved
 * option (or none at all, on first activation) still returns a complete,
 * safe-to-use array.
 */
function fts_get_settings() {
	$saved = get_option( FTS_OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, fts_default_settings() );
}

// ---- Admin settings page --------------------------------------------------

function fts_register_settings() {
	register_setting(
		'fts_settings_group',
		FTS_OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'fts_sanitize_settings',
			'default'           => fts_default_settings(),
		)
	);

	add_settings_section(
		'fts_main_section',
		__( 'Firefox Throbber Suggestion', 'firefox-throbber-suggestion' ),
		function () {
			echo '<p>' . esc_html__( 'Controls the small bottom-right chip suggesting the Firefox tab-icon customization.', 'firefox-throbber-suggestion' ) . '</p>';
		},
		'fts_settings_page'
	);

	add_settings_field(
		'fts_enabled',
		__( 'Show the chip', 'firefox-throbber-suggestion' ),
		'fts_field_enabled',
		'fts_settings_page',
		'fts_main_section'
	);

	add_settings_field(
		'fts_question',
		__( 'Question text', 'firefox-throbber-suggestion' ),
		'fts_field_question',
		'fts_settings_page',
		'fts_main_section'
	);

	add_settings_field(
		'fts_subtext',
		__( 'Sub text', 'firefox-throbber-suggestion' ),
		'fts_field_subtext',
		'fts_settings_page',
		'fts_main_section'
	);

	add_settings_field(
		'fts_yes_label',
		__( '"Yes" button label', 'firefox-throbber-suggestion' ),
		'fts_field_yes_label',
		'fts_settings_page',
		'fts_main_section'
	);

	add_settings_field(
		'fts_no_label',
		__( '"No" button label', 'firefox-throbber-suggestion' ),
		'fts_field_no_label',
		'fts_settings_page',
		'fts_main_section'
	);

	add_settings_field(
		'fts_dont_show_label',
		__( '"Don\'t show again" link label', 'firefox-throbber-suggestion' ),
		'fts_field_dont_show_label',
		'fts_settings_page',
		'fts_main_section'
	);
}
add_action( 'admin_init', 'fts_register_settings' );

function fts_sanitize_settings( $input ) {
	$defaults = fts_default_settings();
	$input    = is_array( $input ) ? $input : array();

	return array(
		'enabled'         => ! empty( $input['enabled'] ) ? 1 : 0,
		'question'        => isset( $input['question'] ) && '' !== trim( $input['question'] )
			? sanitize_text_field( $input['question'] )
			: $defaults['question'],
		'subtext'         => isset( $input['subtext'] ) && '' !== trim( $input['subtext'] )
			? sanitize_text_field( $input['subtext'] )
			: $defaults['subtext'],
		'yes_label'       => isset( $input['yes_label'] ) && '' !== trim( $input['yes_label'] )
			? sanitize_text_field( $input['yes_label'] )
			: $defaults['yes_label'],
		'no_label'        => isset( $input['no_label'] ) && '' !== trim( $input['no_label'] )
			? sanitize_text_field( $input['no_label'] )
			: $defaults['no_label'],
		'dont_show_label' => isset( $input['dont_show_label'] ) && '' !== trim( $input['dont_show_label'] )
			? sanitize_text_field( $input['dont_show_label'] )
			: $defaults['dont_show_label'],
	);
}

function fts_field_enabled() {
	$settings = fts_get_settings();
	?>
	<label>
		<input type="checkbox" name="<?php echo esc_attr( FTS_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
		<?php esc_html_e( 'Show the suggestion chip on the site', 'firefox-throbber-suggestion' ); ?>
	</label>
	<?php
}

function fts_field_question() {
	$settings = fts_get_settings();
	?>
	<input type="text" class="regular-text" style="width:400px;" name="<?php echo esc_attr( FTS_OPTION_KEY ); ?>[question]" value="<?php echo esc_attr( $settings['question'] ); ?>">
	<?php
}

function fts_field_subtext() {
	$settings = fts_get_settings();
	?>
	<input type="text" class="regular-text" style="width:400px;" name="<?php echo esc_attr( FTS_OPTION_KEY ); ?>[subtext]" value="<?php echo esc_attr( $settings['subtext'] ); ?>">
	<?php
}

function fts_field_yes_label() {
	$settings = fts_get_settings();
	?>
	<input type="text" name="<?php echo esc_attr( FTS_OPTION_KEY ); ?>[yes_label]" value="<?php echo esc_attr( $settings['yes_label'] ); ?>">
	<?php
}

function fts_field_no_label() {
	$settings = fts_get_settings();
	?>
	<input type="text" name="<?php echo esc_attr( FTS_OPTION_KEY ); ?>[no_label]" value="<?php echo esc_attr( $settings['no_label'] ); ?>">
	<?php
}

function fts_field_dont_show_label() {
	$settings = fts_get_settings();
	?>
	<input type="text" name="<?php echo esc_attr( FTS_OPTION_KEY ); ?>[dont_show_label]" value="<?php echo esc_attr( $settings['dont_show_label'] ); ?>">
	<p class="description"><?php esc_html_e( 'Shown as a small link below Yes/No. Suppresses the chip for 30 days, not forever.', 'firefox-throbber-suggestion' ); ?></p>
	<?php
}

function fts_add_settings_page() {
	add_options_page(
		__( 'Firefox Throbber Suggestion', 'firefox-throbber-suggestion' ),
		__( 'Throbber Suggestion', 'firefox-throbber-suggestion' ),
		'manage_options',
		'fts_settings_page',
		'fts_render_settings_page'
	);
}
add_action( 'admin_menu', 'fts_add_settings_page' );

function fts_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Firefox Throbber Suggestion', 'firefox-throbber-suggestion' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'fts_settings_group' );
			do_settings_sections( 'fts_settings_page' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

// ---- Frontend: site-wide chip, no shortcode needed -------------------------

/**
 * Enqueues assets on every frontend page when enabled. Unlike the old
 * shortcode-based version, this chip is meant to appear site-wide, so
 * there's no page-by-page shortcode check to gate it.
 */
function fts_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	$settings = fts_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	wp_enqueue_style(
		'fts-style',
		fts_plugin_url() . 'assets/throbber-suggestion.css',
		array(),
		FTS_VERSION
	);

	wp_enqueue_script(
		'fts-script',
		fts_plugin_url() . 'assets/throbber-suggestion.js',
		array(),
		FTS_VERSION,
		true
	);

	wp_localize_script(
		'fts-script',
		'ftsData',
		array(
			'scriptUrlMac'     => fts_plugin_url() . 'assets/install.sh?ver=' . FTS_VERSION,
			'scriptUrlWindows' => fts_plugin_url() . 'assets/install.ps1?ver=' . FTS_VERSION,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fts_enqueue_assets' );

/**
 * Outputs the chip markup once, site-wide, via wp_footer rather than a
 * shortcode a site editor would need to place manually. Markup for both
 * states is always present; the JS toggles which one is visible, so the
 * chip degrades to a plain (non-interactive, but still readable) question
 * if JS is disabled.
 */
function fts_render_chip() {
	if ( is_admin() ) {
		return;
	}

	$settings = fts_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	?>
	<div class="fts-chip" id="fts-prompt">
		<button type="button" class="fts-close" id="fts-close" aria-label="<?php esc_attr_e( 'Dismiss', 'firefox-throbber-suggestion' ); ?>">&times;</button>
		<div class="fts-step fts-step-1" id="fts-step-1">
			<p class="fts-question">
				<strong><?php echo esc_html( $settings['question'] ); ?></strong><br>
				<span class="fts-sub"><?php echo esc_html( $settings['subtext'] ); ?></span>
			</p>
			<div class="fts-buttons">
				<button type="button" class="fts-btn fts-yes" id="fts-yes">
					<?php echo esc_html( $settings['yes_label'] ); ?>
				</button>
				<button type="button" class="fts-btn fts-no" id="fts-no">
					<?php echo esc_html( $settings['no_label'] ); ?>
				</button>
			</div>
			<label class="fts-dont-show" for="fts-dont-show">
				<input type="checkbox" id="fts-dont-show">
				<?php echo esc_html( $settings['dont_show_label'] ); ?>
			</label>
		</div>
		<div class="fts-step fts-step-2" id="fts-step-2" style="display:none;">
			<p class="fts-downloaded"><strong><?php esc_html_e( 'Downloaded! Two quick steps:', 'firefox-throbber-suggestion' ); ?></strong></p>
			<ol class="fts-steps">
				<li>
					<span id="fts-open-where"><?php esc_html_e( 'Open Terminal', 'firefox-throbber-suggestion' ); ?></span>
					<span class="fts-sub" id="fts-open-how"><?php esc_html_e( '(press ⌘ Space, type Terminal, press Enter)', 'firefox-throbber-suggestion' ); ?></span>
				</li>
				<li>
					<?php esc_html_e( 'Paste this and press Enter:', 'firefox-throbber-suggestion' ); ?>
					<div class="fts-command">
						<code id="fts-command-text">sh ~/Downloads/install.sh</code>
						<button type="button" class="fts-copy" id="fts-copy"><?php esc_html_e( 'Copy', 'firefox-throbber-suggestion' ); ?></button>
					</div>
				</li>
			</ol>
			<p class="fts-sub">
				<?php esc_html_e( "That's it. Firefox will quit and reopen on its own when it's done, nothing else happens on your computer.", 'firefox-throbber-suggestion' ); ?>
			</p>
			<p class="fts-sub">
				<a href="<?php echo esc_url( fts_plugin_url() . 'assets/install.sh?ver=' . FTS_VERSION ); ?>" target="_blank" rel="noopener" id="fts-view-script-link">
					<?php esc_html_e( 'Want to check what it does first? View the file', 'firefox-throbber-suggestion' ); ?>
				</a>
			</p>
		</div>
		<div class="fts-step fts-step-unsupported" id="fts-step-unsupported" style="display:none;">
			<p><?php esc_html_e( "This is built for macOS and Windows right now, and we couldn't tell which one you're on.", 'firefox-throbber-suggestion' ); ?></p>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'fts_render_chip' );
