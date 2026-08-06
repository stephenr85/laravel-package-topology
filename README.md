# laravel-package-topology

A declarative **package-topology substrate** for Laravel monorepos. Declare a repo's architecture as one
`TopologyContract` and assert it holds — instead of hand-rolling an architectural-fitness test per rule.

It **rides [`rushing/laravel-graphine`](https://github.com/stephenr85/laravel-graphine)**: a package-require
graph *is* a graph (nodes = packages, edges = requires), so the checks are graphine queries, not bespoke
scans. Nothing Splicewire-specific lives here — it reads *your* `vendor/` and *your* `src/`.

## Why

Architectural-fitness tests that enforce a package hierarchy tend to be hand-rolled and copied per repo —
one reading `composer.json` requires with `expect()->toContain()` chains, another `str_contains`-scanning a
package's `src/` for a forbidden namespace. Both are the same idea (*enforce package hierarchy*) at two
layers. This package lifts that into a reusable substrate: **declare a contract, point it at your vendor
tree.**

## Two axes, one contract

| Axis | What it reads | Rules |
|------|---------------|-------|
| **Package-graph** | `vendor/{pkg}/composer.json` `require` keys, as a graphine graph | `mustRequire` / `mustNotRequire` (direct edge), `mustRequireDev` (DEV-only edge, asserted off-graph against `require-dev`), `neverReaches` / `downOnly` / `layerOrder` (transitive, via `shortestPath`), `mustBeAcyclic` (`detectCycles`), `mustBeInstalled` (phantom node) |
| **Source-import** | a package's `src/` (parsed AST) | `sourceNeverReferences` — delegates to graphine's `SeamGuard`, stronger than a substring scan (ignores strings/comments) |

## Install

```bash
composer require --dev rushing/laravel-package-topology
```

Requires PHP ^8.3 and `rushing/laravel-graphine`. The source-import axis needs a `nikic/php-parser`-providing
host (suggest-only); the testing kit needs PHPUnit.

## Usage

Fill two seams and call one assertion. From within a framework TestCase (Laravel / tenancy), use the trait:

```php
use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Testing\AssertsPackageTopology;

final class PackageHierarchyTest extends \Tests\TestCase
{
    use AssertsPackageTopology;

    protected function vendorPath(): string
    {
        return base_path('vendor');
    }

    protected function topologyContract(): TopologyContract
    {
        return TopologyContract::for('engine-hierarchy')
            // package-graph axis
            ->mustNotRequire('acme/engine-a', 'acme/engine-b', because: 'the two engines are decoupled peers')
            ->mustRequire('acme/engine-a', 'acme/kernel')
            ->downOnly('acme/kernel', from: ['acme/engine-a', 'acme/engine-b'], because: 'the kernel depends DOWN only')
            ->mustBeAcyclic()
            // source-import axis
            ->sourceNeverReferences('acme/spine-data', prefixes: ['Acme\\Engine\\'], because: 'engine→spine must not invert')
            ->build();
    }

    public function test_the_hierarchy_holds(): void
    {
        $this->assertTopologyHolds();
    }
}
```

Override `includeGlobs()` to scope which vendors the require-graph loads — it defaults to
`['rushing/*', 'splicewire/*']` and **is load-bearing**: unscoped it would ingest all of `vendor/`,
exploding the reachability queries. Keep it tight.

For a host with no base-class constraint, extend `PackageTopologyConformance` instead of using the trait —
it ships a ready `test_topology_holds()` off the same two seams.

## Declared manifests — let the packages own their own rules

Hand-declaring the whole contract in one consumer works, but every consumer then re-copies (and drifts
from) the same edges. The **declared-manifest** mode moves each rule to the package it is *about*: a
package states its own invariants in its `composer.json` `extra.package-topology` block, and the consumer
runs one thin test that merges whatever the installed tree declares — it declares nothing itself. This is
the "doctor / install-manifest" pattern applied to topology; correcting a direction is a one-line edit in
the owning package, and every consumer follows.

The rule keys are the builder-method names verbatim (subject = the declaring package):

```jsonc
// splicewire/laravel-beam  composer.json
"extra": {
    "package-topology": {
        "because": "the ADR-0138 diamond",
        "mustRequire": ["schemastud/laravel-frame"],
        "mustRequireDev": ["rushing/laravel-surgeon"],         // DEV-only edge — asserted against require-dev
        "mustNotRequire": ["splicewire/laravel-satellite*"],   // glob → expanded against the installed set
        "policy": {                                            // estate-wide prefix rules (no single owner)
            "noRequire": [
                { "fromPrefix": "schemastud/", "toPrefix": "splicewire/", "exceptPrefix": "splicewire/laravel-beam" }
            ]
        }
    }
}
```

Also supported: `neverReaches`, `downOnly` (the from-list), `mustBeInstalled`, `sourceNeverReferences`
(prefixes). `mustBeAcyclic` is always appended. Consume it with the trait / base:

```php
final class DeclaredTopologyTest extends \Tests\TestCase
{
    use \Rushing\PackageTopology\Testing\AssertsDeclaredTopology;

    protected function vendorPath(): string { return base_path('vendor'); }

    public function test_the_declared_topology_holds(): void { $this->assertDeclaredTopologyHolds(); }
}
```

`AssertsDeclaredTopology` widens the default scope to `schemastud/*` too (the open foundation
participates in the vendor-seam rules). `DeclaredTopologyConformance` is the base-class variant.

## Rule reference

| Builder call | Meaning | Check |
|---|---|---|
| `mustRequire($a, $b)` | `$a` requires `$b` directly | `neighbours($a, Descendants, maxDepth: 1)` contains `$b` |
| `mustRequireDev($a, $b)` | `$a` requires `$b` in `require-dev` (DEV-only edge) | `vendor/$a/composer.json`'s `require-dev` contains `$b` (read off-graph — the graph is runtime-`require` only) |
| `mustNotRequire($a, $b)` | `$a` must not require `$b` directly | `neighbours(…, maxDepth: 1)` excludes `$b` |
| `neverReaches($a, $b)` | no transitive require path `$a → … → $b` | `shortestPath($a, $b) === null` |
| `downOnly($pkg, from: […])` | `$pkg` never depends UP on any listed tier | each: `shortestPath($pkg, $tier) === null` |
| `layerOrder([lo … hi])` | no lower layer reaches a higher one | pairwise `shortestPath(lower, higher) === null` |
| `mustBeAcyclic()` | the in-scope require graph is a DAG | `detectCycles() === []` |
| `mustBeInstalled($pkg)` | `$pkg` has an installed manifest | node `properties['installed'] === true` |
| `sourceNeverReferences($pkg, prefixes: […])` | `$pkg`'s `src/` references none of the prefixes | `SeamGuard($prefixes)->scan(vendor/{pkg}/src) === []` |

A **phantom node** — a required target with no installed manifest — is emitted with `installed => false` and
its edge is *kept*, so `mustNotRequire` and `mustBeInstalled` still fire against a missing package (a missing
dependency is a finding). A `sourceNeverReferences` rule whose package has no `src/` is skipped gracefully,
not failed.

## How it works

`ComposerManifestGraphSource` (a graphine `GraphSource`) reads the scoped manifests into `Node`/`Edge`;
`RelationalDriverFactory::make(...)` hydrates them into graphine's in-memory spine **once**, and
`TopologyEvaluator` answers every rule from that snapshot — direct edges via bounded `neighbours`,
reachability via `shortestPath`, cycles via `detectCycles`, and source rules via `SeamGuard`. Returns legible
`TopologyViolation`s (which rule, which edge/file, the `because`); an empty list means the contract holds.

## Teeth

The package ships planted-violation fixtures on **both** axes (a bad direct edge, a require cycle, a
required-but-absent phantom, and an upward namespace `use`), so a green consumer means "the hierarchy holds",
not "the checker is broken".

```bash
composer test   # Pest: the two-axis teeth + the kit smoke consumer
```

## License

MIT © Stephen Rushing
