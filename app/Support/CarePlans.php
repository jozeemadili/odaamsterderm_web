<?php

namespace App\Support;

/**
 * What day-old chicks need in their first days, per chick type, from
 * recommended practice and the breeder guides (Ross, Cobb, Hy-Line):
 * starter feed for the first days, vitamins or electrolytes in the water
 * after the journey, the first vaccines, and drinkers. Each step says how
 * to find a matching product and how much one chick needs, so a
 * suggestion can be sized to the boxes booked.
 *
 * Kinds and amounts:
 * - feed: kg per chick over the period
 * - water: litres of drinking water per chick per day, for N days
 *   (the product's own dose per litre is read from its use rate)
 * - vaccine: doses per chick
 * - equipment: one per N chicks
 */
class CarePlans
{
    /** Shown at most, in this order of importance. */
    public const MAX_SUGGESTIONS = 3;

    private const VITAMINS = [
        'key' => 'vitamins', 'kind' => 'water', 'litres_per_chick_day' => 0.05, 'days' => 3,
        'categories' => ['C20'], 'use_cases' => ['stress_vaccination_transport', 'early_growth'], 'prefer' => ['chick', 'vitamix', 'electrolyte'],
        'reason' => 'In their drinking water for the first 3 days, after the journey',
        'reason_sw' => 'Kwenye maji ya kunywa siku 3 za mwanzo, baada ya safari',
    ];

    private const NEWCASTLE = [
        'key' => 'newcastle', 'kind' => 'vaccine', 'doses_per_chick' => 1,
        'categories' => ['C40'], 'disease' => 'newcastle', 'prefer' => ['lasota', 'i-2', 'thermostable'],
        'reason' => 'First Newcastle vaccine, usually given around day 7–10',
        'reason_sw' => 'Chanjo ya kwanza ya Newcastle (kideri), kwa kawaida siku ya 7–10',
    ];

    private const GUMBORO = [
        'key' => 'gumboro', 'kind' => 'vaccine', 'doses_per_chick' => 1,
        'categories' => ['C40'], 'disease' => 'gumboro', 'prefer' => [],
        'reason' => 'Gumboro vaccine, usually given around day 14',
        'reason_sw' => 'Chanjo ya Gumboro, kwa kawaida siku ya 14',
    ];

    private const DRINKERS = [
        'key' => 'drinkers', 'kind' => 'equipment', 'chicks_per_item' => 100,
        'categories' => ['C6010'], 'prefer' => ['chick drinker', 'drinker'], 'must' => ['drinker'],
        'reason' => 'About one chick drinker for every 100 chicks in the first week',
        'reason_sw' => 'Takriban chombo kimoja cha maji kwa kila vifaranga 100 wiki ya kwanza',
    ];

    /** @return list<array<string, mixed>> steps for a chick type, most important first */
    public static function for(string $chickType): array
    {
        $starter = match ($chickType) {
            'broiler' => [
                'key' => 'starter_feed', 'kind' => 'feed', 'kg_per_chick' => 0.3,
                'categories' => ['C1010'], 'stage' => 'LS-POU-STR',
                'prefer' => ['broiler pre-starter', 'pre-starter', 'broiler starter', 'starter crumble'], 'avoid' => ['layer'],
                'reason' => 'Starter feed for the first 10 days (about 0.3 kg a chick)',
                'reason_sw' => 'Chakula cha kuanzia kwa siku 10 za mwanzo (takriban kilo 0.3 kwa kifaranga)',
            ],
            'layers' => [
                'key' => 'starter_feed', 'kind' => 'feed', 'kg_per_chick' => 0.25,
                'categories' => ['C1010'], 'stage' => 'LS-POU-STR',
                'prefer' => ['layer starter', 'chick mash', 'chick & duck', 'starter crumble'], 'avoid' => ['broiler pre-starter'],
                'reason' => 'Chick mash for the first 2 weeks (about 0.25 kg a chick)',
                'reason_sw' => 'Chakula cha vifaranga kwa wiki 2 za mwanzo (takriban kilo 0.25 kwa kifaranga)',
            ],
            default => [
                'key' => 'starter_feed', 'kind' => 'feed', 'kg_per_chick' => 0.25,
                'categories' => ['C1010'], 'stage' => 'LS-POU-STR',
                'prefer' => ['chick mash', 'chick & duck', 'layer starter', 'starter crumble'], 'avoid' => ['broiler pre-starter'],
                'reason' => 'Chick feed for the first 2 weeks (about 0.25 kg a chick)',
                'reason_sw' => 'Chakula cha vifaranga kwa wiki 2 za mwanzo (takriban kilo 0.25 kwa kifaranga)',
            ],
        };

        return [$starter, self::VITAMINS, self::NEWCASTLE, self::GUMBORO, self::DRINKERS];
    }
}
