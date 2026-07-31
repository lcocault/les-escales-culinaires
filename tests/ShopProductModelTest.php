<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class ShopProductModelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/ShopProductModel.php';
    }

    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    public function testCreateSupportsExternalPhotoUrl(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(12);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $this->injectPdo($pdo);

        $model = new ShopProductModel();
        $id = $model->create([
            'name' => 'Produit test',
            'description' => 'Desc',
            'price_cents' => 1200,
            'is_available' => true,
            'external_photo_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertSame(12, $id);
        $this->assertStringContainsString('external_photo_url', $capturedSql);
        $this->assertStringContainsString('portion_count', $capturedSql);
        $this->assertStringContainsString('min_order_portions', $capturedSql);
        $this->assertSame('https://example.com/photo.jpg', $capturedParams[':external_photo_url']);
        $this->assertSame(1, $capturedParams[':portion_count']);
        $this->assertSame(1, $capturedParams[':min_order_portions']);
    }

    public function testUpdateIncludesPortionCount(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $this->injectPdo($pdo);

        $model = new ShopProductModel();
        $model->update(3, [
            'name' => 'Produit',
            'description' => 'Desc',
            'price_cents' => 900,
            'portion_count' => 4,
            'min_order_portions' => 3,
            'is_available' => true,
        ]);

        $this->assertStringContainsString('portion_count = :portion_count', $capturedSql);
        $this->assertStringContainsString('min_order_portions = :min_order_portions', $capturedSql);
        $this->assertSame(4, $capturedParams[':portion_count']);
        $this->assertSame(3, $capturedParams[':min_order_portions']);
        $this->assertSame(3, $capturedParams[':id']);
    }

    public function testUpdatePhotoUpdatesFilenameAndExternalUrl(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
            $capturedSql = $sql;
            return $stmt;
        });

        $this->injectPdo($pdo);

        $model = new ShopProductModel();
        $model->updatePhoto(7, null, 'https://example.com/new.jpg');

        $this->assertStringContainsString('photo_filename = :photo', $capturedSql);
        $this->assertStringContainsString('external_photo_url = :external_photo_url', $capturedSql);
        $this->assertNull($capturedParams[':photo']);
        $this->assertSame('https://example.com/new.jpg', $capturedParams[':external_photo_url']);
        $this->assertSame(7, $capturedParams[':id']);
    }
}
