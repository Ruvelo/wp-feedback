<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Support;

/**
 * Thrown instead of redirecting when RUVELO_FEEDBACK_TESTS is defined, so
 * the form handlers can be tested.
 */
final class Redirect extends \RuntimeException
{
    public function __construct(public readonly string $url, public readonly int $status = 302)
    {
        parent::__construct($url);
    }
}
