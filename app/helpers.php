<?php

if (! function_exists('app_route')) {
    /**
     * URL to an embedded-app route that keeps the shop/host query App Bridge needs.
     */
    function app_route(string $name, array $parameters = []): string
    {
        $request = request();

        return route($name, array_filter(array_merge([
            'shop' => $request->query('shop') ?? $request->attributes->get('store')?->shop_domain,
            'host' => $request->query('host'),
        ], $parameters)));
    }
}
