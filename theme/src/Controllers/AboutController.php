<?php
/**
 * About us page controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\SiteContent;
use ZB\Theme\Routing\Request;

/**
 * The about us page: Zerobits in 2001 with a screenshot of the website of that
 * time, the milestones since, a then/now comparison and what we do today.
 * Texts from content/{lang}.json "story", the image and the lists from
 * content/site.json "story".
 */
final class AboutController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'about.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		$context = parent::context();

		$context['story'] = ( new SiteContent( Request::current()->lang ) )->story();

		return $context;
	}
}
