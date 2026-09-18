<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PromoCodeModel.
 */
#[RunTestsInSeparateProcesses]
class PromoCodeModelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/PromoCodeModel.php';
    }

    /** Inject a mock PDO into the Database singleton. */
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    // -------------------------------------------------------------------------
    // create – INSERT SQL contains required columns
    // -------------------------------------------------------------------------

    public function testCreateInsertsRow(): void
    {
        $capturedSql = '';

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(5);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });

        $this->injectPdo($pdo);

        $model = new PromoCodeModel();
        $id = $model->create([
            'code'           => 'PROMO10',
            'session_id'     => null,
            'discount_cents' => 1000,
            'max_uses'       => null,
            'expires_at'     => null,
        ]);

        $this->assertSame(5, $id);
        $this->assertStringContainsStringIgnoringCase('INSERT INTO promo_codes', $capturedSql);
        $this->assertStringContainsString(':code', $capturedSql);
        $this->assertStringContainsString(':discount_cents', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // findByCode – returns null when not found
    // -------------------------------------------------------------------------

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new PromoCodeModel();
        $this->assertNull($model->findByCode('NOTEXIST'));
    }

    // -------------------------------------------------------------------------
    // delete – issues an UPDATE … SET deleted_at = NOW()
    // -------------------------------------------------------------------------

    public function testDeleteSetsDeletedAt(): void
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

        $model = new PromoCodeModel();
        $model->delete(3);

        $this->assertStringContainsStringIgnoringCase('UPDATE promo_codes', $capturedSql);
        $this->assertStringContainsStringIgnoringCase('deleted_at', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // validateForSession – returns null when code not found
    // -------------------------------------------------------------------------

    public function testValidateForSessionReturnsNullWhenCodeNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new PromoCodeModel();
        $this->assertNull($model->validateForSession('BADCODE', 1));
    }

    // -------------------------------------------------------------------------
    // validateForSession – returns null when code is for a different session
    // -------------------------------------------------------------------------

    public function testValidateForSessionReturnsNullWhenSessionMismatch(): void
    {
        $fakePromo = [
            'id'             => 1,
            'code'           => 'SESSION5',
            'session_id'     => 99,
            'discount_cents' => 500,
            'max_uses'       => null,
            'used_count'     => 0,
            'expires_at'     => null,
            'session_title'  => 'Séance A',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fakePromo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new PromoCodeModel();
        // Code is for session 99, but we are booking session 1
        $this->assertNull($model->validateForSession('SESSION5', 1));
    }

    // -------------------------------------------------------------------------
    // validateForSession – returns null when code is expired
    // -------------------------------------------------------------------------

    public function testValidateForSessionReturnsNullWhenExpired(): void
    {
        $fakePromo = [
            'id'             => 2,
            'code'           => 'OLDCODE',
            'session_id'     => null,
            'discount_cents' => 1000,
            'max_uses'       => null,
            'used_count'     => 0,
            'expires_at'     => '2020-01-01 00:00:00+00',
            'session_title'  => null,
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fakePromo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new PromoCodeModel();
        $this->assertNull($model->validateForSession('OLDCODE', 5));
    }

    // -------------------------------------------------------------------------
    // validateForSession – returns null when usage limit reached
    // -------------------------------------------------------------------------

    public function testValidateForSessionReturnsNullWhenUsageLimitReached(): void
    {
        $fakePromo = [
            'id'             => 3,
            'code'           => 'LIMITED',
            'session_id'     => null,
            'discount_cents' => 500,
            'max_uses'       => 10,
            'used_count'     => 10,
            'expires_at'     => null,
            'session_title'  => null,
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fakePromo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new PromoCodeModel();
        $this->assertNull($model->validateForSession('LIMITED', 5));
    }

    // -------------------------------------------------------------------------
    // validateForSession – returns promo row for a valid global code
    // -------------------------------------------------------------------------

    public function testValidateForSessionReturnsPromoWhenValid(): void
    {
        $fakePromo = [
            'id'             => 4,
            'code'           => 'VALID20',
            'session_id'     => null,
            'discount_cents' => 2000,
            'max_uses'       => null,
            'used_count'     => 3,
            'expires_at'     => null,
            'session_title'  => null,
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fakePromo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model  = new PromoCodeModel();
        $result = $model->validateForSession('VALID20', 7);

        $this->assertIsArray($result);
        $this->assertSame(4, (int) $result['id']);
        $this->assertSame(2000, (int) $result['discount_cents']);
    }

    // -------------------------------------------------------------------------
    // validateForSession – valid session-specific code
    // -------------------------------------------------------------------------

    public function testValidateForSessionReturnsPromoForMatchingSession(): void
    {
        $fakePromo = [
            'id'             => 5,
            'code'           => 'SPECIAL',
            'session_id'     => 7,
            'discount_cents' => 500,
            'max_uses'       => 5,
            'used_count'     => 2,
            'expires_at'     => null,
            'session_title'  => 'Séance B',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fakePromo);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model  = new PromoCodeModel();
        $result = $model->validateForSession('SPECIAL', 7);

        $this->assertIsArray($result);
        $this->assertSame(5, (int) $result['id']);
    }

    // -------------------------------------------------------------------------
    // incrementUsedCount – issues an UPDATE used_count = used_count + 1
    // -------------------------------------------------------------------------

    public function testIncrementUsedCountExecutesUpdate(): void
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

        $model = new PromoCodeModel();
        $model->incrementUsedCount(4, 2);

        $this->assertStringContainsStringIgnoringCase('UPDATE promo_codes', $capturedSql);
        $this->assertStringContainsStringIgnoringCase('used_count', $capturedSql);
        $this->assertStringContainsString(':qty', $capturedSql);
        $this->assertSame(2, $capturedParams[':qty']);
    }
}
