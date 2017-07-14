<?php
/*
 Plugin Name: BRAD
 Plugin URI: https://github.com/aaronjorbin/brad
 Description: Better Responsility Around Discoverability
 Version: 0.1.0
 Author: Aaron Jorbin
 Author URI: https://daily.jorb.in
 Text Domain: brad
 */

/**
 * Start our engines.
 */
class BRAD_Admin_Notice {

	/**
	 * Call our hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_notices',						array( $this, 'admin_notices'               )           );
		add_action( 'wp_ajax_dismiss_brad_notice',          array( $this, 'dismiss_notice'              )           );
		add_action( 'admin_footer',                         array( $this, 'add_js_for_ajax'             )           );

		// Our setup for the scheduled check and clear of the setting.
		add_action( 'check_dismissed',                      array( $this, 'check_dismissed'             )           );
		add_filter( 'cron_schedules',                       array( $this, 'add_weekly_cron_time'        )           );
		register_activation_hook( __FILE__,                 array( $this, 'set_setting_cron'            )           );
		register_deactivation_hook( __FILE__,               array( $this, 'clear_setting_cron'          )           );
	}

	/**
	 * Display the notification regarding the "blog public" setting.
	 *
	 * @return html
	 */
	public function admin_notices() {

		// Confirm we should show it.
		if ( false === $check = $this->maybe_handle_notice() ) {
			return;
		}

		// Now echo out the message.
		echo '<div class="notice notice-warning is-dismissible brad-not-public-notice">';

		/*
			TRANSLATORS:
			1: Opening strong (bold) tag
			2: Closing strong (bold) tag
			3: Opening anchor (including href)
			4: Closing anchor
		*/
		echo '<p>' . sprintf( __( '%1$s NOTICE: %2$s This site is NOT public. %3$s Click here %4$s to update this setting.', 'brad' ), '<strong>', '</strong>', '<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">', '</a>' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Make a small setting for dismissing the notice.
	 *
	 * @return boolean
	 */
	public function dismiss_notice() {

		// Only run this on the admin side.
		if ( ! is_admin() ) {
			die();
		}

		// Check for the specific notice.
		if ( empty( $_POST['action'] ) || 'dismiss_brad_notice' !== sanitize_key( $_POST['action'] ) ) {
			return false;
		}

		// Check for the specific dismiss.
		if ( empty( $_POST['dismiss'] ) ) {
			return false;
		}

		// Update our option.
		update_option( 'brad_dismiss_notice', 'yes', 'no' );

		// And true.
		return true;
	}

	/**
	 * Add the required JS for handling the ajax request.
	 *
	 * @return void
	 */
	public function add_js_for_ajax() {

		// Confirm we should load the JS.
		if ( false === $check = $this->maybe_handle_notice() ) {
			return;
		}
		?>
		<script>
		jQuery( '.brad-not-public-notice' ).on( 'click', 'button.notice-dismiss', function (event) {

			var data = {
				action:  'dismiss_brad_notice',
				dismiss: true,
			};

			jQuery.post( ajaxurl, data );
		});
		</script>
		<?php
	}

	/**
	 * Run the various checks required to show the notice and add the JS.
	 *
	 * @param  boolean $setting  Whether to check the blog and dismissed setting.
	 *
	 * @return boolean
	 */
	public function maybe_handle_notice( $setting = true ) {

		// Bail if our function doesnt exist or we aren't in the proper place.
		if ( is_network_admin() || is_user_admin() || ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		// Get my current screen.
		$screen = get_current_screen();

		// Bail without the screen object.
		if ( empty( $screen ) || ! is_object( $screen ) || empty( $screen->id ) || 'dashboard' !== sanitize_key( $screen->id ) ) {
			return false;
		}

		// If we requested the settings check, handle that.
		if ( ! empty( $setting ) ) {

			// Check the status of our dismissed notice.
			if ( 'yes' === $dismiss = get_option( 'brad_dismiss_notice', 'no' ) ) {
				return false;
			}

			// Check if the actual setting is populated.
			if ( '1' === $public = get_option( 'blog_public' ) ) {
				return false;
			}
		}

		// We hit the end. Must be true.
		return true;
	}

	/**
	 * Add our new weekly timestamp for cron jobs.
	 *
	 * @param  array $schedules  The existing array of time schedules.
	 *
	 * @return array $schedules  The modified array of time schedules.
	 */
	public function add_weekly_cron_time( $schedules ) {

		// If we don't already have the weekly schedule, add it.
		if ( ! isset( $schedules['weekly'] ) ) {

			$schedules['weekly'] = array(
				'display' => __( 'Once Weekly', 'brad' ),
				'interval' => 604800,
			);
		}

		// Return the array of schedules.
		return $schedules;
	}

	/**
	 * Set a WP cron check for clearing the dismissed setting.
	 */
	public function set_setting_cron() {

		// Add our scheduled event on activation.
	    if ( ! wp_next_scheduled( 'check_dismissed' ) ) {
			wp_schedule_event( time(), 'weekly', 'check_dismissed' );
	    }
	}

	/**
	 * Clear my cron job on deactivation.
	 *
	 * @return void
	 */
	public function clear_setting_cron() {

		// Get the time setting.
		$check  = wp_next_scheduled( 'check_dismissed' );

		// Remove the jobs.
		wp_unschedule_event( $check, 'check_dismissed', array() );
	}

	/**
	 * Clear the dismissed setting.
	 *
	 * @return void
	 */
	public function check_dismissed() {
		update_option( 'brad_dismiss_notice', 'no', 'no' );
	}

	// End our class.
}

// Call our class.
$BRAD_Admin_Notice = new BRAD_Admin_Notice();
$BRAD_Admin_Notice->init();
