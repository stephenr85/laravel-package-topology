<?php

declare(strict_types=1);

namespace Rushing\PackageTopology\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\PackageTopology\PackageTopologyServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PackageTopologyServiceProvider::class];
    }

    /** Absolute path to a fixture subtree under tests/fixtures. */
    protected function fixturePath(string $relative = ''): string
    {
        return rtrim(__DIR__.'/fixtures/'.ltrim($relative, '/'), '/');
    }
}
