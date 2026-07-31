<?php
// src/GroupBookingModel.php – CRUD for group booking requests (birthday parties)

class GroupBookingModel
{
    /** Price per child (in cents) when hosted at the group's home. */
    public const PRICE_HOME_CENTS = 3000;

    /** Price per child (in cents) when hosted at Escales Culinaires. */
    public const PRICE_ESCALES_CENTS = 3500;

    /** Minimum number of children in a group booking. */
    public const MIN_CHILDREN = 4;

    /** Maximum number of children in a group booking. */
    public const MAX_CHILDREN = 8;

    /** Minimum days in advance a group booking must be made. */
    public const MIN_ADVANCE_DAYS = 7;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.first_name, u.last_name, u.email
             FROM group_booking_requests r
             JOIN users u ON u.id = r.user_id
             WHERE r.id = :id AND r.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM group_booking_requests
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT r.*, u.first_name, u.last_name, u.email
             FROM group_booking_requests r
             JOIN users u ON u.id = r.user_id
             WHERE r.deleted_at IS NULL
             ORDER BY r.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function getPending(): array
    {
        $stmt = $this->db->query(
            "SELECT r.*, u.first_name, u.last_name, u.email
             FROM group_booking_requests r
             JOIN users u ON u.id = r.user_id
             WHERE r.deleted_at IS NULL AND r.status = 'pending'
             ORDER BY r.preferred_date ASC, r.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_booking_requests
                 (user_id, group_session_slot_id, contact_phone, nb_children, children_ages,
                  preferred_date, location_type, location_address,
                  allergies, additional_info)
             VALUES
                 (:user_id, :group_session_slot_id, :contact_phone, :nb_children, :children_ages,
                  :preferred_date, :location_type, :location_address,
                  :allergies, :additional_info)
             RETURNING id'
        );
        $stmt->execute([
            ':user_id'                => (int) $data['user_id'],
            ':group_session_slot_id'  => isset($data['group_session_slot_id']) ? (int) $data['group_session_slot_id'] : null,
            ':contact_phone'          => $data['contact_phone'] ?: null,
            ':nb_children'            => (int) $data['nb_children'],
            ':children_ages'          => $data['children_ages'] ?: null,
            ':preferred_date'         => $data['preferred_date'],
            ':location_type'          => $data['location_type'],
            ':location_address'       => $data['location_address'] ?: null,
            ':allergies'              => $data['allergies'] ?: null,
            ':additional_info'        => $data['additional_info'] ?: null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(int $id, string $status, ?string $adminNotes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_booking_requests
             SET status = :status, admin_notes = :admin_notes
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':id'          => $id,
            ':status'      => $status,
            ':admin_notes' => $adminNotes,
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_booking_requests SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Returns the estimated total price in cents for a group booking.
     *
     * @param int    $nbChildren   Number of children.
     * @param string $locationType 'home' or 'escales'.
     * @param int|null $priceHomeCents    Per-child price for home (slot-specific); falls back to PRICE_HOME_CENTS.
     * @param int|null $priceEscalesCents Per-child price for escales (slot-specific); falls back to PRICE_ESCALES_CENTS.
     */
    public static function estimatePrice(
        int $nbChildren,
        string $locationType,
        ?int $priceHomeCents = null,
        ?int $priceEscalesCents = null
    ): int {
        $homePrice    = $priceHomeCents    ?? self::PRICE_HOME_CENTS;
        $escalesPrice = $priceEscalesCents ?? self::PRICE_ESCALES_CENTS;
        $unitPrice    = $locationType === 'home' ? $homePrice : $escalesPrice;
        return $nbChildren * $unitPrice;
    }

    public function countPending(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM group_booking_requests
             WHERE deleted_at IS NULL AND status = 'pending'"
        );
        return (int) $stmt->fetchColumn();
    }
}
