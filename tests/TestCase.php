<?php

namespace Rushing\PackageTopology\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * The framework-free base for php-package-topology's suite. The substrate is a
 * test-time architectural-fitness kit that reads composer manifests and package
 * source over an in-memory graph — no Laravel container is needed, so a plain
 * PHPUnit TestCase backs the whole suite.
 */
abstract class TestCase extends BaseTestCase
{
    /** Absolute path to a fixture subtree under tests/fixtures. */
    protected function fixturePath(string $relative = ''): string
    {
        return rtrim(__DIR__.'/fixtures/'.ltrim($relative, '/'), '/');
    }
}
