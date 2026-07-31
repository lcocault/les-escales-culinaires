<?php
// src/ShopOrderModel.php – shop order CRUD

class ShopOrderModel
{
    private PDO $db;

    /** Delivery fee in cents for home delivery. */
    public const HOME_DELIVERY_FEE_CENTS = 500;

    /** Minimum number of days before delivery date when ordering. */
    public const MIN_DAYS_BEFORE_DELIVERY = 2;

    /** Market delivery pickup starts at 08:30 and needs 36h prep. */
    public const MARKET_PICKUP_TIME = '08:30:00';
    public const MARKET_PREPARATION_HOURS = 36;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Retrieval ─────────────────────────────────────────────────────────────

    /** Returns all orders with user info, newest first. */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT o.*, u.first_name, u.last_name, u.email
             FROM shop_orders o
             JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    /** Returns all orders for a user, newest first. */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM shop_orders WHERE user_id = :uid ORDER BY created_at DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Returns a single order or null if not found. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM shop_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Returns all items for an order, with product info. */
    public function getItems(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, p.photo_filename, p.external_photo_url
             FROM shop_order_items i
             LEFT JOIN shop_products p ON p.id = i.product_id
             WHERE i.order_id = :oid
             ORDER BY i.id ASC'
        );
        $stmt->execute([':oid' => $orderId]);
        return $stmt->fetchAll();
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    /**
     * Creates a new order with items and returns the order ID.
     *
     * @param int    $userId
     * @param string $deliveryMethod  'home'|'market_wednesday'|'market_friday'|'shop'
     * @param string $deliveryDate    'Y-m-d'
     * @param string $deliveryAddress Only required when deliveryMethod='home'
     * @param array  $items           Array of ['product_id', 'product_name', 'unit_price_cents', 'quantity']
     * @return int  New order ID
     */
    public function create(
        int $userId,
        string $deliveryMethod,
        string $deliveryDate,
        string $deliveryAddress,
        array $items
    ): int {
        $deliveryFeeCents = ($deliveryMethod === 'home') ? self::HOME_DELIVERY_FEE_CENTS : 0;

        $itemsTotal = 0;
        foreach ($items as $item) {
            $itemsTotal += (int) $item['unit_price_cents'] * (int) $item['quantity'];
        }
        $totalCents = $itemsTotal + $deliveryFeeCents;

        $stmt = $this->db->prepare(
            'INSERT INTO shop_orders
                 (user_id, delivery_method, delivery_date, delivery_address, delivery_fee_cents, total_cents)
             VALUES (:uid, :method, :date, :address, :fee, :total)
             RETURNING id'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':method'  => $deliveryMethod,
            ':date'    => $deliveryDate,
            ':address' => $deliveryAddress !== '' ? $deliveryAddress : null,
            ':fee'     => $deliveryFeeCents,
            ':total'   => $totalCents,
        ]);
        $orderId = (int) $stmt->fetchColumn();

        $insert = $this->db->prepare(
            'INSERT INTO shop_order_items (order_id, product_id, product_name, unit_price_cents, quantity)
             VALUES (:oid, :pid, :pname, :price, :qty)'
        );
        foreach ($items as $item) {
            $insert->execute([
                ':oid'   => $orderId,
                ':pid'   => $item['product_id'] ?? null,
                ':pname' => $item['product_name'],
                ':price' => (int) $item['unit_price_cents'],
                ':qty'   => (int) $item['quantity'],
            ]);
        }

