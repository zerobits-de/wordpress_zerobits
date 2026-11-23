<?php
/**
 * Sections of the homepage, the service pages and the project pages.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Content;

use ZB\Theme\Routing\Urls;

/**
 * Joins content/site.json (ids, order, images, icons, links) with the texts of
 * one language (content/{lang}.json) into the data the section templates render.
 *
 * Lists are defined once in site.json; the texts of item "automation" are found
 * at "{section}.items.automation" in every language file.
 */
final class SiteContent {

	/**
	 * Language-neutral data.
	 *
	 * @var array<string, mixed>
	 */
	private array $site;

	/**
	 * Texts of the language.
	 *
	 * @var array<string, mixed>
	 */
	private array $strings;

	/**
	 * Language code.
	 *
	 * @var string
	 */
	private string $lang;

	/**
	 * Constructor.
	 *
	 * @param string $lang Language code.
	 */
	public function __construct( string $lang ) {
		$this->lang    = $lang;
		$this->site    = Store::site();
		$this->strings = Store::strings( $lang );
	}

	/**
	 * Sections of the homepage, keyed by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function home(): array {
		$hero          = $this->texts( 'hero' );
		$hero['image'] = Photo::make( $this->site_string( 'hero.image' ), $this->string( 'hero.image_alt' ) );

		return array(
			'hero'      => $hero,
			'about'     => $this->texts( 'about' ),
			'services'  => $this->services(),
			'portfolio' => $this->portfolio(),
			'team'      => $this->team(),
			'contact'   => $this->contact(),
		);
	}

	/**
	 * The services: every one is a card on the homepage and has a page of its
	 * own. A service can have further blocks below its own text (`blocks`), show
	 * technologies (`tools`, ids of the list "tools") and the Odoo partner section (`odoo`).
	 *
	 * @return array<string, mixed>
	 */
	public function services(): array {
		$items = array();

		foreach ( $this->items( 'services', $this->site_list( 'services' ) ) as $item ) {
			$id     = (string) $item['id'];
			$path   = 'services.items.' . $id;
			$blocks = array();

			foreach ( (array) ( $item['blocks'] ?? array() ) as $block ) {
				$key   = is_array( $block ) ? (string) ( $block['id'] ?? '' ) : '';
				$texts = Store::get( $this->strings, $path . '.blocks.' . $key );

				if ( '' === $key || ! is_array( $texts ) || ! is_array( $block ) ) {
					continue;
				}

				$entry             = array_merge( $texts, $block );
				$entry['image']    = Photo::make( (string) ( $block['image'] ?? '' ), (string) ( $texts['image_alt'] ?? '' ) );
				$entry['features'] = $this->features( (array) ( $block['features'] ?? array() ), $path . '.blocks.' . $key );

				$blocks[] = $entry;
			}

			$item['features']  = $this->features( (array) ( $item['features'] ?? array() ), $path );
			$item['blocks']    = $blocks;
			$item['url']       = Urls::service( $this->lang, $id );
			$item['card_icon'] = Photo::url( (string) ( $item['card_icon'] ?? '' ) );

			$items[] = $item;
		}

		return array_merge( $this->texts( 'services' ), array( 'items' => $items ) );
	}

	/**
	 * Page of one service: its rows (the service, its blocks and, where site.json
	 * asks for it, the Odoo partner section), the other services as cards and its
	 * technologies. Null when the id is unknown.
	 *
	 * @param string $id Service id from site.json "services".
	 * @return array<string, mixed>|null
	 */
	public function service( string $id ): ?array {
		$services = $this->services();
		$service  = null;
		$others   = array();

		foreach ( $services['items'] as $item ) {
			if ( $id === $item['id'] ) {
				$service = $item;
			} else {
				$others[] = $item;
			}
		}

		if ( null === $service ) {
			return null;
		}

		unset( $services['items'] );

		$rows = array( $this->row( $service ) );

		foreach ( $service['blocks'] as $block ) {
			$rows[] = $this->row( $block );
		}

		if ( true === ( $service['odoo'] ?? false ) ) {
			$rows[] = $this->odoo();
		}

		return array(
			'rows'    => $rows,
			'others'  => $others,
			'texts'   => $services,
			'tools'   => $this->tools( (array) ( $service['tools'] ?? array() ) ),
			'pricing' => $this->pricing(),
			'team'    => $this->team(),
		);
	}

