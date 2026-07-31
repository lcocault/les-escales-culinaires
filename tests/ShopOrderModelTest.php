<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class ShopOrderModelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/ShopOrderModel.php';
    }

    public function testNextAvailableMarketWednesdayRespectsThirtySixHourPreparation(): void
    {
        $nowTs = strtotime('2026-04-07 21:00:00 UTC');
        $this->assertSame(
            '2026-04-15',
            ShopOrderModel::nextAvailableDate('market_wednesday', $nowTs, [])
        );
    }

    public function testNextAvailableMarketFridaySkipsCancelledCandidateDate(): void
    {
        $nowTs = strtotime('2026-04-06 08:00:00 UTC');
        $this->assertSame(
            '2026-04-17',
            ShopOrderModel::nextAvailableDate('market_friday', $nowTs, ['2026-04-10'])
        );
    }

    public function testValidateDeliveryDateRejectsCancelledMarketDate(): void
    {
        $nowTs = strtotime('2026-04-06 08:00:00 UTC');
        $this->assertFalse(
            ShopOrderModel::validateDeliveryDate('market_wednesday', '2026-04-08', $nowTs, ['2026-04-08'])
        );
    }

    public function testValidateDeliveryDateAcceptsMarketDateWhenPreparationDelayIsMet(): void
    {
        $nowTs = strtotime('2026-04-07 00:00:00 UTC');
        $this->assertTrue(
            ShopOrderModel::validateDeliveryDate('market_friday', '2026-04-10', $nowTs, [])
        );
    }
}
