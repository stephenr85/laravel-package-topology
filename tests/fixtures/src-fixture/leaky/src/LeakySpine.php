<?php

declare(strict_types=1);

namespace Splicewire\Spine;

use Splicewire\SomeEngine\Scheduling\Scheduler;

// UPWARD reference: a spine class reaching up into its engine's namespace.
final class LeakySpine
{
    public function schedule(): Scheduler
    {
        return new Scheduler;
    }
}
