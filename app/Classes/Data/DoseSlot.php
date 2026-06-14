<?php

namespace Modules\Pharmacy\Classes\Data;

use Carbon\Carbon;

readonly class DoseSlot
{
    public function __construct(
        public int $sequence,
        public Carbon $dueAt,
    ) {}
}
