<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Documents\IssuedDocument;
use App\Models\Documents\ProductDocument;
use App\Models\Store;
use App\Services\Documents\ProductDocuments;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Support\Features;
use App\Support\Spa\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Automation › Product documents (G42): certificates and PDFs sent with workflow emails. */
class ProductDocumentController extends Controller
{
    public function index(Store $store): Page
    {
        $this->gate($store);
        $since = now()->subDays(30);
        $sent = IssuedDocument::where('store_id', $store->id)->where('created_at', '>=', $since)->selectRaw('product_document_id, count(*) as total')
            ->groupBy('product_document_id')->pluck('total', 'product_document_id');

        return page('automation/documents', [
            'documents' => ProductDocument::where('store_id', $store->id)->orderBy('name')
                ->get(['id', 'name', 'kind', 'file_name', 'file_size', 'match', 'match_items', 'match_tag', 'per', 'certificate', 'active', 'updated_at'])
                ->map(fn (ProductDocument $d) => [
                    'id' => $d->id, 'name' => $d->name, 'kind' => $d->kind, 'file_name' => $d->file_name, 'file_size' => $d->file_size,
                    'match' => $d->match, 'match_items' => $d->match_items ?? [], 'match_tag' => $d->match_tag, 'per' => $d->per,
                    'certificate' => $d->certificateSettings(), 'active' => $d->active, 'sent' => (int) ($sent[$d->id] ?? 0),
                    'has_logo' => ProductDocument::whereKey($d->id)->whereNotNull('logo')->exists(),
                    'preview' => route('app.automation.documents.preview', ['document' => $d->id], false),
                ]),
            'linkDays' => IssuedDocument::LINK_DAYS,
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $this->gate($store);
        $document = new ProductDocument(['store_id' => $store->id]);
        $this->fill($request, $document, true);

        return redirect()->to(app_route('app.automation.documents', ['notice' => 'document_saved', 'open' => $document->id]));
    }

    public function update(Request $request, Store $store, int $document): RedirectResponse
    {
        $this->gate($store);
        $this->fill($request, $this->find($store, $document), false);

        return redirect()->to(app_route('app.automation.documents', ['notice' => 'document_saved', 'open' => $document]));
    }

    public function destroy(Store $store, int $document): RedirectResponse
    {
        $this->gate($store);
        $this->find($store, $document)->delete();

        return redirect()->to(app_route('app.automation.documents', ['notice' => 'document_deleted']));
    }

    /** The document as a customer would get it, filled with sample order details. */
    public function preview(Store $store, int $document, ProductDocuments $documents): Response
    {
        $this->gate($store);

        return response($documents->sample($store, $this->find($store, $document)), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="sample.pdf"', 'Cache-Control' => 'private, no-store',
        ]);
    }

    /** "Send a test": the sample document emailed to the person clicking. */
    public function test(Request $request, Store $store, int $document, ProductDocuments $documents, EmailSender $sender): RedirectResponse
    {
        $this->gate($store);
        $doc = $this->find($store, $document);
        $to = $request->attributes->get('storeUser')?->email ?: $store->email;
        if (! $to) {
            throw ValidationException::withMessages(['test' => 'Your Shopify account has no email address to send the test to.']);
        }
        $result = $sender->send(OutgoingEmail::fromText($to, 'Test: '.$doc->name, "This is how \"{$doc->name}\" looks when a workflow email sends it. The attached sample uses made-up order details.", [
            'fromName' => $store->name ?: null, 'category' => 'automation', 'storeId' => $store->id,
            'attachments' => [['name' => 'sample-'.preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($doc->name)).'.pdf', 'mime' => 'application/pdf', 'data' => $documents->sample($store, $doc)]],
        ]));

        return redirect()->to(app_route('app.automation.documents', ['notice' => $result->ok() ? 'document_test_sent' : 'document_test_failed', 'open' => $doc->id]));
    }

