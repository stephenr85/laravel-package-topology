<?php

use Rushing\PackageTopology\Query\PackageJsonQuery;

/**
 * THE PACKAGE-JSON QUERY, PROVEN ON ITS MOTIVATING CASE.
 *
 * The `query/` fixture tree is a miniature of the real defect: `splicewire/tower`
 * requires `splicewire/beam-calendars`, which requires `splicewire/spine`, and
 * each declares providers in its `extra.laravel.providers`. Booting them in the
 * wrong order is not a crash — beam-calendars seeds the registries that tower's
 * provider then overrides, so booting tower's first binds ports to nothing and
 * the suite stays green. The fixture is deliberately arranged so ALPHABETICAL
 * order gets it wrong (`beam-calendars` < `spine`) and only the require graph
 * gets it right.
 *
 * Nothing under test knows the string `laravel`. The path is spelled here, at
 * the call site, which is the whole point of the design.
 */
function queryFixture(string $relative = 'query'): string
{
    return __DIR__.'/../fixtures/vendor-fixture/'.$relative;
}

function query(string $relative = 'query'): PackageJsonQuery
{
    return new PackageJsonQuery(queryFixture($relative));
}

// --- ordering: the thing only this package can supply -----------------------

test('packages come back in dependency order, requirements first', function () {
    expect(query()->orderedPackages())->toBe([
        'rushing/lib',
        'rushing/no-json',
        'splicewire/spine',
        'splicewire/beam-calendars',
        'splicewire/tower',
    ]);
});

test('the dependency order is NOT the alphabetical order', function () {
    $ordered = query()->orderedPackages();
    $alphabetical = $ordered;
    sort($alphabetical);

    // beam-calendars sorts before spine but must BOOT after it.
    expect($ordered)->not->toBe($alphabetical)
        ->and(array_search('splicewire/spine', $ordered, true))
        ->toBeLessThan(array_search('splicewire/beam-calendars', $ordered, true));
});

test('a require cycle appends rather than drops — the set stays complete', function () {
    expect(query('cyclic')->orderedPackages())
        ->toEqualCanonicalizing(['splicewire/cyc-a', 'splicewire/cyc-b']);
});

// --- the motivating case ----------------------------------------------------

test('the providers path returns the right FQCNs in dependency order', function () {
    expect(query()->query('$.extra.laravel.providers'))->toBe([
        'Fixture\Spine\SpineServiceProvider',
        'Fixture\Beam\BeamCalendarsServiceProvider',
        'Fixture\Tower\TowerCalendarsServiceProvider',
        'Fixture\Tower\TowerServiceProvider',
    ]);
});

test('the keyed form carries provenance and the same order', function () {
    expect(query()->queryByPackage('$.extra.laravel.providers'))->toBe([
        'splicewire/spine' => ['Fixture\Spine\SpineServiceProvider'],
        'splicewire/beam-calendars' => ['Fixture\Beam\BeamCalendarsServiceProvider'],
        'splicewire/tower' => [
            'Fixture\Tower\TowerCalendarsServiceProvider',
            'Fixture\Tower\TowerServiceProvider',
        ],
    ]);
});

// --- absence is never an error ---------------------------------------------

test('a package whose path matches nothing is absent, not an error', function () {
    // rushing/lib HAS an `extra` block (branch-alias) but no laravel.providers;
    // rushing/no-json has no `extra` at all. Both must simply not appear.
    expect(array_keys(query()->queryByPackage('$.extra.laravel.providers')))
        ->not->toContain('rushing/lib')
        ->not->toContain('rushing/no-json');
});

test('a package missing the file entirely is absent, not an error', function () {
    // Only rushing/lib ships a package.json; the other four have none.
    expect(query()->queryByPackage('$.name', file: 'package.json'))
        ->toBe(['rushing/lib' => ['@fixture/lib']]);
});

// --- the other two call shapes ---------------------------------------------

test('an alternate file is queried in the package root', function () {
    expect(query()->query('$.version', file: 'package.json'))->toBe(['0.1.0']);
});

test('a passthrough callable answers what a path expression cannot', function () {
    $keys = query()->query(
        fn (array $json): array => array_keys($json['extra']['branch-alias'] ?? []),
    );

    expect($keys)->toBe(['dev-main']);
});

test('a passthrough sees every package and is ordered like a path query', function () {
    expect(query()->query(fn (array $json): array => [$json['name']]))->toBe([
        'rushing/lib',
        'rushing/no-json',
        'splicewire/spine',
        'splicewire/beam-calendars',
        'splicewire/tower',
    ]);
});

// --- flattening and de-duplication -----------------------------------------

test('a non-list match is returned whole rather than spread', function () {
    expect(query()->query('$.extra.laravel'))
        ->toBe([
            ['providers' => ['Fixture\Spine\SpineServiceProvider']],
            ['providers' => ['Fixture\Beam\BeamCalendarsServiceProvider']],
            ['providers' => [
                'Fixture\Tower\TowerCalendarsServiceProvider',
                'Fixture\Tower\TowerServiceProvider',
            ]],
        ]);
});

test('the flat form is a union — the first contributor of a value wins', function () {
    // Every package answers `$.require` keys; splicewire/spine and rushing/lib
    // both contribute nothing, but `php` is declared by spine only once even
    // though the flat form walks five packages.
    $flat = query()->query(fn (array $json): array => array_keys($json['require'] ?? []));

    expect($flat)->toBe([
        'php',
        'splicewire/spine',
        'splicewire/beam-calendars',
        'rushing/lib',
        'illuminate/support',
    ]);
});
