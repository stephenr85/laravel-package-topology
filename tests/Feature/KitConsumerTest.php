<?php

declare(strict_types=1);

namespace Rushing\PackageTopology\Tests\Feature;

use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Testing\AssertsPackageTopology;
use Rushing\PackageTopology\Tests\TestCase;

/**
 * The smoke consumer: exercises the shipped {@see AssertsPackageTopology} kit
 * end to end (source → factory → evaluator → assertion) against the clean
 * fixture tree, proving a consumer wires the whole spine with two seams and one
 * `assertTopologyHolds()` call — both axes in one contract.
 */
final class KitConsumerTest extends TestCase
{
    use AssertsPackageTopology;

    protected function vendorPath(): string
    {
        return __DIR__.'/../fixtures/vendor-fixture/clean';
    }

    protected function topologyContract(): TopologyContract
    {
        return TopologyContract::for('kit-consumer')
            ->mustRequire('splicewire/engine-a', 'splicewire/kernel')
            ->mustNotRequire('splicewire/engine-a', 'splicewire/engine-b', because: 'engines are decoupled peers')
            ->mustRequire('splicewire/kernel', 'splicewire/spine')
            ->downOnly('splicewire/kernel', from: ['splicewire/engine-a', 'splicewire/engine-b'])
            ->mustBeAcyclic()
            ->build();
    }

    public function test_the_kit_asserts_a_clean_hierarchy_holds(): void
    {
        $this->assertTopologyHolds();
    }
}
