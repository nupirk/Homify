<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PartnerBalance;
use App\Models\PartnerPayout;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\LedgerService;

/**
 * Payout chain: withdrawal deduction, finalize (completed once), and fail
 * (restore only when money was actually deducted - never on a bare
 * processing_started_at flag).
 */
class PayoutLedgerTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $partner;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'System',
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));
        \Illuminate\Support\Facades\DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

        $this->partner = User::create([
            'first_name' => 'Test', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner', 'is_active' => true,
        ]);
        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        // Seed a positive balance: a past completed job's earning.
        $this->order = Order::unguarded(fn () => Order::create([
            'order_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => 1,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'online',
            'subtotal' => 1000.00,
            'total_amount' => 1000,
            'final_payable' => 1000,
        ]));

        app(LedgerService::class)->recordPartnerEarning(
            $this->order->order_id,
            $this->partner->user_id,
            800.00,
            ['service_id' => null, 'subtotal' => 1000.00]
        );

        PartnerBalance::getOrCreateForPartner($this->partner->user_id);
        PartnerBalance::recalculateForPartner($this->partner->user_id);
    }

    private function makePayout(string $status = 'pending'): PartnerPayout
    {
        return PartnerPayout::create([
            'partner_id' => $this->partner->user_id,
            'amount' => 500.00,
            'platform_fee' => 10.00,
            'net_amount' => 490.00,
            'status' => $status,
            'payment_method' => 'esewa',
            'transaction_reference' => 'PAYOUT-' . \Illuminate\Support\Str::uuid()->toString(),
        ]);
    }

    public function test_withdrawal_and_finalize_posts_entries_once(): void
    {
        $payout = $this->makePayout();
        $service = app(LedgerService::class);

        // Deduct: WITHDRAWAL (net) + PLATFORM_FEE (fee) on the partner.
        $this->assertTrue($service->recordPayoutWithdrawal($payout));
        $withdrawal = LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'WITHDRAWAL')->where('metadata->payout_id', $payout->payout_id)->first();
        $fee = LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'PLATFORM_FEE')->where('metadata->payout_id', $payout->payout_id)->first();
        $this->assertNotNull($withdrawal);
        $this->assertNotNull($fee);
        $this->assertEqualsWithDelta(490.00, (float) $withdrawal->amount, 0.01);
        $this->assertEqualsWithDelta(10.00, (float) $fee->amount, 0.01);

        // A second withdrawal call is a no-op (narration guard).
        $this->assertFalse($service->recordPayoutWithdrawal($payout));
        $this->assertSame(1, LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'WITHDRAWAL')->where('metadata->payout_id', $payout->payout_id)->count());

        // Finalize: status completed, marker entry appended, no duplicate withdrawal.
        $this->assertTrue($service->finalizePayout($payout->payout_id, ['source' => 'admin']));
        $this->assertFalse($service->finalizePayout($payout->payout_id, ['source' => 'webhook']));
        $this->assertSame('completed', $payout->refresh()->status);
        $this->assertSame(1, LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'WITHDRAWAL')->where('metadata->payout_id', $payout->payout_id)->count());
        $this->assertSame(1, LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'WITHDRAWAL_COMPLETED')->where('metadata->payout_id', $payout->payout_id)->count());
    }

    public function test_fail_payout_restores_balance_only_when_deducted(): void
    {
        $service = app(LedgerService::class);

        // Payout failed BEFORE any withdrawal was deducted: balance untouched.
        $payout = $this->makePayout('processing');
        $before = PartnerBalance::where('partner_id', $this->partner->user_id)->first()->available_balance;
        $this->assertTrue($service->failPayout($payout->payout_id, 'bank declined', 'esewa'));
        $this->assertSame('failed', $payout->refresh()->status);
        $this->assertEqualsWithDelta(
            (float) $before,
            (float) PartnerBalance::where('partner_id', $this->partner->user_id)->first()->available_balance,
            0.01,
            'No deduction yet - balance must not change'
        );
        $this->assertSame(0, LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'WITHDRAWAL_REVERSAL')->where('metadata->payout_id', $payout->payout_id)->count());

        // Payout failed AFTER withdrawal: gross amount restored, net withdrawn reversed.
        $deducted = $this->makePayout('processing');
        $this->assertTrue($service->recordPayoutWithdrawal($deducted));
        $before = PartnerBalance::where('partner_id', $this->partner->user_id)->first()->available_balance;
        $this->assertTrue($service->failPayout($deducted->payout_id, 'bank declined', 'esewa'));
        $this->assertSame('failed', $deducted->refresh()->status);
        $this->assertEqualsWithDelta(
            (float) $before + 500.00,
            (float) PartnerBalance::where('partner_id', $this->partner->user_id)->first()->available_balance,
            0.01,
            'Deducted payout must be restored on failure'
        );

        // Fail is idempotent on an already-failed payout.
        $this->assertFalse($service->failPayout($deducted->payout_id, 'again', 'admin'));
    }

    public function test_connectips_payout_request_is_accepted(): void
    {
        Sanctum::actingAs($this->partner, ['*']);
        $response = $this->postJson('/api/v1/payouts/request', [
            'amount' => 200.00,
            'payment_method' => 'connectips',
            'connectips_details' => [
                'account_number' => '1234567890',
                'account_name' => 'Test Partner',
                'bank_code' => 'NIBL',
                'bank_name' => 'NIC ASIA Bank',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $payout = PartnerPayout::where('partner_id', $this->partner->user_id)
            ->where('payment_method', 'connectips')
            ->first();
        $this->assertNotNull($payout);
        $this->assertSame('pending', $payout->status);
        $this->assertSame('1234567890', $payout->bank_transfer_details['account_number'] ?? null);
    }

    /**
     * The admin Fail action must leave no payout in `processing`: a processing
     * payout failed from the payouts screen becomes failed and its deducted
     * amount returns to the partner.
     */
    public function test_admin_fail_action_moves_processing_payout_out_of_processing(): void
    {
        $admin = User::where('user_type', 'admin')->first();
        $this->assertNotNull($admin);

        $payout = $this->makePayout('processing');
        app(LedgerService::class)->recordPayoutWithdrawal($payout);
        $afterDeduction = (float) PartnerBalance::where('partner_id', $this->partner->user_id)->first()->available_balance;

        // Both screens must render a processing payout with its resolve actions.
        $this->actingAs($admin)->get(route('admin.ledger.partner-payouts'))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.ledger.partner-payouts.details', $payout->payout_id))
            ->assertOk()
            ->assertSee('Resolve this payout');

        $this->actingAs($admin)
            ->post(route('admin.ledger.partner-payouts.fail'), [
                'payout_id' => $payout->payout_id,
                'failure_reason' => 'Bank rejected the transfer',
            ])
            ->assertRedirect();

        $payout->refresh();
        $this->assertSame('failed', $payout->status, 'A failed payout must not stay in processing');
        $this->assertSame('Bank rejected the transfer', $payout->failure_reason);
        $this->assertEqualsWithDelta(
            $afterDeduction + 500.00,
            (float) PartnerBalance::where('partner_id', $this->partner->user_id)->first()->available_balance,
            0.01,
            'Failing a deducted payout must return the gross amount'
        );
    }

    public function test_connectips_payout_requires_details(): void
    {
        Sanctum::actingAs($this->partner, ['*']);
        $response = $this->postJson('/api/v1/payouts/request', [
            'amount' => 200.00,
            'payment_method' => 'connectips',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, PartnerPayout::where('partner_id', $this->partner->user_id)->count());
    }

    /**
     * A payment that failed before any transfer must be closable from the
     * admin screen without first marking the payout "processing" (which would
     * deduct the partner's money).
     */
    public function test_admin_can_fail_a_pending_payout_without_processing_it(): void
    {
        $admin = User::where('user_type', 'admin')->first();
        $payout = $this->makePayout('pending');

        $this->actingAs($admin)
            ->post(route('admin.ledger.partner-payouts.fail'), [
                'payout_id' => $payout->payout_id,
                'failure_reason' => 'eSewa transfer could not be sent',
            ])
            ->assertRedirect();

        $payout->refresh();
        $this->assertSame('failed', $payout->status);
        $this->assertSame(0, LedgerEntry::where('user_id', $this->partner->user_id)
            ->where('type', 'WITHDRAWAL')->count(), 'A pending payout never deducted, so nothing to reverse');

        // Both admin screens must render the failed payout with its reason.
        $this->actingAs($admin)->get(route('admin.ledger.partner-payouts'))->assertOk();
        $this->actingAs($admin)->get(route('admin.ledger.partner-payouts.details', $payout->payout_id))->assertOk();
    }
}