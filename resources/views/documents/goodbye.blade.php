<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Why did you leave? · Growvia</title>
@include('documents.style')
<style>
    form { text-align: left; } label.r { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid var(--line); cursor: pointer; }
    textarea { box-sizing: border-box; width: 100%; margin-top: 12px; padding: 10px; border: 1px solid var(--line); border-radius: 10px; font: inherit; background: transparent; color: inherit; }
    button { margin-top: 14px; min-height: 44px; padding: 0 18px; border: 0; border-radius: 10px; background: #5b45f0; color: #fff; font: 700 15px system-ui, sans-serif; cursor: pointer; }
</style>
</head>
<body>
<main>
@if ($sent)
    <h1>Thank you</h1>
    <p>Your answer helps us make Growvia better for every store.</p>
@else
    <h1>Why did you leave?</h1>
    <p>One optional question{{ $store ? ' about '.$store : '' }}. It takes ten seconds and only the Growvia team sees it.</p>
    <form method="POST" action="{{ $action }}">
        @foreach ($reasons as $key => $label)
            <label class="r"><input type="radio" name="reason" value="{{ $key }}" required> {{ $label }}</label>
        @endforeach
        <label for="note" class="small" style="display:block;margin-top:10px">Anything we should know? (optional)</label>
        <textarea id="note" name="note" rows="3" maxlength="2000"></textarea>
        <button type="submit">Send</button>
    </form>
@endif
</main>
</body>
</html>
