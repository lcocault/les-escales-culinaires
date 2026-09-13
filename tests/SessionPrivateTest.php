<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for private session support in SessionModel.
 */
#[RunTestsInSeparateProcesses]
class SessionPrivateTest extends TestCase
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
    // SessionModel::create() includes is_private
    // -------------------------------------------------------------------------

    public function testCreateIncludesIsPrivateColumn(): void
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
            'session_date'  => '2026-03-01',
            'start_time'    => '10:00',
            'end_time'      => '12:00',
            'max_attendees' => 8,
            'price_cents'   => 1500,
            'age_category'  => '6-12',
            'is_private'    => true,
        ]);

        $this->assertStringContainsString('is_private', $capturedSql);
    }

    public function testCreateDefaultsIsPrivateToFalse(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->create([
            'title'         => 'Test',
            'theme'         => 'Pâtisserie',
            'session_date'  => '2026-03-01',
            'start_time'    => '10:00',
            'end_time'      => '12:00',
            'max_attendees' => 8,
            'price_cents'   => 1500,
        ]);

        $this->assertSame('FALSE', $capturedParams[':is_private']);
    }

    public function testCreateSetsIsPrivateToTrue(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchColumn')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->create([
            'title'         => 'Private Test',
            'theme'         => 'Pâtisserie',
            'session_date'  => '2026-03-01',
            'start_time'    => '10:00',
            'end_time'      => '12:00',
            'max_attendees' => 8,
            'price_cents'   => 1500,
            'is_private'    => true,
        ]);

        $this->assertSame('TRUE', $capturedParams[':is_private']);
    }

    // -------------------------------------------------------------------------
    // SessionModel::update() includes is_private
    // -------------------------------------------------------------------------

    public function testUpdateIncludesIsPrivateColumn(): void
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
        $model->update(1, [
            'title'         => 'Test',
            'theme'         => 'Pâtisserie',
            'session_date'  => '2026-03-01',
            'start_time'    => '10:00',
            'end_time'      => '12:00',
            'max_attendees' => 8,
            'price_cents'   => 1500,
            'age_category'  => '6-12',
            'is_private'    => false,
        ]);

        $this->assertStringContainsString('is_private', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // SessionModel::getUpcoming() filters private sessions when no user
    // -------------------------------------------------------------------------

    public function testGetUpcomingFiltersPrivateSessionsWhenNoUser(): void
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
        $model->getUpcoming();

        $this->assertStringContainsString('is_private = FALSE', $capturedSql);
    }

    public function testGetUpcomingIncludesAllowedPrivateSessionsForUser(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->getUpcoming(42);

        $this->assertStringContainsString('session_allowances', $capturedSql);
        $this->assertSame(42, $capturedParams[':user_id']);
    }

    public function testGetUpcomingForCatalogIncludesPrivateSessions(): void
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
        $model->getUpcomingForCatalog();

        $this->assertStringContainsString('is_private', $capturedSql);
        $this->assertStringNotContainsString('AND is_private = FALSE', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // SessionModel::isUserAllowed()
    // -------------------------------------------------------------------------

    public function testIsUserAllowedReturnsTrueWhenAllowanceExists(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $this->assertTrue($model->isUserAllowed(1, 5));
    }

    public function testIsUserAllowedReturnsFalseWhenNoAllowance(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $this->assertFalse($model->isUserAllowed(1, 99));
    }

    // -------------------------------------------------------------------------
    // SessionModel::allowUser() and revokeUser()
    // -------------------------------------------------------------------------

    public function testAllowUserExecutesPreparedStatement(): void
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
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->allowUser(3, 7);

        $this->assertStringContainsString('session_allowances', $capturedSql);
        $this->assertSame(3, $capturedParams[':sid']);
        $this->assertSame(7, $capturedParams[':uid']);
    }

    public function testRevokeUserExecutesPreparedStatement(): void
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
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $model->revokeUser(3, 7);

        $this->assertStringContainsString('DELETE FROM session_allowances', $capturedSql);
        $this->assertSame(3, $capturedParams[':sid']);
        $this->assertSame(7, $capturedParams[':uid']);
    }

    // -------------------------------------------------------------------------
    // SessionModel::getAllowedUsers() includes participation counts
    // -------------------------------------------------------------------------

    public function testGetAllowedUsersIncludesParticipationCounts(): void
    {
        $capturedSql = '';
        $fakeRow = [
            'id'                        => 1,
            'first_name'                => 'Alice',
            'last_name'                 => 'Dupont',
            'email'                     => 'alice@example.com',
            'is_allowed'                => true,
            'has_booking'               => false,
            'private_sessions_attended' => 3,
            'public_sessions_attended'  => 5,
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([$fakeRow]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new SessionModel();
        $users = $model->getAllowedUsers(10);

        $this->assertStringContainsString('private_sessions_attended', $capturedSql);
        $this->assertStringContainsString('public_sessions_attended', $capturedSql);
        $this->assertCount(1, $users);
        $this->assertSame(3, $users[0]['private_sessions_attended']);
        $this->assertSame(5, $users[0]['public_sessions_attended']);
    }
}
