<?php
/**
 * Front-end assets.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Content\Store;
use ZB\Theme\Registrable;
use ZB\Theme\Routing\Request;
use ZB\Theme\Theme;

/**
 * Enqueues the design-system stylesheets and the single, deferred script, and
 * prints the favicon. Fonts, styles and scripts are served from the theme.
 *
 * Stylesheets are ordered: fonts -> tokens -> base -> layout -> header/footer ->
 * components -> sections -> blog. Keep the order; later files rely on earlier
 * custom properties.
 *
 * The chat widget (content/site.json "chat", a Chatwoot instance on the
 * company's own domain) is the only script from another host. main.js loads it
 * after the page is idle; remove "chat" from site.json to switch it off.
 */
final class Assets implements Registrable {

	/**
	 * Stylesheet basenames in load order.
	 *
	 * @var array<int, string>
	 */
	private const STYLES = array(
		'fonts',
		'tokens',
		'base',
		'layout',
		'header',
		'footer',
		'components',
		'sections',
		'blog',
	);

	/**
	 * Font files needed for the first screen.
	 *
	 * @var array<int, string>
	 */
	private const PRELOAD_FONTS = array(
		'roboto-latin-variable',
		'poppins-latin-700',
	);

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'preload_fonts' ), 1 );
		add_action( 'wp_head', array( $this, 'favicon' ), 2 );
	}

	/**
	 * Enqueue stylesheets and the main script.
	 */
	public function enqueue(): void {
		$previous = array();

		foreach ( self::STYLES as $name ) {
			$relative = 'assets/css/' . $name . '.css';
			$handle   = 'zb-' . $name;

			wp_enqueue_style( $handle, Theme::url( $relative ), $previous, Theme::asset_version( $relative ) );

			$previous = array( $handle );
		}

		$script = 'assets/js/main.js';

		wp_enqueue_script(
			'zb-main',
			Theme::url( $script ),
			array(),
			Theme::asset_version( $script ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		$chat = self::chat();

		if ( array() !== $chat ) {
			wp_add_inline_script( 'zb-main', 'window.zbChat = ' . wp_json_encode( $chat ) . ';', 'before' );
		}

		// Not used by this theme; removing them avoids unused requests.
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'global-styles' );
	}

	/**
	 * Settings of the chat widget, empty when it is not configured.
	 *
	 * @return array<string, string>
	 */
	public static function chat(): array {
		$site  = Store::site();
		$url   = (string) Store::get( $site, 'chat.url' );
		$token = (string) Store::get( $site, 'chat.token' );

		if ( '' === $url || '' === $token ) {
			return array();
		}

		return array(
			'url'      => esc_url_raw( untrailingslashit( $url ) ),
			'token'    => $token,
			'position' => 'right' === Store::get( $site, 'chat.position' ) ? 'right' : 'left',
			'locale'   => Request::current()->lang,
		);
	}

	/**
	 * Preload the fonts of the first screen, so the hero heading does not render
	 * in a fallback font first.
	 */
	public function preload_fonts(): void {
		foreach ( self::PRELOAD_FONTS as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( Theme::url( 'assets/fonts/' . $font . '.woff2' ) )
			);
		}
	}

	/**
	 * The Zerobits icon, unless a site icon is set in the Customizer.
	 */
	public function favicon(): void {
		if ( has_site_icon() ) {
			return;
		}

		$url = Theme::url( 'assets/images/favicon.png' );

		printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( $url ) );
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $url ) );
	}
}
