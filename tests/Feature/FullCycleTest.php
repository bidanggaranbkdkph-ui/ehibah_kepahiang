<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Support\ScenarioBuilder;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    public function test_hibah_uang_dari_draf_sampai_selesai(): void
    {
        $s = new ScenarioBuilder;
        $app = $s->advance($s->newApplication(), Status::CLOSED);

        $this->assertSame(Status::CLOSED, $app->status);
        $this->assertNotNull($app->reg_number);
        $this->assertSame(100_000_000, $app->disbursedTotal());
        $this->assertCount(2, $app->disbursements()->where('status', 'DISALURKAN')->get());
        $this->assertSame(70_000_000, (int) $app->disbursements()->where('stage', 1)->value('amount'));
        $this->assertSame(5_000_000, $app->returnedTotal());
    }

    public function test_hibah_barang_sampai_selesai(): void
    {
        $s = new ScenarioBuilder;
        $app = $s->advance($s->newApplication(['grant_type' => 'HIBAH_BARANG', 'amount' => 50_000_000]), Status::CLOSED);

        $this->assertSame(Status::CLOSED, $app->status);
        $this->assertSame('DICATAT', $app->goodsDelivery->status);
        $this->assertSame(0, $app->disbursements()->count());
    }
}
