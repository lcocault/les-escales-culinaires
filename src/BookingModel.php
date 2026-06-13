<?php
// src/BookingModel.php – booking CRUD

class BookingModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUserAndSession(int $userId, int $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM bookings WHERE user_id = :uid AND session_id = :sid'
        );
        $stmt->execute([':uid' => $userId, ':sid' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, s.title, s.session_date, s.start_time, s.end_time, s.theme
             FROM bookings b
             JOIN sessions s ON s.id = b.session_id
             WHERE b.user_id = :uid
             ORDER BY s.session_date DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getBySession(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, u.first_name, u.last_name, u.email, u.phone, u.phone2, u.photo_consent
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.session_id = :sid
             ORDER BY b.created_at ASC'
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns confirmed bookings for a session, including the user info needed
     * to send notification emails.
     */
    public function getConfirmedBySession(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, u.first_name, u.last_name, u.email, u.phone
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.session_id = :sid AND b.status = 'confirmed'
             ORDER BY b.created_at ASC"
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll();
    }

    public function create(
        int $userId,
        int $sessionId,
        bool $usedCredit = false,
        string $childFirstName = '',
        string $childLastName = '',
        int $childAge = 0,
        string $childAllergies = '',
        ?int $promoCodeId = null,
        int $discountCents = 0,
        int $nbChildren = 1
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO bookings
                 (user_id, session_id, used_credit,
                  child_first_name, child_last_name, child_age, child_allergies,
                  promo_code_id, discount_cents, nb_children)
             VALUES (:uid, :sid, :credit, :cfn, :cln, :cage, :callergies,
                     :promo_code_id, :discount_cents, :nb_children)
             RETURNING id'
        );
        $stmt->execute([
            ':uid'            => $userId,
            ':sid'            => $sessionId,
            ':credit'         => ($usedCredit ? 'TRUE' : 'FALSE'),
            ':cfn'            => $childFirstName,
            ':cln'            => $childLastName,
            ':cage'           => $childAge > 0 ? $childAge : null,
            ':callergies'     => $childAllergies !== '' ? $childAllergies : null,
            ':promo_code_id'  => $promoCodeId,
            ':discount_cents' => $discountCents,
            ':nb_children'    => max(1, $nbChildren),
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Inserts additional children (2nd, 3rd, etc.) for an existing booking.
     * Each element of $children must have keys: first_name, last_name, age, allergies.
     * child_order starts at 2 for the second child.
     */
    public function addExtraChildren(int $bookingId, array $children): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO booking_children (booking_id, first_name, last_name, age, allergies, child_order)
             VALUES (:booking_id, :first_name, :last_name, :age, :allergies, :child_order)'
        );
        $order = 2;
        foreach ($children as $child) {
            $stmt->execute([
                ':booking_id'  => $bookingId,
                ':first_name'  => $child['first_name'],
                ':last_name'   => $child['last_name'],
                ':age'         => isset($child['age']) && (int) $child['age'] > 0 ? (int) $child['age'] : null,
                ':allergies'   => ($child['allergies'] ?? '') !== '' ? $child['allergies'] : null,
                ':child_order' => $order++,
            ]);
        }
    }

    /**
     * Returns all extra children (2nd, 3rd, etc.) for a booking, ordered by child_order.
     */
    public function getExtraChildren(int $bookingId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM booking_children WHERE booking_id = :bid ORDER BY child_order ASC'
        );
        $stmt->execute([':bid' => $bookingId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns all extra children for a list of booking IDs, indexed by booking_id.
     *
     * @param int[] $bookingIds
     * @return array<int, array>  Keyed by booking_id, each value is an array of children rows.
     */
    public function getExtraChildrenMapped(array $bookingIds): array
    {
        if (empty($bookingIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM booking_children WHERE booking_id IN ($placeholders) ORDER BY booking_id, child_order ASC"
        );
        $stmt->execute(array_values($bookingIds));
        $rows = $stmt->fetchAll();
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(int) $row['booking_id']][] = $row;
        }
        return $mapped;
    }

    public function storePaymentRef(int $bookingId, string $ref): void
    {
        $stmt = $this->db->prepare(
            'UPDATE bookings SET payment_intent_id = :ref WHERE id = :id'
        );
        $stmt->execute([':ref' => $ref, ':id' => $bookingId]);
    }

    public function confirm(int $bookingId, string $paymentIntentId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET status = 'confirmed', payment_intent_id = :pi, paid_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':pi' => $paymentIntentId, ':id' => $bookingId]);
    }

    public function markAttended(int $bookingId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET status = 'attended', confirmed_by_admin = TRUE WHERE id = :id"
        );
        $stmt->execute([':id' => $bookingId]);
    }

    public function markAbsent(int $bookingId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET status = 'absent' WHERE id = :id"
        );
        $stmt->execute([':id' => $bookingId]);
    }

    public function markCredited(int $bookingId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET status = 'credited' WHERE id = :id"
        );
        $stmt->execute([':id' => $bookingId]);
    }

    public function cancel(int $bookingId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET status = 'cancelled' WHERE id = :id"
        );
        $stmt->execute([':id' => $bookingId]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM bookings WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function hasAccessToContent(int $userId, int $sessionId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE user_id = :uid AND session_id = :sid AND status = 'attended'"
        );
        $stmt->execute([':uid' => $userId, ':sid' => $sessionId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Returns all 'attended' bookings that have no rating and whose
     * rating_reminder_dismissed flag is not set, ordered by session date.
     * Includes user and session info for display.
     */
    public function getAttendedWithoutRating(): array
    {
        $stmt = $this->db->query(
            "SELECT b.id AS booking_id,
                    b.user_id, b.session_id,
                    u.first_name, u.last_name, u.email,
                    s.title AS session_title, s.session_date
             FROM bookings b
             JOIN users    u ON u.id = b.user_id
             JOIN sessions s ON s.id = b.session_id
             WHERE b.status = 'attended'
               AND b.rating_reminder_dismissed = FALSE
               AND NOT EXISTS (
                   SELECT 1 FROM ratings r
                   WHERE r.booking_id = b.id
               )
             ORDER BY s.session_date DESC, u.last_name ASC, u.first_name ASC"
        );
        return $stmt->fetchAll();
    }

    public function dismissRatingReminder(int $bookingId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE bookings SET rating_reminder_dismissed = TRUE WHERE id = :id'
        );
        $stmt->execute([':id' => $bookingId]);
    }
}
