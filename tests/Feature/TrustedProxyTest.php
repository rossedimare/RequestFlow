<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The proxy list comes from config('trustedproxy.proxies'), which is reset with the
// application for every test. TrustProxies also keeps the trusted headers in static
// properties that would outlive the application, so flush them before and after each test.
beforeEach(function () {
    TrustProxies::flushState();

    // These tests assume no proxy is trusted by default (TRUSTED_PROXIES unset).
    if (config('trustedproxy.proxies') !== null) {
        $this->markTestSkipped('TRUSTED_PROXIES is set in this environment.');
    }

    // A tiny route that reports what Laravel believes about the incoming request.
    Route::get('/_proxy-probe', fn (Request $request) => response()->json([
        'secure' => $request->isSecure(),
        'scheme' => $request->getScheme(),
        'host' => $request->getHost(),
        'ip' => $request->ip(),
        'url' => url('/example'),
    ]));
});

afterEach(function () {
    TrustProxies::flushState();
});

const PROXY_IP = '10.0.0.5';

test('https is recognized when the proxy is trusted and X-Forwarded-Proto is https', function () {
    config(['trustedproxy.proxies' => '*']);

    $this->withServerVariables(['REMOTE_ADDR' => PROXY_IP])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
        ->getJson('http://localhost/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'secure' => true,
            'scheme' => 'https',
            'url' => 'https://localhost/example',
        ]);
});

test('the forwarded protocol is ignored when no proxy is trusted', function () {
    $this->withServerVariables(['REMOTE_ADDR' => PROXY_IP])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
        ->getJson('http://localhost/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'secure' => false,
            'scheme' => 'http',
            'url' => 'http://localhost/example',
        ]);
});

test('the proxy list can be restricted to specific addresses', function (string $remoteAddr, bool $secure) {
    config(['trustedproxy.proxies' => PROXY_IP]);

    $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->getJson('http://localhost/_proxy-probe')
        ->assertOk()
        ->assertJson(['secure' => $secure]);
})->with([
    'listed proxy is trusted' => [PROXY_IP, true],
    'other address is not trusted' => ['10.0.0.99', false],
]);

test('X-Forwarded-Host and X-Forwarded-For never override the host or client IP', function (?string $proxies) {
    config(['trustedproxy.proxies' => $proxies]);

    $this->withServerVariables(['REMOTE_ADDR' => PROXY_IP])
        ->withHeaders([
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-For' => '203.0.113.9',
        ])
        ->getJson('http://localhost/_proxy-probe')
        ->assertOk()
        ->assertJson([
            'host' => 'localhost',
            'ip' => PROXY_IP,
        ]);
})->with([
    'proxy not trusted' => [null],
    // Even a trusted proxy must not be able to change them: only Proto and Port are read.
    'proxy trusted' => ['*'],
]);
