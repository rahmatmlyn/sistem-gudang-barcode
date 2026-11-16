<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Test tidak bergantung pada hasil `npm run build` (public/build tidak di-commit). */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
