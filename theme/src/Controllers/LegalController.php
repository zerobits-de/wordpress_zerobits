<?php
/**
 * Legal page controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use ZB\Theme\Content\Store;
use ZB\Theme\Routing\Request;

/**
 * Imprint and privacy policy from content/legal/{lang}/{key}.html.
 */
final class LegalController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'legal.twig' );
	}

	/**
	 * Template context.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		$context = parent::context();
		$request = Request::current();

		$context['legal'] = array(
			'title'   => Store::text( $request->lang, 'legal.' . $request->page . '.title' ),
			'content' => Store::legal_html( $request->lang, $request->page ),
		);

		return $context;
	}
}
