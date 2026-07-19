<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Nanayawkumi\NykLogger\Support\Redactor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    private function redactor(): Redactor
    {
        return new Redactor(
            keys: ['password', 'token', 'authorization'],
            patterns: ['/(?i)(bearer\s+)[a-z0-9._\-]+/'],
        );
    }

    #[Test]
    public function it_redacts_matching_keys_case_insensitively(): void
    {
        $result = $this->redactor()->scrub([
            'Password' => 'hunter2',
            'email' => 'user@example.com',
        ]);

        $this->assertSame('[REDACTED]', $result['Password']);
        $this->assertSame('user@example.com', $result['email']);
    }

    #[Test]
    public function it_redacts_nested_arrays(): void
    {
        $result = $this->redactor()->scrub([
            'user' => ['token' => 'abc123', 'name' => 'Ada'],
        ]);

        $this->assertSame('[REDACTED]', $result['user']['token']);
        $this->assertSame('Ada', $result['user']['name']);
    }

    #[Test]
    public function it_redacts_secret_value_patterns(): void
    {
        $result = $this->redactor()->scrub([
            'header' => 'Authorization: Bearer sk_live_9f8a7b',
        ]);

        $this->assertStringContainsString('[REDACTED]', $result['header']);
        $this->assertStringNotContainsString('sk_live_9f8a7b', $result['header']);
    }

    #[Test]
    public function it_leaves_non_sensitive_scalars_untouched(): void
    {
        $result = $this->redactor()->scrub(['count' => 5, 'active' => true]);

        $this->assertSame(5, $result['count']);
        $this->assertTrue($result['active']);
    }
}
