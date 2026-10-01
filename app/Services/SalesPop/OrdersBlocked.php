<?php

namespace App\Services\SalesPop;

use RuntimeException;

/**
 * Shopify refused order access: the app needs protected customer data approval
 * (Partner Dashboard → API access → Protected customer data).
 */
class OrdersBlocked extends RuntimeException {}
