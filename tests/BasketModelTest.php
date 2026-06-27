<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BasketModel.
 */
#[RunTestsInSeparateProcesses]
class BasketModelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/BasketModel.php';
    }

    /** Inject a mock PDO into the Database singleton. */
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    // -------------------------------------------------------------------------
    // addItem – SQL includes required columns
    // -------------------------------------------------------------------------

    public function testAddItemSqlIncludesRequiredColumns(): void
    {
        $capturedSql = '';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->addItem(1, 2, 'Alice', 'Dupont', 8, 'noix');

        $this->assertStringContainsString('basket_items', $capturedSql);
        $this->assertStringContainsString('child_first_name', $capturedSql);
        $this->assertStringContainsString('child_last_name', $capturedSql);
        $this->assertStringContainsString('child_age', $capturedSql);
        $this->assertStringContainsString('child_allergies', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // addItem – parameters are passed correctly
    // -------------------------------------------------------------------------

    public function testAddItemPassesParameters(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->addItem(5, 10, 'Bob', 'Martin', 12, 'gluten');

        $this->assertSame(5, $capturedParams[':uid']);
        $this->assertSame(10, $capturedParams[':sid']);
        $this->assertSame('Bob', $capturedParams[':cfn']);
        $this->assertSame('Martin', $capturedParams[':cln']);
        $this->assertSame(12, $capturedParams[':cage']);
        $this->assertSame('gluten', $capturedParams[':callergies']);
    }

    // -------------------------------------------------------------------------
    // addItem – empty optional fields stored as NULL
    // -------------------------------------------------------------------------

    public function testAddItemStoresNullForEmptyOptionalFields(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->addItem(1, 2, 'Alice', 'Dupont', 0); // no allergies, age = 0

        $this->assertNull($capturedParams[':cage']);
        $this->assertNull($capturedParams[':callergies']);
    }

    // -------------------------------------------------------------------------
    // addItem – number_of_children is stored and passed correctly
    // -------------------------------------------------------------------------

    public function testAddItemIncludesNumberOfChildrenColumn(): void
    {
        $capturedSql = '';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->addItem(1, 2, 'Alice', 'Dupont', 8, 'noix', 2);

        $this->assertStringContainsString('number_of_children', $capturedSql);
    }

    public function testAddItemPassesNumberOfChildrenParameter(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->addItem(1, 2, 'Alice', 'Dupont', 8, 'noix', 3);

        $this->assertSame(3, $capturedParams[':number_of_children']);
    }

    public function testAddItemDefaultsNumberOfChildrenToOne(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->addItem(1, 2, 'Alice', 'Dupont', 8);

        $this->assertSame(1, $capturedParams[':number_of_children']);
    }

    // -------------------------------------------------------------------------
    // countByUser – returns integer from fetchColumn
    // -------------------------------------------------------------------------

    public function testCountByUserReturnsInteger(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('3');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $count = $model->countByUser(7);

        $this->assertSame(3, $count);
    }

    // -------------------------------------------------------------------------
    // removeItem – executes DELETE with correct params
    // -------------------------------------------------------------------------

    public function testRemoveItemExecutesDeleteWithCorrectParams(): void
    {
        $capturedSql    = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->removeItem(3, 7);

        $this->assertStringContainsString('DELETE', strtoupper($capturedSql));
        $this->assertStringContainsString('basket_items', $capturedSql);
        $this->assertSame(3, $capturedParams[':uid']);
        $this->assertSame(7, $capturedParams[':sid']);
    }

    // -------------------------------------------------------------------------
    // clearByUser – executes DELETE for user
    // -------------------------------------------------------------------------

    public function testClearByUserExecutesDeleteForUser(): void
    {
        $capturedSql    = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BasketModel();
        $model->clearByUser(9);

        $this->assertStringContainsString('DELETE', strtoupper($capturedSql));
        $this->assertStringContainsString('basket_items', $capturedSql);
        $this->assertSame(9, $capturedParams[':uid']);
    }
}
