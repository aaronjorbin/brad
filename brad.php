<?php
/*
 Plugin Name: BRAD
 Plugin URI: https://github.com/aaronjorbin/brad
 Description: Better Responsility Around Discoverability
 Version: 0.2.1
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
		add_action( 'updated_option',                       array( $this, 'clear_dismissed_on_changes'  ),  10, 3   );
		add_action( 'admin_notices',                        array( $this, 'admin_notices'               )           );
		add_action( 'wp_ajax_dismiss_brad_notice',          array( $this, 'dismiss_notice'              )           );
		add_action( 'admin_footer',                         array( $this, 'add_js_for_ajax'             )           );

		// Our setup for the scheduled check and clear of the setting.
		add_action( 'check_dismissed',                      array( $this, 'check_dismissed'             )           );
		add_filter( 'cron_schedules',                       array( $this, 'add_weekly_cron_time'        )           );
		register_activation_hook( __FILE__,                 array( $this, 'set_setting_cron'            )           );
		register_deactivation_hook( __FILE__,               array( $this, 'clear_setting_cron'          )           );
	}

	/**
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * @param  string $option     Name of the updated option.
	 * @param  mixed  $old_value  The old option value.
	 * @param  mixed  $value      The new option value.
	 *
	 * @return void
	 */
	public function clear_dismissed_on_changes( $option, $old_value, $value ) {

		// If we are on public flag, the privacy policy, or one of the URL updates, handle that.
		if ( $old_value !== $value && in_array( sanitize_key( $option ), array( 'blog_public', 'home', 'siteurl', 'wp_page_for_privacy_policy' ) ) ) {
			update_option( 'brad_dismiss_notice', 'no', 'no' );
		}
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

		// Wrap the message in our div, including the dismissable class and our own for the Ajax call.
		echo '<div class="notice notice-warning is-dismissible brad-not-public-notice">';

			/*
				TRANSLATORS:
				1: Opening strong (bold) tag
				2: Closing strong (bold) tag
				3: Opening anchor (including href)
				4: Closing anchor
			*/
			echo '<p>' . sprintf( __( '%1$s NOTICE: %2$s This site is NOT public. %3$s Click here %4$s to update this setting.', 'brad' ), '<strong>', '</strong>', '<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">', '</a>' ) . '</p>';

		// Close up the div.
		echo '</div>';
	}

	/**
	 * Handle our small Ajax call for when the notice is dismissed.
	 *
	 * @return boolean
	 */
	public function dismiss_notice() {

		// Only run this on the admin side.
		if ( ! is_admin() ) {
			die();
		}

		// Bail if we aren't able to do this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Bail if the nonce check fails.
		if ( false === $nonce = check_ajax_referer( 'brad_nonce', 'brad_nonce', false ) ) {
			return false;
		}

		// Check for the specific action and the dismiss flag.
		if ( empty( $_POST['dismiss'] ) || empty( $_POST['action'] ) || 'dismiss_brad_notice' !== sanitize_key( $_POST['action'] ) ) {
			return false;
		}

		// Update our option.
		update_option( 'brad_dismiss_notice', 'yes', 'no' );

		// And return true.
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
				brad_nonce: '<?php echo wp_create_nonce( 'brad_nonce' ); ?>',
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

			// Set the weekly array data.
			$schedules['weekly'] = array(
				'display'   => __( 'Once Weekly', 'brad' ),
				'interval'  => 604800,
			);
		}

		// Return the modified array of schedules.
		return $schedules;
	}

	/**
	 * Set a WP cron check for clearing the dismissed setting.
	 *
	 * @return void
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
