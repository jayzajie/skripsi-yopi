<?php

namespace Tests\Feature;

use Tests\TestCase;

class HalamanMasukTest extends TestCase
{
    public function test_halaman_masuk_dapat_dibuka(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('Masuk ke Akun Anda');
    }
}
