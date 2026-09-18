<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class MailerGroupBookingTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        if (!defined('APP_BASE_URL')) {
            define('APP_BASE_URL', 'https://example.com');
        }

        require_once __DIR__ . '/../src/Mailer.php';
    }

    private function callPrivate(string $method, array $args): string
    {
        $ref = new ReflectionMethod(Mailer::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke(null, ...$args);
    }

    public function testAwaitingPaymentStatusBodyContainsPaymentInvitationLink(): void
    {
        $user = [
            'first_name' => 'Alice',
            'last_name'  => 'Dupont',
        ];
        $request = [
            'id'                            => 9,
            'preferred_date'                => '2026-09-20',
            'status'                        => 'awaiting_payment',
            'admin_notes'                   => 'Créneau réservé pour vous.',
            'nb_children'                   => 6,
            'location_type'                 => 'escales',
            'price_per_child_escales_cents' => 3500,
        ];

        $body = $this->callPrivate('groupBookingStatusUpdateBody', [$user, $request]);

        $this->assertStringContainsString('Régler ma séance anniversaire', $body);
        $this->assertStringContainsString(APP_BASE_URL . '/group-booking-pay.php?id=9', $body);
        $this->assertStringContainsString('210,00 €', $body);
    }

    public function testConfirmedStatusBodyMentionsPaymentReceived(): void
    {
        $user = [
            'first_name' => 'Bob',
            'last_name'  => 'Martin',
        ];
        $request = [
            'id'              => 4,
            'preferred_date'  => '2026-09-21',
            'status'          => 'confirmed',
            'admin_notes'     => null,
            'nb_children'     => 5,
            'location_type'   => 'home',
        ];

        $body = $this->callPrivate('groupBookingStatusUpdateBody', [$user, $request]);

        $this->assertStringContainsString('paiement a bien été reçu', $body);
        $this->assertStringContainsString('confirmée ✅', $body);
        $this->assertStringNotContainsString('group-booking-pay.php', $body);
    }
}
