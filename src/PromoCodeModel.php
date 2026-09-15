<?php
// src/PromoCodeModel.php – promotional code CRUD and validation

class PromoCodeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, s.title AS session_title
             FROM promo_codes p
             LEFT JOIN sessions s ON s.id = p.session_id
             WHERE p.id = :id AND p.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, s.title AS session_title
             FROM promo_codes p
             LEFT JOIN sessions s ON s.id = p.session_id
             WHERE p.code = :code AND p.deleted_at IS NULL'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, s.title AS session_title
             FROM promo_codes p
             LEFT JOIN sessions s ON s.id = p.session_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO promo_codes
                 (code, session_id, discount_cents, max_uses, expires_at)
             VALUES (:code, :session_id, :discount_cents, :max_uses, :expires_at)
             RETURNING id'
        );
        $stmt->execute([
            ':code'           => strtoupper(trim($data['code'])),
            ':session_id'     => $data['session_id'] ?: null,
            ':discount_cents' => (int) $data['discount_cents'],
            ':max_uses'       => isset($data['max_uses']) && $data['max_uses'] !== '' ? (int) $data['max_uses'] : null,
            ':expires_at'     => $data['expires_at'] ?: null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_codes
             SET code = :code,
                 session_id = :session_id,
                 discount_cents = :discount_cents,
                 max_uses = :max_uses,
                 expires_at = :expires_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':code'           => strtoupper(trim($data['code'])),
            ':session_id'     => $data['session_id'] ?: null,
            ':discount_cents' => (int) $data['discount_cents'],
            ':max_uses'       => isset($data['max_uses']) && $data['max_uses'] !== '' ? (int) $data['max_uses'] : null,
            ':expires_at'     => $data['expires_at'] ?: null,
            ':id'             => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_codes SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function incrementUsedCount(int $id, int $quantity = 1): void
    {
        $quantity = max(1, $quantity);

        $stmt = $this->db->prepare(
            'UPDATE promo_codes SET used_count = used_count + :qty WHERE id = :id'
        );
        $stmt->execute([':qty' => $quantity, ':id' => $id]);
    }

    /**
     * Validates a promo code for a given session.
     *
     * Returns the promo code row (with discount_cents) if valid, or null if:
     * – code does not exist or is deleted
     * – code is session-specific and does not match $sessionId
     * – code has reached its max_uses limit
     * – code has expired
     *
     * @param string $code      The promotional code entered by the user.
     * @param int    $sessionId The session being booked.
     * @return array|null       The promo code row, or null if invalid.
     */
    public function validateForSession(string $code, int $sessionId): ?array
    {
        $promo = $this->findByCode($code);

        if ($promo === null) {
            return null;
        }

        // Session-specific check
        if ($promo['session_id'] !== null && (int) $promo['session_id'] !== $sessionId) {
            return null;
        }

        // Expiry check
        if ($promo['expires_at'] !== null && strtotime($promo['expires_at']) < time()) {
            return null;
        }

        // Usage limit check
        if ($promo['max_uses'] !== null && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
            return null;
        }

        return $promo;
    }
}
