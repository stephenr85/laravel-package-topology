<?php

declare(strict_types=1);

namespace Splicewire\Spine;

use Splicewire\Spine\Support\Helper;

// A spine class referencing only its own namespace — the direction holds.
final class CleanSpine
{
    public function make(): Helper
    {
        return new Helper;
    }
}
