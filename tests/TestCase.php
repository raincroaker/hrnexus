<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // App enables CSRF on API routes as well; disable in tests to avoid 419->302 noise.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
