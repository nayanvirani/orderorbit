<?php

namespace App\Services\Documents;

use App\Models\Documents\IssuedDocument;
use App\Models\Documents\ProductDocument;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Product documents (G42): which documents an order gets, the documents made for it (one per item,
 * product line or order, each with a serial and a private link) and their PDFs. A certificate is made
 * from its saved details whenever it's opened, so only uploaded PDFs are stored.
 */
class ProductDocuments
{
    /** Attachments above this total are sent as download links instead. */
    public const MAX_ATTACH = 10 * 1024 * 1024;

    public function __construct(private readonly AdminApi $api) {}

    /**
     * The documents for an order (from the workflow's order context), made once: a retry or a
     * second email finds the same documents, serials and links.
     *
     * @return Collection<int, IssuedDocument>
     */
    public function forOrder(Store $store, array $order, ?array $customer = null, array $only = []): Collection
    {
        // A workflow can send chosen documents; each still goes only to orders with its products.
        $documents = ProductDocument::where('store_id', $store->id)->where('active', true)->when($only, fn ($q) => $q->whereIn('id', $only))->get();
        $lines = array_values(array_filter((array) ($order['lines'] ?? []), fn ($l) => ($l['product_id'] ?? '') !== ''));
        if ($documents->isEmpty() || ! $lines || empty($order['id'])) {
            return collect();
        }

        $facts = $documents->contains(fn ($d) => in_array($d->match, ['collection', 'tag'], true)) ? $this->productFacts($store, $lines) : [];
        $issued = collect();
        foreach ($documents as $document) {
            $matching = array_values(array_filter($lines, fn ($l) => $this->matches($document, $l, $facts)));
            if (! $matching) {
                continue;
            }
            foreach ($this->units($document, $matching) as [$lineKey, $unit, $line]) {
                $issued->push($this->issue($store, $document, $order, $customer, $lineKey, $unit, $line));
            }
        }
        if ($issued->isNotEmpty()) {
            $this->showInAccount($store, (string) $order['id']);
        }

        return $issued;
    }

