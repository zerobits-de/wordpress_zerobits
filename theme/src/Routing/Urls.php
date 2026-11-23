<?php
/**
 * URLs of the site.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Routing;

use ZB\Theme\Content\Store;
use ZB\Theme\Functions\BlogLanguage;
use ZB\Theme\Functions\Router;
use ZB\Theme\I18n\Languages;
use WP_Post;

/**
 * Builds every internal URL, so the URL scheme is defined in one place
 * (together with the rewrite rules in Functions\Router).
 */
final class Urls {

	public const BLOG_BASE = 'blog';

	/**
	 * Homepage of a language.
	 *
	 * @param string $lang Language code.
	 */
	public static function home( string $lang ): string {
		return home_url( '/' . Languages::prefix( $lang ) );
	}

	/**
	 * Section anchor on the homepage of a language.
	 *
	 * @param string $lang   Language code.
	 * @param string $anchor Section id.
	 */
	public static function section( string $lang, string $anchor ): string {
		return self::home( $lang ) . '#' . $anchor;
	}

	/**
	 * Blog list of a language.
	 *
	 * @param string $lang  Language code.
	 * @param int    $paged Page number.
	 */
	public static function blog( string $lang, int $paged = 1 ): string {
		$path = Languages::prefix( $lang ) . self::BLOG_BASE . '/';

		if ( $paged > 1 ) {
			$path .= 'page/' . $paged . '/';
		}

		return home_url( '/' . $path );
	}

	/**
	 * Article URL: /blog/{slug}/ or /{lang}/blog/{slug}/, from the article's language.
	 *
	 * @param WP_Post $post Blog article.
	 */
	public static function post( WP_Post $post ): string {
		$slug = '' !== $post->post_name ? $post->post_name : sanitize_title( $post->post_title );

		return home_url( '/' . Languages::prefix( BlogLanguage::of( $post->ID ) ) . self::BLOG_BASE . '/' . $slug . '/' );
	}

	/**
	 * Former page of a language: /{slug}/ or /{lang}/{slug}/, slug from
	 * content/{lang}.json "{key}.slug" (keys: services, portfolio, odoo). These
	 * addresses only redirect (Functions\Router::redirect()); "services.slug" and
	 * "portfolio.slug" are also the first segment of the service and project pages.
	 *
	 * @param string $lang Language code.
	 * @param string $key  Page key.
	 */
	public static function page( string $lang, string $key ): string {
		$slug = self::page_slug( $lang, $key );

		return '' !== $slug ? home_url( '/' . Languages::prefix( $lang ) . $slug . '/' ) : self::home( $lang );
	}

	/**
	 * Slug of a former page in a language.
	 *
	 * @param string $lang Language code.
	 * @param string $key  Page key (services, portfolio, odoo).
	 */
	public static function page_slug( string $lang, string $key ): string {
		return sanitize_title( Store::text( $lang, $key . '.slug' ) );
	}

	/**
	 * Page of one service: /services/{slug}/ or /{lang}/services/{slug}/, slugs
	 * from content/{lang}.json ("services.slug" and "services.items.{id}.slug").
	 *
	 * @param string $lang Language code.
	 * @param string $id   Service id from site.json "services".
	 */
	public static function service( string $lang, string $id ): string {
		$base = self::page_slug( $lang, Request::SERVICES );
		$slug = self::service_slug( $lang, $id );

		if ( '' === $base || '' === $slug ) {
			return self::section( $lang, 'services' );
		}

		return home_url( '/' . Languages::prefix( $lang ) . $base . '/' . $slug . '/' );
	}

	/**
	 * Slug of one service in a language.
	 *
	 * @param string $lang Language code.
	 * @param string $id   Service id.
	 */
	public static function service_slug( string $lang, string $id ): string {
		return sanitize_title( Store::text( $lang, 'services.items.' . sanitize_key( $id ) . '.slug' ) );
	}

