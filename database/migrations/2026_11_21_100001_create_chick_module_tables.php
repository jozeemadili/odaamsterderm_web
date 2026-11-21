<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Day-old chicks: the chick types (Broiler, Layers, Sasso, Tanbro), which
 * producers (hatcheries) make which types, and the hatch dates the chick
 * seller (Amsterdam Group) publishes with the number of chicks each date
 * will give. Customers book whole boxes against a hatch date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chick_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('name_sw')->nullable();
            $table->string('purpose', 20); // meat, eggs, dual
            $table->string('summary')->nullable();
            $table->string('summary_sw')->nullable();
            $table->text('description')->nullable();
            $table->text('description_sw')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('producers', function (Blueprint $table) {
            // chicks, feed, chicks_feed: what the company produces.
            $table->string('deals_in', 20)->nullable()->after('role');
            $table->string('phone', 40)->nullable()->after('website');
            $table->string('location')->nullable()->after('phone');
        });

        Schema::create('chick_type_producer', function (Blueprint $table) {
            $table->foreignId('chick_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->primary(['chick_type_id', 'producer_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('chick_type_id')->nullable()->after('producer_id')->constrained()->nullOnDelete();
        });

        Schema::create('hatch_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chick_type_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->date('hatch_date');
            $table->date('order_cutoff_date');
            $table->unsignedInteger('capacity'); // chicks this hatch will give
            $table->decimal('price_per_chick', 12, 2)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hatch_date', 'chick_type_id']);
            $table->unique(['producer_id', 'chick_type_id', 'hatch_date']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('hatch_schedule_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        $now = now();
        DB::table('chick_types')->insert([
            [
                'code' => 'broiler', 'name' => 'Broiler', 'name_sw' => 'Kuku wa nyama (Broiler)', 'purpose' => 'meat', 'sort_order' => 1,
                'summary' => 'Fast-growing meat birds, ready for market in about 5–6 weeks.',
                'summary_sw' => 'Kuku wa nyama wanaokua haraka, tayari kuuzwa baada ya wiki 5–6 hivi.',
                'description' => "Broilers are bred for meat. With good feed, brooding and water they reach market weight (about 2 kg) in roughly 5–6 weeks.\n\n• Purpose: meat\n• Time to market: about 5–6 weeks\n• Feeds: starter, grower and finisher\n• Needs: warm brooding in the first 2 weeks, clean water and feed at all times",
                'description_sw' => "Broiler ni kuku wa nyama. Wakipata chakula, joto na maji vizuri wanafikia uzito wa soko (takriban kilo 2) ndani ya wiki 5–6.\n\n• Lengo: nyama\n• Muda hadi soko: wiki 5–6 hivi\n• Vyakula: starter, grower na finisher\n• Mahitaji: joto la kutosha wiki 2 za mwanzo, maji safi na chakula muda wote",
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'layers', 'name' => 'Layers', 'name_sw' => 'Kuku wa mayai (Layers)', 'purpose' => 'eggs', 'sort_order' => 2,
                'summary' => 'Productive egg-laying breeds for consistent eggs, laying from about 18–20 weeks.',
                'summary_sw' => 'Kuku wa mayai wanaotaga kwa wingi, huanza kutaga wiki 18–20 hivi.',
                'description' => "Layers are bred for eggs. Pullets are reared on chick and grower feeds and start laying at about 18–20 weeks, then lay steadily for over a year on layer feed.\n\n• Purpose: eggs\n• Start of lay: about 18–20 weeks\n• Feeds: chick mash, grower mash, then layer mash\n• Needs: good lighting programme, calcium in the laying feed, vaccination",
                'description_sw' => "Layers ni kuku wa mayai. Hulelewa kwa chakula cha vifaranga na grower, huanza kutaga wiki 18–20 hivi, kisha hutaga kwa mfululizo zaidi ya mwaka mmoja wakipata layer mash.\n\n• Lengo: mayai\n• Kuanza kutaga: wiki 18–20 hivi\n• Vyakula: chick mash, grower mash, kisha layer mash\n• Mahitaji: mpango mzuri wa mwanga, kalsiamu kwenye chakula cha kutaga, chanjo",
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'sasso', 'name' => 'Sasso', 'name_sw' => 'Sasso', 'purpose' => 'dual', 'sort_order' => 3,
                'summary' => 'Hardy coloured birds for free-range and semi-intensive farming, with a distinct flavour.',
                'summary_sw' => 'Kuku chotara imara kwa ufugaji huria na nusu-huria, wenye ladha ya kipekee.',
                'description' => "Sasso are coloured, hardy birds suited to free-range and semi-intensive farming. They grow more slowly than broilers but cope well with village conditions, and are valued for their meat flavour; hens also lay eggs.\n\n• Purpose: meat and eggs\n• Farming: free-range or semi-intensive\n• Strengths: hardy, active foragers, good meat flavour",
                'description_sw' => "Sasso ni kuku chotara wa rangi, imara, wanaofaa ufugaji huria na nusu-huria. Hukua taratibu kuliko broiler lakini huvumilia mazingira ya kijijini, na nyama yao ina ladha nzuri; matetea pia hutaga mayai.\n\n• Lengo: nyama na mayai\n• Ufugaji: huria au nusu-huria\n• Sifa: imara, hujitafutia chakula, nyama tamu",
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'tanbro', 'name' => 'Tanbro', 'name_sw' => 'Tanbro', 'purpose' => 'dual', 'sort_order' => 4,
                'summary' => 'Robust, dual-purpose breed for meat and eggs.',
                'summary_sw' => 'Kuku imara wa matumizi mawili: nyama na mayai.',
                'description' => "Tanbro is a robust dual-purpose breed for meat and eggs, suited to local conditions and to farmers who want both from the same flock.\n\n• Purpose: meat and eggs\n• Farming: semi-intensive or free-range\n• Strengths: robust, adapts well to local conditions",
                'description_sw' => "Tanbro ni kuku imara wa matumizi mawili, nyama na mayai, wanaofaa mazingira ya hapa nchini na wafugaji wanaotaka vyote kutoka kundi moja.\n\n• Lengo: nyama na mayai\n• Ufugaji: nusu-huria au huria\n• Sifa: imara, huzoea mazingira ya hapa",
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('hatch_schedule_id'));
        Schema::dropIfExists('hatch_schedules');
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('chick_type_id'));
        Schema::dropIfExists('chick_type_producer');
        Schema::table('producers', fn (Blueprint $table) => $table->dropColumn(['deals_in', 'phone', 'location']));
        Schema::dropIfExists('chick_types');
    }
};
