<?php
/*
 Plugin Name: BRAD: Better Responsility Around Discoverability 
 Plugin URI: https://github.com/aaronjorbin/brad
 Description: Adds a more prominant notice for users about private sites 
 Version: 0.1.0
 Author: Aaron Jorbin 
 Author URI: https://daily.jorb.in
 Text Domain: brad 
 */

add_action( 'admin_notices', 'brad_admin_notices' );

function brad_admin_notices(){
	$screen = get_current_screen();
	if ( ! is_network_admin() && ! is_user_admin() && 'dashboard' === $screen->id && current_user_can( 'manage_options' ) && '0' == get_option( 'blog_public' ) ) {
		echo '<div class="notice-info notice">';
		/* TRANSLATORS:
			1: Opening h1
			2: Opening anchor (including href)
			3: Closing anchor
			4: Closing h1
		*/
		printf( __( '%1$s This Site is NOT public %2$s Change This %3$s %4$s', 'brad' ), '<h1>', '<a href="' . esc_attr( admin_url('options-reading.php')  ) . '">', '</a>', '</h1>' );
		echo '</div>';
	}

}
