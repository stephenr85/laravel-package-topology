<?php

namespace Rushing\PackageTopology\Testing;

use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Sources\DeclaredContractSource;

/**
 * THE DECLARED-TOPOLOGY KIT — the manifest-driven counterpart to
 * {@see AssertsPackageTopology}. A consumer certifies its hierarchy WITHOUT
 * hand-declaring a single rule: the contract is assembled from the
 * `extra.package-topology` blocks of the installed packages (see
 * {@see DeclaredContractSource}). Fill ONE seam and call one assertion:
 *
 *   class DeclaredTopologyTest extends \Tests\TestCase
 *   {
 *       use AssertsDeclaredTopology;
 *
 *       protected function vendorPath(): string { return base_path('vendor'); }
 *
 *       public function test_the_declared_topology_holds(): void { $this->assertDeclaredTopologyHolds(); }
 *   }
 *
 * It composes {@see AssertsPackageTopology} — the merged contract is fed through
 * the exact same source -> driver -> evaluator path, so the failure rendering and
 * the two axes behave identically. The seam it fills for you is
 * {@see AssertsPackageTopology::topologyContract()}.
 */
trait AssertsDeclaredTopology
{
    use AssertsPackageTopology;

    /**
     * The declared contract, assembled from the installed manifests. Consumers do
     * not override this — they declare rules in the PACKAGES, not here.
     */
    protected function topologyContract(): TopologyContract
    {
        return (new DeclaredContractSource($this->vendorPath(), $this->includeGlobs()))->contract();
    }

    /**
     * Declared usage reads `schemastud/*` too (the open foundation participates in
     * the vendor-seam rules), so widen the default beyond the rushing+splicewire
     * estate. Override to scope tighter/wider.
     *
     * @return list<string>
     */
    protected function includeGlobs(): array
    {
        return ['rushing/*', 'splicewire/*', 'schemastud/*'];
    }

    protected function assertDeclaredTopologyHolds(): void
    {
        $this->assertTopologyHolds();
    }
}
