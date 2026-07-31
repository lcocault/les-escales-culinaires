<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for rating-reminder methods in BookingModel.
 */
#[RunTestsInSeparateProcesses]
class BookingRatingReminderTest extends TestCase
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
    // BookingModel::getAttendedWithoutRating()
    // -------------------------------------------------------------------------

    public function testGetAttendedWithoutRatingReturnsRows(): void
    {
        $expectedRows = [
            [
                'booking_id'    => 1,
                'user_id'       => 2,
                'session_id'    => 3,
                'first_name'    => 'Alice',
                'last_name'     => 'Dupont',
                'email'         => 'alice@example.com',
                'session_title' => 'Atelier crêpes',
                'session_date'  => '2026-03-15',
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($expectedRows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $result = $model->getAttendedWithoutRating();

        $this->assertSame($expectedRows, $result);
    }

    public function testGetAttendedWithoutRatingReturnsEmptyArrayWhenNone(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $result = $model->getAttendedWithoutRating();

        $this->assertSame([], $result);
    }

    public function testGetAttendedWithoutRatingQueryFiltersCorrectly(): void
    {
        $capturedSql = '';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new BookingModel();
        $model->getAttendedWithoutRating();

        $this->assertStringContainsString("status = 'attended'", $capturedSql);
        $this->assertStringContainsString('rating_reminder_dismissed', $capturedSql);
        $this->assertStringContainsString('NOT EXISTS', $capturedSql);
        $this->assertStringContainsString('ratings', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // BookingModel::dismissRatingReminder()
    // -------------------------------------------------------------------------

    public function testDismissRatingReminderExecutesUpdateWithCorrectId(): void
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

        $model = new BookingModel();
        $model->dismissRatingReminder(42);

        $this->assertStringContainsString('rating_reminder_dismissed', $capturedSql);
        $this->assertStringContainsString('TRUE', $capturedSql);
        $this->assertStringContainsString('UPDATE bookings', $capturedSql);
        $this->assertSame(42, $capturedParams[':id']);
    }
}
