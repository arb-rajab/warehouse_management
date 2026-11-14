<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A controller may raise PHP's time limit for one slow web request
     * (`Admin\RowController::exportQrCodes()` calls set_time_limit(300)). In a
     * test the request runs inside the long-lived Pest worker, so that limit
     * would then cover every later test in the worker, and a slow run killed
     * a worker mid-suite with "Maximum execution time of 300 seconds
     * exceeded". Back to the CLI's unlimited default after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        set_time_limit(0);
    }
}
