<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Farmers are notified when a product's remaining quantity reaches this
    | value. The alert is sent once per low-stock cycle and becomes eligible
    | again after the product is replenished above the threshold.
    |
    */

    'low_stock_threshold' => (int) env('FARMREACH_LOW_STOCK_THRESHOLD', 10),

];
