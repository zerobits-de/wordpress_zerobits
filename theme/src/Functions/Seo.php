<?php
/**
 * Titles, meta data and structured data.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Content\Store;
use ZB\Theme\I18n\Languages;
use ZB\Theme\Registrable;
use ZB\Theme\Routing\Request;
use ZB\Theme\Routing\Urls;
use ZB\Theme\Theme;
use WP_Post;

/**
 * - <html lang> and the document title per language and route
 * - meta description, canonical, hreflang alternates (pages that exist in every language)
 * - Open Graph tags
 * - Organization JSON-LD on the homepage
 *
 * Texts come from content/{lang}.json, articles use their title and excerpt.
 */
final class Seo implements Registrable {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'language_attributes', array( $this, 'language_attributes' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_action( 'wp_head', array( $this, 'meta' ), 3 );

		// The theme prints its own canonical (WordPress only does for singular views).
		remove_action( 'wp_head', 'rel_canonical' );
	}

	/**
	 * Language of the page, not of the WordPress installation.
	 */
	public function language_attributes(): string {
		if ( is_admin() ) {
			return 'lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"';
		}

		return 'lang="' . esc_attr( Store::text( Request::current()->lang, 'lang.html' ) ) . '"';
	}

	/**
	 * Document title.
	 *
	 * @param string $title Title WordPress would use.
	 */
	public function title( string $title ): string {
		if ( is_admin() ) {
			return $title;
		}

		$request = Request::current();
		$lang    = $request->lang;
		$brand   = $this->brand();

		switch ( $request->route ) {
			case Request::HOME:
				return Store::text( $lang, 'meta.title' );
			case Request::SERVICE:
				return Store::text( $lang, 'services.items.' . $request->page . '.meta_title' ) . ' | ' . $brand;
			case Request::PROJECT:
				return Store::text( $lang, 'portfolio.items.' . $request->page . '.meta_title' ) . ' | ' . $brand;
			case Request::BLOG:
				$page = $request->paged > 1 ? ' - ' . strtr(
					Store::text( $lang, 'blog.page' ),
					array(
						'{n}'     => (string) $request->paged,
						'{total}' => (string) max( 1, (int) $GLOBALS['wp_query']->max_num_pages ),
					)
				) : '';

				return Store::text( $lang, 'blog.meta_title' ) . $page . ' | ' . $brand;
			case Request::POST:
			case Request::PAGE:
				return wp_strip_all_tags( single_post_title( '', false ) ) . ' | ' . $brand;
			case Request::LEGAL:
				return Store::text( $lang, 'legal.' . $request->page . '.title' ) . ' | ' . $brand;
			default:
				return Store::text( $lang, 'not_found.title' ) . ' | ' . $brand;
		}
	}

	/**
	 * Description, canonical, alternates, Open Graph, JSON-LD.
	 */
	public function meta(): void {
		$request     = Request::current();
		$description = $this->description( $request );
		$canonical   = Urls::canonical( $request );

		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}

		if ( '' === $canonical ) {
			echo '<meta name="robots" content="noindex">' . "\n";
			return;
		}

		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );

		// Alternates only where the page exists in every language.
		if ( Urls::has_equivalent( $request ) ) {
			foreach ( Languages::all() as $lang ) {
				printf(
					'<link rel="alternate" hreflang="%s" href="%s">' . "\n",
					esc_attr( $lang ),
					esc_url( Urls::alternate( $request, $lang ) )
				);
			}

			printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( Urls::alternate( $request, Languages::default() ) ) );
		}

		$this->open_graph( $request, $canonical, $description );

		if ( Request::HOME === $request->route ) {
			$this->organization( $request->lang );
		}
	}

	/**
	 * Meta description of the current page.
	 *
	 * @param Request $request Current request.
	 */
	private function description( Request $request ): string {
		switch ( $request->route ) {
			case Request::POST:
				$post = get_queried_object();

				if ( ! $post instanceof WP_Post ) {
					return '';
				}

				$text = '' !== $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );

				return trim( wp_strip_all_tags( $text ) );
			case Request::BLOG:
				return Store::text( $request->lang, 'blog.intro' );
			case Request::SERVICE:
				return Store::text( $request->lang, 'services.items.' . $request->page . '.meta_description' );
			case Request::PROJECT:
				return Store::text( $request->lang, 'portfolio.items.' . $request->page . '.meta_description' );
			case Request::HOME:
				return Store::text( $request->lang, 'meta.description' );
			default:
				return '';
		}
	}

	/**
	 * Open Graph tags.
	 *
	 * @param Request $request     Current request.
	 * @param string  $url         Canonical URL.
	 * @param string  $description Description.
	 */
	private function open_graph( Request $request, string $url, string $description ): void {
		$site  = Store::site();
		$image = Theme::url( 'assets/images/favicon.png' );
		$type  = Request::POST === $request->route ? 'article' : 'website';

		if ( Request::POST === $request->route && has_post_thumbnail() ) {
			$image = (string) get_the_post_thumbnail_url( null, 'large' );
		}

		if ( Request::SERVICE === $request->route && Router::odoo_service_id() === $request->page ) {
			$image = Theme::url( 'assets/' . (string) Store::get( $site, 'odoo.logo' ) );
		}

		if ( Request::PROJECT === $request->route ) {
			foreach ( (array) ( $site['portfolio'] ?? array() ) as $project ) {
				if ( is_array( $project ) && ( $project['id'] ?? '' ) === $request->page && is_string( $project['images'][0] ?? null ) ) {
					$image = Theme::url( 'assets/' . $project['images'][0] );
				}
			}
		}

		$tags = array(
			'og:type'        => $type,
			'og:site_name'   => $this->brand(),
			'og:title'       => wp_get_document_title(),
			'og:description' => $description,
			'og:url'         => $url,
			'og:image'       => $image,
			'og:locale'      => Store::text( $request->lang, 'lang.og_locale' ),
		);

		foreach ( $tags as $property => $content ) {
			if ( '' !== $content ) {
				printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
			}
		}

		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	/**
	 * Organization with its address and profiles.
	 *
	 * @param string $lang Language code.
	 */
	private function organization( string $lang ): void {
		$site    = Store::site();
		$same_as = array();

		foreach ( (array) ( $site['social'] ?? array() ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['url'] ) && 'whatsapp' !== ( $entry['id'] ?? '' ) ) {
				$same_as[] = (string) $entry['url'];
			}
		}

		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Organization',
			'name'        => $this->brand(),
			'url'         => Urls::home( Languages::default() ),
			'logo'        => Theme::url( 'assets/images/favicon.png' ),
			'email'       => (string) Store::get( $site, 'contact.email' ),
			'telephone'   => (string) Store::get( $site, 'contact.phone' ),
			'description' => Store::text( $lang, 'meta.description' ),
			'address'     => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => (string) Store::get( $site, 'contact.street' ),
				'postalCode'      => (string) Store::get( $site, 'contact.postal_code' ),
				'addressLocality' => (string) Store::get( $site, 'contact.city' ),
				'addressCountry'  => (string) Store::get( $site, 'contact.country_code' ),
			),
			'sameAs'      => $same_as,
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
		);
	}

	/**
	 * Brand name.
	 */
	private function brand(): string {
		$name = Store::get( Store::site(), 'brand.name' );

		return is_string( $name ) && '' !== $name ? $name : get_bloginfo( 'name' );
	}
}
