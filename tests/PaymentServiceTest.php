<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PaymentService.
 *
 * Each test runs in its own process because PHP constants cannot be redefined
 * once set, and PaymentService reads them via defined().
 */
#[RunTestsInSeparateProcesses]
class PaymentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Minimal constants required by PaymentService (normally from config/config.php).
        if (!defined('APP_BASE_URL')) {
            define('APP_BASE_URL', 'http://localhost');
        }

        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/PaymentService.php';
    }

    // -------------------------------------------------------------------------
    // Stripe – demo fall-through (key is the placeholder value)
    // -------------------------------------------------------------------------

    public function testStripeDemoFallbackWhenKeyIsPlaceholder(): void
    {
        define('PAYMENT_PROVIDER', 'stripe');
        define('STRIPE_SECRET_KEY', 'sk_test_...');

        $result = PaymentService::createCheckoutUrl(42, 'Cours de Pain', 6500, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('/payment_success.php', $result['url']);
        $this->assertStringContainsString('booking_id=42', $result['url']);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    // -------------------------------------------------------------------------
    // Square – demo fall-through (token is the placeholder value)
    // -------------------------------------------------------------------------

    public function testSquareDemoFallbackWhenTokenIsPlaceholder(): void
    {
        define('PAYMENT_PROVIDER', 'square');
        define('SQUARE_ACCESS_TOKEN', 'EAAAl...');
        define('SQUARE_LOCATION_ID', 'test_loc');
        define('SQUARE_ENVIRONMENT', 'sandbox');

        $result = PaymentService::createCheckoutUrl(7, 'Cours de Pâtisserie', 7500, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('/payment_success.php', $result['url']);
        $this->assertStringContainsString('booking_id=7', $result['url']);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    // -------------------------------------------------------------------------
    // Unsupported provider
    // -------------------------------------------------------------------------

    public function testUnsupportedProviderThrowsRuntimeException(): void
    {
        define('PAYMENT_PROVIDER', 'paypal');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported PAYMENT_PROVIDER/');

        PaymentService::createCheckoutUrl(1, 'Cours', 5000, 'eur');
    }

    // -------------------------------------------------------------------------
    // Default provider (PAYMENT_PROVIDER not defined → 'stripe')
    // -------------------------------------------------------------------------

    public function testDefaultProviderFallsBackToStripeDemo(): void
    {
        // PAYMENT_PROVIDER intentionally not defined.
        define('STRIPE_SECRET_KEY', 'sk_test_...');

        $result = PaymentService::createCheckoutUrl(99, 'Cours', 8000, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertStringContainsString('booking_id=99', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    public function testStripeGroupBookingDemoFallbackWhenKeyIsPlaceholder(): void
    {
        define('PAYMENT_PROVIDER', 'stripe');
        define('STRIPE_SECRET_KEY', 'sk_test_...');

        $result = PaymentService::createGroupBookingCheckoutUrl(14, 'Atelier anniversaire', 18000, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('/payment_success.php', $result['url']);
        $this->assertStringContainsString('group_booking_id=14', $result['url']);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    public function testSquareGroupBookingDemoFallbackWhenTokenIsPlaceholder(): void
    {
        define('PAYMENT_PROVIDER', 'square');
        define('SQUARE_ACCESS_TOKEN', 'EAAAl...');
        define('SQUARE_LOCATION_ID', 'test_loc');
        define('SQUARE_ENVIRONMENT', 'sandbox');

        $result = PaymentService::createGroupBookingCheckoutUrl(18, 'Atelier anniversaire', 21000, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('/payment_success.php', $result['url']);
        $this->assertStringContainsString('group_booking_id=18', $result['url']);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    public function testGroupBookingUnsupportedProviderThrowsRuntimeException(): void
    {
        define('PAYMENT_PROVIDER', 'paypal');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported PAYMENT_PROVIDER/');

        PaymentService::createGroupBookingCheckoutUrl(3, 'Atelier anniversaire', 12000, 'eur');
    }

    // -------------------------------------------------------------------------
    // Refund – Stripe demo mode (placeholder key → silent no-op)
    // -------------------------------------------------------------------------

    public function testStripeRefundDemoModeIsNoOp(): void
    {
        define('PAYMENT_PROVIDER', 'stripe');
        define('STRIPE_SECRET_KEY', 'sk_test_...');

        // Should complete without throwing an exception.
        PaymentService::refund('pi_demo_12345');
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Refund – Square demo mode (placeholder token → silent no-op)
    // -------------------------------------------------------------------------

    public function testSquareRefundDemoModeIsNoOp(): void
    {
        define('PAYMENT_PROVIDER', 'square');
        define('SQUARE_ACCESS_TOKEN', 'EAAAl...');
        define('SQUARE_LOCATION_ID', 'test_loc');
        define('SQUARE_ENVIRONMENT', 'sandbox');

        // Should complete without throwing an exception.
        PaymentService::refund('sq_payment_demo');
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Refund – unsupported provider
    // -------------------------------------------------------------------------

    public function testRefundWithUnsupportedProviderThrowsRuntimeException(): void
    {
        define('PAYMENT_PROVIDER', 'paypal');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported PAYMENT_PROVIDER/');

        PaymentService::refund('some_payment_id');
    }

    // -------------------------------------------------------------------------
    // isRealPaymentRef – helper logic
    // -------------------------------------------------------------------------

    public function testIsRealPaymentRefReturnsFalseForDemoRefs(): void
    {
        $this->assertFalse(PaymentService::isRealPaymentRef(''));
        $this->assertFalse(PaymentService::isRealPaymentRef('credit'));
        $this->assertFalse(PaymentService::isRealPaymentRef('demo_42'));
        $this->assertFalse(PaymentService::isRealPaymentRef('paid_42'));
    }

    public function testIsRealPaymentRefReturnsTrueForRealRefs(): void
    {
        $this->assertTrue(PaymentService::isRealPaymentRef('pi_3AbCdEfGhIjKlMnO'));
        $this->assertTrue(PaymentService::isRealPaymentRef('sq_payment_abc123'));
        $this->assertTrue(PaymentService::isRealPaymentRef('sq_order_ORDER123XYZ'));
    }

    // -------------------------------------------------------------------------
    // createBasketCheckoutUrl – Stripe demo mode
    // -------------------------------------------------------------------------

    public function testStripeBasketDemoFallbackWhenKeyIsPlaceholder(): void
    {
        define('PAYMENT_PROVIDER', 'stripe');
        define('STRIPE_SECRET_KEY', 'sk_test_...');

        $lineItems = [
            ['name' => 'Séance 1', 'amount_cents' => 5000],
            ['name' => 'Séance 2', 'amount_cents' => 7000],
        ];

        $result = PaymentService::createBasketCheckoutUrl($lineItems, 12000, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('/payment_success.php', $result['url']);
        $this->assertStringContainsString('basket=1', $result['url']);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    // -------------------------------------------------------------------------
    // createBasketCheckoutUrl – Square demo mode
    // -------------------------------------------------------------------------

    public function testSquareBasketDemoFallbackWhenTokenIsPlaceholder(): void
    {
        define('PAYMENT_PROVIDER', 'square');
        define('SQUARE_ACCESS_TOKEN', 'EAAAl...');
        define('SQUARE_LOCATION_ID', 'test_loc');
        define('SQUARE_ENVIRONMENT', 'sandbox');

        $lineItems = [
            ['name' => 'Séance A', 'amount_cents' => 6500],
        ];

        $result = PaymentService::createBasketCheckoutUrl($lineItems, 6500, 'eur');

        $this->assertIsArray($result);
        $this->assertStringContainsString('/payment_success.php', $result['url']);
        $this->assertStringContainsString('basket=1', $result['url']);
        $this->assertStringContainsString('_demo=1', $result['url']);
        $this->assertNull($result['squareOrderId']);
    }

    // -------------------------------------------------------------------------
    // createBasketCheckoutUrl – unsupported provider
    // -------------------------------------------------------------------------

    public function testBasketUnsupportedProviderThrowsRuntimeException(): void
    {
        define('PAYMENT_PROVIDER', 'paypal');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported PAYMENT_PROVIDER/');

        PaymentService::createBasketCheckoutUrl([['name' => 'Test', 'amount_cents' => 5000]], 5000, 'eur');
    }
}
