<?php

namespace Rushing\PackageTopology\Query;

use JsonPath\JsonObject;
use Rushing\Graphine\Algorithms\TopologicalSort;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;

/**
 * THE PACKAGE-JSON QUERY — run one JSONPath expression across every in-scope
 * installed package's JSON, and get the answers back in DEPENDENCY ORDER.
 *
 * ```php
 * $q = new PackageJsonQuery($vendorPath);
 * $q->query('$.extra.laravel.providers');            // default file: composer.json
 * $q->query('$.name', file: 'package.json');         // any file in the package root
 * $q->query(fn (array $json): array => [...]);       // passthrough, when a path won't do
 * ```
 *
 * THE JSONPATH IS THE CALLER'S VOCABULARY, NOT THIS PACKAGE'S. `$.extra.laravel.providers`
 * is a *composer* convention that a *caller* happens to care about; nothing here
 * knows what a service provider is, and nothing here may ever learn. The whole
 * reason this is a path query rather than a `providers()` method is that the
 * moment the key is spelled inside this package, the package has a framework —
 * and `php-package-topology` requires only `php` and `rushing/php-graphine` on
 * purpose. Callers that want Laravel semantics spell them at the call site.
 *
 * ORDERING IS THE PART ONLY THIS PACKAGE CAN SUPPLY. A flat read of
 * `installed.json`, a `glob()` over `vendor/`, or a `ksort()` of package names
 * all answer "which packages" and none of them answer "in what order" —
 * composer's file order is not dependency order. Here the answer comes off the
 * require graph {@see ComposerManifestGraphSource} already builds: Kahn's sort
 * ({@see TopologicalSort}) over the REVERSED edge set, so for a `require` edge
 * `a → b` the dependency `b` is emitted BEFORE its dependent `a`. Requirements
 * first, in other words — which is the order in which a thing that seeds
 * registries must run relative to the thing that overrides them.
 *
 * @see \Rushing\PackageTopology\Tests\Feature\PackageJsonQueryTest the ordering proof
 *
 * WHY A FLAT LIST IS THE PRIMARY RETURN. The obvious shape is
 * `array<string, mixed>` keyed by package name, and it is available here as
 * {@see queryByPackage()} — but it is the secondary shape, because the caller
 * with a real question ("what do I hand to a boot list?") wants values, not a
 * map it must immediately flatten, and flattening a map is exactly where an
 * ordering acquired at cost gets thrown away by an `array_merge` in the wrong
 * direction. So {@see query()} returns `list<mixed>`, already ordered, already
 * unioned; the keyed form is there when provenance matters and is ordered too
 * (PHP preserves insertion order, so `foreach` over it walks the same sequence).
 *
 * A PACKAGE THAT DOES NOT ANSWER IS ABSENT, NEVER AN ERROR. No such file, file
 * is not JSON, path matches nothing — the package contributes nothing and no
 * exception is raised. This is the overwhelmingly common case, not the edge:
 * ask 53 packages for `$.extra.laravel.providers` and most of them have no
 * `extra` block at all. A query that threw on absence would be unusable for the
 * question it exists to answer. A malformed PATH is the caller's own error and
 * does raise — that is a fact about the expression, not about any package.
 *
 * CYCLIC PACKAGES ARE APPENDED, NEVER DROPPED. `TopologicalSort` excludes nodes
 * in or behind a cycle from its sorted output; silently inheriting that would
 * make a require cycle present as a shorter answer rather than as a problem —
 * this estate's recurring defect, an instrument reporting success by not
 * running. They are appended in scope order instead, so the result stays
 * complete and merely stops being fully ordered. Use the contract's
 * `mustBeAcyclic()` rule to learn that a cycle exists; that is its job.
 *
 * Pure filesystem, like the source it rides. No container, no framework, no
 * Splicewire vocabulary.
 */
class PackageJsonQuery
{
    private ComposerManifestGraphSource $source;

    /** @var list<string>|null memoised dependency-ordered package names */
    private ?array $ordered = null;

    /**
     * @param  string  $vendorPath  the composer `vendor/` directory (e.g. base_path('vendor'))
     * @param  list<string>  $include  vendor/name globs — the LOAD-BEARING scope, as on the graph source
     * @param  list<string>  $requireKeys  manifest keys the ORDERING is derived from (opt in 'require-dev')
     */
    public function __construct(
        private string $vendorPath,
        array $include = ['rushing/*', 'splicewire/*'],
        array $requireKeys = ['require'],
    ) {
        $this->source = new ComposerManifestGraphSource($vendorPath, $include, $requireKeys);
    }

