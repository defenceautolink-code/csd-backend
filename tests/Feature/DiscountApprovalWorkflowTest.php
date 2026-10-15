<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DiscountApproval;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountApprovalWorkflowTest extends TestCase
{
    protected $adminUser;
    protected $salesExecutiveUser;
    protected $testLead;

    protected function setUp(): void
    {
        parent::setUp();

        // Find or create admin user
        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_test@example.com'],
            [
                'name' => 'Alexander Vance',
                'phone' => '+91 99999 11111',
                'password' => bcrypt('password'),
                'role' => UserRole::SUPER_ADMIN->value,
                'status' => 'Active',
            ]
        );

        // Find or create sales executive
        $this->salesExecutiveUser = User::firstOrCreate(
            ['email' => 'executive_test@example.com'],
            [
                'name' => 'Amit Kumar',
                'phone' => '+91 99999 22222',
                'password' => bcrypt('password'),
                'role' => UserRole::SALES_EXECUTIVE->value,
                'status' => 'Active',
            ]
        );

        // Create test lead
        $this->testLead = Lead::create([
            'name' => 'Captain Vikram Rathore',
            'email' => 'vikram.test@example.com',
            'phone' => '9876543210',
            'model_variant' => 'Hyundai Creta Automatic',
            'priority' => 'Hot',
            'status_name' => 'New',
            'assigned_to' => $this->salesExecutiveUser->id,
            'assigned_user_name' => 'Amit Kumar (Sales Executive)',
        ]);
    }

    public function test_follow_up_creates_discount_approval_and_sets_lead_to_pending(): void
    {
        $payload = [
            'lead_id' => $this->testLead->id,
            'follow_up_date' => now()->toDateString(),
            'follow_up_time' => '14:30',
            'type' => 'Call',
            'notes' => 'Customer ready to pay token today if 25k discount given',
            'status' => 'Completed',
            'is_discount_requested' => true,
            'discount_amount' => 25000,
            'proposed_price' => 1625000,
            'reason' => 'Customer ready to pay token today if 25k discount given',
        ];

        $response = $this->actingAs($this->salesExecutiveUser, 'sanctum')
            ->postJson('/api/follow-ups', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.is_discount_requested', true);
        $response->assertJsonPath('data.discount_amount', 25000);

        // Assert DiscountApproval record created
        $approval = DiscountApproval::where('lead_id', $this->testLead->id)->latest('id')->first();
        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);
        $this->assertEquals(25000, $approval->discount_amount);
        $this->assertEquals(1625000, $approval->proposed_price);
        $this->assertEquals('Customer ready to pay token today if 25k discount given', $approval->reason);
        $this->assertEquals($this->salesExecutiveUser->id, $approval->requested_by);

        // Assert Lead was updated to "Discount Pending"
        $this->testLead->refresh();
        $this->assertEquals('Discount Pending', $this->testLead->status_name);
        $this->assertEquals('pending', $this->testLead->discount_approval_status);
        $this->assertEquals(25000, $this->testLead->requested_discount);
    }

    public function test_get_leads_api_includes_discount_badge_fields(): void
    {
        // Set lead state
        $this->testLead->update([
            'discount_approval_status' => 'pending',
            'requested_discount' => 25000,
            'approved_discount' => 0,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/leads?search=Captain+Vikram+Rathore');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $leadItem = collect($data)->firstWhere('id', $this->testLead->id);
        $this->assertNotNull($leadItem);
        $this->assertArrayHasKey('discount_approval_status', $leadItem);
        $this->assertArrayHasKey('requested_discount', $leadItem);
        $this->assertArrayHasKey('approved_discount', $leadItem);
        $this->assertEquals('pending', $leadItem['discount_approval_status']);
        $this->assertEquals(25000, $leadItem['requested_discount']);
        $this->assertEquals(0, $leadItem['approved_discount']);
    }

    public function test_pending_approvals_list_api_returns_expected_structure(): void
    {
        // Create pending approval
        $approval = DiscountApproval::create([
            'lead_id' => $this->testLead->id,
            'requested_by' => $this->salesExecutiveUser->id,
            'discount_amount' => 25000,
            'proposed_price' => 1625000,
            'reason' => 'Customer ready to pay token today if 25k discount given',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/discount-approvals/pending');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertIsArray($data);

        $card = collect($data)->firstWhere('id', $approval->id);
        $this->assertNotNull($card);
        $this->assertEquals($this->testLead->id, $card['lead_id']);
        $this->assertEquals('Captain Vikram Rathore', $card['customer_name']);
        $this->assertEquals('9876543210', $card['customer_phone']);
        $this->assertEquals('Hyundai Creta Automatic', $card['vehicle_name']);
        $this->assertStringContainsString('Amit Kumar', $card['requested_by_name']);
        $this->assertEquals(25000, $card['discount_amount']);
        $this->assertEquals(1625000, $card['proposed_price']);
        $this->assertEquals('Customer ready to pay token today if 25k discount given', $card['reason']);
        $this->assertNotEmpty($card['created_at']);
    }

    public function test_admin_can_approve_discount_request(): void
    {
        $approval = DiscountApproval::create([
            'lead_id' => $this->testLead->id,
            'requested_by' => $this->salesExecutiveUser->id,
            'discount_amount' => 25000,
            'proposed_price' => 1625000,
            'reason' => 'Customer ready to pay token today if 25k discount given',
            'status' => 'pending',
        ]);

        $actionPayload = [
            'action' => 'approve',
            'remarks' => 'Approved 25k discount for immediate booking',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/discount-approvals/{$approval->id}/action", $actionPayload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'approved');

        // Check database
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
        $this->assertEquals($this->adminUser->id, $approval->action_by);
        $this->assertEquals('Approved 25k discount for immediate booking', $approval->remarks);

        // Check linked lead
        $this->testLead->refresh();
        $this->assertEquals('Discount Approved', $this->testLead->status_name);
        $this->assertEquals('approved', $this->testLead->discount_approval_status);
        $this->assertEquals(25000, $this->testLead->approved_discount);
    }

    public function test_admin_can_reject_discount_request(): void
    {
        $approval = DiscountApproval::create([
            'lead_id' => $this->testLead->id,
            'requested_by' => $this->salesExecutiveUser->id,
            'discount_amount' => 30000,
            'reason' => 'Asking too much discount',
            'status' => 'pending',
        ]);

        $actionPayload = [
            'action' => 'reject',
            'remarks' => 'Maximum allowed discount is 15k only',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/discount-approvals/{$approval->id}/action", $actionPayload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'rejected');

        // Check database
        $approval->refresh();
        $this->assertEquals('rejected', $approval->status);
        $this->assertEquals($this->adminUser->id, $approval->action_by);
        $this->assertEquals('Maximum allowed discount is 15k only', $approval->remarks);

        // Check linked lead
        $this->testLead->refresh();
        $this->assertEquals('Discount Rejected', $this->testLead->status_name);
        $this->assertEquals('rejected', $this->testLead->discount_approval_status);
        $this->assertEquals(0, $this->testLead->approved_discount);
    }

    protected function tearDown(): void
    {
        // Clean up created records
        if ($this->testLead) {
            DiscountApproval::where('lead_id', $this->testLead->id)->delete();
            $this->testLead->followUps()->delete();
            $this->testLead->delete();
        }

        parent::tearDown();
    }
}
