<?php
/**
 * Project page controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\SiteContent;
use ZB\Theme\Routing\Request;

/**
 * Page of one project of the portfolio: its text and gallery, the technologies
 * used (as chips) and the link to the project.
 * Texts from content/{lang}.json "portfolio", images, the address
 * and the technologies from content/site.json.
 */
final class ProjectController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'project.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		$context = parent::context();
		$request = Request::current();

		$context['page'] = ( new SiteContent( $request->lang ) )->project( $request->page );

		return $context;
	}
}
