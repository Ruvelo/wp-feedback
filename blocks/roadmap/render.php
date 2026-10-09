<?php

/**
 * Renders the Roadmap block.
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Views;

defined('ABSPATH') || exit;

echo Views::roadmap($attributes, get_block_wrapper_attributes(Views::wrapperArgs('roadmap'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the view.
