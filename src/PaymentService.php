<?php
// src/PaymentService.php – provider-agnostic payment service.
// Delegates to Stripe or Square depending on the PAYMENT_PROVIDER constant.

use Square\SquareClient;
use Square\Environments as SquareEnvironments;
use Square\Checkout\PaymentLinks\Requests\CreatePaymentLinkRequest;
use Square\Types\QuickPay;
use Square\Types\Money;
use Square\Payments\Requests\GetPaymentsRequest;
use Square\Orders\Requests\GetOrdersRequest;
use Square\Refunds\Requests\RefundPaymentRequest;

class PaymentService
{
    /**
     * Creates a checkout URL for the given booking.
     *
     * @param int    $bookingId     The booking record ID (used as reference and in return URLs).
     * @param string $itemName      Human-readable name of the item being paid for.
     * @param int    $amountCents   Price in the smallest currency unit (e.g. euro cents).
     * @param string $currency      ISO 4217 currency code (e.g. "eur", "usd").
     * @return array{url: string, squareOrderId: ?string}
     *              url           – The redirect URL of the hosted payment page.
     *              squareOrderId – The Square Order ID created with the link (Square only), or null.
     * @throws RuntimeException     If the configured provider is unsupported or misconfigured.
     */
    public static function createCheckoutUrl(
        int $bookingId,
        string $itemName,
        int $amountCents,
        string $currency
    ): array {
        $provider = defined('PAYMENT_PROVIDER') ? strtolower(PAYMENT_PROVIDER) : 'stripe';

        return match ($provider) {
            'stripe' => self::stripeCheckoutUrl($bookingId, $itemName, $amountCents, $currency),
            'square' => self::squareCheckoutUrl($bookingId, $itemName, $amountCents, $currency),
            default  => throw new RuntimeException(
                "Unsupported PAYMENT_PROVIDER \"$provider\". Must be \"stripe\" or \"square\"."
            ),
        };
    }

    /**
     * Creates a checkout URL for a basket containing multiple bookings.
     *
     * @param array  $lineItems    Array of ['name' => string, 'amount_cents' => int] entries.
     * @param int    $totalCents   Total amount in the smallest currency unit.
     * @param string $currency     ISO 4217 currency code (e.g. "eur").
     * @param string $urlTag       Query parameter appended to success/cancel URLs (e.g. "basket" or "pack").
     * @return array{url: string, squareOrderId: ?string}
     * @throws RuntimeException   If the configured provider is unsupported or misconfigured.
     */
    public static function createBasketCheckoutUrl(
        array $lineItems,
        int $totalCents,
        string $currency,
        string $urlTag = 'basket'
    ): array {
        $provider = defined('PAYMENT_PROVIDER') ? strtolower(PAYMENT_PROVIDER) : 'stripe';

        return match ($provider) {
            'stripe' => self::stripeBasketCheckoutUrl($lineItems, $currency, $urlTag),
            'square' => self::squareBasketCheckoutUrl($totalCents, $currency, $urlTag),
            default  => throw new RuntimeException(
                "Unsupported PAYMENT_PROVIDER \"$provider\". Must be \"stripe\" or \"square\"."
            ),
        };
    }

    /**
     * Creates a checkout URL for a shop order containing multiple products.
     *
     * @param int    $orderId     The shop_orders record ID.
     * @param array  $lineItems   Array of ['name' => string, 'amount_cents' => int, 'quantity' => int] entries.
     * @param int    $totalCents  Total amount in the smallest currency unit.
     * @param string $currency    ISO 4217 currency code (e.g. "eur").
     * @return array{url: string, squareOrderId: ?string}
     * @throws RuntimeException  If the configured provider is unsupported or misconfigured.
     */
    public static function createShopOrderCheckoutUrl(
        int $orderId,
        array $lineItems,
        int $totalCents,
        string $currency
    ): array {
        $provider = defined('PAYMENT_PROVIDER') ? strtolower(PAYMENT_PROVIDER) : 'stripe';

        return match ($provider) {
            'stripe' => self::stripeShopOrderCheckoutUrl($orderId, $lineItems, $currency),
            'square' => self::squareShopOrderCheckoutUrl($orderId, $totalCents, $currency),
            default  => throw new RuntimeException(
                "Unsupported PAYMENT_PROVIDER \"$provider\". Must be \"stripe\" or \"square\"."
            ),
        };
    }

