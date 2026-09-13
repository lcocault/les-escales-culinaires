<?php
// src/WorkshopAgenda.php – compose and filter public workshop agenda items

final class WorkshopAgenda
{
    public static function buildItems(array $sessions, array $groupSlots): array
    {
        $allItems = [];
        foreach ($sessions as $session) {
            $allItems[] = [
                'type'    => 'session',
                'segment' => !empty($session['is_private']) ? 'group_private' : 'regular',
                'date'    => $session['session_date'],
                'time'    => $session['start_time'],
                'data'    => $session,
            ];
        }
        foreach ($groupSlots as $slot) {
            $allItems[] = [
                'type'    => 'group_slot',
                'segment' => 'group_private',
                'date'    => $slot['slot_date'],
                'time'    => $slot['start_time'],
                'data'    => $slot,
            ];
        }

        usort($allItems, static function (array $a, array $b): int {
            $dateComparison = strcmp((string) $a['date'], (string) $b['date']);
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return strcmp((string) $a['time'], (string) $b['time']);
        });

        return $allItems;
    }

    public static function filterItems(array $items, string $selectedFilter): array
    {
        if ($selectedFilter === 'all') {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn(array $item): bool => $item['segment'] === $selectedFilter
        ));
    }

    public static function resolveGroupSlotPrices(array $slot): array
    {
        $homePrice = isset($slot['price_per_child_home_cents']) && (int) $slot['price_per_child_home_cents'] > 0
            ? (int) $slot['price_per_child_home_cents']
            : null;
        $escalesPrice = isset($slot['price_per_child_escales_cents']) && (int) $slot['price_per_child_escales_cents'] > 0
            ? (int) $slot['price_per_child_escales_cents']
            : null;

        return [
            'home_cents'    => $homePrice,
            'escales_cents' => $escalesPrice,
            'has_fallback'  => $homePrice === null && $escalesPrice === null,
        ];
    }
}
