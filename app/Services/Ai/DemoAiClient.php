<?php

namespace App\Services\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Free stand-in for Claude while developing (no API key, no cost).
 *
 * It answers the same two requests as the real model — Ask AI search and
 * the order assistant — with simple word matching and quantity rules,
 * reading the product/offer list from the system prompt the services
 * build. Answers go through the same server checks as real AI answers.
 * Not intelligent: unusual questions get weaker results.
 */
class DemoAiClient implements AiClient
{
    public const MODEL = 'demo';

    /** Words customers use → words in our catalog (English and Kiswahili). */
    private const SYNONYMS = [
        'vifaranga' => ['chick', 'starter'], 'kifaranga' => ['chick', 'starter'],
        'chicks' => ['chick', 'starter'], 'chick' => ['chick', 'starter'],
        'kuku' => ['chicken', 'poultry'], 'chickens' => ['chicken', 'poultry'], 'hens' => ['layers', 'chicken'],
        'broiler' => ['chicken'], 'broilers' => ['chicken'], 'nyama' => ['chicken'],
        'mayai' => ['layers'], 'eggs' => ['layers'], 'layer' => ['layers'],
        'ngombe' => ['cattle', 'dairy'], 'cows' => ['cattle', 'dairy'], 'cow' => ['cattle', 'dairy'], 'maziwa' => ['dairy'], 'milk' => ['dairy'],
        'mbuzi' => ['goat'], 'goats' => ['goat'], 'kondoo' => ['sheep'],
        'nguruwe' => ['pig'], 'pigs' => ['pig'],
        'samaki' => ['fish', 'tilapia'], 'fish' => ['fish', 'tilapia'], 'sato' => ['tilapia'],
        'chakula' => ['feed', 'meal', 'mash', 'pellets'], 'food' => ['feed', 'meal', 'mash', 'pellets'], 'feed' => ['feed', 'meal', 'mash', 'pellets'],
        'dawa' => ['medicine'], 'medicine' => ['medicine'], 'sick' => ['medicine'], 'mgonjwa' => ['medicine'],
        'chanjo' => ['vaccine'], 'vaccine' => ['vaccine'], 'vaccines' => ['vaccine'],
        'minyoo' => ['dewormer'], 'worms' => ['dewormer'], 'deworm' => ['dewormer'],
        'vitamini' => ['vitamin'], 'vitamins' => ['vitamin'], 'madini' => ['mineral'], 'minerals' => ['mineral'],
        'maji' => ['drinker'], 'water' => ['drinker'], 'drinker' => ['drinker'], 'feeder' => ['feeder'],
    ];

    private const SWAHILI = ['nahitaji', 'chakula', 'vifaranga', 'kifaranga', 'kuku', 'ngombe', 'mbuzi', 'kondoo', 'nguruwe', 'samaki', 'dawa', 'chanjo', 'minyoo', 'nina', 'kwa', 'cha', 'ya', 'za', 'bei', 'nafuu', 'karibu', 'leo', 'mifuko', 'mfuko', 'habari', 'naomba'];

    private const CHEAP = ['cheap', 'cheapest', 'cheaper', 'budget', 'nafuu', 'rahisi'];

    private const NEAR = ['near', 'nearest', 'nearby', 'close', 'karibu'];

    private const NOW = ['now', 'today', 'urgent', 'leo', 'sasa', 'haraka'];

    /** Rough rule: one 25 kg bag of feed per this many birds for about two weeks. */
    private const BIRDS_PER_BAG = 50;

    public function isConfigured(): bool
    {
        return true;
    }

    public function structured(string $system, string|array $prompt, array $schema, int $maxTokens = 4000): AiResponse
    {
        $messages = is_string($prompt) ? [['role' => 'user', 'content' => $prompt]] : $prompt;
        $data = array_key_exists('basket', $schema['properties'] ?? [])
            ? $this->order($system, $messages)
            : $this->search($system, end($messages)['content']);

        return new AiResponse($data, self::MODEL);
    }

