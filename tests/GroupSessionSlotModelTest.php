<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GroupSessionSlotModel.
 */
#[RunTestsInSeparateProcesses]
class GroupSessionSlotModelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/GroupSessionSlotModel.php';
    }

    /** Inject a mock PDO into the Database singleton. */
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    // -------------------------------------------------------------------------
    // create() – SQL and parameters
    // -------------------------------------------------------------------------

    public function testCreateIncludesRequiredColumns(): void
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

        $model = new GroupSessionSlotModel();
        $model->create([
            'title'                          => 'Atelier anniversaire',
            'description'                    => 'Un atelier pour fêter votre anniversaire',
            'slot_date'                      => '2026-06-15',
            'start_time'                     => '10:00',
            'end_time'                       => '12:00',
            'max_groups'                     => 1,
            'price_per_child_home_cents'     => 3000,
            'price_per_child_escales_cents'  => 3500,
        ]);

        $this->assertStringContainsString('slot_date', $capturedSql);
        $this->assertStringContainsString('start_time', $capturedSql);
        $this->assertStringContainsString('end_time', $capturedSql);
        $this->assertStringContainsString('max_groups', $capturedSql);
        $this->assertStringContainsString('price_per_child_home_cents', $capturedSql);
        $this->assertStringContainsString('price_per_child_escales_cents', $capturedSql);
    }

    public function testCreatePassesCorrectParameters(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(42);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new GroupSessionSlotModel();
        $id    = $model->create([
            'title'                          => 'Atelier anniversaire',
            'description'                    => 'Description',
            'slot_date'                      => '2026-07-20',
            'start_time'                     => '14:00',
            'end_time'                       => '16:00',
            'max_groups'                     => 2,
            'price_per_child_home_cents'     => 3000,
            'price_per_child_escales_cents'  => 3500,
        ]);

        $this->assertSame(42, $id);
        $this->assertSame('Atelier anniversaire', $capturedParams[':title']);
        $this->assertSame('2026-07-20', $capturedParams[':slot_date']);
        $this->assertSame(2, $capturedParams[':max_groups']);
        $this->assertSame(3000, $capturedParams[':price_per_child_home_cents']);
        $this->assertSame(3500, $capturedParams[':price_per_child_escales_cents']);
    }

    public function testCreateStoresNullForEmptyDescription(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(10);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new GroupSessionSlotModel();
        $model->create([
            'title'                          => 'Atelier',
            'description'                    => '',
            'slot_date'                      => '2026-08-01',
            'start_time'                     => '09:00',
            'end_time'                       => '11:00',
            'max_groups'                     => 1,
            'price_per_child_home_cents'     => 3000,
            'price_per_child_escales_cents'  => 3500,
        ]);

        $this->assertNull($capturedParams[':description']);
    }

    // -------------------------------------------------------------------------
    // update() – SQL and parameters
    // -------------------------------------------------------------------------

    public function testUpdatePassesCorrectParameters(): void
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

        $model = new GroupSessionSlotModel();
        $model->update(5, [
            'title'                          => 'Updated title',
            'description'                    => 'New description',
            'slot_date'                      => '2026-09-10',
            'start_time'                     => '11:00',
            'end_time'                       => '13:00',
            'max_groups'                     => 3,
            'price_per_child_home_cents'     => 2800,
            'price_per_child_escales_cents'  => 4000,
            'status'                         => 'open',
        ]);

        $this->assertSame(5, $capturedParams[':id']);
        $this->assertSame('Updated title', $capturedParams[':title']);
        $this->assertSame('2026-09-10', $capturedParams[':slot_date']);
        $this->assertSame(3, $capturedParams[':max_groups']);
        $this->assertSame(2800, $capturedParams[':price_per_child_home_cents']);
        $this->assertSame(4000, $capturedParams[':price_per_child_escales_cents']);
        $this->assertSame('open', $capturedParams[':status']);
    }

    // -------------------------------------------------------------------------
    // softDelete()
    // -------------------------------------------------------------------------

    public function testSoftDeletePassesCorrectId(): void
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

        $model = new GroupSessionSlotModel();
        $model->softDelete(7);

        $this->assertSame(7, $capturedParams[':id']);
    }

    // -------------------------------------------------------------------------
    // decrementGroups() / incrementGroups()
    // -------------------------------------------------------------------------

    public function testDecrementGroupsPassesCorrectId(): void
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

        $model = new GroupSessionSlotModel();
        $model->decrementGroups(3);

        $this->assertStringContainsString('remaining_groups - 1', $capturedSql);
        $this->assertSame(3, $capturedParams[':id']);
    }

    public function testIncrementGroupsPassesCorrectId(): void
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

        $model = new GroupSessionSlotModel();
        $model->incrementGroups(3);

        $this->assertStringContainsString('remaining_groups + 1', $capturedSql);
        $this->assertSame(3, $capturedParams[':id']);
    }
}
