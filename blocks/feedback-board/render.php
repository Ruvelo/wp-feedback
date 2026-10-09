<?php

/**
 * Renders the Feedback Board block.
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Views;

defined('ABSPATH') || exit;

echo Views::board($attributes, get_block_wrapper_attributes(Views::wrapperArgs('board'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the view.
