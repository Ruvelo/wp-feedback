<?php

/**
 * Renders the What’s New block.
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Views;

defined('ABSPATH') || exit;

echo Views::whatsNew($attributes, get_block_wrapper_attributes(Views::wrapperArgs('whats-new', 'ruvelo-fb-inline'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the view.
