<?php
// src/BasketModel.php – basket item CRUD

class BasketModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Returns all basket items for a user, joined with session info.
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT bi.*, s.title, s.session_date, s.start_time, s.end_time,
                    s.price_cents, s.remaining_seats, s.status AS session_status,
                    s.is_private
             FROM basket_items bi
             JOIN sessions s ON s.id = bi.session_id
             WHERE bi.user_id = :uid
             ORDER BY s.session_date ASC, s.start_time ASC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns a single basket item for the given user and session, or null.
     */
    public function findItem(int $userId, int $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM basket_items WHERE user_id = :uid AND session_id = :sid'
        );
        $stmt->execute([':uid' => $userId, ':sid' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Adds a session to the user's basket. Replaces child info if item already exists.
     */
    public function addItem(
        int $userId,
        int $sessionId,
        string $childFirstName = '',
        string $childLastName = '',
        int $childAge = 0,
        string $childAllergies = '',
        int $numberOfChildren = 1
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO basket_items
                 (user_id, session_id, child_first_name, child_last_name, child_age, child_allergies, number_of_children)
             VALUES (:uid, :sid, :cfn, :cln, :cage, :callergies, :number_of_children)
             ON CONFLICT (user_id, session_id) DO UPDATE
                 SET child_first_name  = EXCLUDED.child_first_name,
                     child_last_name   = EXCLUDED.child_last_name,
                     child_age         = EXCLUDED.child_age,
                     child_allergies   = EXCLUDED.child_allergies,
                     number_of_children = EXCLUDED.number_of_children'
        );
        $stmt->execute([
            ':uid'                => $userId,
            ':sid'                => $sessionId,
            ':cfn'                => $childFirstName !== '' ? $childFirstName : null,
            ':cln'                => $childLastName  !== '' ? $childLastName  : null,
            ':cage'               => $childAge > 0 ? $childAge : null,
            ':callergies'         => $childAllergies !== '' ? $childAllergies : null,
            ':number_of_children' => max(1, $numberOfChildren),
        ]);
    }

    /**
     * Removes a specific item from the basket.
     */
    public function removeItem(int $userId, int $sessionId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM basket_items WHERE user_id = :uid AND session_id = :sid'
        );
        $stmt->execute([':uid' => $userId, ':sid' => $sessionId]);
    }

    /**
     * Removes all items from the user's basket.
     */
    public function clearByUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM basket_items WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
    }

    /**
     * Returns the number of items currently in the user's basket.
     */
    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM basket_items WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
