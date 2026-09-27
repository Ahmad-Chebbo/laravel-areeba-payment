<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\AreebaServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const API = 'https://areeba.test/api/rest/version/100/merchant/TEST123';

    protected function setUp(): void
    {
        parent::setUp();

        // Set after boot: before it, a nested key would replace the package's whole
        // `webhook` array when the provider merges its config (the merge is one level deep).
        config([
            'areeba.gateway_url' => 'https://areeba.test',
            'areeba.api_version' => 100,
            'areeba.merchant_id' => 'TEST123',
            'areeba.api_password' => 'api-password',
            'areeba.merchant_name' => 'Test Shop',
            'areeba.currency' => 'USD',
            'areeba.webhook.secret' => 'notification-secret',
        ]);

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [AreebaServiceProvider::class];
    }
}
