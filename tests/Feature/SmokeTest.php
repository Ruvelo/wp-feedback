<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Tests\TestCase;

final class SmokeTest extends TestCase
{
    public function test_post_types_are_registered(): void
    {
        $this->assertTrue(post_type_exists('ruvelo_request'));
        $this->assertTrue(post_type_exists('ruvelo_entry'));
    }
}
