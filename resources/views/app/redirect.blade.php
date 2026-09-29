<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
</head>
<body>
    <script>
        window.open(@json($url), '_top');
    </script>
</body>
</html>
