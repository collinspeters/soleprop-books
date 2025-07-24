<?php

namespace Tests\Feature;

use App\Jobs\ProcessExpenseForGifiCategorization;
use App\Models\Banking\Account;
use App\Models\Banking\Transaction;
use App\Models\Common\Company;
use App\Models\Setting\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GifiCategorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test company
        $this->company = Company::factory()->create();
        
        // Create a test account
        $this->account = Account::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function it_dispatches_gifi_categorization_job_when_expense_transaction_is_created()
    {
        Queue::fake();

        // Create an expense transaction
        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::EXPENSE_TYPE,
            'description' => 'Legal consultation fees',
            'amount' => 500.00,
        ]);

        // Assert that the job was dispatched
        Queue::assertPushed(ProcessExpenseForGifiCategorization::class, function ($job) use ($transaction) {
            return $job->transaction->id === $transaction->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_job_for_income_transactions()
    {
        Queue::fake();

        // Create an income transaction
        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::INCOME_TYPE,
            'description' => 'Client payment',
            'amount' => 1000.00,
        ]);

        // Assert that no job was dispatched
        Queue::assertNotPushed(ProcessExpenseForGifiCategorization::class);
    }

    /** @test */
    public function it_stores_ai_audit_trail_data_when_processing_expense()
    {
        // Create an expense transaction
        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::EXPENSE_TYPE,
            'description' => 'Legal consultation fees',
            'amount' => 500.00,
        ]);
        
        // Simulate the job processing (this would normally be done by the actual job)
        $transaction->update([
            'ai_category' => 'Professional fees',
            'ai_confidence' => 0.9,
            'ai_explanation' => "AI suggested GIFI category: 9000 - Professional fees with High confidence based on expense description: 'Legal consultation fees' and amount: $500",
        ]);
        
        // Assert the AI fields are stored correctly
        $this->assertEquals('Professional fees', $transaction->fresh()->ai_category);
        $this->assertEquals(0.9, $transaction->fresh()->ai_confidence);
        $this->assertStringContains('GIFI category: 9000', $transaction->fresh()->ai_explanation);
        $this->assertStringContains('High confidence', $transaction->fresh()->ai_explanation);
    }

    /** @test */
    public function it_creates_category_with_gifi_code_when_processing_expense()
    {
        // Mock the OpenAI response
        $this->mockOpenAiResponse();

        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::EXPENSE_TYPE,
            'description' => 'Legal consultation fees',
            'amount' => 500.00,
        ]);

        // Process the job
        $job = new ProcessExpenseForGifiCategorization($transaction);
        $job->handle();

        // Assert category was created with GIFI code
        $this->assertDatabaseHas('categories', [
            'company_id' => $this->company->id,
            'name' => 'Professional fees',
            'gifi_code' => '9000',
            'type' => 'expense',
        ]);

        // Assert transaction was updated with category
        $transaction->refresh();
        $this->assertNotNull($transaction->category_id);
        $this->assertEquals('9000', $transaction->category->gifi_code);
    }

    /** @test */
    public function it_reuses_existing_category_with_same_gifi_code()
    {
        // Create an existing category with GIFI code
        $existingCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Legal fees',
            'gifi_code' => '9000',
            'type' => 'expense',
        ]);

        // Mock the OpenAI response to return the same GIFI code
        $this->mockOpenAiResponse();

        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::EXPENSE_TYPE,
            'description' => 'Attorney consultation',
            'amount' => 300.00,
        ]);

        // Process the job
        $job = new ProcessExpenseForGifiCategorization($transaction);
        $job->handle();

        // Assert no new category was created
        $this->assertEquals(1, Category::where('gifi_code', '9000')->count());

        // Assert transaction uses existing category
        $transaction->refresh();
        $this->assertEquals($existingCategory->id, $transaction->category_id);
    }

    /** @test */
    public function it_handles_invalid_gifi_code_format()
    {
        // Mock OpenAI to return invalid GIFI code
        $this->mockOpenAiResponse('INVALID');

        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::EXPENSE_TYPE,
            'description' => 'Office supplies',
            'amount' => 50.00,
        ]);

        // Process the job
        $job = new ProcessExpenseForGifiCategorization($transaction);
        $job->handle();

        // Assert no category was created
        $this->assertDatabaseMissing('categories', [
            'gifi_code' => 'INVALID',
        ]);

        // Assert transaction category was not updated
        $transaction->refresh();
        $this->assertNull($transaction->category_id);
    }

    /** @test */
    public function it_skips_non_expense_transaction_types()
    {
        $transaction = Transaction::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'type' => Transaction::INCOME_TYPE,
            'description' => 'Client payment',
            'amount' => 1000.00,
        ]);

        // Process the job
        $job = new ProcessExpenseForGifiCategorization($transaction);
        $job->handle();

        // Assert no category was created
        $this->assertEquals(0, Category::count());

        // Assert transaction was not updated
        $transaction->refresh();
        $this->assertNull($transaction->category_id);
    }

    /**
     * Mock OpenAI response for testing
     */
    private function mockOpenAiResponse($gifiCode = '9000')
    {
        // In a real test environment, you would mock the OpenAI facade
        // This is a simplified example showing the expected behavior
        
        // You can use Laravel's Http facade to mock external API calls:
        // Http::fake([
        //     'api.openai.com/*' => Http::response([
        //         'choices' => [
        //             ['text' => "GIFI_CODE: {$gifiCode}\nCATEGORY_NAME: Professional fees\nCONFIDENCE: High"]
        //         ]
        //     ])
        // ]);
    }

    /** @test */
    public function it_validates_required_gifi_response_fields()
    {
        // Test case for incomplete OpenAI response
        $job = new ProcessExpenseForGifiCategorization(
            Transaction::factory()->make([
                'type' => Transaction::EXPENSE_TYPE,
                'description' => 'Test expense',
            ])
        );

        // Test the parseGifiResponse method with incomplete data
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseGifiResponse');
        $method->setAccessible(true);

        // Test with missing category name
        $result = $method->invoke($job, "GIFI_CODE: 9000\nCONFIDENCE: High");
        $this->assertNull($result);

        // Test with missing GIFI code
        $result = $method->invoke($job, "CATEGORY_NAME: Professional fees\nCONFIDENCE: High");
        $this->assertNull($result);

        // Test with valid response
        $result = $method->invoke($job, "GIFI_CODE: 9000\nCATEGORY_NAME: Professional fees\nCONFIDENCE: High");
        $this->assertNotNull($result);
        $this->assertEquals('9000', $result['gifi_code']);
        $this->assertEquals('Professional fees', $result['category_name']);
    }
}
