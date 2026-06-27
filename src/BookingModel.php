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
        int $numberOfChildren = 1
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO bookings
                 (user_id, session_id, used_credit,
                  child_first_name, child_last_name, child_age, child_allergies,
                  promo_code_id, discount_cents, number_of_children)
             VALUES (:uid, :sid, :credit, :cfn, :cln, :cage, :callergies,
                     :promo_code_id, :discount_cents, :number_of_children)
             RETURNING id'
        );
        $stmt->execute([
            ':uid'                => $userId,
            ':sid'                => $sessionId,
            ':credit'             => ($usedCredit ? 'TRUE' : 'FALSE'),
            ':cfn'                => $childFirstName,
            ':cln'                => $childLastName,
            ':cage'               => $childAge > 0 ? $childAge : null,
            ':callergies'         => $childAllergies !== '' ? $childAllergies : null,
            ':promo_code_id'      => $promoCodeId,
            ':discount_cents'     => $discountCents,
            ':number_of_children' => max(1, $numberOfChildren),
        ]);
        return (int) $stmt->fetchColumn();
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
}
