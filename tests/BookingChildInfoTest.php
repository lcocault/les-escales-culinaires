<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for child information fields in BookingModel::create().
 */
#[RunTestsInSeparateProcesses]
class BookingChildInfoTest extends TestCase
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
    // BookingModel::create() includes child info columns
    // -------------------------------------------------------------------------

    public function testCreateIncludesChildInfoColumns(): void
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
        $model->create(1, 2, false, 'Alice', 'Dupont', 8, 'noix');

        $this->assertStringContainsString('child_first_name', $capturedSql);
        $this->assertStringContainsString('child_last_name', $capturedSql);
        $this->assertStringContainsString('child_age', $capturedSql);
        $this->assertStringContainsString('child_allergies', $capturedSql);
    }

    public function testCreatePassesChildInfoParameters(): void
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
        $model->create(1, 2, false, 'Alice', 'Dupont', 8, 'noix');

        $this->assertSame('Alice', $capturedParams[':cfn']);
        $this->assertSame('Dupont', $capturedParams[':cln']);
        $this->assertSame(8, $capturedParams[':cage']);
        $this->assertSame('noix', $capturedParams[':callergies']);
    }

    public function testCreateStoresNullForEmptyOptionalFields(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(3);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Bob', 'Martin', 10);

        $this->assertNull($capturedParams[':callergies']);
    }

    public function testCreateStoresNullForZeroAge(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(4);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Bob', 'Martin', 0);

        $this->assertNull($capturedParams[':cage']);
    }

    public function testCreateIncludesNumberOfChildrenColumn(): void
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
        $model->create(1, 2, false, 'Alice', 'Dupont', 8, 'noix', null, 0, 3);

        $this->assertStringContainsString('number_of_children', $capturedSql);
    }

    public function testCreatePassesNumberOfChildrenParameter(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(7);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Alice', 'Dupont', 8, 'noix', null, 0, 2);

        $this->assertSame(2, $capturedParams[':number_of_children']);
    }

    public function testCreateDefaultsNumberOfChildrenToOne(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(8);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Bob', 'Martin', 10);

        $this->assertSame(1, $capturedParams[':number_of_children']);
    }

    public function testCreateEnforcesMinimumOfOneChild(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(9);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->create(1, 2, false, 'Bob', 'Martin', 10, '', null, 0, 0);

        $this->assertSame(1, $capturedParams[':number_of_children']);
    }
}