	/**
	 * Pricing models, shown on the service pages.
	 *
	 * @return array<string, mixed>
	 */
	public function pricing(): array {
		return array_merge( $this->texts( 'pricing' ), array( 'items' => $this->items( 'pricing', $this->site_list( 'pricing' ) ) ) );
	}

	/**
	 * "Work with us" band: texts and the photo.
	 *
	 * @return array<string, mixed>
	 */
	public function team(): array {
		$team          = $this->texts( 'team' );
		$team['image'] = Photo::make( $this->site_string( 'team.image' ), $this->string( 'team.image_alt' ) );

		return $team;
	}

	/**
	 * Technologies section of a service page: the entries of the list
	 * "tools" in site.json with the given ids, in the given order, each with its
	 * description. Null when none of the ids is known.
	 *
	 * @param array<int|string, mixed> $ids Tool ids.
	 * @return array<string, mixed>|null
	 */
	public function tools( array $ids ): ?array {
		$items = $this->tool_items( $ids );

		return array() !== $items ? array_merge( $this->texts( 'tools' ), array( 'items' => $items ) ) : null;
	}

	/**
	 * Portfolio projects, each with its gallery, the technologies used and the
	 * address of its page (`url`; `website` is the project itself).
	 *
	 * @return array<string, mixed>
	 */
	public function portfolio(): array {
		$items = $this->items( 'portfolio', $this->site_list( 'portfolio' ) );

		foreach ( $items as $index => $item ) {
			$items[ $index ]['website'] = (string) ( $item['url'] ?? '' );
			$items[ $index ]['url']     = Urls::project( $this->lang, (string) $item['id'] );
			$items[ $index ]['tools']   = $this->tool_items( (array) ( $item['tools'] ?? array() ) );

			$images = array();

			foreach ( (array) ( $item['images'] ?? array() ) as $path ) {
				$photo = is_string( $path ) ? Photo::make( $path, (string) ( $item['image_alt'] ?? '' ) ) : null;

				if ( null !== $photo ) {
					$images[] = $photo;
				}
			}

			$items[ $index ]['images'] = $images;
		}

		return array_merge( $this->texts( 'portfolio' ), array( 'items' => $items ) );
	}

	/**
	 * Page of one project: the project (with its technologies), the texts of the
	 * portfolio and the call to action. Null when the id is unknown.
	 *
	 * @param string $id Project id from site.json "portfolio".
	 * @return array<string, mixed>|null
	 */
	public function project( string $id ): ?array {
		$portfolio = $this->portfolio();

		foreach ( $portfolio['items'] as $project ) {
			if ( $id !== $project['id'] ) {
				continue;
			}

			unset( $portfolio['items'] );

			return array(
				'project' => $project,
				'texts'   => $portfolio,
				'team'    => $this->team(),
			);
		}

		return null;
	}

	/**
	 * Contact section: texts, map addresses and the chat widget settings.
	 *
	 * @return array<string, mixed>
	 */
	public function contact(): array {
		$contact                  = $this->texts( 'contact' );
		$contact['map_embed_url'] = $this->site_string( 'contact.map_embed_url' );
		$contact['map_url']       = $this->site_string( 'contact.map_url' );

		return $contact;
	}

	/**
	 * Odoo partner section as a row of a service page: the introduction, the
	 * partner logo and the steps of the work as points.
	 *
	 * @return array{id: string, title: string, paragraphs: array<int, string>, image: mixed, points: array<int, string>}
	 */
	public function odoo(): array {
		$odoo   = $this->texts( 'odoo' );
		$points = array();

		foreach ( (array) Store::get( $odoo, 'work.items' ) as $item ) {
			if ( is_array( $item ) && is_string( $item['title'] ?? null ) ) {
				$points[] = $item['title'];
			}
		}

		return array(
			'id'         => 'odoo',
			'title'      => (string) ( $odoo['title'] ?? '' ),
			'paragraphs' => array_values( array_filter( (array) ( $odoo['intro'] ?? array() ), 'is_string' ) ),
			'image'      => Photo::make( $this->site_string( 'odoo.logo' ), $this->string( 'odoo.logo_alt' ) ),
			'points'     => $points,
		);
	}

