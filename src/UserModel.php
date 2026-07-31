<?php
// src/UserModel.php – user CRUD

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, phone, phone2, photo_consent)
             VALUES (:email, :password_hash, :first_name, :last_name, :phone, :phone2, :photo_consent)
             RETURNING id'
        );
        $stmt->execute([
            ':email'         => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':first_name'    => $data['first_name'],
            ':last_name'     => $data['last_name'],
            ':phone'         => $data['phone'] ?? null,
            ':phone2'        => $data['phone2'] ?? null,
            ':photo_consent' => ($data['photo_consent'] ? 'TRUE' : 'FALSE'),
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return null;
    }

    public function updateCredits(int $userId, int $delta): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET credits = credits + :delta WHERE id = :id'
        );
        $stmt->execute([':delta' => $delta, ':id' => $userId]);
    }

    public function getAnnouncementRecipients(): array
    {
        $stmt = $this->db->query(
            "SELECT id, first_name, last_name, email
             FROM users
             WHERE deleted_at IS NULL
               AND role = 'user'
             ORDER BY last_name ASC, first_name ASC"
        );
        return $stmt->fetchAll();
    }
}
