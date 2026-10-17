<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 55);
            $table->string('code', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Source: regions(3).sql (Tanzania region reference list supplied by the client).
        $now = now();
        DB::table('regions')->insert(collect([
            [1, 'Arusha', 'ARS'],
            [2, 'DAR ES SALAAM', 'DAR'],
            [3, 'Dodoma', 'DDM'],
            [4, 'Geita', 'OTH'],
            [5, 'Iringa', 'IRN'],
            [6, 'Kagera', 'KGR'],
            [7, 'Katavi', 'OTH'],
            [8, 'Kigoma', 'KGM'],
            [9, 'Kilimanjaro', 'KLM'],
            [10, 'Lindi', 'LIN'],
            [11, 'Manyara', 'MNY'],
            [12, 'Mara', 'MAR'],
            [13, 'Mbeya', 'MBY'],
            [14, 'Morogoro', 'MOR'],
            [15, 'Mtwara', 'MTR'],
            [16, 'Mwanza', 'MWZ'],
            [17, 'Njombe', 'OTH'],
            [18, 'Pwani', 'OTH'],
            [19, 'Rukwa', 'RKA'],
            [20, 'Ruvuma', 'RVM'],
            [21, 'Shinyanga', 'SHY'],
            [22, 'Simiyu', 'OTH'],
            [23, 'Singida', 'SIN'],
            [24, 'Songwe', 'OTH'],
            [25, 'Tabora', 'TBR'],
            [26, 'Tanga', 'TAN'],
            [27, 'Unguja Kaskazini', 'UNG'],
            [28, 'Unguja Kusini', 'UNG'],
            [29, 'Pemba Kaskazini', 'PEM'],
            [30, 'Pemba Kusini', 'PEM'],
            [31, 'Mjini Magharibi', 'UNG'],
            [32, 'Overseas', '003'],
        ])->map(fn ($row) => [
            'id' => $row[0],
            'name' => $row[1],
            'code' => $row[2],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
