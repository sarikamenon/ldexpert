<?php

declare(strict_types=1);

return [
    /*
     * The generator scans closure events whose start_date falls within this many days
     * from "today". Anything further out is ignored — the next daily run will pick it up
     * once it enters the window. Anything past event_date is treated as historical.
     */
    'generator_lookahead_days' => 15,
];