        return $orderId;
    }

    /** Marks an order as paid and stores the payment reference. */
    public function markPaid(int $orderId, string $paymentIntentId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shop_orders
             SET status = 'paid', payment_intent_id = :pi, paid_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':pi' => $paymentIntentId, ':id' => $orderId]);
    }

    /** Advances an order to 'prepared'. */
    public function markPrepared(int $orderId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shop_orders SET status = 'prepared' WHERE id = :id"
        );
        $stmt->execute([':id' => $orderId]);
    }

    /** Advances an order to 'delivered'. */
    public function markDelivered(int $orderId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shop_orders SET status = 'delivered' WHERE id = :id"
        );
        $stmt->execute([':id' => $orderId]);
    }

    /** Marks an order as 'cancelled'. */
    public function cancel(int $orderId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shop_orders SET status = 'cancelled' WHERE id = :id"
        );
        $stmt->execute([':id' => $orderId]);
    }

    /** Deletes a pending order (and its items via cascade) – used on payment cancel. */
    public function deletePending(int $orderId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM shop_orders WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([':id' => $orderId]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the next available date (as 'Y-m-d') for a given delivery method
     * that is at least MIN_DAYS_BEFORE_DELIVERY days from today.
     *
     * For market_wednesday / market_friday, it returns the next Wednesday or
     * Friday that satisfies the constraint.
     * For home / shop, it just returns today + MIN_DAYS_BEFORE_DELIVERY.
     */
    public static function nextAvailableDate(string $deliveryMethod, ?int $nowTs = null, array $cancelledMarketDates = []): string
    {
        $nowTs = $nowTs ?? time();
        $cancelledSet = array_fill_keys($cancelledMarketDates, true);
        switch ($deliveryMethod) {
            case 'market_wednesday':
                return self::nextMarketDate(3, $nowTs, $cancelledSet); // 3 = Wednesday
            case 'market_friday':
                return self::nextMarketDate(5, $nowTs, $cancelledSet); // 5 = Friday
            default:
                $minTs = strtotime('+' . self::MIN_DAYS_BEFORE_DELIVERY . ' days', $nowTs);
                return date('Y-m-d', $minTs);
        }
    }

    /**
     * Returns the earliest date string >= $fromTs that falls on $targetDow (0=Sun … 6=Sat).
     * When $fromTs already falls on $targetDow (diff = 0), that same day is returned because
     * $fromTs already satisfies the minimum-days constraint computed by the caller.
     */
    private static function nextWeekday(int $fromTs, int $targetDow): string
    {
        $dow  = (int) date('w', $fromTs);
        $diff = ($targetDow - $dow + 7) % 7;
        return date('Y-m-d', $fromTs + $diff * 86400);
    }

    /**
     * Validates that a delivery date string is:
     *  – a valid date
     *  – at least MIN_DAYS_BEFORE_DELIVERY days from today
     *  – on the correct weekday for market methods
     */
    public static function validateDeliveryDate(string $deliveryMethod, string $dateStr, ?int $nowTs = null, array $cancelledMarketDates = []): bool
    {
        $ts = strtotime($dateStr . ' midnight');
        if ($ts === false) {
            return false;
        }
        $nowTs = $nowTs ?? time();

        if ($deliveryMethod === 'market_wednesday' || $deliveryMethod === 'market_friday') {
            $expectedDow = ($deliveryMethod === 'market_wednesday') ? 3 : 5;
            if ((int) date('w', $ts) !== $expectedDow) {
                return false;
            }
            if (in_array($dateStr, $cancelledMarketDates, true)) {
                return false;
            }
            $pickupTs = strtotime($dateStr . ' ' . self::MARKET_PICKUP_TIME);
            if ($pickupTs === false) {
                return false;
            }
            return $pickupTs >= self::marketCutoffTimestamp($nowTs);
        }

        $minTs = strtotime('+' . self::MIN_DAYS_BEFORE_DELIVERY . ' days midnight', $nowTs);
        if ($ts < $minTs) {
            return false;
        }

        return true;
    }

    /** Returns market pickup cutoff timestamp based on preparation delay. */
    public static function marketCutoffTimestamp(?int $nowTs = null): int
    {
        return ($nowTs ?? time()) + self::MARKET_PREPARATION_HOURS * 3600;
    }

    /**
     * Validates whether a date belongs to a market delivery day.
     *
     * @param string $dateStr Date in 'Y-m-d' format.
     * @return bool True when parsed date is Wednesday (3) or Friday (5).
     */
    public static function isMarketDeliveryDate(string $dateStr): bool
    {
        $ts = strtotime($dateStr . ' midnight');
        if ($ts === false) {
            return false;
        }
        $dow = (int) date('w', $ts);
        return $dow === 3 || $dow === 5;
    }

    /**
     * Returns upcoming candidate market delivery dates (Wednesday/Friday).
     *
     * @return array<int, array{date:string, method:string}>
     */
    public static function candidateMarketDates(int $limit = 12, ?int $nowTs = null): array
    {
        $limit = max(1, $limit);
        $nowTs = $nowTs ?? time();
        $cancelledSet = [];

        $next = [
            'market_wednesday' => self::nextMarketDate(3, $nowTs, $cancelledSet),
            'market_friday'    => self::nextMarketDate(5, $nowTs, $cancelledSet),
        ];

        $out = [];
        while (count($out) < $limit) {
            if ($next['market_wednesday'] <= $next['market_friday']) {
                $date = $next['market_wednesday'];
                $out[] = ['date' => $date, 'method' => 'market_wednesday'];
                $next['market_wednesday'] = date('Y-m-d', strtotime($date . ' +7 days'));
            } else {
                $date = $next['market_friday'];
                $out[] = ['date' => $date, 'method' => 'market_friday'];
                $next['market_friday'] = date('Y-m-d', strtotime($date . ' +7 days'));
            }
        }

        return $out;
    }

    /** Returns cancelled market delivery dates from today onward. */
    public function getCancelledMarketDates(): array
    {
        $stmt = $this->db->query(
            'SELECT delivery_date::text
             FROM shop_market_delivery_cancellations
             WHERE delivery_date >= CURRENT_DATE
             ORDER BY delivery_date ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** Returns number of orders per delivery date for provided date list. */
    public function getOrderCountsByDeliveryDates(array $dates): array
    {
        $dates = array_values(array_unique(array_filter($dates, static fn ($d): bool => is_string($d) && $d !== '')));
        if (empty($dates)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($dates as $i => $date) {
            $key = ':d' . $i;
            $placeholders[] = $key;
            $params[$key] = $date;
        }

        $sql = 'SELECT delivery_date::text AS d, COUNT(*)::int AS c
                FROM shop_orders
                WHERE delivery_method IN (\'market_wednesday\', \'market_friday\')
                  AND delivery_date IN (' . implode(',', $placeholders) . ')
                GROUP BY delivery_date';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['d']] = (int) $row['c'];
        }
        return $out;
    }

    /** Cancels a candidate market date (insert ignore if already cancelled). */
    public function cancelMarketDate(string $date): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO shop_market_delivery_cancellations (delivery_date)
             VALUES (:date)
             ON CONFLICT (delivery_date) DO NOTHING'
        );
        $stmt->execute([':date' => $date]);
    }

    private static function nextMarketDate(int $targetDow, int $nowTs, array $cancelledSet): string
    {
        $cutoffTs = self::marketCutoffTimestamp($nowTs);
        $candidate = self::nextWeekday($cutoffTs, $targetDow);
        $pickupTs = strtotime($candidate . ' ' . self::MARKET_PICKUP_TIME);
        if ($pickupTs === false || $pickupTs < $cutoffTs) {
            $candidate = date('Y-m-d', strtotime($candidate . ' +7 days'));
        }

        while (isset($cancelledSet[$candidate])) {
            $candidate = date('Y-m-d', strtotime($candidate . ' +7 days'));
        }
        return $candidate;
    }
}
