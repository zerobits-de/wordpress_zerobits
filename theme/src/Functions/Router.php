<?php
/**
 * Language-aware URLs.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Content\Store;
use ZB\Theme\Controllers\AboutController;
use ZB\Theme\Controllers\AbstractController;
use ZB\Theme\Controllers\BlogController;
use ZB\Theme\Controllers\HomeController;
use ZB\Theme\Controllers\LegalController;
use ZB\Theme\Controllers\NotFoundController;
use ZB\Theme\Controllers\PageController;
use ZB\Theme\Controllers\PostController;
use ZB\Theme\Controllers\ProjectController;
use ZB\Theme\Controllers\ServiceController;
use ZB\Theme\I18n\Languages;
use ZB\Theme\Registrable;
use ZB\Theme\Routing\Request;
use ZB\Theme\Routing\Urls;
use WP_Post;
use WP_Query;

/**
 * URL scheme (see Routing\Request for the list of routes):
 *
 *   Rewrite rules  -> query vars zb_route, zb_lang, zb_page
 *   pre_get_posts  -> blog list shows the articles of one language
 *   template_redirect -> 302 from / to the homepage in the visitor's language
 *                        (language switch cookie, else the browser's language);
 *                        301 to the canonical URL (article under the wrong
 *                        language, /slug/ of WordPress' own rules, archives,
 *                        the former services, portfolio and Odoo pages, addresses
 *                        of the former Elementor site)
 *   template_include  -> always index.php, which renders Router::controller()
 *
 * The rules are flushed automatically when they change (new language, a new
 * service, a new project or a new slug in content/{lang}.json), so a deployment needs
 * no manual step.
 */
final class Router implements Registrable {

	public const POSTS_PER_PAGE = 9;

	/**
	 * Former pages that only redirect: route name = key of the slug in content/{lang}.json.
	 * /services/ and /portfolio/ lead to their sections of the homepage,
	 * /odoo-partner/ to the Odoo section on the page of a service.
	 */
	public const PAGES = array( Request::SERVICES, Request::PORTFOLIO, Request::ODOO );

