<?php

namespace App\Services\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The farm guides the assistant answers "how do I raise…" questions from:
 * breeder handbooks (Ross broilers, Hy-Line Brown layers…) stored page by
 * page in database/data/farm-guides/*.json. For each question the pages
 * that match it best are found (Kiswahili farm words are mapped to the
 * handbooks' English terms) and handed to the model as excerpts it must
 * base its answer on and cite.
 */
class FarmGuides
{
    /** Pages handed to the model per question. */
    public const PAGE_LIMIT = 6;

    /** Characters of one page handed to the model. */
    private const PAGE_CHARS = 3000;

    /**
     * Kiswahili (and common English) farm words → the handbooks' terms.
     *
     * @var array<string, list<string>>
     */
    private const SYNONYMS = [
        'kuku wa nyama' => ['broiler'], 'nyama' => ['broiler'], 'broilers' => ['broiler'], 'ross' => ['broiler'],
        'kuku wa mayai' => ['layer', 'laying'], 'layers' => ['layer', 'laying'], 'hy-line' => ['layer'], 'hyline' => ['layer'],
        'mayai' => ['egg', 'production', 'layer'], 'yai' => ['egg'], 'kutaga' => ['laying', 'production', 'egg'], 'eggs' => ['egg'],
        'vifaranga' => ['chick', 'brooding', 'placement'], 'kifaranga' => ['chick', 'brooding'], 'chicks' => ['chick'],
        'kulea' => ['brooding', 'management'], 'brooder' => ['brooding'], 'brood' => ['brooding'],
        'joto' => ['temperature', 'heat'], 'baridi' => ['cold', 'temperature'], 'unyevu' => ['humidity'], 'hot' => ['heat', 'temperature'],
        'chakula' => ['feed', 'feeding', 'intake'], 'wale' => ['feed', 'intake'], 'wanakula' => ['feed', 'intake'], 'lishe' => ['feed', 'nutrition'], 'kula' => ['feed', 'intake'], 'feeding' => ['feed'],
        'maji' => ['water', 'drinker'], 'kunywa' => ['water', 'drinker'], 'drinking' => ['water'],
        'mwanga' => ['light', 'lighting'], 'taa' => ['light', 'lighting'], 'giza' => ['dark', 'lighting'],
        'uzito' => ['weight', 'body', 'objective'], 'kilo' => ['weight'], 'gramu' => ['weight'], 'weigh' => ['weight'],
        'banda' => ['house', 'housing'], 'nyumba' => ['house'], 'matandazo' => ['litter'], 'maranda' => ['litter', 'shavings'],
        'hewa' => ['ventilation', 'air'], 'nafasi' => ['space', 'density', 'stocking'], 'msongamano' => ['stocking', 'density'],
        'chanjo' => ['vaccination', 'vaccine'], 'chanja' => ['vaccination'], 'ugonjwa' => ['disease', 'health'], 'magonjwa' => ['disease', 'health'],
        'usafi' => ['biosecurity', 'cleaning', 'disinfection'], 'vifo' => ['mortality'], 'kufa' => ['mortality'], 'wanakufa' => ['mortality'],
        'umri' => ['age'], 'siku ya kwanza' => ['placement', 'day-old'], 'wiki' => ['week', 'weeks'], 'siku' => ['day', 'days'],
        'kuchinja' => ['processing', 'catching'], 'kuuza' => ['processing', 'market'], 'soko' => ['market'],
        'dawa' => ['medication', 'treatment'], 'kuhara' => ['enteric', 'diarrhea'], 'mafua' => ['respiratory'],
        'vyombo' => ['feeder', 'drinker', 'equipment'], 'ukuaji' => ['growth', 'weight'], 'kukua' => ['growth'],
        'mbegu' => ['breed'], 'wastani' => ['uniformity', 'average'],
    ];

