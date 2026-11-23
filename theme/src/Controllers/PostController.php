<?php
/**
 * Blog article controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\BlogContent;
use ZB\Theme\Routing\Request;
use Timber\Post;
use Timber\Timber;

/**
 * One article with the author box.
 */
final class PostController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'single.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		$context = parent::context();
		$post    = Timber::get_post();
		$lang    = Request::current()->lang;

		if ( $post instanceof Post ) {
			$context['article'] = BlogContent::card( $post, $lang );
			$context['author']  = BlogContent::author( $lang );
		}

		return $context;
	}
}
