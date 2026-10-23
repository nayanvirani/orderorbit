<?php

namespace App\Services\Shopify;

/** The app's client secrets for checking what Shopify signed: the current one, plus the old one during a rotation. */
class Secrets
{
    /** @return list<string> */
    public static function verifying(): array
    {
        return array_values(array_unique(array_filter([(string) config('shopify.api_secret'), (string) config('shopify.api_secret_previous')], fn ($s) => $s !== '')));
    }
}
