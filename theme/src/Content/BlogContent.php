<?php
/**
 * Blog articles as cards.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Content;

use ZB\Theme\Functions\Router;
use ZB\Theme\Routing\Urls;
use Timber\Image;
use Timber\Post;
use Timber\Timber;

/**
 * Turns articles into the data the post card and the article template render.
 */
final class BlogContent {

	/**
	 * Latest articles of a language.
	 *
	 * @param string $lang    Language code.
	 * @param int    $count   Number of articles.
	 * @param int    $exclude Post ID to leave out.
	 * @return array<int, array<string, mixed>>
	 */
	public static function latest( string $lang, int $count, int $exclude = 0 ): array {
		$posts = Timber::get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $count,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'post__not_in'        => $exclude > 0 ? array( $exclude ) : array(),
				'tax_query'           => Router::language_query( $lang ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small, indexed query.
			)
		);

		return self::cards( $posts, $lang );
	}

	/**
	 * Published articles of a language by slug, in the given order. Unknown slugs
	 * and articles of another language are skipped.
	 *
	 * @param string             $lang  Language code.
	 * @param array<int, string> $slugs Post slugs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function by_slugs( string $lang, array $slugs ): array {
		$slugs = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );

		if ( array() === $slugs ) {
			return array();
		}

		$posts = Timber::get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'post_name__in'       => $slugs,
				'orderby'             => 'post_name__in',
				'posts_per_page'      => count( $slugs ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'tax_query'           => Router::language_query( $lang ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small, indexed query.
			)
		);

		return self::cards( $posts, $lang );
	}

	/**
	 * Articles of the main query.
	 *
	 * @param string $lang Language code.
	 * @return array<int, array<string, mixed>>
	 */
	public static function main_query( string $lang ): array {
		return self::cards( Timber::get_posts(), $lang );
	}

	/**
	 * Card data of one article.
	 *
	 * The articles of the former site start with their own <h1> (often longer than
	 * the post title) followed by a copy of the featured image. Both are taken out
	 * of the content: the heading becomes `headline`, the image is the featured image.
	 *
	 * @param Post   $post Article.
	 * @param string $lang Language code.
	 * @return array<string, mixed>
	 */
	public static function card( Post $post, string $lang ): array {
		$timestamp = (int) get_post_timestamp( $post->ID );
		$thumbnail = $post->thumbnail();
		$title     = html_entity_decode( (string) $post->title(), ENT_QUOTES, 'UTF-8' );
		$content   = self::clean( (string) $post->content() );
		$headline  = $title;

		if ( 1 === preg_match( '#^\s*<h1[^>]*>(.*?)</h1>#is', $content, $matches ) ) {
			$headline = self::plain( $matches[1] );
			$content  = (string) substr( $content, strlen( $matches[0] ) );
		}

		if ( $thumbnail instanceof Image ) {
			$content = (string) preg_replace( '#^\s*<p>\s*<img\b[^>]*>\s*</p>#i', '', $content, 1 );
		}

		return array(
			'id'       => $post->ID,
			'lang'     => $lang,
			'title'    => $title,
			'headline' => '' !== $headline ? $headline : $title,
			'url'      => Urls::post( $post->wp_object() ),
			'excerpt'  => (string) preg_replace(
				'/(?:\s*(?:…|\.{3}))+$/u',
				'…',
				self::plain(
					(string) $post->excerpt(
						array(
							'words'     => 24,
							'force'     => true,
							'read_more' => false,
						)
					)
				)
			),
			'content'  => trim( $content ),
			'date_iso' => $timestamp > 0 ? wp_date( 'c', $timestamp ) : '',
			'image'    => $thumbnail instanceof Image ? $thumbnail : null,
		);
	}

	/**
	 * Author box next to an article (content/site.json "author", texts "blog.author").
	 *
	 * @param string $lang Language code.
	 * @return array<string, mixed>
	 */
	public static function author( string $lang ): array {
		$site = Store::site();

		return array(
			'name'  => (string) Store::get( $site, 'author.name' ),
			'bio'   => Store::text( $lang, 'blog.author.bio' ),
			'image' => Photo::make( (string) Store::get( $site, 'author.image' ), Store::text( $lang, 'blog.author.image_alt' ) ),
		);
	}

	/**
	 * Remove what editors pasted along with the text: block comments that ended up
	 * inside paragraphs, and the empty paragraphs they leave behind.
	 *
	 * @param string $html Rendered post content.
	 */
	private static function clean( string $html ): string {
		$html = (string) preg_replace( '#<!--.*?-->#s', '', $html );

		return (string) preg_replace( '#<p>(?:\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html );
	}

	/**
	 * Plain text without tags or HTML entities (Twig escapes it again).
	 *
	 * @param string $html HTML.
	 */
	private static function plain( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Cards of a collection.
	 *
	 * @param iterable $posts Posts.
	 * @param string   $lang  Language code.
	 * @return array<int, array<string, mixed>>
	 */
	private static function cards( iterable $posts, string $lang ): array {
		$cards = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof Post ) {
				$cards[] = self::card( $post, $lang );
			}
		}

		return $cards;
	}
}