    private function fill(Request $request, ProductDocument $document, bool $creating): void
    {
        $data = json_decode((string) $request->input('data', '{}'), true) ?: [];
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:file,certificate'],
            'match' => ['required', 'in:products,collection,tag,all'],
            'match_items' => ['array', 'max:100'],
            'match_items.*.id' => ['required', 'string', 'regex:#^gid://shopify/(Product|Collection)/\d+$#'],
            'match_items.*.title' => ['nullable', 'string', 'max:255'],
            'match_items.*.variants' => ['nullable', 'array', 'max:100'],
            'match_items.*.variants.*' => ['string', 'regex:#^gid://shopify/ProductVariant/\d+$#'],
            'match_tag' => ['nullable', 'string', 'max:120'],
            'per' => ['required', 'in:item,line,order'],
            'active' => ['boolean'],
            'remove_logo' => ['boolean'],
            'certificate.heading' => ['nullable', 'string', 'max:80'],
            'certificate.intro' => ['nullable', 'string', 'max:120'],
            'certificate.owns' => ['nullable', 'string', 'max:40'],
            'certificate.show_name' => ['boolean'],
            'certificate.serial_prefix' => ['nullable', 'string', 'max:12'],
            'certificate.signature' => ['nullable', 'string', 'max:80'],
            'certificate.footer' => ['nullable', 'string', 'max:200'],
            'certificate.show_qr' => ['boolean'],
            'certificate.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['match_tag' => 'tag']);
        $validator->after(function ($v) use ($data, $request, $document, $creating) {
            if (($data['match'] ?? '') === 'tag' && trim((string) ($data['match_tag'] ?? '')) === '') {
                $v->errors()->add('match_tag', 'Enter the product tag.');
            }
            if (in_array($data['match'] ?? '', ['products', 'collection'], true) && empty($data['match_items'])) {
                $v->errors()->add('match_items', ($data['match'] ?? '') === 'collection' ? 'Choose a collection.' : 'Choose at least one product.');
            }
            if (($data['kind'] ?? '') === 'file' && ! $request->hasFile('file') && ($creating || ! $document->file_data)) {
                $v->errors()->add('file', 'Upload the PDF to send.');
            }
        });
        $data = $validator->validate();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $bytes = (string) file_get_contents($file->getRealPath());
            if (! str_starts_with($bytes, '%PDF') || strlen($bytes) > ProductDocument::MAX_FILE) {
                throw ValidationException::withMessages(['file' => 'Upload a PDF of 10 MB or less.']);
            }
            $document->fill(['file_name' => mb_substr(preg_replace('/[^\w .()-]+/u', '', $file->getClientOriginalName()) ?: 'document.pdf', 0, 160), 'file_size' => strlen($bytes), 'file_data' => base64_encode($bytes)]);
        }
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $mime = $logo->getMimeType();
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true) || $logo->getSize() > 500 * 1024) {
                throw ValidationException::withMessages(['logo' => 'Use a PNG, JPG or GIF logo of 500 KB or less.']);
            }
            $document->logo = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logo->getRealPath()));
        } elseif (! empty($data['remove_logo'])) {
            $document->logo = null;
        }

        $match = $data['match'];
        $document->fill([
            'name' => trim($data['name']), 'kind' => $data['kind'], 'match' => $match, 'per' => $data['per'],
            'match_items' => in_array($match, ['products', 'collection'], true) ? array_values(array_map(fn ($i) => array_filter([
                'id' => $i['id'], 'title' => $i['title'] ?? null, 'variants' => $match === 'products' ? array_values($i['variants'] ?? []) : null,
            ], fn ($x) => $x !== null && $x !== []), $match === 'collection' ? array_slice($data['match_items'], 0, 1) : $data['match_items'])) : null,
            'match_tag' => $match === 'tag' ? trim((string) $data['match_tag']) : null,
            'certificate' => array_intersect_key((array) ($data['certificate'] ?? []), array_flip(['heading', 'intro', 'owns', 'show_name', 'serial_prefix', 'signature', 'footer', 'show_qr', 'accent'])),
            'active' => $data['active'] ?? true,
        ]);
        if ($data['kind'] === 'certificate') {
            $document->fill(['file_name' => null, 'file_size' => null, 'file_data' => null]);
        }
        $document->save();
    }

    private function find(Store $store, int $id): ProductDocument
    {
        return ProductDocument::where('store_id', $store->id)->findOrFail($id);
    }

    private function gate(Store $store): void
    {
        abort_unless(Features::enabled('product_documents', $store), 404);
    }
}
