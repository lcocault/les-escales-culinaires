<?php
// src/SessionModel.php – cooking-session CRUD

class SessionModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Upcoming sessions visible to the public (and to allowed users for private sessions)
    public function getUpcoming(?int $userId = null): array
    {
        if ($userId !== null) {
            $stmt = $this->db->prepare(
                "SELECT id, title, theme, session_date, start_time, end_time,
                        max_attendees, remaining_seats, price_cents, summary, age_category, is_private
                 FROM sessions
                 WHERE session_date >= CURRENT_DATE AND deleted_at IS NULL AND status != 'cancelled'
                   AND (is_private = FALSE OR id IN (
                       SELECT session_id FROM session_allowances WHERE user_id = :user_id
                   ))
                 ORDER BY session_date ASC, start_time ASC"
            );
            $stmt->execute([':user_id' => $userId]);
        } else {
            $stmt = $this->db->query(
                "SELECT id, title, theme, session_date, start_time, end_time,
                        max_attendees, remaining_seats, price_cents, summary, age_category, is_private
                 FROM sessions
                 WHERE session_date >= CURRENT_DATE AND deleted_at IS NULL AND status != 'cancelled'
                   AND is_private = FALSE
                 ORDER BY session_date ASC, start_time ASC"
            );
        }
        return $stmt->fetchAll();
    }

    // Active (pending) sessions for admin – chronological order, with registered count
    public function getAll(): array
    {
        $stmt = $this->db->query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM bookings b
                     WHERE b.session_id = s.id
                       AND b.status IN ('confirmed', 'attended', 'absent', 'credited')) AS registered_count
             FROM sessions s
             WHERE s.deleted_at IS NULL
               AND s.status NOT IN ('confirmed', 'cancelled')
             ORDER BY s.session_date ASC, s.start_time ASC"
        );
        return $stmt->fetchAll();
    }

    // Archived sessions (closed/cancelled) for admin – newest first
    public function getArchived(): array
    {
        $stmt = $this->db->query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM bookings b
                     WHERE b.session_id = s.id
                       AND b.status IN ('confirmed', 'attended', 'absent', 'credited')) AS registered_count
             FROM sessions s
             WHERE s.deleted_at IS NULL
               AND s.status IN ('confirmed', 'cancelled')
             ORDER BY s.session_date DESC, s.start_time DESC"
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sessions WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sessions
                (title, theme, session_date, start_time, end_time,
                 max_attendees, remaining_seats, price_cents,
                 summary, objectives, theoretical_content, recipe, age_category, is_private)
             VALUES
                (:title, :theme, :session_date, :start_time, :end_time,
                 :max_attendees, :max_attendees, :price_cents,
                 :summary, :objectives, :theoretical_content, :recipe, :age_category, :is_private)
             RETURNING id'
        );
        $stmt->execute([
            ':title'               => $data['title'],
            ':theme'               => $data['theme'],
            ':session_date'        => $data['session_date'],
            ':start_time'          => $data['start_time'],
            ':end_time'            => $data['end_time'],
            ':max_attendees'       => (int) $data['max_attendees'],
            ':price_cents'         => (int) $data['price_cents'],
            ':summary'             => $data['summary'] ?? null,
            ':objectives'          => $data['objectives'] ?? null,
            ':theoretical_content' => $data['theoretical_content'] ?? null,
            ':recipe'              => $data['recipe'] ?? null,
            ':age_category'        => $data['age_category'] ?? '6-12',
            ':is_private'          => ($data['is_private'] ?? false) ? 'TRUE' : 'FALSE',
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sessions SET
                title = :title, theme = :theme, session_date = :session_date,
                start_time = :start_time, end_time = :end_time,
                max_attendees = :max_attendees, price_cents = :price_cents,
                summary = :summary, objectives = :objectives,
                theoretical_content = :theoretical_content, recipe = :recipe,
                age_category = :age_category, is_private = :is_private
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':title'               => $data['title'],
            ':theme'               => $data['theme'],
            ':session_date'        => $data['session_date'],
            ':start_time'          => $data['start_time'],
            ':end_time'            => $data['end_time'],
            ':max_attendees'       => (int) $data['max_attendees'],
            ':price_cents'         => (int) $data['price_cents'],
            ':summary'             => $data['summary'] ?? null,
            ':objectives'          => $data['objectives'] ?? null,
            ':theoretical_content' => $data['theoretical_content'] ?? null,
            ':recipe'              => $data['recipe'] ?? null,
            ':age_category'        => $data['age_category'] ?? '6-12',
            ':is_private'          => ($data['is_private'] ?? false) ? 'TRUE' : 'FALSE',
            ':id'                  => $id,
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sessions SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function confirmSession(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE sessions SET status = 'confirmed' WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
    }

    public function cancelSession(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE sessions SET status = 'cancelled' WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
    }

    public function closePastSession(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE sessions
             SET status = 'confirmed'
             WHERE id = :id
               AND deleted_at IS NULL
               AND status = 'pending'
               AND (session_date::timestamp + end_time::interval) < NOW()"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Returns pending sessions whose start datetime falls within the next 24 hours.
     * Used by the cron job to decide whether to confirm or cancel each session.
     */
    public function getSessionsDueForCheck(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, title, session_date, start_time, end_time, status
             FROM sessions
             WHERE deleted_at IS NULL
               AND status = 'pending'
               AND (session_date + start_time)::timestamp
                   BETWEEN NOW()::timestamp
                       AND (NOW() + INTERVAL '24 hours')::timestamp
             ORDER BY session_date ASC, start_time ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUpcomingInPeriod(string $fromDate, string $toDate): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, title, theme, session_date, start_time, end_time, age_category, price_cents
             FROM sessions
             WHERE deleted_at IS NULL
               AND status != 'cancelled'
               AND session_date >= CURRENT_DATE
               AND session_date BETWEEN :from_date AND :to_date
             ORDER BY session_date ASC, start_time ASC"
        );
        $stmt->execute([
            ':from_date' => $fromDate,
            ':to_date'   => $toDate,
        ]);
        return $stmt->fetchAll();
    }

    public function isUserAllowed(int $sessionId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM session_allowances WHERE session_id = :sid AND user_id = :uid'
        );
        $stmt->execute([':sid' => $sessionId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function allowUser(int $sessionId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO session_allowances (session_id, user_id)
             VALUES (:sid, :uid)
             ON CONFLICT (session_id, user_id) DO NOTHING'
        );
        $stmt->execute([':sid' => $sessionId, ':uid' => $userId]);
    }

    public function revokeUser(int $sessionId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM session_allowances WHERE session_id = :sid AND user_id = :uid'
        );
        $stmt->execute([':sid' => $sessionId, ':uid' => $userId]);
    }

    /**
     * Returns all users (with allowance info) for a given private session.
     * Each row includes user data plus an `is_allowed` flag, `has_booking` flag,
     * and participation counts for private and public sessions.
     */
    public function getAllowedUsers(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email,
                    (sa.user_id IS NOT NULL) AS is_allowed,
                    (bchk.user_id IS NOT NULL) AS has_booking,
                    COALESCE(agg.private_sessions_attended, 0) AS private_sessions_attended,
                    COALESCE(agg.public_sessions_attended, 0) AS public_sessions_attended
             FROM users u
             LEFT JOIN session_allowances sa
                    ON sa.session_id = :sid AND sa.user_id = u.id
             LEFT JOIN (
                 SELECT DISTINCT b.user_id
                 FROM bookings b
                 WHERE b.session_id = :sid2
                   AND b.status NOT IN ('cancelled')
             ) bchk ON bchk.user_id = u.id
             LEFT JOIN (
                 SELECT b.user_id,
                        SUM(CASE WHEN s.is_private = TRUE  THEN 1 ELSE 0 END) AS private_sessions_attended,
                        SUM(CASE WHEN s.is_private = FALSE THEN 1 ELSE 0 END) AS public_sessions_attended
                 FROM bookings b
                 JOIN sessions s ON s.id = b.session_id
                 WHERE b.status IN ('attended', 'credited')
                 GROUP BY b.user_id
             ) agg ON agg.user_id = u.id
             WHERE u.deleted_at IS NULL AND u.role = 'user'
             ORDER BY u.last_name ASC, u.first_name ASC"
        );
        $stmt->execute([':sid' => $sessionId, ':sid2' => $sessionId]);
        return $stmt->fetchAll();
    }

    public function decrementSeats(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sessions SET remaining_seats = remaining_seats - 1
             WHERE id = :id AND remaining_seats > 0'
        );
        $stmt->execute([':id' => $id]);
    }

    public function incrementSeats(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sessions SET remaining_seats = remaining_seats + 1
             WHERE id = :id AND remaining_seats < max_attendees'
        );
        $stmt->execute([':id' => $id]);
    }
}
