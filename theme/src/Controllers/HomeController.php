<?php
/**
 * Homepage controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\BlogContent;
use ZB\Theme\Content\SiteContent;
use ZB\Theme\Routing\Request;

/**
 * Homepage: every section comes from content/site.json and content/{lang}.json,
 * the article carousel from the latest articles of the language.
 */
final class HomeController extends AbstractController {

	public const CAROUSEL_POSTS = 6;

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'home.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		$context = parent::context();
		$lang    = Request::current()->lang;

		$context['home']  = ( new SiteContent( $lang ) )->home();
		$context['posts'] = BlogContent::latest( $lang, self::CAROUSEL_POSTS );

		return $context;
	}
}
