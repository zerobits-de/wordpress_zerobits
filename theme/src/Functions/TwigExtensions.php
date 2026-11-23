<?php
/**
 * Theme-specific Twig functions and filters.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Components\Icon;
use ZB\Theme\Registrable;
use ZB\Theme\Theme;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Small helpers for presentation only.
 *
 *   {{ asset('images/foo.svg') }}           theme asset URL
 *   {{ icon('arrow-right', 'icon--lg') }}   inline SVG icon (decorative)
 *   {{ icon('linkedin', '', 'LinkedIn') }}  labelled icon
 *   {{ t.blog.minutes|fill({n: 4}) }}       replace {n} placeholders in a text
 */
final class TwigExtensions implements Registrable {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'timber/twig', array( $this, 'extend' ) );
	}

	/**
	 * Register functions and filters.
	 *
	 * @param Environment $twig Twig environment.
	 */
	public function extend( Environment $twig ): Environment {
		$twig->addFunction( new TwigFunction( 'asset', array( $this, 'asset' ) ) );
		$twig->addFunction( new TwigFunction( 'icon', array( Icon::class, 'render' ), array( 'is_safe' => array( 'html' ) ) ) );
		$twig->addFilter( new TwigFilter( 'fill', array( $this, 'fill' ) ) );

		return $twig;
	}

	/**
	 * URL of a file in assets/.
	 *
	 * @param string $relative Path relative to assets/.
	 */
	public function asset( string $relative ): string {
		return Theme::url( 'assets/' . ltrim( $relative, '/' ) );
	}

	/**
	 * Replace {name} placeholders.
	 *
	 * @param string                    $text   Text with placeholders.
	 * @param array<string, int|string> $values Values by placeholder name.
	 */
	public function fill( string $text, array $values ): string {
		$pairs = array();

		foreach ( $values as $name => $value ) {
			$pairs[ '{' . $name . '}' ] = (string) $value;
		}

		return strtr( $text, $pairs );
	}
}