	/**
	 * Addresses of the former site that have no page of their own any more:
	 * path (regular expression, without language prefix) => section of the homepage.
	 */
	private const LEGACY_SECTIONS = array(
		'portfolio'             => 'portfolio',
		'portfolio-details'     => 'portfolio',
		'projects(?:/.*)?'      => 'portfolio',
		'home-software-company' => '',
	);

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'main_query' ) );
		add_filter( 'redirect_canonical', array( $this, 'redirect_canonical' ) );
		add_action( 'template_redirect', array( $this, 'redirect' ), 5 );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
	}

	/**
	 * Rewrite rules for every language, flushed when they change.
	 */
	public function add_rules(): void {
		$rules = self::rules();

		foreach ( $rules as $regex => $query ) {
			add_rewrite_rule( $regex, $query, 'top' );
		}

		$hash = md5( (string) wp_json_encode( $rules ) );

		if ( get_option( 'zb_rewrite_rules' ) !== $hash ) {
			flush_rewrite_rules( false );
			update_option( 'zb_rewrite_rules', $hash, true );
		}
	}

	/**
	 * The theme's rewrite rules, more specific ones first.
	 *
	 * @return array<string, string>
	 */
	public static function rules(): array {
		$rules = array();
		$blog  = Urls::BLOG_BASE;

		foreach ( Languages::all() as $lang ) {
			$prefix = Languages::prefix( $lang );
			$vars   = 'zb_lang=' . $lang;

			if ( '' !== $prefix ) {
				$rules[ '^' . $lang . '/?$' ] = 'index.php?zb_route=home&' . $vars;
			}

			$rules[ '^' . $prefix . $blog . '/page/([0-9]+)/?$' ] = 'index.php?zb_route=blog&' . $vars . '&paged=$matches[1]';
			$rules[ '^' . $prefix . $blog . '/?$' ]               = 'index.php?zb_route=blog&' . $vars;
			$rules[ '^' . $prefix . $blog . '/([^/]+)/?$' ]       = 'index.php?zb_route=post&' . $vars . '&name=$matches[1]';

			foreach ( self::PAGES as $page ) {
				$slug = Urls::page_slug( $lang, $page );

				if ( '' !== $slug ) {
					$rules[ '^' . $prefix . $slug . '/?$' ] = 'index.php?zb_route=' . $page . '&' . $vars;
				}
			}

			$services = Urls::page_slug( $lang, Request::SERVICES );

			foreach ( '' !== $services ? self::service_ids() : array() as $id ) {
				$slug = Urls::service_slug( $lang, $id );

				if ( '' !== $slug ) {
					$rules[ '^' . $prefix . $services . '/' . $slug . '/?$' ] = 'index.php?zb_route=service&' . $vars . '&zb_page=' . $id;
				}
			}

			$portfolio = Urls::page_slug( $lang, Request::PORTFOLIO );

			foreach ( '' !== $portfolio ? self::project_ids() : array() as $id ) {
				$slug = Urls::project_slug( $lang, $id );

				if ( '' !== $slug ) {
					$rules[ '^' . $prefix . $portfolio . '/' . $slug . '/?$' ] = 'index.php?zb_route=project&' . $vars . '&zb_page=' . $id;
				}
			}

			$about = Urls::about_slug( $lang );

			if ( '' !== $about ) {
				$rules[ '^' . $prefix . $about . '/?$' ] = 'index.php?zb_route=about&' . $vars;
			}

			foreach ( self::legal_keys() as $key ) {
				$slug = Urls::legal_slug( $lang, $key );

				if ( '' !== $slug ) {
					$rules[ '^' . $prefix . $slug . '/?$' ] = 'index.php?zb_route=legal&' . $vars . '&zb_page=' . $key;
				}
			}
		}

		return $rules;
	}

	/**
	 * Keys of the legal pages (content/site.json "legal").
	 *
	 * @return array<int, string>
	 */
	public static function legal_keys(): array {
		$keys = Store::site()['legal'] ?? array();

		return is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : array();
	}

	/**
	 * Ids of the services, each with a page of its own (content/site.json "services").
	 *
	 * @return array<int, string>
	 */
	public static function service_ids(): array {
		return self::ids( 'services' );
	}

	/**
	 * Ids of the projects, each with a page of its own (content/site.json "portfolio").
	 *
	 * @return array<int, string>
	 */
	public static function project_ids(): array {
		return self::ids( 'portfolio' );
	}

	/**
	 * Ids of a list in content/site.json.
	 *
	 * @param string $key Key of the list.
	 * @return array<int, string>
	 */
	private static function ids( string $key ): array {
		$ids = array();

		foreach ( (array) ( Store::site()[ $key ] ?? array() ) as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) && is_string( $item['id'] ) && '' !== $item['id'] ) {
				$ids[] = $item['id'];
			}
		}

		return $ids;
	}

	/**
	 * Id of the service whose page shows the Odoo partner section (`"odoo": true`
	 * in content/site.json), '' when there is none.
	 */
	public static function odoo_service_id(): string {
		foreach ( (array) ( Store::site()['services'] ?? array() ) as $service ) {
			if ( is_array( $service ) && true === ( $service['odoo'] ?? false ) && is_string( $service['id'] ?? null ) ) {
				return $service['id'];
			}
		}

		return '';
	}

	/**
	 * Public query vars.
	 *
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'zb_route', 'zb_lang', 'zb_page' ) );
	}

	/**
	 * Main query: the blog list shows one language; routes that render repo
	 * content need no posts.
	 *
	 * @param WP_Query $query Query.
	 */
	public function main_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$route = (string) $query->get( 'zb_route' );

		if ( Request::BLOG === $route ) {
			$query->set( 'post_type', 'post' );
			$query->set( 'posts_per_page', self::POSTS_PER_PAGE );
			$query->set( 'ignore_sticky_posts', true );
			$query->set( 'tax_query', self::language_query( Languages::sanitize( (string) $query->get( 'zb_lang' ) ) ) );
			return;
		}

		if ( Request::POST === $route ) {
			$query->set( 'post_type', 'post' );
			return;
		}

		if ( in_array( $route, array( Request::HOME, Request::ABOUT, Request::LEGAL, Request::SERVICE, Request::PROJECT, ...self::PAGES ), true ) || ( '' === $route && $query->is_home() ) ) {
			$query->set( 'posts_per_page', 1 );
			$query->set( 'no_found_rows', true );
		}
	}

	/**
	 * Tax query for the articles of one language. Articles without a language
	 * count as the default language.
	 *
	 * @param string $lang Language code.
	 * @return array<int|string, mixed>
	 */
	public static function language_query( string $lang ): array {
		$query = array(
			array(
				'taxonomy' => BlogLanguage::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( $lang ),
			),
		);

		if ( Languages::default() === $lang ) {
			$query = array(
				'relation' => 'OR',
				$query[0],
				array(
					'taxonomy' => BlogLanguage::TAXONOMY,
					'operator' => 'NOT EXISTS',
				),
			);
		}

		return $query;
	}

	/**
	 * WordPress' canonical redirect does not know the theme's URLs.
	 *
	 * @param string|false $url Redirect target.
	 * @return string|false
	 */
	public function redirect_canonical( $url ) {
		if ( '' !== (string) get_query_var( 'zb_route' ) || is_singular( 'post' ) ) {
			return false;
		}

		return $url;
	}

	/**
	 * 301 to the canonical URL where WordPress would otherwise serve the same
	 * content under a second address.
	 */
	public function redirect(): void {
		$request = Request::current();

		if ( Request::POST === $request->route ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post && ! is_preview() ) {
				$this->redirect_if_different( Urls::post( $post ) );
			}

			return;
		}

		// Archives and search are not part of the site: send them to the blog.
		if ( is_category() || is_tag() || is_author() || is_date() || is_search() ) {
			wp_safe_redirect( Urls::blog( Languages::default() ), 301 );
			exit;
		}

		// The services, the portfolio and the Odoo partner page are sections now.
		if ( Request::SERVICES === $request->route || Request::PORTFOLIO === $request->route ) {
			wp_safe_redirect( Urls::section( $request->lang, $request->route ), 301 );
			exit;
		}

		if ( Request::ODOO === $request->route ) {
			wp_safe_redirect( Urls::odoo( $request->lang ), 301 );
			exit;
		}

		// Former site: pages left in the database must not shadow the new URLs.
		if ( in_array( $request->route, array( Request::ERROR, Request::PAGE ), true ) && ! is_preview() ) {
			$this->redirect_legacy();
		}

		// A route the rules cannot produce (an unknown service or project id in the query string).
		if ( Request::ERROR === $request->route && ! is_404() ) {
			$GLOBALS['wp_query']->set_404();
			status_header( 404 );
			nocache_headers();
		}

		if ( Request::HOME === $request->route && is_paged() ) {
			wp_safe_redirect( Urls::home( $request->lang ), 301 );
			exit;
		}

		if ( Request::HOME === $request->route && Languages::default() === $request->lang ) {
			$this->redirect_to_visitor_language();
		}
	}

	/**
	 * The homepage without a language prefix opens in the visitor's language:
	 * the one chosen in the language switch (cookie), otherwise the one the
	 * browser prefers. Only this address negotiates, and only for visitors who
	 * arrive from elsewhere; every other URL shows the language it names. Crawlers send no Accept-Language header and get the
	 * default language.
	 */
	private function redirect_to_visitor_language(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'get';
		$query  = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';

		// Addresses with parameters (previews, campaign links) stay as they are.
		if ( ! in_array( $method, array( 'get', 'head' ), true ) || '' !== $query || is_preview() || is_customize_preview() ) {
			return;
		}

		// The answer depends on these headers: shared caches must not mix visitors up.
		header( 'Vary: Accept-Language, Cookie, Referer', false );

		// A link within the site (language switch, logo) is a choice already: only arrivals negotiate.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';

		if ( '' !== $referer && (string) wp_parse_url( home_url(), PHP_URL_HOST ) === $referer ) {
			return;
		}

		$chosen = isset( $_COOKIE[ Languages::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ Languages::COOKIE ] ) ) : '';
		$accept = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		$lang   = Languages::is_supported( $chosen ) ? $chosen : Languages::preferred( $accept );

		if ( Languages::default() !== $lang ) {
			nocache_headers();
			wp_safe_redirect( Urls::home( $lang ), 302 );
			exit;
		}
	}

	/**
	 * Addresses of the former Elementor/TranslatePress site:
	 *
	 *   /de/{article}/           articles had no /blog/ segment and answered under both languages
	 *   /impressum/, /de/impress/  legal pages answered under both language prefixes
	 *   /ueber-uns/              the about us page answered without the language prefix
	 *   /projects/…              pages that are sections of the homepage now
	 */
	private function redirect_legacy(): void {
		$path = Request::path();
		$lang = Languages::from_path( $path );

		if ( $path === $lang || str_starts_with( $path, $lang . '/' ) ) {
			$path     = trim( substr( $path, strlen( $lang ) ), '/' );
			$explicit = true;
		} else {
			$explicit = false;
		}

		if ( '' === $path ) {
			return;
		}

		foreach ( self::LEGACY_SECTIONS as $pattern => $anchor ) {
			if ( 1 === preg_match( '#^' . $pattern . '$#', $path ) ) {
				wp_safe_redirect( '' !== $anchor ? Urls::section( $lang, $anchor ) : Urls::home( $lang ), 301 );
				exit;
			}
		}

		// A page or legal slug of another language: /impressum/ -> /de/impressum/, /de/impress/ -> /de/impressum/.
		foreach ( Languages::all() as $other ) {
			$target = $explicit ? $lang : $other;

			if ( Urls::about_slug( $other ) === $path ) {
				wp_safe_redirect( Urls::about( $target ), 301 );
				exit;
			}

			foreach ( self::legal_keys() as $key ) {
				if ( Urls::legal_slug( $other, $key ) === $path ) {
					wp_safe_redirect( Urls::legal( $target, $key ), 301 );
					exit;
				}
			}

			foreach ( self::PAGES as $page ) {
				if ( Urls::page_slug( $other, $page ) === $path ) {
					wp_safe_redirect( Urls::page( $target, $page ), 301 );
					exit;
				}
			}
		}

		// An article under a language prefix without /blog/.
		if ( ! str_contains( $path, '/' ) ) {
			$post = get_page_by_path( sanitize_title( $path ), OBJECT, 'post' );

			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				wp_safe_redirect( Urls::post( $post ), 301 );
				exit;
			}
		}
	}

	/**
	 * Redirect when the requested path differs from the canonical one.
	 *
	 * @param string $canonical Canonical URL.
	 */
	private function redirect_if_different( string $canonical ): void {
		$uri       = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$requested = trailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		$expected  = trailingslashit( (string) wp_parse_url( $canonical, PHP_URL_PATH ) );

		if ( $requested !== $expected ) {
			wp_safe_redirect( $canonical, 301 );
			exit;
		}
	}

	/**
	 * Every front-end request renders through index.php.
	 *
	 * @param string $template Template WordPress picked.
	 */
	public function template( string $template ): string {
		if ( is_feed() || is_embed() || is_robots() || is_favicon() ) {
			return $template;
		}

		$index = get_template_directory() . '/index.php';

		return is_readable( $index ) ? $index : $template;
	}

	/**
	 * Controller for the current request.
	 */
	public static function controller(): AbstractController {
		switch ( Request::current()->route ) {
			case Request::HOME:
				return new HomeController();
			case Request::SERVICE:
				return new ServiceController();
			case Request::PROJECT:
				return new ProjectController();
			case Request::ABOUT:
				return new AboutController();
			case Request::BLOG:
				return new BlogController();
			case Request::POST:
				return new PostController();
			case Request::LEGAL:
				return new LegalController();
			case Request::PAGE:
				return new PageController();
			default:
				return new NotFoundController();
		}
	}

	/**
	 * Article permalinks follow the article's language.
	 *
	 * @param string  $url  Permalink.
	 * @param WP_Post $post Post.
	 */
	public function post_link( string $url, WP_Post $post ): string {
		if ( 'post' !== $post->post_type || in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true ) ) {
			return $url;
		}

		return Urls::post( $post );
	}
}
