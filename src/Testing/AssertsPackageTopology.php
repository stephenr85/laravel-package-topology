<?php

namespace Rushing\PackageTopology\Testing;

use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Contract\TopologyViolation;
use Rushing\PackageTopology\Evaluator\TopologyEvaluator;
use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;

/**
 * THE ASSERTION KIT — as a trait, mirroring graphine's `ConformsToGraphStore`.
 *
 * Shipping the assertions as a TRAIT (not only a base class) lets a consumer
 * certify its hierarchy from WITHIN its own framework TestCase — a Laravel /
 * tenancy-aware TestCase the abstract {@see PackageTopologyConformance} could
 * never extend (PHP is single-inheritance). The consumer fills two seams and
 * calls one assertion:
 *
 *   class MyHierarchyTest extends \Tests\TestCase
 *   {
 *       use AssertsPackageTopology;
 *
 *       protected function vendorPath(): string { return base_path('vendor'); }
 *       protected function topologyContract(): TopologyContract { return TopologyContract::for(...)->...->build(); }
 *
 *       public function test_hierarchy_holds(): void { $this->assertTopologyHolds(); }
 *   }
 *
 * `assertTopologyHolds()` builds the {@see ComposerManifestGraphSource}, hydrates
 * it through graphine's {@see RelationalDriverFactory}, runs the
 * {@see TopologyEvaluator}, and asserts zero violations — with a legible failure
 * message so a green suite means "the hierarchy holds", not "the checker broke".
 */
trait AssertsPackageTopology
{
    /** The composer `vendor/` directory to read manifests + sources from. */
    abstract protected function vendorPath(): string;

    /** The contract this consumer declares. */
    abstract protected function topologyContract(): TopologyContract;

    /**
     * The vendor/name globs the source is scoped to — the LOAD-BEARING allow-list.
     * Override to widen/narrow; the default covers the rushing + splicewire estate.
     *
     * @return list<string>
     */
    protected function includeGlobs(): array
    {
        return ['rushing/*', 'splicewire/*'];
    }

    protected function assertTopologyHolds(): void
    {
        $source = new ComposerManifestGraphSource($this->vendorPath(), $this->includeGlobs());
        $store = RelationalDriverFactory::make($source, 'package-topology');

        $violations = (new TopologyEvaluator)->evaluate($this->topologyContract(), $store, $this->vendorPath());

        $this->assertSame([], $violations, $this->renderViolations($violations));
    }

    /**
     * @param  list<TopologyViolation>  $violations
     */
    private function renderViolations(array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        return "Package-topology contract violated:\n - ".implode("\n - ", array_map(
            static fn (TopologyViolation $v): string => $v->message(),
            $violations,
        ));
    }
}
