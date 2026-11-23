<?php
/**
 * Global Timber context.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Content\Photo;
use ZB\Theme\Content\Store;
use ZB\Theme\I18n\Languages;
use ZB\Theme\Registrable;
use ZB\Theme\Routing\Request;
use ZB\Theme\Routing\Urls;

/**
 * Adds what every template needs:
 *
 * - request     {route, lang}
 * - t           every text of the current language (content/{lang}.json)
 * - brand       name, logo, contact data, social links, source repository (content/site.json)
 * - home_url    homepage of the current language
 * - blog_url    blog of the current language
 * - contact_url contact section (an anchor on the homepage itself)
 * - navigation  [{title, url, current}]
 * - languages   [{code, name, short, url, current}] for the language switch
 * - legal_links [{title, url}]
 * - chat        {url, token, position, locale}, empty when the chat widget is off
 * - body_classes  route-based classes
 */
final class Context implements Registrable {

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'timber/context', array( $this, 'extend' ) );
	}

	/**
	 * Add theme data to the global context.
	 *
	 * @param array<string, mixed> $context Timber context.
	 * @return array<string, mixed>
	 */
	public function extend( array $context ): array {
		$request = Request::current();
		$lang    = $request->lang;
		$is_home = Request::HOME === $request->route;

		$context['request']      = array(
			'route' => $request->route,
			'lang'  => $lang,
		);
		$context['t']            = Store::strings( $lang );
		$context['brand']        = $this->brand( $lang );
		$context['home_url']     = Urls::home( $lang );
		$context['blog_url']     = Urls::blog( $lang );
		$context['contact_url']  = $is_home ? '#contact' : Urls::section( $lang, 'contact' );
		$context['navigation']   = $this->navigation( $lang, $request->route );
		$context['languages']    = $this->languages( $request );
		$context['legal_links']  = $this->legal_links( $lang );
		$context['chat']         = Assets::chat();
		$context['body_classes'] = 'route-' . $request->route . ' lang-' . $lang;

		return $context;
	}

	/**
	 * Brand and contact data.
	 *
	 * @param string $lang Language code.
	 * @return array<string, mixed>
	 */
	private function brand( string $lang ): array {
		$site   = Store::site();
		$name   = (string) ( Store::get( $site, 'brand.name' ) ?? get_bloginfo( 'name' ) );
		$social = array();

		foreach ( (array) ( $site['social'] ?? array() ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'], $entry['url'] ) ) {
				$social[] = array(
					'id'    => (string) $entry['id'],
					'icon'  => (string) ( $entry['icon'] ?? '' ),
					'url'   => (string) $entry['url'],
					'title' => Store::text( $lang, 'footer.networks.' . $entry['id'] ),
				);
			}
		}

		return array(
			'name'          => $name,
			'logo'          => array(
				'src'    => Photo::url( (string) Store::get( $site, 'brand.logo' ) ),
				'width'  => (int) Store::get( $site, 'brand.logo_width' ),
				'height' => (int) Store::get( $site, 'brand.logo_height' ),
			),
			'email'         => (string) Store::get( $site, 'contact.email' ),
			'phone'         => (string) Store::get( $site, 'contact.phone' ),
			'phone_display' => (string) Store::get( $site, 'contact.phone_display' ),
			'booking_url'   => (string) Store::get( $site, 'contact.booking_url' ),
			'whatsapp_url'  => (string) ( array_column( $social, 'url', 'id' )['whatsapp'] ?? '' ),
			'address'       => trim( Store::get( $site, 'contact.street' ) . ', ' . Store::get( $site, 'contact.postal_code' ) . ' ' . Store::get( $site, 'contact.city' ), ', ' ),
			'social'        => $social,
			'source_url'    => (string) Store::get( $site, 'brand.source_url' ),
		);
	}

	/**
	 * Main navigation: five sections of the homepage ("Portfolio" is the services
	 * section, "References" the projects section with the id "portfolio"). On the homepage the links
	 * are plain anchors; on every other page they lead to the homepage. "Blog"
	 * is marked as current on the blog and its articles.
	 *
	 * @param string $lang  Language code.
	 * @param string $route Current route.
	 * @return array<int, array{title: string, url: string, current: bool}>
	 */
	private function navigation( string $lang, string $route ): array {
		$is_home = Request::HOME === $route;
		$items   = array();

		foreach ( array(
			'about'      => 'about-us',
			'portfolio'  => 'services',
			'references' => 'portfolio',
			'blog'       => 'blog',
			'contact'    => 'contact',
		) as $key => $anchor ) {
			$items[] = array(
				'title'   => Store::text( $lang, 'nav.' . $key ),
				'url'     => $is_home ? '#' . $anchor : Urls::section( $lang, $anchor ),
				'current' => 'blog' === $key && in_array( $route, array( Request::BLOG, Request::POST ), true ),
			);
		}

		return $items;
	}

	/**
	 * Language switch: the current page in every language.
	 *
	 * @param Request $request Current request.
	 * @return array<int, array{code: string, name: string, short: string, html: string, url: string, current: bool, same: bool}>
	 */
	private function languages( Request $request ): array {
		$languages = array();

		foreach ( Languages::all() as $code ) {
			$languages[] = array(
				'code'    => $code,
				'name'    => Store::text( $code, 'lang.name' ),
				'short'   => Store::text( $code, 'lang.short' ),
				'html'    => Store::text( $code, 'lang.html' ),
				'url'     => Urls::alternate( $request, $code ),
				'current' => $code === $request->lang,
				// Same page in the other language (keeps the scroll position, see main.js).
				'same'    => $code === $request->lang || Urls::has_equivalent( $request ),
			);
		}

		return $languages;
	}

	/**
	 * Footer links to the legal pages.
	 *
	 * @param string $lang Language code.
	 * @return array<int, array{title: string, url: string}>
	 */
	private function legal_links( string $lang ): array {
		$links = array();

		foreach ( Router::legal_keys() as $key ) {
			$links[] = array(
				'title' => Store::text( $lang, 'legal.' . $key . '.title' ),
				'url'   => Urls::legal( $lang, $key ),
			);
		}

		return $links;
	}
}
