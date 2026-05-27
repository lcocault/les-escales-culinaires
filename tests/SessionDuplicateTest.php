<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for session duplication via SessionModel::create().
 *
 * Duplication consists of loading an existing session, clearing its date/time
 * and age_category, then creating a new session with the amended data.
 */
#[RunTestsInSeparateProcesses]
class SessionDuplicateTest extends TestCase
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

    /**
     * When duplicating, all content fields are copied from the source session.
     */
    public function testDuplicateCreatesNewSessionWithSourceContent(): void
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

        $sourceSession = [
            'title'               => 'Les produits laitiers',
            'theme'               => 'Pâtisserie',
            'session_date'        => '2026-03-01',
            'start_time'          => '10:00',
            'end_time'            => '12:00',
            'max_attendees'       => 8,
            'price_cents'         => 1500,
            'age_category'        => '6-12',
            'summary'             => 'Atelier pâte à crêpes.',
            'objectives'          => 'Mesurer, mélanger, cuire.',
            'theoretical_content' => 'Origine du lait.',
            'recipe'              => 'Farine, œufs, lait.',
        ];

        // Simulate what session-edit.php does: copy source, override date/time/age
        $newData = array_merge($sourceSession, [
            'session_date' => '2026-04-10',
            'start_time'   => '14:00',
            'end_time'     => '16:00',
            'age_category' => '3-5',
        ]);

        $model  = new SessionModel();
        $newId  = $model->create($newData);

        $this->assertSame(42, $newId);
        $this->assertSame('Les produits laitiers', $capturedParams[':title']);
        $this->assertSame('2026-04-10', $capturedParams[':session_date']);
        $this->assertSame('3-5', $capturedParams[':age_category']);
        $this->assertSame('14:00', $capturedParams[':start_time']);
        $this->assertSame('Atelier pâte à crêpes.', $capturedParams[':summary']);
    }

    /**
     * When duplicating, the new session gets a fresh remaining_seats equal to
     * max_attendees (i.e. no carry-over from the source session's bookings).
     */
    public function testDuplicateResetsRemainingSeats(): void
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

        $model = new SessionModel();
        $model->create([
            'title'         => 'Test',
            'theme'         => 'Pâtisserie',
            'session_date'  => '2026-04-10',
            'start_time'    => '14:00',
            'end_time'      => '16:00',
            'max_attendees' => 8,
            'price_cents'   => 1500,
            'age_category'  => '3-5',
        ]);

        // The INSERT should set remaining_seats = max_attendees (not a separate value)
        $this->assertStringContainsString(':max_attendees, :max_attendees', $capturedSql);
    }

    public function testGetArchivedReturnsRows(): void
    {
        $rows = [
            ['id' => 9, 'title' => 'Atelier terminé', 'status' => 'confirmed'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $result = $model->getArchived();

        $this->assertSame($rows, $result);
    }

    public function testGetArchivedFiltersOnArchivedStatuses(): void
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

        $model = new SessionModel();
        $model->getArchived();

        $this->assertStringContainsString("s.status IN ('confirmed', 'cancelled')", $capturedSql);
    }
}