	/**
	 * Page of one project: /portfolio/{slug}/ or /{lang}/portfolio/{slug}/, slugs
	 * from content/{lang}.json ("portfolio.slug" and "portfolio.items.{id}.slug").
	 *
	 * @param string $lang Language code.
	 * @param string $id   Project id from site.json "portfolio".
	 */
	public static function project( string $lang, string $id ): string {
		$base = self::page_slug( $lang, Request::PORTFOLIO );
		$slug = self::project_slug( $lang, $id );

		if ( '' === $base || '' === $slug ) {
			return self::section( $lang, 'portfolio' );
		}

		return home_url( '/' . Languages::prefix( $lang ) . $base . '/' . $slug . '/' );
	}

	/**
	 * Slug of one project in a language.
	 *
	 * @param string $lang Language code.
	 * @param string $id   Project id.
	 */
	public static function project_slug( string $lang, string $id ): string {
		return sanitize_title( Store::text( $lang, 'portfolio.items.' . sanitize_key( $id ) . '.slug' ) );
	}

	/**
	 * The Odoo partner section: an anchor on the page of the service that has
	 * `"odoo": true` in site.json.
	 *
	 * @param string $lang Language code.
	 */
	public static function odoo( string $lang ): string {
		$id = Router::odoo_service_id();

		return '' !== $id ? self::service( $lang, $id ) . '#odoo' : self::section( $lang, 'services' );
	}

	/**
	 * Legal page of a language; the slug comes from content/{lang}.json.
	 *
	 * @param string $lang Language code.
	 * @param string $key  Legal page key.
	 */
	public static function legal( string $lang, string $key ): string {
		$slug = self::legal_slug( $lang, $key );

		return '' !== $slug ? home_url( '/' . Languages::prefix( $lang ) . $slug . '/' ) : self::home( $lang );
	}

	/**
	 * Slug of a legal page in a language.
	 *
	 * @param string $lang Language code.
	 * @param string $key  Legal page key.
	 */
	public static function legal_slug( string $lang, string $key ): string {
		return sanitize_title( Store::text( $lang, 'legal.' . $key . '.slug' ) );
	}

	/**
	 * The current page in another language, for the language switch and hreflang.
	 * An article without a translation leads to the blog of the other language.
	 *
	 * @param Request $request Current request.
	 * @param string  $lang    Target language.
	 */
	public static function alternate( Request $request, string $lang ): string {
		switch ( $request->route ) {
			case Request::BLOG:
				return self::blog( $lang, $lang === $request->lang ? $request->paged : 1 );
			case Request::POST:
				$post = get_queried_object();

				return $post instanceof WP_Post && $lang === $request->lang ? self::post( $post ) : self::blog( $lang );
			case Request::LEGAL:
				return self::legal( $lang, $request->page );
			case Request::SERVICE:
				return self::service( $lang, $request->page );
			case Request::PROJECT:
				return self::project( $lang, $request->page );
			case Request::SERVICES:
			case Request::PORTFOLIO:
				return self::section( $lang, $request->route );
			case Request::ODOO:
				return self::odoo( $lang );
			default:
				return self::home( $lang );
		}
	}

	/**
	 * Whether alternate() leads to the same page in another language (home, a service,
	 * a project, legal pages, first blog page) rather than to a fallback such as the blog
	 * of the other language.
	 *
	 * @param Request $request Current request.
	 */
	public static function has_equivalent( Request $request ): bool {
		switch ( $request->route ) {
			case Request::HOME:
			case Request::SERVICE:
			case Request::PROJECT:
			case Request::LEGAL:
				return true;
			case Request::BLOG:
				return $request->paged <= 1;
			default:
				return false;
		}
	}

	/**
	 * Canonical URL of the current request, '' when there is none (404).
	 *
	 * @param Request $request Current request.
	 */
	public static function canonical( Request $request ): string {
		switch ( $request->route ) {
			case Request::ERROR:
				return '';
			case Request::PAGE:
				$page = get_queried_object();

				return $page instanceof WP_Post ? (string) get_permalink( $page ) : '';
			default:
				return self::alternate( $request, $request->lang );
		}
	}
}
