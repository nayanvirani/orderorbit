<?php

namespace Tests\Feature;

use App\Automation\Context;
use App\Automation\Triggers;
use App\Models\AuditLog;
use App\Models\Automation\AutomationEmail;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Models\LegalPage;
use App\Models\Mail\EmailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Protected customer data: encryption at rest, retention, redaction and the access log. */
class CustomerDataProtectionTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private function run_(\App\Models\Store $store, array $over = []): WorkflowRun
    {
        $manager = app(\App\Automation\WorkflowManager::class);
        $workflow = Workflow::where('store_id', $store->id)->first() ?? $manager->create($store, 'vip-customer', null);
        if (! $workflow->published_version_id) {
            $manager->publish($workflow->fresh(), null);
            $workflow = $workflow->fresh();
        }
        $context = Context::fromOrder(['id' => 5001, 'name' => '#1052', 'customer' => ['id' => 900, 'email' => 'ana@example.com', 'first_name' => 'Ana', 'tags' => '']]);

        return WorkflowRun::create(array_merge(['store_id' => $store->id, 'workflow_id' => $workflow->id, 'version_id' => $workflow->published_version_id, 'trigger' => 'order_paid', 'idempotency_key' => uniqid('k', true), 'subject' => 'Order #1052',
            'status' => 'completed', 'step' => 0, 'attempts' => 0, 'depth' => 0, 'context' => $context, 'results' => [], 'test' => false], $over));
    }

    public function test_names_and_emails_are_encrypted_in_the_database(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore();
        $run = $this->run_($store);
        $email = AutomationEmail::create(['store_id' => $store->id, 'run_id' => $run->id, 'customer_id' => '900', 'to_email' => 'ana@example.com', 'subject' => 'Thanks Ana', 'body' => 'Hi Ana', 'status' => 'waiting_for_provider']);
        $delivery = EmailDelivery::create(['store_id' => $store->id, 'email_provider_id' => null, 'provider_name' => 'x', 'category' => 'automation', 'to_email' => 'ana@example.com', 'subject' => 'Thanks Ana', 'status' => 'sent']);

        foreach ([['automation_runs', $run->id, 'context'], ['automation_emails', $email->id, 'to_email'], ['automation_emails', $email->id, 'body'], ['email_deliveries', $delivery->id, 'to_email']] as [$table, $id, $column]) {
            $raw = DB::table($table)->where('id', $id)->value($column);
            $this->assertStringNotContainsString('ana', strtolower($raw), "{$table}.{$column} is stored encrypted");
        }
        // The app still reads them.
        $this->assertSame('ana@example.com', $run->fresh()->context['customer']['email']);
        $this->assertSame('Hi Ana', $email->fresh()->body);
        $this->assertSame($delivery->id, EmailDelivery::where('to_hash', EmailDelivery::hash(' Ana@Example.com'))->value('id'));
    }

    public function test_automation_data_is_deleted_after_12_months_and_on_customer_redact(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore();
        $old = $this->run_($store);
        $old->forceFill(['created_at' => now()->subMonths(13)])->save();
        $waiting = $this->run_($store, ['status' => 'waiting']);
        $waiting->forceFill(['created_at' => now()->subMonths(13)])->save();
        $recent = $this->run_($store);

        $this->artisan('orderorbit:prune-analytics')->assertSuccessful();
        $this->assertNull($old->fresh());
        $this->assertNotNull($waiting->fresh(), 'A run still waiting to continue is kept.');

        app(Triggers::class)->forget($store, '900');
        $this->assertNull($recent->fresh()->context['customer']);
    }

    public function test_views_of_customer_data_are_logged_and_the_security_policy_is_published(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $this->page('/app/automation/emails', $owner)->assertOk();
        $log = AuditLog::where('action', 'personal_data.viewed')->sole();
        $this->assertSame(['customer emails', (string) $owner->id, 'store_user'], [$log->context['what'], (string) $log->actor_id, $log->actor_type]);

        $this->assertTrue(LegalPage::where('slug', 'security')->where('is_published', true)->exists());
        $this->assertStringContainsString('within 72 hours', LegalPage::where('slug', 'security')->value('body'));
    }
}
