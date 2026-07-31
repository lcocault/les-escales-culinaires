<?php
// src/ShopProductModel.php – shop product (prepared meals) CRUD

class ShopProductModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Retrieval ─────────────────────────────────────────────────────────────

    /** Returns all non-deleted products, newest first. */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM shop_products WHERE deleted_at IS NULL ORDER BY created_at DESC'
        );
        return $stmt->fetchAll();
    }

    /** Returns only available (non-deleted, is_available=true) products. */
    public function getAvailable(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM shop_products WHERE deleted_at IS NULL AND is_available = TRUE ORDER BY name ASC"
        );
        return $stmt->fetchAll();
    }

    /** Returns a single product or null if not found / soft-deleted. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM shop_products WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    /** Creates a product and returns its new ID. */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO shop_products (name, description, photo_filename, external_photo_url, price_cents, portion_count, min_order_portions, is_available)
             VALUES (:name, :description, :photo, :external_photo_url, :price, :portion_count, :min_order_portions, :available)
             RETURNING id'
        );
        $stmt->execute([
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null,
            ':photo'       => $data['photo_filename'] ?? null,
            ':external_photo_url' => $data['external_photo_url'] ?? null,
            ':price'       => (int) $data['price_cents'],
            ':portion_count' => max(1, (int) ($data['portion_count'] ?? 1)),
            ':min_order_portions' => max(1, (int) ($data['min_order_portions'] ?? 1)),
            ':available'   => ($data['is_available'] ?? true) ? 'TRUE' : 'FALSE',
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** Updates a product's editable fields. */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE shop_products
             SET name = :name,
                 description = :description,
                 price_cents = :price,
                 portion_count = :portion_count,
                 min_order_portions = :min_order_portions,
                 is_available = :available
             WHERE id = :id'
        );
        $stmt->execute([
            ':name'      => $data['name'],
            ':description' => $data['description'] ?? null,
            ':price'     => (int) $data['price_cents'],
            ':portion_count' => max(1, (int) ($data['portion_count'] ?? 1)),
            ':min_order_portions' => max(1, (int) ($data['min_order_portions'] ?? 1)),
            ':available' => ($data['is_available'] ?? true) ? 'TRUE' : 'FALSE',
            ':id'        => $id,
        ]);
    }

    /** Updates the image source (uploaded filename and/or external URL) for a product. */
    public function updatePhoto(int $id, ?string $filename, ?string $externalUrl = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE shop_products
             SET photo_filename = :photo,
                 external_photo_url = :external_photo_url
             WHERE id = :id'
        );
        $stmt->execute([
            ':photo' => $filename,
            ':external_photo_url' => $externalUrl,
            ':id' => $id,
        ]);
    }

    /** Soft-deletes a product. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE shop_products SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }
}