    /**
     * The order's documents in an app-owned order metafield ($app:documents) that the customer
     * account "My orders" block reads, so customers find them again after the email.
     */
    public function showInAccount(Store $store, string $orderId): void
    {
        $list = IssuedDocument::with('document')->where('store_id', $store->id)->where('order_id', $orderId)->orderBy('id')->get()
            ->filter(fn ($d) => $d->document !== null)
            ->map(fn ($d) => array_filter(['name' => $d->document->name, 'serial' => $d->serial, 'url' => $d->url(), 'expires' => $d->expires_at->toDateString()]))
            ->values()->all();
        try {
            $this->ensureDefinition($store);
            $this->api->graphql($store, 'mutation ($m: [MetafieldsSetInput!]!) { metafieldsSet(metafields: $m) { userErrors { message } } }', ['m' => [[
                'ownerId' => 'gid://shopify/Order/'.$orderId, 'namespace' => '$app', 'key' => 'documents', 'type' => 'json', 'value' => json_encode($list),
            ]]]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function ensureDefinition(Store $store): void
    {
        $key = "documents-metafield-definition:{$store->id}";
        if (\Illuminate\Support\Facades\Cache::get($key)) {
            return;
        }
        $result = $this->api->graphql($store, 'mutation ($d: MetafieldDefinitionInput!) { metafieldDefinitionCreate(definition: $d) { createdDefinition { id } userErrors { code message } } }', ['d' => [
            'name' => 'Growvia product documents', 'namespace' => '$app', 'key' => 'documents', 'ownerType' => 'ORDER', 'type' => 'json', 'access' => ['customerAccount' => 'READ'],
        ]]);
        $error = $result['metafieldDefinitionCreate']['userErrors'][0] ?? null;
        if ($error === null || ($error['code'] ?? '') === 'TAKEN') {
            \Illuminate\Support\Facades\Cache::forever($key, true);
        }
    }

    private function matches(ProductDocument $document, array $line, array $facts): bool
    {
        $product = (string) $line['product_id'];
        $variant = (string) ($line['variant_id'] ?? '');

        return match ($document->match) {
            'all' => true,
            'products' => collect((array) $document->match_items)->contains(function ($item) use ($product, $variant) {
                $id = Str::afterLast((string) ($item['id'] ?? ''), '/');
                $variants = array_map(fn ($v) => Str::afterLast((string) $v, '/'), (array) ($item['variants'] ?? []));

                return $id === $product && (! $variants || in_array($variant, $variants, true));
            }),
            'collection' => collect((array) $document->match_items)->contains(fn ($c) => in_array((string) ($c['id'] ?? ''), $facts[$product]['collections'] ?? [], true)),
            'tag' => in_array(mb_strtolower((string) $document->match_tag), $facts[$product]['tags'] ?? [], true),
            default => false,
        };
    }

    /** [line key, unit number, line] for each document to make: per item, per product line or once per order. */
    private function units(ProductDocument $document, array $lines): array
    {
        if ($document->per === 'order') {
            return [['order', 1, $lines[0]]];
        }
        $units = [];
        foreach ($lines as $line) {
            $key = (string) ($line['variant_id'] ?: $line['product_id']);
            $count = $document->per === 'item' ? max(1, min(50, (int) ($line['quantity'] ?? 1))) : 1;
            for ($i = 1; $i <= $count; $i++) {
                $units[] = [$key, $i, $line];
            }
        }

        return $units;
    }

    private function issue(Store $store, ProductDocument $document, array $order, ?array $customer, string $lineKey, int $unit, array $line): IssuedDocument
    {
        $existing = IssuedDocument::where(['product_document_id' => $document->id, 'order_id' => (string) $order['id'], 'line_key' => $lineKey, 'unit' => $unit])->first();
        if ($existing) {
            return $existing;
        }

        $settings = $document->certificateSettings();
        $number = preg_replace('/\D+/', '', (string) ($order['name'] ?? '')) ?: (string) $order['id'];
        $position = IssuedDocument::where('product_document_id', $document->id)->where('order_id', (string) $order['id'])->count() + 1;
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $settings['serial_prefix']));

        return IssuedDocument::create([
            'store_id' => $store->id, 'product_document_id' => $document->id,
            'order_id' => (string) $order['id'], 'order_name' => (string) ($order['name'] ?? '') ?: null,
            'ordered_at' => ! empty($order['created_at']) ? \Carbon\Carbon::parse($order['created_at']) : now(),
            'line_key' => $lineKey, 'unit' => $unit,
            'serial' => $document->kind === 'certificate' ? ltrim($prefix.'-'.$number.'-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT), '-') : null,
            'customer_name' => $document->kind === 'certificate' && $settings['show_name'] ? ($customer['name'] ?? null) : null,
            'product_title' => $document->per === 'order' ? null : ($line['title'] ?? null),
            'variant_title' => $document->per === 'order' ? null : (($line['variant_title'] ?? '') ?: null),
            'token' => Str::random(48), 'verify_code' => Str::lower(Str::random(16)),
            'expires_at' => now()->addDays(IssuedDocument::LINK_DAYS),
        ]);
    }

    /** Each product's tags and collections (lower-case tags, collection gids), in one Admin API call. */
    private function productFacts(Store $store, array $lines): array
    {
        $ids = array_values(array_unique(array_map(fn ($l) => 'gid://shopify/Product/'.$l['product_id'], $lines)));
        try {
            $data = $this->api->graphql($store, 'query ($ids: [ID!]!) { nodes(ids: $ids) { ... on Product { id tags collections(first: 100) { nodes { id } } } } }', ['ids' => array_slice($ids, 0, 100)]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
        $facts = [];
        foreach ((array) ($data['nodes'] ?? []) as $node) {
            if (! isset($node['id'])) {
                continue;
            }
            $facts[Str::afterLast($node['id'], '/')] = [
                'tags' => array_map('mb_strtolower', (array) ($node['tags'] ?? [])),
                'collections' => array_column((array) ($node['collections']['nodes'] ?? []), 'id'),
            ];
        }

        return $facts;
    }

    /** The PDF of an issued document: the uploaded file, or the certificate made from its details. */
    public function pdf(IssuedDocument $issued): string
    {
        $document = $issued->document()->first();
        if ($document->kind === 'file') {
            return (string) base64_decode((string) $document->file_data);
        }

        return $this->certificate($issued->store()->first(), $document, [
            'name' => $issued->customer_name, 'product' => $issued->product_title, 'variant' => $issued->variant_title,
            'order' => $issued->order_name, 'date' => $issued->ordered_at, 'serial' => $issued->serial, 'verify' => $issued->verifyUrl(),
        ]);
    }

    /** A sample for the editor's preview and "Send a test". */
    public function sample(Store $store, ProductDocument $document): string
    {
        if ($document->kind === 'file') {
            return (string) base64_decode((string) $document->file_data);
        }

        return $this->certificate($store, $document, ['name' => 'Alex Sample', 'product' => 'Sample product', 'variant' => 'Size 6',
            'order' => '#1001', 'date' => now(), 'serial' => ltrim(strtoupper((string) $document->certificateSettings()['serial_prefix']).'-1001-01', '-'), 'verify' => url('/verify/sample')]);
    }

    private function certificate(Store $store, ProductDocument $document, array $v): string
    {
        $s = $document->certificateSettings();
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['accent']) ? $s['accent'] : '#1f2328';
        $e = fn ($x) => e((string) $x);
        $qr = $s['show_qr'] ? (new QRCode(new QROptions(['outputType' => 'svg', 'outputBase64' => true, 'addQuietzone' => false])))->render($v['verify']) : null;
        $logo = is_string($document->logo) && preg_match('#^data:image/(png|jpeg|gif|svg\+xml);base64,#', $document->logo) ? $document->logo : null;
        $item = trim(implode(' · ', array_filter([$v['product'], $v['variant']])));
        $owner = $v['name'] ? '<p class="name">'.$e($v['name']).'</p>' : '<p class="name">the owner of order '.$e($v['order']).'</p>';
        $date = $v['date'] ? \Carbon\Carbon::parse($v['date'])->format('j M Y') : '';

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 0; }
            body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1f2328; }
            .frame { position: absolute; top: 20pt; left: 20pt; width: 796pt; height: 549pt; border: 3pt solid '.$accent.'; }
            .inner { position: absolute; top: 28pt; left: 28pt; width: 784pt; height: 537pt; border: 1pt solid '.$accent.'; }
            .content { position: absolute; top: 56pt; left: 60pt; width: 722pt; text-align: center; }
            .store { margin: 12pt 0 0; font-size: 10pt; letter-spacing: 3pt; text-transform: uppercase; color: '.$accent.'; }
            h1 { margin: 8pt 0 16pt; font-size: 28pt; font-weight: bold; color: '.$accent.'; }
            .intro { margin: 0; font-size: 11pt; color: #55606b; }
            .name { margin: 6pt 0 8pt; font-size: 22pt; font-weight: bold; }
            .item { margin: 4pt 0 10pt; font-size: 14pt; }
            .meta { margin: 0; font-size: 9.5pt; color: #55606b; }
            .foot { position: absolute; top: 440pt; left: 80pt; width: 682pt; font-size: 9pt; color: #55606b; }
            .sign { position: absolute; left: 0; top: 50pt; width: 260pt; text-align: left; } .sign b { display: block; margin-top: 4pt; border-top: 1pt solid #8a929b; padding-top: 4pt; font-weight: normal; }
            .qr { position: absolute; right: 0; top: 0; width: 100pt; text-align: center; } .qr img { width: 76pt; height: 76pt; }
            .logo { max-height: 56px; max-width: 220px; }
        </style></head><body><div class="frame"></div><div class="inner"></div><div class="content">'
            .($logo ? '<img class="logo" src="'.$logo.'" alt="">' : '')
            .'<p class="store">'.$e($store->name ?: $store->shop_domain).'</p>'
            .'<h1>'.$e($s['heading']).'</h1>'
            .'<p class="intro">'.$e($s['intro']).'</p>'.$owner
            .($item !== '' ? '<p class="intro">'.$e($s['owns']).'</p><p class="item"><b>'.$e($item).'</b></p>' : '')
            .'<p class="meta">'.$e(implode(' · ', array_filter(['Order '.$v['order'], $date, $v['serial'] ? 'Serial '.$v['serial'] : null]))).'</p>'
            .($s['footer'] !== '' ? '<p class="meta" style="margin-top:10pt">'.$e($s['footer']).'</p>' : '')
            .'</div><div class="foot"><div class="sign">'.($s['signature'] !== '' ? $e($s['signature']).'<b>Signature</b>' : '').'</div>'
            .($qr ? '<div class="qr"><img src="'.$qr.'" alt=""><br>Scan to verify</div>' : '').'</div>'
            .'</body></html>';

        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return (string) $pdf->output();
    }
}