	/**
	 * A service or one of its blocks as a row of the service page: its text, its
	 * illustration and the titles of its features as points.
	 *
	 * @param array<string, mixed> $entry Service or block.
	 * @return array{id: string, title: string, paragraphs: array<int, string>, image: mixed, points: array<int, string>}
	 */
	private function row( array $entry ): array {
		$points = array();

		foreach ( (array) ( $entry['features'] ?? array() ) as $feature ) {
			if ( is_array( $feature ) && is_string( $feature['title'] ?? null ) ) {
				$points[] = $feature['title'];
			}
		}

		return array(
			'id'         => (string) ( $entry['id'] ?? '' ),
			'title'      => (string) ( $entry['title'] ?? '' ),
			'paragraphs' => array( (string) ( $entry['text'] ?? '' ) ),
			'image'      => $entry['image'] ?? null,
			'points'     => $points,
		);
	}

	/**
	 * Entries of the list "tools" in site.json (name, link) with their texts, for
	 * the given ids and in their order. Unknown ids are skipped.
	 *
	 * @param array<int|string, mixed> $ids Tool ids.
	 * @return array<int, array<string, mixed>>
	 */
	private function tool_items( array $ids ): array {
		$known = array();

		foreach ( $this->items( 'tools', $this->site_list( 'tools' ) ) as $tool ) {
			$known[ (string) $tool['id'] ] = $tool;
		}

		$items = array();

		foreach ( $ids as $id ) {
			if ( is_string( $id ) && isset( $known[ $id ] ) ) {
				$items[] = $known[ $id ];
			}
		}

		return $items;
	}

	/**
	 * Features of a service or block: ids and icons from site.json joined with
	 * their texts at "{path}.features.{id}".
	 *
	 * @param array<int|string, mixed> $entries Features from site.json.
	 * @param string                   $path    Dot path of the texts of the owner.
	 * @return array<int, array<string, mixed>>
	 */
	private function features( array $entries, string $path ): array {
		$features = array();

		foreach ( $entries as $feature ) {
			$key   = is_array( $feature ) ? (string) ( $feature['id'] ?? '' ) : '';
			$texts = Store::get( $this->strings, $path . '.features.' . $key );

			// Texts sit under the same key as the site list: keep only the joined entries.
			if ( '' !== $key && is_array( $texts ) && is_array( $feature ) ) {
				$features[] = array_merge( $feature, $texts );
			}
		}

		return $features;
	}

	/**
	 * Items of a section: site data + texts + photo.
	 *
	 * @param string                           $section Section key in the language file.
	 * @param array<int, array<string, mixed>> $entries Items from site.json.
	 * @return array<int, array<string, mixed>>
	 */
	private function items( string $section, array $entries ): array {
		$items = array();

		foreach ( $entries as $item ) {
			$id    = (string) ( $item['id'] ?? '' );
			$texts = Store::get( $this->strings, $section . '.items.' . $id );

			if ( '' === $id || ! is_array( $texts ) ) {
				continue;
			}

			// Site data wins for keys both define (features, blocks: ids and icons; the texts are joined later).
			$entry          = array_merge( $texts, $item );
			$entry['image'] = isset( $item['image'] ) && is_string( $item['image'] )
				? Photo::make( $item['image'], (string) ( $texts['image_alt'] ?? '' ) )
				: null;

			$items[] = $entry;
		}

		return $items;
	}

	/**
	 * Texts of a section without its item list.
	 *
	 * @param string $section Section key.
	 * @return array<string, mixed>
	 */
	private function texts( string $section ): array {
		$texts = $this->strings[ $section ] ?? array();

		if ( ! is_array( $texts ) ) {
			return array();
		}

		unset( $texts['items'] );

		return $texts;
	}

	/**
	 * Text by path.
	 *
	 * @param string $path Dot path.
	 */
	private function string( string $path ): string {
		$value = Store::get( $this->strings, $path );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Site value by path.
	 *
	 * @param string $path Dot path.
	 */
	private function site_string( string $path ): string {
		$value = Store::get( $this->site, $path );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Site list by path.
	 *
	 * @param string $path Dot path.
	 * @return array<int, array<string, mixed>>
	 */
	private function site_list( string $path ): array {
		$value = Store::get( $this->site, $path );

		return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
	}
}
