<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class WorkshopAgendaTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/WorkshopAgenda.php';
    }

    public function testBuildItemsMergesAndSortsSessionsAndGroupSlots(): void
    {
        $sessions = [
            [
                'id'           => 10,
                'session_date' => '2026-05-10',
                'start_time'   => '15:00:00',
                'is_private'   => false,
            ],
            [
                'id'           => 11,
                'session_date' => '2026-05-10',
                'start_time'   => '09:00:00',
                'is_private'   => true,
            ],
        ];
        $groupSlots = [
            [
                'id'         => 20,
                'slot_date'  => '2026-05-09',
                'start_time' => '14:00:00',
            ],
        ];

        $items = WorkshopAgenda::buildItems($sessions, $groupSlots);

        $this->assertCount(3, $items);
        $this->assertSame('group_slot', $items[0]['type']);
        $this->assertSame('group_private', $items[0]['segment']);
        $this->assertSame(11, $items[1]['data']['id']);
        $this->assertSame('group_private', $items[1]['segment']);
        $this->assertSame(10, $items[2]['data']['id']);
        $this->assertSame('regular', $items[2]['segment']);
    }

    public function testFilterItemsHandlesAllRegularAndGroupPrivate(): void
    {
        $items = [
            ['segment' => 'regular', 'type' => 'session'],
            ['segment' => 'group_private', 'type' => 'session'],
            ['segment' => 'group_private', 'type' => 'group_slot'],
        ];

        $this->assertCount(3, WorkshopAgenda::filterItems($items, 'all'));
        $this->assertCount(1, WorkshopAgenda::filterItems($items, 'regular'));
        $this->assertCount(2, WorkshopAgenda::filterItems($items, 'group_private'));
    }

    public function testResolveGroupSlotPricesSupportsThreeDisplayBranches(): void
    {
        $homeOnly = WorkshopAgenda::resolveGroupSlotPrices([
            'price_per_child_home_cents' => 3200,
            'price_per_child_escales_cents' => 0,
        ]);
        $escalesOnly = WorkshopAgenda::resolveGroupSlotPrices([
            'price_per_child_home_cents' => 0,
            'price_per_child_escales_cents' => 3500,
        ]);
        $fallback = WorkshopAgenda::resolveGroupSlotPrices([
            'price_per_child_home_cents' => 0,
            'price_per_child_escales_cents' => 0,
        ]);

        $this->assertSame(3200, $homeOnly['home_cents']);
        $this->assertNull($homeOnly['escales_cents']);
        $this->assertFalse($homeOnly['has_fallback']);

        $this->assertNull($escalesOnly['home_cents']);
        $this->assertSame(3500, $escalesOnly['escales_cents']);
        $this->assertFalse($escalesOnly['has_fallback']);

        $this->assertNull($fallback['home_cents']);
        $this->assertNull($fallback['escales_cents']);
        $this->assertTrue($fallback['has_fallback']);
    }
}
