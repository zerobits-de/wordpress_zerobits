<?php
/**
 * Base controller.
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

namespace ZB\Theme\Controllers;

use Timber\Timber;

/**
 * A controller maps one route to a Twig template and prepares the context it
 * needs.
 *
 *   Flow: index.php -> Functions\Router::controller() -> render() -> Timber::render(twig, context)
 */
abstract class AbstractController {

	/**
	 * Candidate Twig templates in priority order (first found wins).
	 *
	 * @return array<int, string>
	 */
	abstract protected function templates(): array;

	/**
	 * Context passed to Twig. Extend Timber's global context; never replace it.
	 *
	 * @return array<string, mixed>
	 */
	protected function context(): array {
		return Timber::context();
	}

	/**
	 * Render the first matching template.
	 */
	public function render(): void {
		Timber::render( $this->templates(), $this->context() );
	}
}
