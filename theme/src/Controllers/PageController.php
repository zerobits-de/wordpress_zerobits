<?php
/**
 * Page controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

/**
 * WordPress pages are not part of the site's content model (everything but the
 * blog comes from the repository). A page created in wp-admin still renders
 * presentably: title and block content.
 */
final class PageController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( 'page.twig' );
	}
}
