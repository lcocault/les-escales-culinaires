<?php
// src/GroupSessionSlotModel.php – CRUD for admin-defined group session slots

class GroupSessionSlotModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM group_session_slots WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Upcoming slots (from today onwards, not cancelled, not deleted). */
    public function getUpcoming(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM group_session_slots
             WHERE slot_date >= CURRENT_DATE AND deleted_at IS NULL AND status != 'cancelled'
             ORDER BY slot_date ASC, start_time ASC"
        );
        return $stmt->fetchAll();
    }

    /** All slots for admin listing (active, not deleted). */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM group_booking_requests r
                     WHERE r.group_session_slot_id = s.id
                       AND r.deleted_at IS NULL
                       AND r.status != \'cancelled\') AS booking_count
             FROM group_session_slots s
             WHERE s.deleted_at IS NULL
             ORDER BY s.slot_date ASC, s.start_time ASC'
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_session_slots
                 (title, description, slot_date, start_time, end_time,
                  max_groups, remaining_groups,
                  price_per_child_home_cents, price_per_child_escales_cents)
             VALUES
                 (:title, :description, :slot_date, :start_time, :end_time,
                  :max_groups, :max_groups,
                  :price_per_child_home_cents, :price_per_child_escales_cents)
             RETURNING id'
        );
        $stmt->execute([
            ':title'                        => $data['title'],
            ':description'                  => $data['description'] ?: null,
            ':slot_date'                    => $data['slot_date'],
            ':start_time'                   => $data['start_time'],
            ':end_time'                     => $data['end_time'],
            ':max_groups'                   => (int) $data['max_groups'],
            ':price_per_child_home_cents'   => (int) $data['price_per_child_home_cents'],
            ':price_per_child_escales_cents' => (int) $data['price_per_child_escales_cents'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_session_slots
             SET title = :title,
                 description = :description,
                 slot_date = :slot_date,
                 start_time = :start_time,
                 end_time = :end_time,
                 max_groups = :max_groups,
                 price_per_child_home_cents = :price_per_child_home_cents,
                 price_per_child_escales_cents = :price_per_child_escales_cents,
                 status = :status
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':id'                            => $id,
            ':title'                         => $data['title'],
            ':description'                   => $data['description'] ?: null,
            ':slot_date'                     => $data['slot_date'],
            ':start_time'                    => $data['start_time'],
            ':end_time'                      => $data['end_time'],
            ':max_groups'                    => (int) $data['max_groups'],
            ':price_per_child_home_cents'    => (int) $data['price_per_child_home_cents'],
            ':price_per_child_escales_cents' => (int) $data['price_per_child_escales_cents'],
            ':status'                        => $data['status'],
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_session_slots SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function decrementGroups(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_session_slots
             SET remaining_groups = remaining_groups - 1
             WHERE id = :id AND remaining_groups > 0'
        );
        $stmt->execute([':id' => $id]);
    }

    public function incrementGroups(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_session_slots
             SET remaining_groups = LEAST(remaining_groups + 1, max_groups)
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function countUpcoming(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM group_session_slots
             WHERE slot_date >= CURRENT_DATE AND deleted_at IS NULL AND status != 'cancelled'"
        );
        return (int) $stmt->fetchColumn();
    }
}
