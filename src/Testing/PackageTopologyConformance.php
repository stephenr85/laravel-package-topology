<?php

declare(strict_types=1);

namespace Rushing\PackageTopology\Testing;

use PHPUnit\Framework\TestCase;

/**
 * THE CONFORMANCE BASE — the convenience entry point for a consumer whose test
 * has no framework base-class constraint. Extend it, fill the two seams
 * ({@see AssertsPackageTopology::vendorPath()} + {@see AssertsPackageTopology::topologyContract()}),
 * and inherit a ready `test_topology_holds()`.
 *
 * A consumer that MUST extend a framework TestCase (Laravel / tenancy) uses the
 * {@see AssertsPackageTopology} trait directly instead — PHP being
 * single-inheritance. Ships in src/ (autoloaded, not test-only); needs a
 * phpunit-providing host (suggest-only dep).
 */
abstract class PackageTopologyConformance extends TestCase
{
    use AssertsPackageTopology;

    public function test_topology_holds(): void
    {
        $this->assertTopologyHolds();
    }
}