    /**
     * Issues a full refund for a previously completed payment.
     *
     * @param string $paymentIntentId  The payment/intent ID stored on the booking.
     * @throws RuntimeException        If the configured provider is unsupported or the refund fails.
     */
    public static function refund(string $paymentIntentId): void
    {
        $provider = defined('PAYMENT_PROVIDER') ? strtolower(PAYMENT_PROVIDER) : 'stripe';

        match ($provider) {
            'stripe' => self::stripeRefund($paymentIntentId),
            'square' => self::squareRefund($paymentIntentId),
            default  => throw new RuntimeException(
                "Unsupported PAYMENT_PROVIDER \"$provider\". Must be \"stripe\" or \"square\"."
            ),
        };
    }

    /**
     * Returns true when the given payment reference identifies a real (non-demo) payment
     * that requires an actual refund via the provider API.
     */
    public static function isRealPaymentRef(string $paymentIntentId): bool
    {
        if (empty($paymentIntentId)) {
            return false;
        }
        if ($paymentIntentId === 'credit') {
            return false;
        }
        if (str_starts_with($paymentIntentId, 'demo_')) {
            return false;
        }
        if (str_starts_with($paymentIntentId, 'paid_')) {
            return false;
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Stripe
    // -------------------------------------------------------------------------

    private static function stripeCheckoutUrl(
        int $bookingId,
        string $itemName,
        int $amountCents,
        string $currency
    ): array {
        if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '' || STRIPE_SECRET_KEY === 'sk_test_...') {
            // Stripe is not configured – fall back to demo mode.
            return ['url' => APP_BASE_URL . '/payment_success.php?booking_id=' . $bookingId . '&_demo=1', 'squareOrderId' => null];
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'price_data' => [
                    'currency'     => strtolower($currency),
                    'product_data' => ['name' => $itemName],
                    'unit_amount'  => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => APP_BASE_URL . '/payment_success.php?booking_id=' . $bookingId
                             . '&payment_intent={CHECKOUT_SESSION_ID}',
            'cancel_url'  => APP_BASE_URL . '/payment_cancel.php?booking_id=' . $bookingId,
            'metadata'    => ['booking_id' => $bookingId],
        ]);

        return ['url' => $session->url, 'squareOrderId' => null];
    }

    // -------------------------------------------------------------------------
    // Square
    // -------------------------------------------------------------------------

    private static function squareCheckoutUrl(
        int $bookingId,
        string $itemName,
        int $amountCents,
        string $currency
    ): array {
        if (!defined('SQUARE_ACCESS_TOKEN') || SQUARE_ACCESS_TOKEN === '' || SQUARE_ACCESS_TOKEN === 'EAAAl...') {
            // Square is not configured – fall back to demo mode.
            return ['url' => APP_BASE_URL . '/payment_success.php?booking_id=' . $bookingId . '&_demo=1', 'squareOrderId' => null];
        }

        $environment = (defined('SQUARE_ENVIRONMENT') && SQUARE_ENVIRONMENT === 'production')
            ? SquareEnvironments::Production->value
            : SquareEnvironments::Sandbox->value;

        $client = new SquareClient(
            token: SQUARE_ACCESS_TOKEN,
            options: ['baseUrl' => $environment],
        );

        $request = new CreatePaymentLinkRequest([
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'description'    => 'Booking #' . $bookingId,
            'quickPay'       => new QuickPay([
                'name'       => $itemName,
                'locationId' => SQUARE_LOCATION_ID,
                'priceMoney' => new Money([
                    'amount'   => $amountCents,
                    'currency' => strtoupper($currency),
                ]),
            ]),
            'checkoutOptions' => new \Square\Types\CheckoutOptions([
                'redirectUrl' => APP_BASE_URL . '/payment_success.php?booking_id=' . $bookingId,
            ]),
        ]);

        $response = $client->checkout->paymentLinks->create($request);
        $link = $response->getPaymentLink();

        if ($link === null || $link->getUrl() === null) {
            throw new RuntimeException('Square did not return a payment link URL.');
        }

        return ['url' => $link->getUrl(), 'squareOrderId' => $link->getOrderId()];
    }

    // -------------------------------------------------------------------------
    // Stripe – refund
    // -------------------------------------------------------------------------

    private static function stripeRefund(string $paymentIntentId): void
    {
        if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '' || STRIPE_SECRET_KEY === 'sk_test_...') {
            return; // Demo mode – no actual refund issued.
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        \Stripe\Refund::create(['payment_intent' => $paymentIntentId]);
    }

    // -------------------------------------------------------------------------
    // Square – refund
    // -------------------------------------------------------------------------

    private static function squareRefund(string $paymentIntentId): void
    {
        if (!defined('SQUARE_ACCESS_TOKEN') || SQUARE_ACCESS_TOKEN === '' || SQUARE_ACCESS_TOKEN === 'EAAAl...') {
            return; // Demo mode – no actual refund issued.
        }

        $environment = (defined('SQUARE_ENVIRONMENT') && SQUARE_ENVIRONMENT === 'production')
            ? SquareEnvironments::Production->value
            : SquareEnvironments::Sandbox->value;

        $client = new SquareClient(
            token: SQUARE_ACCESS_TOKEN,
            options: ['baseUrl' => $environment],
        );

        if (str_starts_with($paymentIntentId, 'sq_order_')) {
            // Payment was tracked by Square order ID; resolve tender payment IDs from the order.
            $orderId = substr($paymentIntentId, strlen('sq_order_'));
            $orderResponse = $client->orders->get(
                new GetOrdersRequest(['orderId' => $orderId])
            );
            $order = $orderResponse->getOrder();

            if ($order === null) {
                throw new RuntimeException('Square order not found: ' . $orderId);
            }

            foreach ($order->getTenders() ?? [] as $tender) {
                $tenderPaymentId = $tender->getPaymentId();
                if ($tenderPaymentId !== null) {
                    $client->refunds->refundPayment(new RefundPaymentRequest([
                        'idempotencyKey' => bin2hex(random_bytes(16)),
                        'amountMoney'    => $tender->getAmountMoney(),
                        'paymentId'      => $tenderPaymentId,
                    ]));
                }
            }
            return;
        }

        // Retrieve the original payment to get the amount to refund.
        $paymentResponse = $client->payments->get(
            new GetPaymentsRequest(['paymentId' => $paymentIntentId])
        );
        $payment = $paymentResponse->getPayment();

        if ($payment === null) {
            throw new RuntimeException('Square payment not found: ' . $paymentIntentId);
        }

        $client->refunds->refundPayment(new RefundPaymentRequest([
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'amountMoney'    => $payment->getTotalMoney(),
            'paymentId'      => $paymentIntentId,
        ]));
    }

    // -------------------------------------------------------------------------
    // Stripe – basket checkout (multiple line items)
    // -------------------------------------------------------------------------

    private static function stripeBasketCheckoutUrl(
        array $lineItems,
        string $currency,
        string $urlTag = 'basket'
    ): array {
        if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '' || STRIPE_SECRET_KEY === 'sk_test_...') {
            // Stripe is not configured – fall back to demo mode.
            return ['url' => APP_BASE_URL . '/payment_success.php?' . $urlTag . '=1&_demo=1', 'squareOrderId' => null];
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $stripeLineItems = array_map(fn($item) => [
            'price_data' => [
                'currency'     => strtolower($currency),
                'product_data' => ['name' => $item['name']],
                'unit_amount'  => $item['amount_cents'],
            ],
            'quantity' => 1,
        ], $lineItems);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => $stripeLineItems,
            'mode'                 => 'payment',
            'success_url'          => APP_BASE_URL . '/payment_success.php?' . $urlTag . '=1&payment_intent={CHECKOUT_SESSION_ID}',
            'cancel_url'           => APP_BASE_URL . '/payment_cancel.php?' . $urlTag . '=1',
        ]);

