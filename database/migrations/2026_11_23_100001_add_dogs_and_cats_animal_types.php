<?php

use App\Models\AnimalType;
use App\Models\LifeStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Dogs and cats become animals of their own (they were lumped into "Other
 * livestock / animals"), each with growth stages agents sell for. Cats use
 * AN-FEL because AN-CAT is cattle. Safe to run more than once.
 */
return new class extends Migration
{
    private const ANIMALS = [
        'AN-DOG' => [
            'slug' => 'dogs', 'name' => 'Dogs', 'name_sw' => 'Mbwa', 'sort_order' => 5,
            'description' => 'Pet, guard and working dogs. Sold for them: dog food and treats, dewormers, tick and flea control, rabies and other vaccines, vitamins and supplements, shampoos and wound care, collars, leashes, bowls and kennels.',
            'stages' => [
                ['LS-DOG-PUP', 'Puppy (0–12 months)', 'Mtoto wa mbwa (miezi 0–12)', 10, 'Fast growth. Puppy food several times a day, the first vaccinations and regular deworming as the vet advises.'],
                ['LS-DOG-ADL', 'Adult (1–7 years)', 'Mbwa mzima (miaka 1–7)', 20, 'Adult maintenance food, yearly rabies vaccination, deworming and tick and flea control on a regular schedule.'],
                ['LS-DOG-SNR', 'Senior (7+ years)', 'Mbwa mzee (miaka 7+)', 30, 'Lighter senior food, joint and vitamin support, and more frequent vet checks.'],
                ['LS-DOG-BRD', 'Pregnant / nursing', 'Mbwa mjamzito / anayenyonyesha', 40, 'Extra energy and nutrition for the mother; supplements only as the vet advises.'],
            ],
        ],
        'AN-FEL' => [
            'slug' => 'cats', 'name' => 'Cats', 'name_sw' => 'Paka', 'sort_order' => 6,
            'description' => 'Pet and farm cats. Sold for them: cat food and treats, dewormers, flea and tick control, rabies and other vaccines, vitamins, litter, bowls and carriers.',
            'stages' => [
                ['LS-FEL-KIT', 'Kitten (0–12 months)', 'Mtoto wa paka (miezi 0–12)', 10, 'Fast growth. Kitten food in small meals, the first vaccinations and regular deworming as the vet advises.'],
                ['LS-FEL-ADL', 'Adult (1–7 years)', 'Paka mzima (miaka 1–7)', 20, 'Adult maintenance food, yearly rabies vaccination, deworming and flea control on a regular schedule.'],
                ['LS-FEL-SNR', 'Senior (7+ years)', 'Paka mzee (miaka 7+)', 30, 'Senior food, kidney and joint care, and more frequent vet checks.'],
                ['LS-FEL-BRD', 'Pregnant / nursing', 'Paka mjamzito / anayenyonyesha', 40, 'Extra energy and nutrition for the mother; supplements only as the vet advises.'],
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::ANIMALS as $code => $a) {
            $animal = AnimalType::where('code', $code)->first() ?? AnimalType::where('slug', $a['slug'])->first() ?? new AnimalType;
            $animal->fill([
                'code' => $code, 'slug' => $a['slug'], 'name' => $a['name'], 'name_sw' => $a['name_sw'],
                'description' => $a['description'], 'sort_order' => $a['sort_order'], 'parent_id' => null, 'is_active' => true,
            ])->save();

            foreach ($a['stages'] as [$stageCode, $name, $nameSw, $sort, $description]) {
                $stage = LifeStage::where('code', $stageCode)->first() ?? new LifeStage;
                $stage->fill([
                    'code' => $stageCode, 'animal_type_id' => $animal->id, 'slug' => $a['slug'].'-'.Str::slug($name),
                    'name' => $name, 'name_sw' => $nameSw, 'description' => $description, 'sort_order' => $sort, 'is_active' => true,
                ])->save();
            }
        }

        // "Other" no longer covers dogs (or cats), and moves after them.
        AnimalType::where('code', 'AN-OTH')->update([
            'name' => 'Other livestock / animals (rabbits, bees)',
            'name_sw' => 'Mifugo mingine (sungura, nyuki)',
            'sort_order' => 7,
        ]);
    }

    public function down(): void
    {
        foreach (array_keys(self::ANIMALS) as $code) {
            $animal = AnimalType::where('code', $code)->first();
            if ($animal && ! $animal->taggedProducts()->exists() && ! $animal->products()->exists()) {
                $animal->lifeStages()->each(fn (LifeStage $s) => $s->products()->exists() ? null : $s->delete());
                $animal->lifeStages()->exists() ?: $animal->delete();
            }
        }
        AnimalType::where('code', 'AN-OTH')->update([
            'name' => 'Other livestock / animals (rabbits, dogs, bees)',
            'name_sw' => 'Mifugo mingine (sungura, mbwa, nyuki)',
            'sort_order' => 5,
        ]);
    }
};
