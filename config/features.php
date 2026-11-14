<?php

return [
    // These deliveries are not presented as completed by the first-version menu.
    'v1_extended_inventory' => (bool) env('V1_EXTENDED_INVENTORY', false),
    'v1_other_documents' => (bool) env('V1_OTHER_DOCUMENTS', false),
    'v1_purchases' => (bool) env('V1_PURCHASES', false),
];
