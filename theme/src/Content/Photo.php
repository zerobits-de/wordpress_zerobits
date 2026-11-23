<?php
/**
 * Theme photos.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Content;

use ZB\Theme\Theme;

/**
 * A photo from assets/ as the array views/components/image.twig expects, with
 * its real width and height (so the browser reserves the space, no layout shift).
 */
final class Photo {

	/**
	 * Image sizes keyed by path.
	 *
	 * @var array<string, array{0: int, 1: int}>
	 */
	private static array $sizes = array();

	/**
	 * Photo data, or null when the path is empty or not a file in assets/.
	 *
	 * @param string $path Path relative to assets/, e.g. "images/photos/hero.jpg".
	 * @param string $alt  Alternative text.
	 * @return array{src: string, alt: string, width: int, height: int}|null
	 */
	public static function make( string $path, string $alt ): ?array {
		$path = ltrim( $path, '/' );

		if ( '' === $path || str_contains( $path, '..' ) ) {
			return null;
		}

		$file = Theme::path( 'assets/' . $path );

		if ( ! isset( self::$sizes[ $path ] ) ) {
			self::$sizes[ $path ] = is_readable( $file ) ? self::size( $file ) : array( 0, 0 );
		}

		if ( 0 === self::$sizes[ $path ][0] ) {
			return null;
		}

		return array(
			'src'    => self::url( $path ),
			'alt'    => $alt,
			'width'  => self::$sizes[ $path ][0],
			'height' => self::$sizes[ $path ][1],
		);
	}

	/**
	 * Versioned URL of a file in assets/, '' when the path is empty. For files
	 * without pixel dimensions (SVG), which make() cannot measure.
	 *
	 * @param string $path Path relative to assets/.
	 */
	public static function url( string $path ): string {
		$path = ltrim( $path, '/' );

		if ( '' === $path || str_contains( $path, '..' ) ) {
			return '';
		}

		return Theme::url( 'assets/' . $path ) . '?v=' . Theme::asset_version( 'assets/' . $path );
	}

	/**
	 * Pixel size of an image file; for SVG the size of its viewBox.
	 *
	 * @param string $file Absolute path.
	 * @return array{0: int, 1: int}
	 */
	private static function size( string $file ): array {
		if ( str_ends_with( strtolower( $file ), '.svg' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
			$head = (string) file_get_contents( $file, false, null, 0, 1024 );

			if ( 1 === preg_match( '/viewBox="[\d.]+[ ,]+[\d.]+[ ,]+([\d.]+)[ ,]+([\d.]+)"/', $head, $box ) ) {
				return array( (int) round( (float) $box[1] ), (int) round( (float) $box[2] ) );
			}

			return array( 0, 0 );
		}

		$size = getimagesize( $file );

		return false !== $size ? array( (int) $size[0], (int) $size[1] ) : array( 0, 0 );
	}
}
