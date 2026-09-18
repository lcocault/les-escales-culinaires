<?php

declare(strict_types=1);

namespace Stripe {
    class Stripe
    {
        public static string $apiKey = '';

        public static function setApiKey(string $apiKey): void
        {
            self::$apiKey = $apiKey;
        }
    }
}

namespace Stripe\Checkout {
    class Session
    {
        /** @var array<string, object> */
        public static array $sessions = [];

        public static function retrieve(string $sessionId): object
        {
            return self::$sessions[$sessionId];
        }
    }
}

namespace Square {
    enum Environments: string
    {
        case Production = 'production';
        case Sandbox = 'sandbox';
    }

    final class TestState
    {
        public static ?object $orderResponse = null;

        /** @var array<string, object> */
        public static array $paymentResponses = [];
    }

    class SquareClient
    {
        public object $orders;
        public object $payments;

        public function __construct(string $token, array $options = [])
        {
            $this->orders = new class () {
                public function get(object $request): object
                {
                    return \Square\TestState::$orderResponse;
                }
            };

            $this->payments = new class () {
                public function get(object $request): object
                {
                    return \Square\TestState::$paymentResponses[$request->paymentId];
                }
            };
        }
    }
}

namespace Square\Orders\Requests {
    class GetOrdersRequest
    {
        public string $orderId;

        public function __construct(array $data)
        {
            $this->orderId = (string) $data['orderId'];
        }
    }
}

namespace Square\Payments\Requests {
    class GetPaymentsRequest
    {
        public string $paymentId;

        public function __construct(array $data)
        {
            $this->paymentId = (string) $data['paymentId'];
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    final class TestSquareOrderResponse
    {
        public function __construct(private ?object $order)
        {
        }

        public function getOrder(): ?object
        {
            return $this->order;
        }
    }

    final class TestSquarePaymentResponse
    {
        public function __construct(private ?object $payment)
        {
        }

        public function getPayment(): ?object
        {
            return $this->payment;
        }
    }

    final class TestSquareOrder
    {
        /**
         * @param array<int, object> $tenders
         */
        public function __construct(
            private string $state,
            private array $tenders
        ) {
        }

        public function getState(): string
        {
            return $this->state;
        }

        /**
         * @return array<int, object>
         */
        public function getTenders(): array
        {
            return $this->tenders;
        }
    }

    final class TestSquareTender
    {
        public function __construct(private ?string $paymentId)
        {
        }

        public function getPaymentId(): ?string
        {
            return $this->paymentId;
        }
    }

    final class TestSquarePayment
    {
        public function __construct(private string $status)
        {
        }

        public function getStatus(): string
        {
            return $this->status;
        }
    }

    final class PaymentServiceGroupBookingVerificationTest extends TestCase
    {
        protected function setUp(): void
        {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../src/PaymentService.php';
        }

        /**
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testVerifyStripeGroupBookingPaymentSucceedsForPaidMatchingSession(): void
        {
            define('PAYMENT_PROVIDER', 'stripe');
            define('STRIPE_SECRET_KEY', 'sk_live_test');

            \Stripe\Checkout\Session::$sessions['cs_test_ok'] = (object) [
                'payment_status' => 'paid',
                'metadata'       => (object) ['group_booking_id' => '12'],
            ];

            $result = PaymentService::verifyGroupBookingPayment(12, 'cs_test_ok');

            $this->assertSame('cs_test_ok', $result);
        }

        /**
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testVerifyStripeGroupBookingPaymentReturnsNullForUnpaidSession(): void
        {
            define('PAYMENT_PROVIDER', 'stripe');
            define('STRIPE_SECRET_KEY', 'sk_live_test');

            \Stripe\Checkout\Session::$sessions['cs_test_unpaid'] = (object) [
                'payment_status' => 'unpaid',
                'metadata'       => (object) ['group_booking_id' => '12'],
            ];

            $result = PaymentService::verifyGroupBookingPayment(12, 'cs_test_unpaid');

            $this->assertNull($result);
        }

        /**
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testVerifyStripeGroupBookingPaymentReturnsNullForMetadataMismatch(): void
        {
            define('PAYMENT_PROVIDER', 'stripe');
            define('STRIPE_SECRET_KEY', 'sk_live_test');

            \Stripe\Checkout\Session::$sessions['cs_test_mismatch'] = (object) [
                'payment_status' => 'paid',
                'metadata'       => (object) ['group_booking_id' => '99'],
            ];

            $result = PaymentService::verifyGroupBookingPayment(12, 'cs_test_mismatch');

            $this->assertNull($result);
        }

        /**
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testVerifySquareGroupBookingPaymentSucceedsForCompletedPayment(): void
        {
            define('PAYMENT_PROVIDER', 'square');
            define('SQUARE_ACCESS_TOKEN', 'square_live_token');
            define('SQUARE_LOCATION_ID', 'loc_123');
            define('SQUARE_ENVIRONMENT', 'sandbox');

            \Square\TestState::$orderResponse = new TestSquareOrderResponse(
                new TestSquareOrder('OPEN', [new TestSquareTender('pay_1')])
            );
            \Square\TestState::$paymentResponses['pay_1'] = new TestSquarePaymentResponse(
                new TestSquarePayment('COMPLETED')
            );

            $result = PaymentService::verifyGroupBookingPayment(33, null, 'sq_order_order_1');

            $this->assertSame('sq_order_order_1', $result);
        }

        /**
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testVerifySquareGroupBookingPaymentReturnsNullForUnpaidTender(): void
        {
            define('PAYMENT_PROVIDER', 'square');
            define('SQUARE_ACCESS_TOKEN', 'square_live_token');
            define('SQUARE_LOCATION_ID', 'loc_123');
            define('SQUARE_ENVIRONMENT', 'sandbox');

            \Square\TestState::$orderResponse = new TestSquareOrderResponse(
                new TestSquareOrder('OPEN', [new TestSquareTender('pay_2')])
            );
            \Square\TestState::$paymentResponses['pay_2'] = new TestSquarePaymentResponse(
                new TestSquarePayment('PENDING')
            );

            $result = PaymentService::verifyGroupBookingPayment(33, null, 'sq_order_order_2');

            $this->assertNull($result);
        }
    }
}