    private const STOPWORDS = [
        'the', 'and', 'who', 'how', 'what', 'when', 'why', 'which', 'with', 'for', 'from', 'that', 'this', 'are', 'can', 'should', 'much',
        'many', 'need', 'does', 'have', 'your', 'you', 'they', 'them', 'their', 'about', 'into', 'per', 'give', 'best', 'good', 'tell',
        'jinsi', 'gani', 'kwa', 'wa', 'ya', 'na', 'la', 'za', 'cha', 'vya', 'je', 'nini', 'namna', 'nina', 'kuna', 'hii', 'hiyo',
        'kiasi', 'ngapi', 'iwe', 'liwe', 'kwanza', 'bei', 'sana', 'kila', 'baada', 'kabla', 'mpaka', 'hadi', 'au', 'ni', 'si', 'mimi', 'yangu', 'wangu', 'nataka', 'naomba', 'tafadhali', 'ninawezaje', 'vipi', 'kufuga', 'ufugaji', 'fuga', 'kuku',
        'chicken', 'chickens', 'bird', 'birds', 'raise', 'raising', 'keep', 'manage', 'management',
    ];

    /** @var list<array{slug:string, title:string, short:string, about:string, pages:list<array{page:int, section:string, text:string, lower:string}>}>|null */
    private ?array $guides = null;

    public function __construct(private readonly ?string $directory = null) {}

