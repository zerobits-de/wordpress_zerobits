<?php
/**
 * Single entry point: Functions\Router sends every front-end request here and
 * picks the controller for the route (home, blog, post, legal, page, 404).
 *
 * @package ZB\Theme
 */

declare(strict_types=1);

use ZB\Theme\Functions\Router;

Router::controller()->render();
