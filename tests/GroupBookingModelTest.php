<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GroupBookingModel.
 */
#[RunTestsInSeparateProcesses]
class GroupBookingModelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Database.php';
        require_once __DIR__ . '/../src/GroupBookingModel.php';
    }

    /** Inject a mock PDO into the Database singleton. */
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionProperty(Database::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testPriceConstants(): void
    {
        $this->assertSame(3000, GroupBookingModel::PRICE_HOME_CENTS);
        $this->assertSame(3500, GroupBookingModel::PRICE_ESCALES_CENTS);
        $this->assertSame(4, GroupBookingModel::MIN_CHILDREN);
        $this->assertSame(8, GroupBookingModel::MAX_CHILDREN);
        $this->assertSame(7, GroupBookingModel::MIN_ADVANCE_DAYS);
    }

    // -------------------------------------------------------------------------
    // estimatePrice()
    // -------------------------------------------------------------------------

    public function testEstimatePriceHomeLocation(): void
    {
        $price = GroupBookingModel::estimatePrice(5, 'home');
        $this->assertSame(5 * GroupBookingModel::PRICE_HOME_CENTS, $price);
    }

    public function testEstimatePriceEscalesLocation(): void
    {
        $price = GroupBookingModel::estimatePrice(6, 'escales');
        $this->assertSame(6 * GroupBookingModel::PRICE_ESCALES_CENTS, $price);
    }

    public function testEstimatePriceMinChildren(): void
    {
        $price = GroupBookingModel::estimatePrice(GroupBookingModel::MIN_CHILDREN, 'home');
        $this->assertSame(GroupBookingModel::MIN_CHILDREN * GroupBookingModel::PRICE_HOME_CENTS, $price);
    }

    public function testEstimatePriceMaxChildren(): void
    {
        $price = GroupBookingModel::estimatePrice(GroupBookingModel::MAX_CHILDREN, 'escales');
        $this->assertSame(GroupBookingModel::MAX_CHILDREN * GroupBookingModel::PRICE_ESCALES_CENTS, $price);
    }

    public function testEstimatePriceUsesSlotSpecificHomePriceWhenProvided(): void
    {
        $slotHomeCents = 2500;
        $price = GroupBookingModel::estimatePrice(4, 'home', $slotHomeCents, 3200);
        $this->assertSame(4 * $slotHomeCents, $price);
    }

    public function testEstimatePriceUsesSlotSpecificEscalesPriceWhenProvided(): void
    {
        $slotEscalesCents = 3200;
        $price = GroupBookingModel::estimatePrice(6, 'escales', 2500, $slotEscalesCents);
        $this->assertSame(6 * $slotEscalesCents, $price);
    }

    public function testEstimatePriceFallsBackToConstantsWhenNoPricesProvided(): void
    {
        $priceHome    = GroupBookingModel::estimatePrice(5, 'home', null, null);
        $priceEscales = GroupBookingModel::estimatePrice(5, 'escales', null, null);
        $this->assertSame(5 * GroupBookingModel::PRICE_HOME_CENTS, $priceHome);
        $this->assertSame(5 * GroupBookingModel::PRICE_ESCALES_CENTS, $priceEscales);
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

        $model = new GroupBookingModel();
        $model->create([
            'user_id'          => 1,
            'contact_phone'    => '0600000000',
            'nb_children'      => 5,
            'children_ages'    => '7, 8, 8, 9, 10',
            'preferred_date'   => '2026-06-01',
            'location_type'    => 'escales',
            'location_address' => '',
            'allergies'        => 'noix',
            'additional_info'  => '',
        ]);

        $this->assertStringContainsString('nb_children', $capturedSql);
        $this->assertStringContainsString('preferred_date', $capturedSql);
        $this->assertStringContainsString('location_type', $capturedSql);
        $this->assertStringContainsString('allergies', $capturedSql);
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

        $model = new GroupBookingModel();
        $id    = $model->create([
            'user_id'          => 7,
            'contact_phone'    => '0600000001',
            'nb_children'      => 4,
            'children_ages'    => '6, 7, 8, 9',
            'preferred_date'   => '2026-07-15',
            'location_type'    => 'home',
            'location_address' => '12 rue de la Paix, 31000 Toulouse',
            'allergies'        => '',
            'additional_info'  => 'Thème Ratatouille',
        ]);

        $this->assertSame(42, $id);
        $this->assertSame(7, $capturedParams[':user_id']);
        $this->assertSame(4, $capturedParams[':nb_children']);
        $this->assertSame('2026-07-15', $capturedParams[':preferred_date']);
        $this->assertSame('home', $capturedParams[':location_type']);
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
        $stmt->method('fetchColumn')->willReturn(10);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->injectPdo($pdo);

        $model = new GroupBookingModel();
        $model->create([
            'user_id'          => 3,
            'contact_phone'    => '',
            'nb_children'      => 6,
            'children_ages'    => '',
            'preferred_date'   => '2026-08-20',
            'location_type'    => 'escales',
            'location_address' => '',
            'allergies'        => '',
            'additional_info'  => '',
        ]);

        $this->assertNull($capturedParams[':contact_phone']);
        $this->assertNull($capturedParams[':children_ages']);
        $this->assertNull($capturedParams[':location_address']);
        $this->assertNull($capturedParams[':allergies']);
        $this->assertNull($capturedParams[':additional_info']);
    }

    // -------------------------------------------------------------------------
    // updateStatus()
    // -------------------------------------------------------------------------

    public function testUpdateStatusPassesCorrectParameters(): void
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

        $model = new GroupBookingModel();
        $model->updateStatus(5, 'confirmed', 'Rendez-vous le samedi à 14h');

        $this->assertSame(5, $capturedParams[':id']);
        $this->assertSame('confirmed', $capturedParams[':status']);
        $this->assertSame('Rendez-vous le samedi à 14h', $capturedParams[':admin_notes']);
    }

    public function testUpdateStatusAcceptsNullNotes(): void
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

        $model = new GroupBookingModel();
        $model->updateStatus(3, 'cancelled', null);

        $this->assertNull($capturedParams[':admin_notes']);
        $this->assertSame('cancelled', $capturedParams[':status']);
    }
}