    /**
     * Every value the expression matches across every in-scope package, flattened
     * into one dependency-ordered, de-duplicated list.
     *
     * A match that is itself a list is SPREAD one level — `$.extra.laravel.providers`
     * matches a single value which is an array of class names, and the caller
     * asking that question wants the class names, not the array. Non-list matches
     * (a string, an object/map, a number) are appended whole.
     *
     * De-duplication keeps the FIRST occurrence, so a value contributed by a
     * dependency outranks the same value re-contributed by its dependent.
     *
     * @param  string|callable(array<string,mixed>): array<mixed>  $path  a JSONPath expression, or a
     *                                                                    passthrough given the decoded JSON
     * @param  string  $file  filename relative to the package root
     * @return list<mixed>
     */
    public function query(string|callable $path, string $file = 'composer.json'): array
    {
        $flat = [];
        $seen = [];

        foreach ($this->queryByPackage($path, $file) as $values) {
            foreach ($values as $value) {
                $key = json_encode($value);
                if (is_string($key) && isset($seen[$key])) {
                    continue;
                }
                if (is_string($key)) {
                    $seen[$key] = true;
                }
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /**
     * The same answers keyed by package name, in the same dependency order.
     *
     * Packages that matched nothing — no file, unreadable JSON, path selecting
     * nothing, or a passthrough returning `[]` — are OMITTED rather than present
     * with an empty list, so `array_keys()` is exactly "who answered".
     *
     * @param  string|callable(array<string,mixed>): array<mixed>  $path
     * @return array<string, list<mixed>>
     */
    public function queryByPackage(string|callable $path, string $file = 'composer.json'): array
    {
        $results = [];

        foreach ($this->orderedPackages() as $package) {
            $json = $this->readJson($package, $file);
            if ($json === null) {
                continue;
            }

            $values = is_callable($path)
                ? array_values($path($json))
                : $this->match($json, $path);

            if ($values !== []) {
                $results[$package] = $values;
            }
        }

        return $results;
    }

    /**
     * The in-scope package names in dependency order — requirements before
     * dependents. Public because the ordering is useful on its own: it is the
     * one thing here that a flat `installed.json` read cannot reproduce.
     *
     * @return list<string>
     */
    public function orderedPackages(): array
    {
        if ($this->ordered !== null) {
            return $this->ordered;
        }

        $nodes = [];
        foreach ($this->source->nodes() as $node) {
            /** @var Node $node */
            $nodes[] = $node->id()->value();
        }

        // REVERSED: the source's edges run dependent → dependency (`a` REQUIRES `b`),
        // and Kahn emits `u` before `v` for `u → v`. Feeding the edges as-declared
        // would therefore put every dependent ahead of the thing it depends on —
        // exactly backwards for any consumer that boots or loads in this order.
        $adjacency = [];
        foreach ($this->source->edges() as $edge) {
            /** @var Edge $edge */
            $adjacency[$edge->to()->value()][] = $edge->from()->value();
        }

        $order = TopologicalSort::kahn($nodes, $adjacency);

        // Cyclic nodes are excluded from `$order->sorted`; re-append so the result
        // is always the complete package set (see the class docblock).
        $sorted = $order->sorted;
        foreach ($nodes as $node) {
            if (in_array($node, $order->cyclic, true)) {
                $sorted[] = $node;
            }
        }

        return $this->ordered = $sorted;
    }

    // --- internals ------------------------------------------------------------

    /**
     * Decode `vendor/{package}/{file}`, or null if it is absent or not a JSON object.
     *
     * This is a per-file read of an ARBITRARY file, not a second manifest walker:
     * which packages exist, and in what order, comes entirely from the graph source.
     *
     * @return array<string,mixed>|null
     */
    private function readJson(string $package, string $file): ?array
    {
        $path = $this->vendorPath.'/'.$package.'/'.ltrim($file, '/');
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string,mixed>  $json
     * @return list<mixed>
     */
    private function match(array $json, string $path): array
    {
        $matches = (new JsonObject($json))->get($path);

        // The engine reports "no match" as false in some configurations and [] in others.
        if (! is_array($matches)) {
            return [];
        }

        $values = [];
        foreach ($matches as $match) {
            if (is_array($match) && array_is_list($match)) {
                foreach ($match as $item) {
                    $values[] = $item;
                }

                continue;
            }
            $values[] = $match;
        }

        return $values;
    }
}
