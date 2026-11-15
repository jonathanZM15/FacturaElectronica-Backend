<?php

return [
    // Operational review deadline, not the legal expiration of the tax rate.
    'reviewed_until' => env('IVA_REVIEWED_UNTIL', '2026-12-31'),
    'periods' => [
        ['code' => 0, 'rate' => 0, 'from' => '2024-04-01', 'to' => null, 'source' => 'LRTI arts. 55 y 56; ficha SRI 2.34 tabla 17'],
        ['code' => 4, 'rate' => 15, 'from' => '2024-04-01', 'to' => null, 'source' => 'DE 198; DE 470; circular NAC-DGECCGC25-00000006'],
        ['code' => 6, 'rate' => 0, 'from' => '2024-04-01', 'to' => null, 'source' => 'LRTI art. 54; ficha SRI 2.34 tabla 17'],
        ['code' => 7, 'rate' => 0, 'from' => '2024-04-01', 'to' => null, 'source' => 'Exenciones aplicables de LRTI; ficha SRI 2.34 tabla 17'],
    ],
    // Special rates require legal dates, source and eligible product IDs.
    'special_periods' => [],
];