    /** @return array<string, mixed> */
    protected function search(string $system, string $question): array
    {
        $words = $this->words($question);
        $sw = $this->isSwahili($words);
        $picks = $this->rank($this->rows($system), $words)->take(12);

        if ($picks->isEmpty()) {
            return [
                'product_ids' => [],
                'summary' => $sw
                    ? '(Demo AI) Sikupata bidhaa zinazolingana. Jaribu kutaja mnyama na bidhaa, mfano "chakula cha vifaranga".'
                    : '(Demo AI) I could not find matching products. Try naming the animal and the product, e.g. "feed for chicks".',
                'in_stock_only' => false,
                'sort' => 'relevance',
                'on_topic' => $this->terms($words) !== [],
            ];
        }

        $names = $picks->take(3)->pluck('name')->implode(', ');

        return [
            'product_ids' => $picks->pluck('id')->all(),
            'summary' => $sw
                ? "(Demo AI) Bidhaa zinazolingana zaidi na ombi lako: {$names}. Hii ni hali ya majaribio — inalinganisha maneno tu."
                : "(Demo AI) Best matches for your request: {$names}. This is demo mode — it matches words only.",
            'in_stock_only' => (bool) array_intersect($words, self::NOW),
            'sort' => array_intersect($words, self::CHEAP) ? 'price' : (array_intersect($words, self::NEAR) ? 'nearest' : 'relevance'),
            'on_topic' => true,
        ];
    }

    /**
     * @param  list<array{role:string, content:string}>  $messages
     * @return array<string, mixed>
     */
    protected function order(string $system, array $messages): array
    {
        $userText = collect($messages)->where('role', 'user')->pluck('content')->implode(' ');
        $words = $this->words($userText);
        $latest = $this->words(end($messages)['content']);
        $sw = $this->isSwahili($words);
        $turns = collect($messages)->where('role', 'user')->count();

        $animals = $this->animalCount($userText);
        $bags = $this->statedQuantity($userText);

        // Ask once for the number of animals when we have nothing to go on.
        if ($turns === 1 && $animals === null && $bags === null) {
            return ['basket' => [], 'reply' => $sw
                ? '(Demo AI) Una wanyama wangapi, na ni wa aina gani? Mfano: "vifaranga 200 vya wiki moja".'
                : '(Demo AI) How many animals do you have, and which kind? For example: "200 broiler chicks, 1 week old".'];
        }

        // Rank by the latest message first, so a change of mind wins.
        $offers = $this->rows($system);
        $ranked = $this->rank($offers, $latest);
        if ($ranked->isEmpty()) {
            $ranked = $this->rank($offers, $words);
        }
        if ($ranked->isEmpty()) {
            return ['basket' => [], 'reply' => $sw
                ? '(Demo AI) Sikupata bidhaa hiyo. Taja mnyama na unachohitaji, mfano "chakula cha kuku wa mayai".'
                : '(Demo AI) I could not find that. Name the animal and what you need, e.g. "feed for layer hens".'];
        }

        // The best-matching product, from the cheapest or nearest store.
        $product = $ranked->first()['name'];
        $choices = $ranked->where('name', $product);
        $cheap = (bool) array_intersect($words, self::CHEAP);
        $offer = $cheap || $choices->every(fn ($o) => $o['distance'] === null)
            ? $choices->sortBy('price')->first()
            : $choices->sortBy(fn ($o) => $o['distance'] ?? PHP_FLOAT_MAX)->first();

        $isFeed = Str::contains(Str::lower($offer['category']), 'feed');
        $quantity = $bags ?? ($animals && $isFeed ? (int) ceil($animals / self::BIRDS_PER_BAG) : 1);
        $why = match (true) {
            $bags !== null => $sw ? "kama ulivyoomba ({$bags})" : "as you asked ({$bags})",
            $animals && $isFeed => $sw
                ? 'kanuni ya majaribio: mfuko 1 kwa wanyama '.self::BIRDS_PER_BAG.' kwa takriban wiki 2'
                : 'demo rule: 1 bag per '.self::BIRDS_PER_BAG.' animals for about 2 weeks',
            default => $sw ? 'kiasi cha kuanzia' : 'a starting quantity',
        };
        $how = $cheap ? ($sw ? 'bei nafuu zaidi' : 'cheapest') : ($sw ? 'duka lililo karibu zaidi' : 'nearest store');

        return [
            'basket' => [['offer_id' => $offer['id'], 'quantity' => $quantity]],
            'reply' => $sw
                ? "(Demo AI) Napendekeza {$quantity} × {$offer['name']} kutoka {$offer['store']} ({$how}). Idadi: {$why}. Hakiki kikapu kisha bonyeza \"Weka kwenye kikapu\"."
                : "(Demo AI) I suggest {$quantity} × {$offer['name']} from {$offer['store']} ({$how}). Quantity: {$why}. Check the basket, then press \"Add to cart\".",
        ];
    }

