<?php
/**
 * Site languages.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\I18n;

use ZB\Theme\Content\Store;

/**
 * Languages come from content/site.json. The default language lives at the
 * root (/, /blog/), every other language under its code (/de/, /de/blog/).
 */
final class Languages {

	/**
	 * Cookie with the language a visitor chose in the language switch
	 * (set by assets/js/main.js, read by Functions\Router).
	 */
	public const COOKIE = 'zb_lang';

	/**
	 * Supported language codes, default first.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		$codes = Store::site()['languages'] ?? array();
		$codes = is_array( $codes ) ? array_values( array_filter( $codes, 'is_string' ) ) : array();

		return array() !== $codes ? $codes : array( 'en' );
	}

	/**
	 * Default language (no URL prefix).
	 */
	public static function default(): string {
		$default = Store::site()['default_language'] ?? '';

		return is_string( $default ) && in_array( $default, self::all(), true ) ? $default : self::all()[0];
	}

	/**
	 * Whether a code is a supported language.
	 *
	 * @param string $lang Language code.
	 */
	public static function is_supported( string $lang ): bool {
		return in_array( $lang, self::all(), true );
	}

	/**
	 * A supported code, or the default language.
	 *
	 * @param string $lang Language code.
	 */
	public static function sanitize( string $lang ): string {
		return self::is_supported( $lang ) ? $lang : self::default();
	}

	/**
	 * URL path prefix: '' for the default language, "de/" for the others.
	 *
	 * @param string $lang Language code.
	 */
	public static function prefix( string $lang ): string {
		return self::default() === $lang ? '' : $lang . '/';
	}

	/**
	 * Language of a request path ("de/blog/" -> de), the default otherwise.
	 *
	 * @param string $path Request path without the home path.
	 */
	public static function from_path( string $path ): string {
		$first = explode( '/', trim( $path, '/' ) )[0];

		return self::is_supported( $first ) ? $first : self::default();
	}

	/**
	 * The supported language a browser prefers most, from its Accept-Language
	 * header ("de-DE,de;q=0.9,en;q=0.8" -> de). The default language when the
	 * header is empty or names no supported language.
	 *
	 * @param string $header Accept-Language header.
	 */
	public static function preferred( string $header ): string {
		$best    = self::default();
		$quality = 0.0;

		foreach ( explode( ',', strtolower( $header ) ) as $entry ) {
			$parts = array_map( 'trim', explode( ';', $entry ) );
			$code  = explode( '-', $parts[0] )[0];
			$q     = 1.0;

			foreach ( array_slice( $parts, 1 ) as $param ) {
				if ( str_starts_with( $param, 'q=' ) ) {
					$q = (float) substr( $param, 2 );
				}
			}

			// The first of several equally weighted languages wins.
			if ( $q > $quality && self::is_supported( $code ) ) {
				$best    = $code;
				$quality = $q;
			}
		}

		return $best;
	}
}
