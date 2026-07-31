<?php

namespace Rushing\PackageTopology\Testing;

use PHPUnit\Framework\TestCase;

/**
 * THE DECLARED-CONFORMANCE BASE — the convenience entry point for a consumer with
 * no framework base-class constraint that wants the manifest-driven check. Extend
 * it, fill the single {@see AssertsDeclaredTopology::vendorPath()} seam, and
 * inherit a ready `test_declared_topology_holds()`.
 *
 * A consumer that MUST extend a framework TestCase (Laravel / tenancy) uses the
 * {@see AssertsDeclaredTopology} trait directly instead — PHP being
 * single-inheritance. Mirrors {@see PackageTopologyConformance}.
 */
abstract class DeclaredTopologyConformance extends TestCase
{
    use AssertsDeclaredTopology;

    public function test_declared_topology_holds(): void
    {
        $this->assertDeclaredTopologyHolds();
    }
}
