<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the session-confirmation related methods added to SessionModel
 * and BookingModel.
 *
 * Each test runs in its own process so that the Database singleton can be
 * replaced with a mock without state leaking between tests.
 */
#[RunTestsInSeparateProcesses]
class SessionConfirmationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/SessionModel.php';
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
    // SessionModel::confirmSession()
    // -------------------------------------------------------------------------

    public function testConfirmSessionExecutesWithoutException(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->confirmSession(1);

        $this->addToAssertionCount(1);
    }

    public function testConfirmSessionUsesCorrectStatus(): void
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

        $model = new SessionModel();
        $model->confirmSession(5);

        $this->assertStringContainsString("'confirmed'", $capturedSql);
    }

    // -------------------------------------------------------------------------
    // SessionModel::cancelSession()
    // -------------------------------------------------------------------------

    public function testCancelSessionExecutesWithoutException(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->cancelSession(2);

        $this->addToAssertionCount(1);
    }

    public function testCancelSessionUsesCorrectStatus(): void
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

        $model = new SessionModel();
        $model->cancelSession(3);

        $this->assertStringContainsString("'cancelled'", $capturedSql);
    }

    // -------------------------------------------------------------------------
    // SessionModel::closePastSession()
    // -------------------------------------------------------------------------

    public function testClosePastSessionUsesConfirmedStatusAndPastCondition(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $params) use (&$capturedParams) {
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
        $model->closePastSession(4);

        $this->assertStringContainsString("'confirmed'", $capturedSql);
        $this->assertStringContainsString("status = 'pending'", $capturedSql);
        $this->assertStringContainsString('session_date::timestamp + end_time::interval', $capturedSql);
        $this->assertStringContainsString('< NOW()', $capturedSql);
        $this->assertSame(4, $capturedParams[':id']);
    }

    public function testClosePastSessionReturnsFalseWhenNothingUpdated(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $this->assertFalse($model->closePastSession(6));
    }

    // -------------------------------------------------------------------------
    // SessionModel::getSessionsDueForCheck()
    // -------------------------------------------------------------------------

    public function testGetSessionsDueForCheckReturnsRows(): void
    {
        $rows = [
            [
                'id'           => 10,
                'title'        => 'Pâte à crêpes',
                'status'       => 'pending',
                'session_date' => '2026-02-24',
                'start_time'   => '10:00:00',
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model  = new SessionModel();
        $result = $model->getSessionsDueForCheck();

        $this->assertCount(1, $result);
        $this->assertSame('pending', $result[0]['status']);
    }

    public function testGetSessionsDueForCheckReturnsEmptyArray(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model  = new SessionModel();
        $result = $model->getSessionsDueForCheck();

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // BookingModel::getConfirmedBySession()
    // -------------------------------------------------------------------------

    public function testGetConfirmedBySessionReturnsOnlyConfirmedBookings(): void
    {
        $rows = [
            [
                'id'         => 1,
                'status'     => 'confirmed',
                'first_name' => 'Alice',
                'last_name'  => 'Dupont',
                'email'      => 'alice@example.com',
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model  = new BookingModel();
        $result = $model->getConfirmedBySession(42);

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['first_name']);
    }

    public function testGetConfirmedBySessionReturnsEmptyWhenNone(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model  = new BookingModel();
        $result = $model->getConfirmedBySession(99);

        $this->assertSame([], $result);
    }
}
