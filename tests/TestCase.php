<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Pengaturan::lupakan(); // cache statis antar-tes (database direset tiap tes)
    }
}
