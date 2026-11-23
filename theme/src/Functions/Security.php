<?php
/**
 * Small hardening measures.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Registrable;

/**
 * Reduces information leakage and disables legacy endpoints the site does
 * not use. Server-level hardening (headers, TLS) belongs in the hosting layer.
 */
final class Security implements Registrable {

	/**
	 * Attach hooks and remove leaky defaults.
	 */
	public function register(): void {
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		// The emoji script loads images from s.w.org; the site makes no third-party requests.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'login_errors', array( $this, 'generic_login_error' ) );
	}

	/**
	 * Do not reveal whether the username or the password was wrong.
	 */
	public function generic_login_error(): string {
		return __( 'The credentials are not valid.', 'zb-theme' );
	}
}
