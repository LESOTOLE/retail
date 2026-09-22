<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimiterApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ai-chat');
        RateLimiter::clear('auth');
    }

    public function test_auth_login_endpoint_is_rate_limited_after_10_requests(): void
    {
        // 10 requests allowed
        for ($i = 0; $i < 10; $i++) {
            $res = $this->postJson('/api/v1/auth/login', [
                'email' => "test{$i}@example.com",
                'password' => 'wrong-pass',
            ]);
            // Harus ditangani oleh Auth controller (bukan 429)
            $this->assertNotEquals(429, $res->status());
        }

        // Request ke-11 harus diblokir oleh RateLimiter (HTTP 429)
        $rateLimitedRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'wrong-pass',
        ]);

        $rateLimitedRes->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'TOO_MANY_REQUESTS');
    }

    public function test_ai_chat_endpoint_is_rate_limited_after_15_requests(): void
    {
        // 15 requests allowed
        for ($i = 0; $i < 15; $i++) {
            $res = $this->postJson('/api/v1/ai/chat', [
                'message' => 'Halo bot '.$i,
            ]);
            $this->assertNotEquals(429, $res->status());
        }

        // Request ke-16 harus diblokir oleh RateLimiter (HTTP 429)
        $rateLimitedRes = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Halo bot ke-16',
        ]);

        $rateLimitedRes->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'TOO_MANY_REQUESTS');
    }
}
