<?php
/**
 * Language of blog articles.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Functions;

use ZB\Theme\Content\Store;
use ZB\Theme\I18n\Languages;
use ZB\Theme\Registrable;
use WP_Post;

/**
 * Every blog article is written in exactly one language. The language is a
 * term of the private taxonomy `zb_language` (slug = language code), which the
 * block editor shows as a checkbox list in the sidebar ("Language").
 *
 * - New articles get the default language (`default_term`).
 * - If several languages are ticked, the one that is not the default wins
 *   (the default is pre-ticked, so ticking another one means "this one").
 * - Terms can only be assigned, not created, renamed or deleted by editors.
 *
 * Why a taxonomy and not post meta: it needs no editor plugin, shows up as a
 * column and filter in the post list, and can be queried efficiently.
 */
final class BlogLanguage implements Registrable {

	public const TAXONOMY = 'zb_language';

	/**
	 * Post types with a language.
	 */
	public const POST_TYPES = array( 'post' );

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'after_switch_theme', array( self::class, 'ensure_terms' ) );
		add_action( 'admin_init', array( $this, 'maybe_ensure_terms' ) );
		foreach ( self::POST_TYPES as $type ) {
			add_action( 'save_post_' . $type, array( $this, 'normalize' ), 20 );
			add_action( 'rest_after_insert_' . $type, array( $this, 'normalize_rest' ) );
		}
	}

	/**
	 * Register the taxonomy for posts.
	 */
	public function register_taxonomy(): void {
		$default = Languages::default();

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPES,
			array(
				'labels'             => array(
					'name'          => __( 'Language', 'zb-theme' ),
					'singular_name' => __( 'Language', 'zb-theme' ),
					'menu_name'     => __( 'Languages', 'zb-theme' ),
					'all_items'     => __( 'All languages', 'zb-theme' ),
				),
				'description'        => __( 'Language of the article. Pick exactly one.', 'zb-theme' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'show_tagcloud'      => false,
				'hierarchical'       => true,
				'query_var'          => false,
				'rewrite'            => false,
				'default_term'       => array(
					'name' => Store::text( $default, 'lang.name' ),
					'slug' => $default,
				),
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Create a term for every configured language (idempotent).
	 */
	public static function ensure_terms(): void {
		foreach ( Languages::all() as $lang ) {
			if ( ! term_exists( $lang, self::TAXONOMY ) ) {
				wp_insert_term( Store::text( $lang, 'lang.name' ), self::TAXONOMY, array( 'slug' => $lang ) );
			}
		}

		update_option( 'zb_language_terms', implode( ',', Languages::all() ), false );
	}

	/**
	 * Create missing terms once after the language list changed.
	 */
	public function maybe_ensure_terms(): void {
		if ( get_option( 'zb_language_terms' ) !== implode( ',', Languages::all() ) ) {
			self::ensure_terms();
		}
	}

	/**
	 * Keep exactly one language on an article (classic saves, WP-CLI).
	 *
	 * @param int $post_id Post ID.
	 */
	public function normalize( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$current = $this->assigned( $post_id );
		$wanted  = $this->pick( $current );

		if ( array( $wanted ) !== $current ) {
			wp_set_object_terms( $post_id, $wanted, self::TAXONOMY );
		}
	}

	/**
	 * Block editor saves the terms after save_post, so check again afterwards.
	 *
	 * @param WP_Post $post Saved post.
	 */
	public function normalize_rest( WP_Post $post ): void {
		$this->normalize( $post->ID );
	}

	/**
	 * Language of an article; the default language when none is set.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function of( int $post_id ): string {
		$terms = get_the_terms( $post_id, self::TAXONOMY );

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( Languages::is_supported( $term->slug ) && Languages::default() !== $term->slug ) {
					return $term->slug;
				}
			}
		}

		return Languages::default();
	}

	/**
	 * Supported language slugs assigned to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, string>
	 */
	private function assigned( int $post_id ): array {
		$slugs = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );

		return is_array( $slugs ) ? array_values( array_filter( array_map( 'strval', $slugs ), array( Languages::class, 'is_supported' ) ) ) : array();
	}

	/**
	 * The one language to keep: a non-default one if ticked, else the default.
	 *
	 * @param array<int, string> $slugs Assigned language slugs.
	 */
	private function pick( array $slugs ): string {
		foreach ( $slugs as $slug ) {
			if ( Languages::default() !== $slug ) {
				return $slug;
			}
		}

		return Languages::default();
	}
}
