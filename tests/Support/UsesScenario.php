<?php

namespace Tests\Support;

use App\Enums\Status;
use App\Models\GrantApplication;
use App\Support\ScenarioBuilder;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/** Basis tes: database bersih + data referensi + ScenarioBuilder ($this->s). */
trait UsesScenario
{
    use RefreshDatabase;

    protected ScenarioBuilder $s;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ReferenceSeeder::class);
        $this->s = new ScenarioBuilder;
    }

    protected function appAt(Status $status, array $o = []): GrantApplication
    {
        return $this->s->advance($this->s->newApplication($o), $status);
    }

    /** Pesan validasi (semua kunci) dari tindakan yang diharapkan gagal. */
    protected function failureMessages(callable $fn): string
    {
        try {
            $fn();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return implode(' | ', collect($e->errors())->flatten()->all());
        }
        $this->fail('Diharapkan ValidationException, tetapi tindakan berhasil.');
    }
}
