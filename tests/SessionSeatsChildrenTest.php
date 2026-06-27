<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for number_of_children support in SessionModel seat management.
 */
#[RunTestsInSeparateProcesses]
class SessionSeatsChildrenTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/SessionModel.php';
    }

    /** Inject a mock PDO into the Database singleton. */
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    // -------------------------------------------------------------------------
    // decrementSeats – uses :count parameter
    // -------------------------------------------------------------------------

    public function testDecrementSeatsUsesCountParameter(): void
    {
        $capturedSql    = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $result = $model->decrementSeats(5, 3);

        $this->assertTrue($result);
        $this->assertStringContainsString(':count', $capturedSql);
        $this->assertSame(3, $capturedParams[':count']);
        $this->assertSame(5, $capturedParams[':id']);
    }

    public function testDecrementSeatsReturnsFalseWhenNoRowUpdated(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $result = $model->decrementSeats(99, 10);

        $this->assertFalse($result);
    }

    public function testDecrementSeatsDefaultsToOne(): void
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

        $model = new SessionModel();
        $model->decrementSeats(1);

        $this->assertSame(1, $capturedParams[':count']);
    }

    public function testDecrementSeatsEnforcesMinimumOfOne(): void
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

        $model = new SessionModel();
        $model->decrementSeats(1, 0);

        $this->assertSame(1, $capturedParams[':count']);
    }

    // -------------------------------------------------------------------------
    // incrementSeats – uses :count parameter
    // -------------------------------------------------------------------------

    public function testIncrementSeatsUsesCountParameter(): void
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

        $model = new SessionModel();
        $model->incrementSeats(7, 2);

        $this->assertStringContainsString('LEAST', $capturedSql);
        $this->assertSame(2, $capturedParams[':count']);
        $this->assertSame(7, $capturedParams[':id']);
    }

    public function testIncrementSeatsDefaultsToOne(): void
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

        $model = new SessionModel();
        $model->incrementSeats(1);

        $this->assertSame(1, $capturedParams[':count']);
    }
}
