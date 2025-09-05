<?php

return [
    'check_map' => [
        'stock' => [
            130023 => 'stockStoreAdd',
            130025 => 'stockStoreReduce',
            170021 => 'stockTransfer',
            150021 => 'stockOtherAdd',
            150022 => 'stockOtherReduce',
            160021 => 'stockOtherReduce',
            160022 => 'stockOtherAdd'
        ],
        'purchase' => [
            120023 => 'purStoreAdd',
            120025 => 'purStoreReduce',
            120028 => 'purStockOut',
            120029 => 'purStockBack',
            140021 => 'purOtherIn',
            140022 => 'purOtherOut',
            140023 => 'purOtherOutBack',
            160621 => 'purTransfer',
            150021 => 'purOtherAdd',
            150022 => 'purOtherReduce',
            160021 => 'purOtherReduce',
            160022 => 'purOtherAdd',
        ],
    ],
];