    /**
     * Product or offer rows from the "id | name | brand | animal | category | …" list in the prompt.
     *
     * @return Collection<int, array{id:int, name:string, brand:string, animal:string, category:string, store:string, price:float, distance:?float}>
     */
    protected function rows(string $system): Collection
    {
        $offers = str_contains($system, 'Offers (offer id');

        return collect(preg_split('/\R/', $system))
            ->map(fn ($line) => array_map('trim', explode(' | ', $line)))
            ->filter(fn ($cols) => count($cols) >= 6 && ctype_digit($cols[0]))
            ->map(fn ($cols) => [
                'id' => (int) $cols[0],
                'name' => $cols[1],
                'brand' => $cols[2],
                'animal' => $cols[3],
                'category' => $cols[4],
                'store' => $offers ? $cols[5] : '',
                'price' => (float) preg_replace('/[^\d.]/', '', explode(' per ', $offers ? $cols[6] : $cols[5])[0]),
                'distance' => $offers && isset($cols[8]) && is_numeric($km = trim(str_replace('km', '', $cols[8]))) ? (float) $km : null,
            ])
            ->values();
    }

    /** Rows that match the question's terms, best first. */
    protected function rank(Collection $rows, array $words): Collection
    {
        $terms = $this->terms($words);
        if (! $terms) {
            return collect();
        }

        $animalTerms = ['chick', 'chicken', 'poultry', 'layers', 'cattle', 'dairy', 'goat', 'sheep', 'pig', 'fish', 'tilapia'];
        $wantsAnimal = array_intersect($terms, $animalTerms);

        return $rows
            ->map(function ($row) use ($terms, $wantsAnimal) {
                $fields = ['name' => 3, 'animal' => 2, 'category' => 2, 'brand' => 1];
                $score = 0;
                foreach ($terms as $term) {
                    foreach ($fields as $field => $weight) {
                        if (str_contains(Str::lower($row[$field]), $term)) {
                            $score += $weight;
                        }
                    }
                }
                // Asked about one animal: other animals' products drop out.
                $animalOk = ! $wantsAnimal || collect($wantsAnimal)->contains(fn ($t) => str_contains(Str::lower($row['animal'].' '.$row['name']), $t));

                return $row + ['score' => $animalOk ? $score : 0];
            })
            ->where('score', '>', 0)
            ->sortBy([['score', 'desc'], ['price', 'asc']])
            ->values()
            // Keep strong matches only (e.g. "chick feed" drops chick vaccines).
            ->pipe(fn (Collection $c) => $c->where('score', '>=', ($c->first()['score'] ?? 0) / 2)->values());
    }

    /** @return list<string> */
    protected function words(string $text): array
    {
        $text = str_replace(["'", '’'], '', mb_strtolower($text));

        return array_values(array_filter(preg_split('/[^\pL\pN]+/u', $text)));
    }

    /** Catalog terms for the customer's words (synonyms, plus long words as-is). */
    protected function terms(array $words): array
    {
        $terms = [];
        foreach ($words as $w) {
            if (isset(self::SYNONYMS[$w])) {
                array_push($terms, ...self::SYNONYMS[$w]);
            } elseif (mb_strlen($w) >= 4 && ! ctype_digit($w) && ! in_array($w, [...self::CHEAP, ...self::NEAR, ...self::NOW, 'need', 'want', 'please', 'have', 'with', 'from', 'weeks', 'week', 'nahitaji', 'naomba', 'nina'], true)) {
                $terms[] = Str::singular($w);
            }
        }

        return array_values(array_unique($terms));
    }

    protected function isSwahili(array $words): bool
    {
        return count(array_intersect($words, self::SWAHILI)) >= 1
            && count(array_intersect($words, ['i', 'need', 'the', 'for', 'my', 'want', 'have'])) === 0;
    }

    /** "200 chicks", "vifaranga 200": a number next to an animal word. */
    protected function animalCount(string $text): ?int
    {
        $animals = 'chicks?|broilers?|hens?|layers?|birds?|chickens?|cows?|cattle|goats?|sheep|pigs?|fish|vifaranga|kuku|ngombe|ng\'ombe|mbuzi|kondoo|nguruwe|samaki|wanyama|animals?';
        if (preg_match("/(\d{1,6})\s*(?:\w+\s+)?(?:{$animals})\b/iu", $text, $m) || preg_match("/\b(?:{$animals})\s+(?:\w+\s+)?(\d{1,6})/iu", $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** "3 bags", "mifuko 3", "2 bottles": a quantity the customer stated. */
    protected function statedQuantity(string $text): ?int
    {
        $units = 'bags?|sacks?|bottles?|vials?|pieces?|pcs|sachets?|units?|mifuko|mfuko|chupa|vipande';
        if (preg_match("/(\d{1,4})\s*(?:{$units})\b/iu", $text, $m) || preg_match("/\b(?:{$units})\s+(\d{1,4})/iu", $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
