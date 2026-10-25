<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // cache pengaturan bersifat statis per proses; wajib dibersihkan agar tes saling terisolasi
        \App\Support\Setting::forget();
    }
}
