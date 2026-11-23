<?php
/**
 * Theme supports, image sizes, editor styles.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Registrable;

/**
 * Declares what the theme supports.
 */
final class ThemeSupport implements Registrable {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'setup' ) );
	}

	/**
	 * Declare theme supports, editor styles and image sizes.
	 */
	public function setup(): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support(
			'html5',
			array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);

		// Editor: same tokens and article styles as the front end.
		add_theme_support( 'editor-styles' );
		add_editor_style(
			array(
				'assets/css/fonts.css',
				'assets/css/tokens.css',
				'assets/css/editor.css',
			)
		);

		// Article images: cards and the article header.
		set_post_thumbnail_size( 1600, 900, true );
		add_image_size( 'zb-card', 826, 600, true );
		add_image_size( 'zb-wide', 1600, 900, true );

		$GLOBALS['content_width'] = 760; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
