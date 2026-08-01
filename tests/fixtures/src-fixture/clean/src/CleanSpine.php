<?php

namespace Splicewire\Spine;

use Splicewire\Spine\Support\Helper;

// A spine class referencing only its own namespace — the direction holds.
class CleanSpine
{
    public function make(): Helper
    {
        return new Helper;
    }
}
