<?php

namespace App\Http\Controllers;

use App\Models\Documents\IssuedDocument;
use App\Services\Documents\ProductDocuments;
use Illuminate\Http\Response;

/**
 * Product documents for shoppers (G42): the private download link from an email (valid 30 days)
 * and the public page a certificate's QR code opens to confirm it is genuine.
 */
class DocumentController extends Controller
{
    public function download(string $token, ProductDocuments $documents): Response
    {
        $issued = IssuedDocument::with('document')->where('token', $token)->first();
        if (! $issued || ! $issued->document) {
            return response()->view('documents.message', ['title' => 'Document not found', 'text' => 'This link isn\'t valid. Check that you copied the whole link from the email.'], 404);
        }
        if ($issued->expires_at->isPast()) {
            return response()->view('documents.message', ['title' => 'This link has expired', 'text' => 'Download links work for '.IssuedDocument::LINK_DAYS.' days. Contact the store for a new copy.'], 410);
        }
        $issued->increment('downloads');

        return response($documents->pdf($issued), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$issued->fileName().'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function verify(string $code): Response
    {
        $issued = IssuedDocument::with(['document', 'store'])->where('verify_code', $code)->first();
        if (! $issued || ! $issued->document || $issued->document->kind !== 'certificate') {
            return response()->view('documents.message', ['title' => 'Certificate not found', 'text' => 'We couldn\'t find a certificate with this code. It may be a copy, or the code was mistyped.'], 404);
        }

        // Only what proves it is genuine: no customer name or contact details.
        return response()->view('documents.verify', [
            'store' => $issued->store->name ?: $issued->store->shop_domain,
            'document' => $issued->document->name,
            'item' => trim(implode(' · ', array_filter([$issued->product_title, $issued->variant_title]))),
            'serial' => $issued->serial,
            'date' => $issued->ordered_at?->format('j F Y'),
        ]);
    }
}
