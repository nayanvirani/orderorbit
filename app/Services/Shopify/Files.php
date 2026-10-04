<?php

namespace App\Services\Shopify;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uploads merchant images to the store's Shopify Files (Content → Files), so blocks use Shopify's
 * CDN and the images stay with the store. Needs the write_files scope.
 */
class Files
{
    public function __construct(private readonly AdminApi $api) {}

    /** @return string the image's Shopify CDN URL */
    public function uploadImage(Store $store, UploadedFile $file, string $alt = ''): string
    {
        $target = $this->api->graphql($store, 'mutation ($input: [StagedUploadInput!]!) { stagedUploadsCreate(input: $input) { stagedTargets { url resourceUrl parameters { name value } } userErrors { message } } }', [
            'input' => [[
                'resource' => 'IMAGE', 'httpMethod' => 'POST',
                'filename' => $this->filename($file), 'mimeType' => $file->getMimeType(), 'fileSize' => (string) $file->getSize(),
            ]],
        ])['stagedUploadsCreate'] ?? [];
        if ($error = $target['userErrors'][0]['message'] ?? null) {
            throw new RuntimeException($error);
        }
        $staged = $target['stagedTargets'][0] ?? null;
        if (! $staged) {
            throw new RuntimeException('Shopify did not accept the upload.');
        }

        // The file must come after the signed parameters.
        $params = collect($staged['parameters'])->mapWithKeys(fn ($p) => [$p['name'] => $p['value']])->all();
        $upload = Http::timeout(60)->attach('file', $file->get(), $this->filename($file))->post($staged['url'], $params);
        if ($upload->failed()) {
            throw new RuntimeException('The upload to Shopify failed (HTTP '.$upload->status().').');
        }

        $created = $this->api->graphql($store, 'mutation ($files: [FileCreateInput!]!) { fileCreate(files: $files) { files { id fileStatus ... on MediaImage { image { url } } } userErrors { message } } }', [
            'files' => [['originalSource' => $staged['resourceUrl'], 'contentType' => 'IMAGE', 'alt' => mb_substr($alt, 0, 200)]],
        ])['fileCreate'] ?? [];
        if ($error = $created['userErrors'][0]['message'] ?? null) {
            throw new RuntimeException($error);
        }
        $node = $created['files'][0] ?? null;
        if (! $node) {
            throw new RuntimeException('Shopify did not create the file.');
        }

        // Shopify processes the image for a moment before its CDN link exists.
        for ($try = 0; empty($node['image']['url']) && $try < 10; $try++) {
            if (($node['fileStatus'] ?? '') === 'FAILED') {
                throw new RuntimeException('Shopify could not process this image. Try another file.');
            }
            usleep(app()->runningUnitTests() ? 0 : 800_000);
            $node = $this->api->graphql($store, 'query ($id: ID!) { node(id: $id) { ... on MediaImage { id fileStatus image { url } } } }', ['id' => $node['id']])['node'] ?? [];
        }
        if (empty($node['image']['url'])) {
            throw new RuntimeException('Shopify is still processing the image. Wait a moment and choose it again, or paste its link from Content → Files.');
        }

        return $node['image']['url'];
    }

    private function filename(UploadedFile $file): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()) ?: 'image';

        return 'orderorbit-'.mb_substr(trim($name, '-.'), 0, 80);
    }
}
