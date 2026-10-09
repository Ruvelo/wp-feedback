<?php

/**
 * Renders the Changelog Entry block.
 *
 * @var array<string, mixed> $attributes
 * @var WP_Block $block
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\PostTypes;

defined('ABSPATH') || exit;

$ruvelo_id = isset($block->context['postId']) ? (int) $block->context['postId'] : get_queried_object_id();
$ruvelo_post = get_post($ruvelo_id);
if ($ruvelo_post instanceof WP_Post && $ruvelo_post->post_type === PostTypes::ENTRY) {
    echo Views::entry($ruvelo_post, get_block_wrapper_attributes(Views::wrapperArgs('entry'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the view.
}
