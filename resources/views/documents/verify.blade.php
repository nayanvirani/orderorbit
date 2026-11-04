<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Certificate verified · {{ $store }}</title>
@include('documents.style')
</head>
<body>
<main>
    <p class="mark" aria-hidden="true">✓</p>
    <h1>Genuine certificate</h1>
    <p>This {{ $document }} was issued by <strong>{{ $store }}</strong>.</p>
    <dl>
        @if ($item)<div><dt>Item</dt><dd>{{ $item }}</dd></div>@endif
        @if ($serial)<div><dt>Serial number</dt><dd>{{ $serial }}</dd></div>@endif
        @if ($date)<div><dt>Purchased</dt><dd>{{ $date }}</dd></div>@endif
    </dl>
    <p class="small">Check that the serial number matches the one printed on your certificate.</p>
</main>
</body>
</html>
