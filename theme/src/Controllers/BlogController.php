<?php
/**
 * Blog list controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\BlogContent;
use ZB\Theme\Routing\Request;
use ZB\Theme\Routing\Urls;

/**
 * Articles of the current language, newest first (the main query, filtered in
 * Functions\Router::main_query()).
 */
final class BlogController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'blog.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		global $wp_query;

		$context = parent::context();
		$request = Request::current();
		$total   = max( 1, (int) $wp_query->max_num_pages );

		$context['posts']      = BlogContent::main_query( $request->lang );
		$context['pagination'] = array(
			'current'  => $request->paged,
			'total'    => $total,
			'previous' => $request->paged > 1 ? Urls::blog( $request->lang, $request->paged - 1 ) : '',
			'next'     => $request->paged < $total ? Urls::blog( $request->lang, $request->paged + 1 ) : '',
		);

		return $context;
	}
}
