<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Rede de segurança: os testes apagam o banco. Nunca podem tocar no banco real.
        $connection = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database   = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE');

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                'Testes abortados: o phpunit.xml precisa definir DB_CONNECTION=sqlite e DB_DATABASE=:memory:.'
            );
        }

        parent::setUp();
    }
}
