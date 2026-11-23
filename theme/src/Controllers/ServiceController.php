<?php
/**
 * Service page controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\SiteContent;
use ZB\Theme\Routing\Request;

/**
 * Page of one service (AI, automation, cloud, hosting): its text and features,
 * further blocks, where site.json asks for them the technologies and the Odoo partner
 * section, and the pricing models. Texts from content/{lang}.json "services", "tools" and
 * "odoo", order, images and icons from content/site.json.
 */
final class ServiceController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'service.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		$context = parent::context();
		$request = Request::current();

		$context['page'] = ( new SiteContent( $request->lang ) )->service( $request->page );

		return $context;
	}
}
