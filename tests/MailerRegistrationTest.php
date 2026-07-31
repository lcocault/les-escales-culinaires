<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Mailer::sendRegistrationNotificationToAdmin().
 */
#[RunTestsInSeparateProcesses]
class MailerRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        // Define constants required by Mailer
        if (!defined('ADMIN_EMAIL')) {
            define('ADMIN_EMAIL', 'admin@example.com');
        }
        if (!defined('SMTP_FROM')) {
            define('SMTP_FROM', 'noreply@example.com');
        }
        if (!defined('SMTP_FROM_NAME')) {
            define('SMTP_FROM_NAME', 'Escales Culinaires');
        }
        if (!defined('APP_BASE_URL')) {
            define('APP_BASE_URL', 'https://example.com');
        }

        require_once __DIR__ . '/../src/Mailer.php';
    }

    /** Access a private static method via reflection. */
    private function callPrivate(string $method, array $args): string
    {
        $ref = new ReflectionMethod(Mailer::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke(null, ...$args);
    }

    // -------------------------------------------------------------------------
    // adminRegistrationBody()
    // -------------------------------------------------------------------------

    public function testBodyContainsUserName(): void
    {
        $user = [
            'first_name' => 'Alice',
            'last_name'  => 'Dupont',
            'email'      => 'alice@example.com',
        ];

        $body = $this->callPrivate('adminRegistrationBody', [$user]);

        $this->assertStringContainsString('Alice', $body);
        $this->assertStringContainsString('Dupont', $body);
    }

    public function testBodyContainsUserEmail(): void
    {
        $user = [
            'first_name' => 'Bob',
            'last_name'  => 'Martin',
            'email'      => 'bob@example.com',
        ];

        $body = $this->callPrivate('adminRegistrationBody', [$user]);

        $this->assertStringContainsString('bob@example.com', $body);
    }

    public function testBodyContainsAdminLink(): void
    {
        $user = [
            'first_name' => 'Clara',
            'last_name'  => 'Durand',
            'email'      => 'clara@example.com',
        ];

        $body = $this->callPrivate('adminRegistrationBody', [$user]);

        $this->assertStringContainsString(APP_BASE_URL . '/admin/index.php', $body);
    }

    public function testBodyEscapesHtmlInUserName(): void
    {
        $user = [
            'first_name' => '<script>alert(1)</script>',
            'last_name'  => 'Doe',
            'email'      => 'xss@example.com',
        ];

        $body = $this->callPrivate('adminRegistrationBody', [$user]);

        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testUpcomingSessionsAnnouncementContainsConfiguredPeriodAndSessions(): void
    {
        $user = [
            'first_name' => 'Alice',
            'last_name'  => 'Dupont',
            'email'      => 'alice@example.com',
        ];
        $sessions = [
            [
                'id'           => 7,
                'title'        => 'Les fruits de saison',
                'session_date' => '2026-05-01',
                'start_time'   => '10:00:00',
                'end_time'     => '12:00:00',
                'age_category' => '6-12',
                'price_cents'  => 2500,
            ],
        ];

        $body = $this->callPrivate('upcomingSessionsAnnouncementBody', [$user, $sessions, '2026-05-01', '2026-05-31']);

        $this->assertStringContainsString('2026-05-01', $body);
        $this->assertStringContainsString('2026-05-31', $body);
        $this->assertStringContainsString('Les fruits de saison', $body);
        $this->assertStringContainsString(APP_BASE_URL . '/session.php?id=7', $body);
    }

    public function testUpcomingSessionsAnnouncementEscapesHtmlInSessionTitle(): void
    {
        $user = [
            'first_name' => 'Alice',
            'last_name'  => 'Dupont',
            'email'      => 'alice@example.com',
        ];
        $sessions = [
            [
                'id'           => 9,
                'title'        => '<b>Atelier</b>',
                'session_date' => '2026-05-10',
                'start_time'   => '09:00:00',
                'end_time'     => '11:00:00',
                'age_category' => '6-12',
                'price_cents'  => 2000,
            ],
        ];

        $body = $this->callPrivate('upcomingSessionsAnnouncementBody', [$user, $sessions, '2026-05-01', '2026-05-31']);

        $this->assertStringNotContainsString('<b>Atelier</b>', $body);
        $this->assertStringContainsString('&lt;b&gt;Atelier&lt;/b&gt;', $body);
    }
}
