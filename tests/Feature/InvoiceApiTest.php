<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_invoice_for_sample_1_mohammed_and_persists_minor_units(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'محمد الحميري',
            'items' => [
                ['name' => 'رز بسمتي 10 كيلو', 'qty' => 1, 'price' => 18000],
                ['name' => 'سكر 5 كيلو', 'qty' => 2, 'price' => 6500],
            ],
        ]);

        $response->assertCreated();

        $data = $response->json();

        $this->assertArrayNotHasKey('data', $data);
        $this->assertSame('محمد الحميري', $data['customer']);
        $this->assertSame('YER', $data['currency']);
        $this->assertSame(0.15, $data['tax_rate']);
        $this->assertSame(31000, $data['subtotal']);
        $this->assertSame(4650, $data['tax']);
        $this->assertSame(35650, $data['total']);
        $this->assertCount(2, $data['items']);
        $this->assertSame(1, $data['items'][0]['qty']);
        $this->assertSame(18000, $data['items'][0]['price']);
        $this->assertSame(18000, $data['items'][0]['line_total']);
        $this->assertSame(2, $data['items'][1]['qty']);
        $this->assertSame(6500, $data['items'][1]['price']);
        $this->assertSame(13000, $data['items'][1]['line_total']);
        $this->assertNotEmpty($data['created_at']);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'id' => $data['id'],
            'customer' => 'محمد الحميري',
            'subtotal' => 3100000,
            'tax' => 465000,
            'total' => 3565000,
            'currency' => 'YER',
        ]);

        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $data['id'],
            'name' => 'رز بسمتي 10 كيلو',
            'price' => 1800000,
            'line_total' => 1800000,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $data['id'],
            'name' => 'سكر 5 كيلو',
            'price' => 650000,
            'line_total' => 1300000,
        ]);
    }

    public function test_creates_invoice_for_sample_2_fatima(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'فاطمة العريقي',
            'items' => [
                ['name' => 'شاهي 450 جرام', 'qty' => 3, 'price' => 3100],
            ],
        ]);

        $response->assertCreated();

        $data = $response->json();

        $this->assertSame('فاطمة العريقي', $data['customer']);
        $this->assertSame(9300, $data['subtotal']);
        $this->assertSame(1395, $data['tax']);
        $this->assertSame(10695, $data['total']);
        $this->assertCount(1, $data['items']);
        $this->assertSame(9300, $data['items'][0]['line_total']);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);
    }

    public function test_creates_invoice_for_sample_3_abdullah(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'عبدالله الشامي',
            'items' => [
                ['name' => 'عسل سدر 500 جرام', 'qty' => 1, 'price' => 22000],
                ['name' => 'قهوة يمنية 250 جرام', 'qty' => 2, 'price' => 5200],
                ['name' => 'تونة (4 علب)', 'qty' => 1, 'price' => 3600],
            ],
        ]);

        $response->assertCreated();

        $data = $response->json();

        $this->assertSame('عبدالله الشامي', $data['customer']);
        $this->assertSame(36000, $data['subtotal']);
        $this->assertSame(5400, $data['tax']);
        $this->assertSame(41400, $data['total']);
        $this->assertCount(3, $data['items']);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 3);
    }

    public function test_ignores_client_supplied_totals_and_calculates_server_side(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'خالد العولقي',
            'subtotal' => 1,
            'tax' => 0,
            'total' => 1,
            'items' => [
                ['name' => 'حليب نيدو 900 جرام', 'qty' => 1, 'price' => 9800, 'line_total' => 1],
            ],
        ]);

        $response->assertCreated();

        $data = $response->json();

        $this->assertSame('خالد العولقي', $data['customer']);
        $this->assertSame(9800, $data['subtotal']);
        $this->assertSame(1470, $data['tax']);
        $this->assertSame(11270, $data['total']);
        $this->assertSame(9800, $data['items'][0]['line_total']);
    }

    public function test_calculates_fractional_prices_with_half_up_rounding(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'عميل تجريبي',
            'items' => [
                ['name' => 'صنف عشري', 'qty' => 1, 'price' => 10.33],
            ],
        ]);

        $response->assertCreated();

        $data = $response->json();

        $this->assertSame(10.33, $data['subtotal']);
        $this->assertSame(1.55, $data['tax']);
        $this->assertSame(11.88, $data['total']);
    }

    public function test_calculates_fractional_quantities_with_half_up_rounding(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'عميل كسري',
            'items' => [
                ['name' => 'صنف بالوزن', 'qty' => 1.5, 'price' => 33.33],
            ],
        ]);

        $response->assertCreated();

        $data = $response->json();

        $this->assertSame(1.5, $data['items'][0]['qty']);
        $this->assertSame(50, $data['subtotal']);
        $this->assertSame(7.5, $data['tax']);
        $this->assertSame(57.5, $data['total']);
    }

    public function test_rejects_negative_quantity_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'سارة المقطري',
            'items' => [
                ['name' => 'زيت طبخ 1.5 لتر', 'qty' => -2, 'price' => 4500],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.qty']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_zero_quantity_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'سارة المقطري',
            'items' => [
                ['name' => 'زيت طبخ 1.5 لتر', 'qty' => 0, 'price' => 4500],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.qty']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_missing_price_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'أحمد الصبري',
            'items' => [
                ['name' => 'دقيق أبيض 10 كيلو', 'qty' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_zero_price_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'أحمد الصبري',
            'items' => [
                ['name' => 'دقيق أبيض 10 كيلو', 'qty' => 1, 'price' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_negative_price_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'أحمد الصبري',
            'items' => [
                ['name' => 'دقيق أبيض 10 كيلو', 'qty' => 1, 'price' => -500],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_empty_items_array_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer' => 'نورة الأغبري',
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_missing_customer_with_422_and_zero_db_writes(): void
    {
        $response = $this->postJson('/api/invoices', [
            'items' => [
                ['name' => 'سكر 5 كيلو', 'qty' => 1, 'price' => 6500],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_forces_json_422_without_accept_json_header(): void
    {
        $response = $this->post('/api/invoices', [
            'customer' => '',
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['customer', 'items']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_forces_json_404_without_accept_json_header(): void
    {
        $response = $this->get('/api/invoices/99999');

        $response->assertStatus(404)
            ->assertExactJson(['message' => 'Invoice not found.']);
    }

    public function test_shows_single_invoice_with_items_and_without_data_wrapper(): void
    {
        $created = $this->postJson('/api/invoices', [
            'customer' => 'محمد الحميري',
            'items' => [
                ['name' => 'رز بسمتي 10 كيلو', 'qty' => 1, 'price' => 18000],
                ['name' => 'سكر 5 كيلو', 'qty' => 2, 'price' => 6500],
            ],
        ])->assertCreated()->json();

        $response = $this->getJson("/api/invoices/{$created['id']}");

        $response->assertOk();

        $data = $response->json();

        $this->assertArrayNotHasKey('data', $data);
        $this->assertSame($created['id'], $data['id']);
        $this->assertSame('محمد الحميري', $data['customer']);
        $this->assertSame('YER', $data['currency']);
        $this->assertSame(0.15, $data['tax_rate']);
        $this->assertSame(31000, $data['subtotal']);
        $this->assertSame(4650, $data['tax']);
        $this->assertSame(35650, $data['total']);
        $this->assertCount(2, $data['items']);
        $this->assertSame(18000, $data['items'][0]['line_total']);
        $this->assertSame(13000, $data['items'][1]['line_total']);
    }

    public function test_lists_searches_and_paginates_invoices(): void
    {
        $this->postJson('/api/invoices', [
            'customer' => 'محمد الحميري',
            'items' => [
                ['name' => 'رز بسمتي 10 كيلو', 'qty' => 1, 'price' => 18000],
            ],
        ])->assertCreated();

        $this->postJson('/api/invoices', [
            'customer' => 'فاطمة العريقي',
            'items' => [
                ['name' => 'شاهي 450 جرام', 'qty' => 3, 'price' => 3100],
            ],
        ])->assertCreated();

        $this->postJson('/api/invoices', [
            'customer' => 'عبدالله الشامي',
            'items' => [
                ['name' => 'عسل سدر 500 جرام', 'qty' => 1, 'price' => 22000],
            ],
        ])->assertCreated();

        // Arabic partial search using ?search=
        $searchResponse = $this->getJson('/api/invoices?search='.urlencode('فاطمة'));
        $searchResponse->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertSame('فاطمة العريقي', $searchResponse->json('data.0.customer'));

        // Arabic partial search fallback using ?customer=
        $customerResponse = $this->getJson('/api/invoices?customer='.urlencode('عبدالله'));
        $customerResponse->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertSame('عبدالله الشامي', $customerResponse->json('data.0.customer'));

        // Pagination page 1 (newest first)
        $page1 = $this->getJson('/api/invoices?per_page=2&page=1');
        $page1->assertOk()
            ->assertJsonCount(2, 'data');
        $this->assertSame(3, $page1->json('meta.total'));
        $this->assertSame(2, $page1->json('meta.per_page'));
        $this->assertSame(1, $page1->json('meta.current_page'));
        $this->assertSame('عبدالله الشامي', $page1->json('data.0.customer'));
        $this->assertSame('فاطمة العريقي', $page1->json('data.1.customer'));

        // Pagination page 2
        $page2 = $this->getJson('/api/invoices?per_page=2&page=2');
        $page2->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertSame(2, $page2->json('meta.current_page'));
        $this->assertSame('محمد الحميري', $page2->json('data.0.customer'));

        // per_page upper-bound capping at 100
        $capped = $this->getJson('/api/invoices?per_page=500');
        $capped->assertOk();
        $this->assertSame(100, $capped->json('meta.per_page'));

        // per_page lower-bound fallback to 15
        $fallback = $this->getJson('/api/invoices?per_page=0');
        $fallback->assertOk();
        $this->assertSame(15, $fallback->json('meta.per_page'));
    }
}
