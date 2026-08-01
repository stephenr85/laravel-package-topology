<?php

namespace Rushing\PackageTopology;

use Illuminate\Support\ServiceProvider;

/**
 * Package service provider. The substrate is a TEST-TIME architectural-fitness
 * kit — it binds no runtime services and ships no config; the provider exists so
 * the package auto-discovers cleanly under Laravel's package convention and has a
 * home for any future wiring.
 */
class PackageTopologyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