        return ['url' => $session->url, 'squareOrderId' => null];
    }

    // -------------------------------------------------------------------------
    // Square – basket checkout (single QuickPay with total amount)
    // -------------------------------------------------------------------------

    private static function squareBasketCheckoutUrl(
        int $totalCents,
        string $currency,
        string $urlTag = 'basket'
    ): array {
        if (!defined('SQUARE_ACCESS_TOKEN') || SQUARE_ACCESS_TOKEN === '' || SQUARE_ACCESS_TOKEN === 'EAAAl...') {
            // Square is not configured – fall back to demo mode.
            return ['url' => APP_BASE_URL . '/payment_success.php?' . $urlTag . '=1&_demo=1', 'squareOrderId' => null];
        }

        $environment = (defined('SQUARE_ENVIRONMENT') && SQUARE_ENVIRONMENT === 'production')
            ? SquareEnvironments::Production->value
            : SquareEnvironments::Sandbox->value;

        $client = new SquareClient(
            token: SQUARE_ACCESS_TOKEN,
            options: ['baseUrl' => $environment],
        );

        $request = new CreatePaymentLinkRequest([
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'description'    => 'Panier – séances de cuisine',
            'quickPay'       => new QuickPay([
                'name'       => 'Panier – séances de cuisine',
                'locationId' => SQUARE_LOCATION_ID,
                'priceMoney' => new Money([
                    'amount'   => $totalCents,
                    'currency' => strtoupper($currency),
                ]),
            ]),
            'checkoutOptions' => new \Square\Types\CheckoutOptions([
                'redirectUrl' => APP_BASE_URL . '/payment_success.php?' . $urlTag . '=1',
            ]),
        ]);

        $response = $client->checkout->paymentLinks->create($request);
        $link = $response->getPaymentLink();

        if ($link === null || $link->getUrl() === null) {
            throw new RuntimeException('Square did not return a payment link URL.');
        }

        return ['url' => $link->getUrl(), 'squareOrderId' => $link->getOrderId()];
    }

    // -------------------------------------------------------------------------
    // Stripe – shop order checkout (multiple line items with quantities)
    // -------------------------------------------------------------------------

    private static function stripeShopOrderCheckoutUrl(
        int $orderId,
        array $lineItems,
        string $currency
    ): array {
        if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '' || STRIPE_SECRET_KEY === 'sk_test_...') {
            return ['url' => APP_BASE_URL . '/boutique/order-success.php?order_id=' . $orderId . '&_demo=1', 'squareOrderId' => null];
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $stripeLineItems = array_map(fn($item) => [
            'price_data' => [
                'currency'     => strtolower($currency),
                'product_data' => ['name' => $item['name']],
                'unit_amount'  => $item['amount_cents'],
            ],
            'quantity' => (int) ($item['quantity'] ?? 1),
        ], $lineItems);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => $stripeLineItems,
            'mode'                 => 'payment',
            'success_url'          => APP_BASE_URL . '/boutique/order-success.php?order_id=' . $orderId
                                      . '&payment_intent={CHECKOUT_SESSION_ID}',
            'cancel_url'           => APP_BASE_URL . '/boutique/order-cancel.php?order_id=' . $orderId,
            'metadata'             => ['shop_order_id' => $orderId],
        ]);

        return ['url' => $session->url, 'squareOrderId' => null];
    }

    // -------------------------------------------------------------------------
    // Square – shop order checkout (single QuickPay with total amount)
    // -------------------------------------------------------------------------

    private static function squareShopOrderCheckoutUrl(
        int $orderId,
        int $totalCents,
        string $currency
    ): array {
        if (!defined('SQUARE_ACCESS_TOKEN') || SQUARE_ACCESS_TOKEN === '' || SQUARE_ACCESS_TOKEN === 'EAAAl...') {
            return ['url' => APP_BASE_URL . '/boutique/order-success.php?order_id=' . $orderId . '&_demo=1', 'squareOrderId' => null];
        }

        $environment = (defined('SQUARE_ENVIRONMENT') && SQUARE_ENVIRONMENT === 'production')
            ? SquareEnvironments::Production->value
            : SquareEnvironments::Sandbox->value;

        $client = new SquareClient(
            token: SQUARE_ACCESS_TOKEN,
            options: ['baseUrl' => $environment],
        );

        $request = new CreatePaymentLinkRequest([
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'description'    => 'Commande boutique #' . $orderId,
            'quickPay'       => new QuickPay([
                'name'       => 'Commande boutique #' . $orderId,
                'locationId' => SQUARE_LOCATION_ID,
                'priceMoney' => new Money([
                    'amount'   => $totalCents,
                    'currency' => strtoupper($currency),
                ]),
            ]),
            'checkoutOptions' => new \Square\Types\CheckoutOptions([
                'redirectUrl' => APP_BASE_URL . '/boutique/order-success.php?order_id=' . $orderId,
            ]),
        ]);

        $response = $client->checkout->paymentLinks->create($request);
        $link = $response->getPaymentLink();

        if ($link === null || $link->getUrl() === null) {
            throw new RuntimeException('Square did not return a payment link URL.');
        }

        return ['url' => $link->getUrl(), 'squareOrderId' => $link->getOrderId()];
    }
}
