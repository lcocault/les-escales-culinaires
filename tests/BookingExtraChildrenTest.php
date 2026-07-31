<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for multi-child booking support in BookingModel.
 */
#[RunTestsInSeparateProcesses]
class BookingExtraChildrenTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/BookingModel.php';
    }

    /** Inject a mock PDO into the Database singleton. */
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    // -------------------------------------------------------------------------
    // BookingModel::create() accepts nb_children parameter
    // -------------------------------------------------------------------------

    public function testCreateIncludesNbChildrenColumn(): void
    {
        $capturedSql = '';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Alice', 'Dupont', 8, '', null, 0, 2);

        $this->assertStringContainsString('nb_children', $capturedSql);
    }

    public function testCreatePassesNbChildrenParameter(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(5);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Alice', 'Dupont', 8, '', null, 0, 3);

        $this->assertSame(3, $capturedParams[':nb_children']);
    }

    public function testCreateDefaultsNbChildrenToOne(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(6);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Bob', 'Martin', 10);

        $this->assertSame(1, $capturedParams[':nb_children']);
    }

    // -------------------------------------------------------------------------
    // BookingModel::addExtraChildren() inserts rows into booking_children
    // -------------------------------------------------------------------------

    public function testAddExtraChildrenInsertsRows(): void
    {
        $executedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$executedParams) {
                $executedParams[] = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->addExtraChildren(42, [
            ['first_name' => 'Bob',   'last_name' => 'Dupont', 'age' => '7',  'allergies' => 'gluten'],
            ['first_name' => 'Clara', 'last_name' => 'Dupont', 'age' => '10', 'allergies' => ''],
        ]);

        $this->assertCount(2, $executedParams);

        $this->assertSame(42, $executedParams[0][':booking_id']);
        $this->assertSame('Bob', $executedParams[0][':first_name']);
        $this->assertSame(2, $executedParams[0][':child_order']);

        $this->assertSame(42, $executedParams[1][':booking_id']);
        $this->assertSame('Clara', $executedParams[1][':first_name']);
        $this->assertSame(3, $executedParams[1][':child_order']);
    }

    public function testAddExtraChildrenStoresNullForEmptyAllergies(): void
    {
        $executedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$executedParams) {
                $executedParams[] = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->addExtraChildren(10, [
            ['first_name' => 'Eve', 'last_name' => 'Test', 'age' => '5', 'allergies' => ''],
        ]);

        $this->assertNull($executedParams[0][':allergies']);
    }

    // -------------------------------------------------------------------------
    // BookingModel::getExtraChildren() executes correct query
    // -------------------------------------------------------------------------

    public function testGetExtraChildrenQueriesBookingChildren(): void
    {
        $capturedSql = '';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $result = $model->getExtraChildren(7);

        $this->assertStringContainsString('booking_children', $capturedSql);
        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // BookingModel::getExtraChildrenMapped() returns empty array for no IDs
    // -------------------------------------------------------------------------

    public function testGetExtraChildrenMappedReturnsEmptyForNoIds(): void
    {
        $pdo = $this->createMock(PDO::class);
        $this->injectPdo($pdo);

        $model  = new BookingModel();
        $result = $model->getExtraChildrenMapped([]);

        $this->assertSame([], $result);
    }
}
