<?php
/**
 * 404 controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

/**
 * Not found: large "404" on the brand colour and a way back to the homepage of the language.
 */
final class NotFoundController extends AbstractController {

	/**
	 * Twig template candidates.
	 *
	 * @return array<int, string>
	 */
	protected function templates(): array {
		return array( '404.twig' );
	}
}