    /**
     * The guide pages that best answer this question (with earlier turns
     * for context), best first; empty when nothing matches.
     *
     * @return Collection<int, array{guide:string, title:string, page:int, section:string, text:string, score:float}>
     */
    public function relevant(string $question, int $limit = self::PAGE_LIMIT): Collection
    {
        $terms = $this->terms($question);
        // Only farming questions are looked up ("who won the match?" is not).
        if (! array_intersect($terms, $this->farmWords())) {
            return collect();
        }

        $pages = collect($this->guides())->flatMap(fn (array $g) => collect($g['pages'])->map(fn (array $p) => $p + ['guide' => $g['short'], 'title' => $g['title'], 'slug' => $g['slug']]));
        $total = max(1, $pages->count());

        // Rarer words count for more (idf); a page is scored on how many
        // of the words it contains, not how often one repeats.
        $weights = [];
        foreach ($terms as $term) {
            $df = $pages->filter(fn (array $p) => str_contains($p['lower'], $term))->count();
            if ($df > 0) {
                $weights[$term] = log(1 + $total / $df);
            }
        }
        if ($weights === []) {
            return collect();
        }

        // Ages and amounts asked about ("10 weeks", "wiki ya 3"): pages with
        // a table row for that number answer them.
        preg_match_all('/\b(\d{1,3})\b/', $question, $numbers);
        $numbers = array_unique($numbers[1]);
        $wantsLayers = (bool) array_intersect(['layer', 'laying', 'egg'], $terms);
        $wantsBroilers = in_array('broiler', $terms, true);

        return $pages
            ->map(function (array $p) use ($weights, $numbers, $wantsLayers, $wantsBroilers) {
                $score = 0.0;
                foreach ($weights as $term => $weight) {
                    $hits = substr_count($p['lower'], $term);
                    $score += $hits > 0 ? $weight * (1 + 0.25 * log($hits)) : 0;
                }
                foreach ($numbers as $n) {
                    if (preg_match('/\ntable rows:\n(?:.*\n)*?'.$n.' \|/', $p['lower'])) {
                        $score *= 1.8;
                        break;
                    }
                }
                // Layer questions go to the layer guide, broiler ones to the broiler handbook.
                $isLayerGuide = str_contains($p['slug'], 'layer');
                if (($wantsLayers && ! $isLayerGuide && ! $wantsBroilers) || ($wantsBroilers && $isLayerGuide && ! $wantsLayers)) {
                    $score *= 0.4;
                }

                return ['guide' => $p['guide'], 'title' => $p['title'], 'page' => $p['page'], 'section' => $p['section'], 'text' => $p['text'], 'score' => round($score, 3)];
            })
            ->filter(fn (array $p) => $p['score'] >= 2.0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * The guides section of the assistant's instructions: what guides
     * exist and the excerpts for this question.
     */
    public function promptSection(string $question): string
    {
        $guides = $this->guides();
        if ($guides === []) {
            return '';
        }

        $list = collect($guides)->map(fn (array $g) => "- {$g['title']}: {$g['about']}")->implode("\n");
        $excerpts = $this->relevant($question)->map(fn (array $p) => "[{$p['guide']}, page {$p['page']} — {$p['section']}]\n".Str::limit($p['text'], self::PAGE_CHARS))->implode("\n\n");

        return <<<GUIDES
        Farm guides you can answer from:
        {$list}

        When the customer asks how to raise, feed, brood, house, light, vaccinate or look after chickens (or how they should perform: weight, feed or water intake, egg production by age), base the answer on the guide excerpts below. Give the guide's actual figures (temperatures, weights, amounts by age) converted to what they asked, and name the source briefly, for example "(Ross Broiler Handbook, uk. 30)" in Kiswahili or "(Ross Broiler Handbook, p. 30)" in English. The Ross handbook is for broilers (kuku wa nyama) and the Hy-Line guide for brown layers (kuku wa mayai); say so if they keep a different breed. If the excerpts do not cover the question, say the guides don't cover it and give careful general advice. Never invent figures. For sick birds, still recommend a veterinarian.

        Guide excerpts for this question:
        {$this->orNone($excerpts)}
        GUIDES;
    }

    /**
     * Words that make a question a farming one: every handbook term the
     * Kiswahili words map to, plus common English farm words.
     *
     * @return list<string>
     */
    private function farmWords(): array
    {
        return array_values(array_unique(array_merge(array_merge(...array_values(self::SYNONYMS)), [
            'chick', 'broiler', 'layer', 'pullet', 'hen', 'poultry', 'flock', 'hatch', 'hatchery', 'incubation', 'feeder', 'drinker',
            'nipple', 'vaccine', 'mortality', 'uniformity', 'fcr', 'conversion', 'gumboro', 'newcastle', 'marek', 'coccidiosis',
            'ammonia', 'ventilation', 'litter', 'brooding', 'temperature', 'humidity', 'lighting', 'weight', 'feed', 'water', 'egg',
            'nutrition', 'protein', 'starter', 'grower', 'finisher', 'stocking', 'density', 'biosecurity', 'disinfection', 'house',
        ])));
    }

    /** @return list<string> */
    public function terms(string $question): array
    {
        $text = ' '.mb_strtolower($question).' ';
        $terms = [];
        // Phrases first ("kuku wa nyama"), then single words.
        foreach (self::SYNONYMS as $word => $mapped) {
            if (str_contains($word, ' ') && str_contains($text, " {$word} ")) {
                array_push($terms, ...$mapped);
                // "kuku wa mayai" means layers, not a question about eggs.
                $text = str_replace(" {$word} ", ' ', $text);
            }
        }
        foreach (preg_split('/[^\pL\pN\-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (isset(self::SYNONYMS[$word])) {
                array_push($terms, ...self::SYNONYMS[$word]);
            } elseif (mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true) && ! ctype_digit($word)) {
                $terms[] = Str::singular($word);
            }
        }

        return array_values(array_unique($terms));
    }

    /** @return list<array{slug:string, title:string, short:string, about:string, pages:list<array<string, mixed>>}> */
    public function guides(): array
    {
        if ($this->guides !== null) {
            return $this->guides;
        }

        $files = glob(($this->directory ?? database_path('data/farm-guides')).'/*.json') ?: [];
        sort($files);

        return $this->guides = array_map(function (string $file) {
            $data = json_decode((string) file_get_contents($file), true);

            return [
                'slug' => basename($file, '.json'),
                'title' => $data['title'],
                'short' => $data['short'],
                'about' => $data['about'],
                'pages' => array_map(fn (array $p) => $p + ['lower' => mb_strtolower($p['text'])], $data['pages']),
            ];
        }, $files);
    }

    private function orNone(string $excerpts): string
    {
        return $excerpts !== '' ? $excerpts : '(none matched this question)';
    }
}
