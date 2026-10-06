<?php

namespace Tests\Feature;

use Tests\TestCase;

class StyleguideTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        parent::tearDown();
    }

    /**
     * Le guide de style est accessible (HTTP 200) en environnement local.
     */
    public function test_styleguide_is_accessible_in_local_environment(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        $this->refreshApplication();

        $response = $this->get('/dev/styleguide');

        $response->assertStatus(200);
        $response->assertSee('Guide de Style');
    }

    /**
     * Le guide de style est inaccessible (HTTP 404) hors environnement local.
     */
    public function test_styleguide_returns_404_in_production_environment(): void
    {
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
        $this->refreshApplication();

        $response = $this->get('/dev/styleguide');

        $response->assertStatus(404);
    }
}
