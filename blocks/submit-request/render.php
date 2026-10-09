<?php

/**
 * Renders the Submit Request block.
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Views;

defined('ABSPATH') || exit;

echo Views::submit($attributes, get_block_wrapper_attributes(Views::wrapperArgs('submit'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the view.
