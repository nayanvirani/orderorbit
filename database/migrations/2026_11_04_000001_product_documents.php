<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Product documents in automation emails (G42): stored privately by Growvia, not in Shopify Files. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 20); // file | certificate
            // A PDF uploaded once (base64, so every database stores it the same way).
            $table->string('file_name', 160)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->longText('file_data')->nullable();
            // Which orders: products and variants, a collection, a product tag or every order.
            $table->string('match', 20)->default('products');
            $table->json('match_items')->nullable();
            $table->string('match_tag', 120)->nullable();
            $table->string('per', 10)->default('item'); // item | line | order
            $table->json('certificate')->nullable();
            $table->longText('logo')->nullable(); // a small image as a data URI
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['store_id', 'active']);
        });

        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_document_id')->constrained()->cascadeOnDelete();
            $table->string('order_id', 40);
            $table->string('order_name', 40)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->string('line_key', 40); // variant id, product id or "order"
            $table->unsignedSmallInteger('unit')->default(1);
            $table->string('serial', 80)->nullable();
            $table->string('customer_name', 120)->nullable();
            $table->string('product_title', 255)->nullable();
            $table->string('variant_title', 255)->nullable();
            $table->string('token', 64)->unique();
            $table->string('verify_code', 24)->unique();
            $table->timestamp('expires_at');
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
            $table->unique(['product_document_id', 'order_id', 'line_key', 'unit'], 'issued_documents_once');
            $table->index(['store_id', 'order_id']);
        });

        Schema::table('automation_emails', function (Blueprint $table) {
            // Issued documents for this email and how they're sent: attach | link.
            $table->json('documents')->nullable();
            $table->string('documents_as', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('automation_emails', fn (Blueprint $table) => $table->dropColumn(['documents', 'documents_as']));
        Schema::dropIfExists('issued_documents');
        Schema::dropIfExists('product_documents');
    }
};
