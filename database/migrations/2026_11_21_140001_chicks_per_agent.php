<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Day-old chicks are sold by agents (merchants), each from the hatcheries
 * it has a contract with. A contract names the hatchery, the chick type,
 * the stores the chicks arrive at, the weekdays they arrive (Monday and
 * Thursday by default) and the cap of boxes per day. Order days
 * (hatch_schedules) now belong to an agent and its contract.
 *
 * Also adds the Machinery and per-animal vaccine catalogue categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chick_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chick_type_id')->constrained();
            $table->json('branch_ids')->nullable(); // pickup stores; null = all the agent's marketplace stores
            $table->json('weekdays'); // ISO days chicks arrive: 1 = Monday … 7 = Sunday
            $table->unsignedInteger('cap_boxes')->default(100);
            $table->decimal('price_per_chick', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sample')->default(false); // demo data; its order days are samples too
            $table->date('generated_until')->nullable(); // order days exist up to here
            $table->timestamps();
            $table->unique(['merchant_id', 'producer_id', 'chick_type_id']);
        });

        Schema::table('hatch_schedules', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('chick_contract_id')->nullable()->after('merchant_id')->constrained()->nullOnDelete();
        });

        // One order day per agent, hatchery, type and date (no longer one per hatchery).
        $producerIndex = Schema::hasIndex('hatch_schedules', ['producer_id']);
        Schema::table('hatch_schedules', function (Blueprint $table) use ($producerIndex) {
            if (! $producerIndex) {
                $table->index('producer_id'); // keeps the producer foreign key indexed once the old unique goes
            }
            $table->dropUnique(['producer_id', 'chick_type_id', 'hatch_date']);
            $table->unique(['merchant_id', 'producer_id', 'chick_type_id', 'hatch_date'], 'hatch_schedules_agent_day_unique');
        });

        $now = now();
        $category = fn (string $code) => DB::table('product_categories')->where('code', $code)->value('id');

        if ($equipment = $category('C60')) {
            $this->addCategory('C6090', $equipment, 'Machinery', 'Mashine', 'Feed mixers, pellet machines, choppers, milking machines and other farm machinery.', 34, $now);
        }
        if ($vaccines = $category('C40')) {
            foreach ([
                ['C4010', 'Poultry vaccines', 'Chanjo za kuku'],
                ['C4020', 'Cattle vaccines', 'Chanjo za ng\'ombe'],
                ['C4030', 'Goat & sheep vaccines', 'Chanjo za mbuzi na kondoo'],
                ['C4040', 'Pig vaccines', 'Chanjo za nguruwe'],
            ] as $i => [$code, $name, $sw]) {
                $this->addCategory($code, $vaccines, $name, $sw, null, 20 + $i, $now);
            }
        }
    }

    private function addCategory(string $code, int $parent, string $name, string $sw, ?string $description, int $sort, $now): void
    {
        if (DB::table('product_categories')->where('code', $code)->exists()) {
            return;
        }
        $slug = Str::slug($name);
        $base = $slug;
        for ($i = 2; DB::table('product_categories')->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }
        DB::table('product_categories')->insert([
            'code' => $code, 'parent_id' => $parent, 'name' => $name, 'name_sw' => $sw, 'slug' => $slug,
            'description' => $description, 'sort_order' => $sort, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('hatch_schedules', function (Blueprint $table) {
            $table->dropForeign(['chick_contract_id']);
            $table->dropForeign(['merchant_id']);
        });
        Schema::table('hatch_schedules', function (Blueprint $table) {
            $table->dropUnique('hatch_schedules_agent_day_unique');
            $table->unique(['producer_id', 'chick_type_id', 'hatch_date']);
            $table->dropIndex(['producer_id']);
            $table->dropColumn(['chick_contract_id', 'merchant_id']);
        });
        Schema::dropIfExists('chick_contracts');
        DB::table('product_categories')->whereIn('code', ['C6090', 'C4010', 'C4020', 'C4030', 'C4040'])->delete();
    }
};
