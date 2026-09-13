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
}
