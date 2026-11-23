<?php
/**
 * Content from the repository.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Content;

use ZB\Theme\Theme;

/**
 * Reads the site content that is versioned with the theme.
 *
 * Files:
 *   content/site.json            language-neutral data (images, ids, contact, languages)
 *   content/{lang}.json          every text of one language, same structure in all languages
 *   content/legal/{lang}/*.html  legal pages
 *
 * Blog articles are the only content that lives in WordPress. bin/check-content.php
 * (CI) makes sure all language files have the same keys.
 */
final class Store {

	/**
	 * Decoded JSON files keyed by file name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $cache = array();

	/**
	 * Language-neutral site data.
	 *
	 * @return array<string, mixed>
	 */
	public static function site(): array {
		return self::json( 'site' );
	}

	/**
	 * All texts of one language.
	 *
	 * @param string $lang Language code.
	 * @return array<string, mixed>
	 */
	public static function strings( string $lang ): array {
		return self::json( $lang );
	}

	/**
	 * One text by dot path, e.g. "blog.title". Returns '' when the path does not
	 * lead to a string.
	 *
	 * @param string $lang Language code.
	 * @param string $path Dot-separated key path.
	 */
	public static function text( string $lang, string $path ): string {
		$value = self::get( self::strings( $lang ), $path );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Any value by dot path.
	 *
	 * @param array<string, mixed> $data Data to search.
	 * @param string               $path Dot-separated key path.
	 * @return mixed
	 */
	public static function get( array $data, string $path ) {
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
				return null;
			}

			$data = $data[ $key ];
		}

		return $data;
	}

	/**
	 * HTML of a legal page, '' when the file is missing.
	 *
	 * @param string $lang Language code.
	 * @param string $key  Legal page key (imprint, privacy).
	 */
	public static function legal_html( string $lang, string $key ): string {
		if ( 1 !== preg_match( '/^[a-z-]+$/', $lang . $key ) ) {
			return '';
		}

		return self::read( 'content/legal/' . $lang . '/' . $key . '.html' );
	}

	/**
	 * Decoded JSON file from content/.
	 *
	 * @param string $name File name without extension.
	 * @return array<string, mixed>
	 */
	private static function json( string $name ): array {
		if ( ! isset( self::$cache[ $name ] ) ) {
			$data                 = json_decode( self::read( 'content/' . $name . '.json' ), true );
			self::$cache[ $name ] = is_array( $data ) ? $data : array();
		}

		return self::$cache[ $name ];
	}

	/**
	 * Contents of a theme file, '' when it cannot be read.
	 *
	 * @param string $relative Path relative to the theme root.
	 */
	private static function read( string $relative ): string {
		$file = Theme::path( $relative );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}
}
