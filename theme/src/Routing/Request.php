<?php
/**
 * The current front-end request.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Routing;

use ZB\Theme\Functions\BlogLanguage;
use ZB\Theme\Functions\Router;
use ZB\Theme\I18n\Languages;
use WP_Post;

/**
 * Route and language of the current request, resolved from the query vars the
 * rewrite rules in Functions\Router set (zb_route, zb_lang, zb_page).
 *
 * Routes:
 *   home     /                /de/
 *   service  /services/cloud/ /de/services/cloud/ one service, slugs from content/{lang}.json
 *                                                 "services.slug" and "services.items.{id}.slug"
 *   services /services/       /de/services/       no page: redirects to the section of the homepage
 *   odoo     /odoo-partner/   /de/odoo-partner/   no page: redirects to the service with the Odoo section
 *   project  /portfolio/linguu/ /de/portfolio/linguu/ one project, slugs from content/{lang}.json
 *                                                 "portfolio.slug" and "portfolio.items.{id}.slug"
 *   portfolio /portfolio/     /de/portfolio/      no page: redirects to the section of the homepage
 *   about    /about-us/       /de/ueber-uns/      slug from content/{lang}.json "story.slug"
 *   blog     /blog/           /de/blog/           (+ /page/N/)
 *   post     /blog/slug/      /de/blog/slug/      language = the article's language
 *   legal    /impress/        /de/impressum/      slugs from content/{lang}.json
 *   page     any WordPress page (rendered generically)
 *   404      everything else
 */
final class Request {

	public const HOME      = 'home';
	public const SERVICE   = 'service';
	public const SERVICES  = 'services';
	public const ODOO      = 'odoo';
	public const PROJECT   = 'project';
	public const PORTFOLIO = 'portfolio';
	public const ABOUT     = 'about';
	public const BLOG      = 'blog';
	public const POST      = 'post';
	public const LEGAL     = 'legal';
	public const PAGE      = 'page';
	public const ERROR     = '404';

	/**
	 * Resolved request, cached once the main query has run.
	 *
	 * @var Request|null
	 */
	private static ?Request $current = null;

	/**
	 * Constructor.
	 *
	 * @param string $route Route name (see constants).
	 * @param string $lang  Language code.
	 * @param string $page  Legal page key, service id or project id, '' for other routes.
	 * @param int    $paged Page number of the blog list.
	 */
	public function __construct(
		public readonly string $route,
		public readonly string $lang,
		public readonly string $page = '',
		public readonly int $paged = 1
	) {}

	/**
	 * The current request.
	 */
	public static function current(): Request {
		if ( null !== self::$current ) {
			return self::$current;
		}

		$request = self::resolve();

		// Before the main query, conditionals are not reliable yet: do not cache.
		if ( did_action( 'wp' ) ) {
			self::$current = $request;
		}

		return $request;
	}

	/**
	 * Resolve route and language from the main query.
	 */
	private static function resolve(): Request {
		$route = (string) get_query_var( 'zb_route' );
		$lang  = Languages::sanitize( (string) get_query_var( 'zb_lang' ) );
		$paged = max( 1, (int) get_query_var( 'paged' ) );

		if ( is_404() ) {
			return new self( self::ERROR, self::path_language() );
		}

		if ( is_singular( 'post' ) ) {
			$post = get_queried_object();

			return new self( self::POST, $post instanceof WP_Post ? BlogLanguage::of( $post->ID ) : $lang );
		}

		if ( self::LEGAL === $route ) {
			return new self( self::LEGAL, $lang, sanitize_key( (string) get_query_var( 'zb_page' ) ) );
		}

		if ( self::SERVICE === $route ) {
			$id = sanitize_key( (string) get_query_var( 'zb_page' ) );

			return in_array( $id, Router::service_ids(), true ) ? new self( self::SERVICE, $lang, $id ) : new self( self::ERROR, $lang );
		}

		if ( self::PROJECT === $route ) {
			$id = sanitize_key( (string) get_query_var( 'zb_page' ) );

			return in_array( $id, Router::project_ids(), true ) ? new self( self::PROJECT, $lang, $id ) : new self( self::ERROR, $lang );
		}

		if ( in_array( $route, Router::PAGES, true ) ) {
			return new self( $route, $lang );
		}

		if ( self::ABOUT === $route ) {
			return new self( self::ABOUT, $lang );
		}

		if ( self::BLOG === $route ) {
			return new self( self::BLOG, $lang, '', $paged );
		}

		if ( self::HOME === $route || ( '' === $route && is_front_page() ) ) {
			return new self( self::HOME, $lang );
		}

		if ( is_page() ) {
			return new self( self::PAGE, self::path_language() );
		}

		return new self( self::ERROR, self::path_language() );
	}

	/**
	 * Request path without the home path and without slashes at either end.
	 */
	public static function path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' !== $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Language from the request path (for pages and 404s).
	 */
	private static function path_language(): string {
		return Languages::from_path( self::path() );
	}
}
