<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\AnimalTypes\Index as AnimalTypesIndex;
use App\Livewire\Admin\Producers\Index as ProducersIndex;
use App\Livewire\Admin\ProductCategories\Index as CategoriesIndex;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\CatalogExcel\AnimalTypeSheet;
use App\Services\CatalogExcel\CatalogSheet;
use App\Services\CatalogExcel\CategorySheet;
use App\Services\CatalogExcel\ProducerSheet;
use App\Services\CatalogExcel\ProductSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;
use ZipArchive;

class CatalogExcelTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->buildMarketplace();
        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
    }

    /** Writes rows (by header) into a fresh template and saves it as an .xlsx. */
    private function filled(CatalogSheet $sheet, array $rows, ?string $title = null): string
    {
        $book = $sheet->workbook(false);
        $this->fillSheet($book, $title ?? $sheet->sheetTitle(), $rows);
        $path = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }

    private function fillSheet(Spreadsheet $book, string $title, array $rows): void
    {
        $ws = $book->getSheetByName($title);
        $headers = [];
        foreach ($ws->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
            $headers[CatalogSheet::key((string) $cell->getValue())] = $cell->getColumn();
        }
        foreach ($rows as $i => $row) {
            foreach ($row as $key => $value) {
                $ws->setCellValue($headers[$key].($i + 3), $value);
            }
        }
    }

    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zp').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name) {
            $zip->addFromString('photos/'.$name, UploadedFile::fake()->image($name, 400, 400)->getContent());
        }
        $zip->close();

        return $path;
    }

    public function test_product_template_has_live_dropdowns_and_a_read_me(): void
    {
        $book = (new ProductSheet)->workbook(false);

        $this->assertSame(['Read me', 'Products', 'Required by category', 'Lists'], $book->getSheetNames());
        $lists = $book->getSheetByName('Lists');
        $this->assertSame('hidden', $lists->getSheetState());
        $producers = array_column($lists->rangeToArray('A2:A200'), 0);
        $this->assertContains(Producer::active()->orderBy('name')->value('name'), $producers);
        $this->assertStringContainsString("'Lists'!", $book->getSheetByName('Products')->getDataValidation('C2')->getFormula1());
        $this->assertSame('Product name *', $book->getSheetByName('Products')->getCell('B1')->getValue());

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)->call('downloadTemplate')->assertFileDownloaded('products-template.xlsx');
    }

    public function test_complete_rows_go_live_incomplete_wait_and_bad_rows_are_reported(): void
    {
        $producer = Producer::create(['name' => 'Shamba Pharma', 'code' => 'BR-SHP']);
        $base = [
            'producer' => 'Shamba Pharma',
            'category' => 'C3020 · anything after the dot is ignored',
            'main_animal' => 'AN-CHK · Poultry › Chicken',
            'also_for_animal_2' => 'AN-DCT',
            'pack_size' => '100 ml', 'selling_unit' => 'bottle', 'other_pack_sizes' => '500 ml; 1 L',
            'form' => 'Liquid', 'active_ingredients' => 'Albendazole 10%', 'dosage_use_rate' => '1 ml per 10 kg',
            'how_it_is_given_1' => 'oral_drench', 'meat_withdrawal_days' => 14, 'egg_withdrawal_days' => 7, 'milk_withdrawal_days' => 3,
            'tvla_registration_no' => 'TVLA/1', 'front_photo' => 'albenda.jpg',
        ];
        $path = $this->filled(new ProductSheet, [
            ['product_name' => 'Albenda Plus'] + $base,
            ['product_name' => 'Albenda Lite', 'front_photo' => ''] + $base,
            ['product_name' => 'Mystery Tonic', 'producer' => 'Nobody Ltd'] + $base,
            ['product_name' => 'Bad Days', 'meat_withdrawal_days' => 'soon'] + $base,
        ]);

        $result = (new ProductSheet)->import($path, $this->zip(['albenda.jpg']), $this->admin)->toArray();

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['photos']);
        $plus = Product::where('name', 'Albenda Plus')->sole();
        $this->assertTrue($plus->isApproved());
        $this->assertSame($producer->id, $plus->producer_id);
        $this->assertNotNull($plus->code);
        $this->assertSame(['oral_drench'], $plus->administration_route);
        $this->assertEqualsCanonicalizing(['AN-CHK', 'AN-DCT'], $plus->animalTypes->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['100 ml', '500 ml', '1 L'], $plus->variants->pluck('name')->all());
        $this->assertNotNull($plus->image());

        $lite = Product::where('name', 'Albenda Lite')->sole();
        $this->assertTrue($lite->isPending());
        $this->assertStringContainsString('Front photo', implode(' ', $result['warnings'][4]));

        $this->assertStringContainsString('Nobody Ltd', implode(' ', $result['errors'][5]));
        $this->assertStringContainsString('whole number', implode(' ', $result['errors'][6]));
        $this->assertFalse(Product::where('name', 'Mystery Tonic')->exists());
    }

    public function test_downloaded_products_update_in_place_when_uploaded_again(): void
    {
        $product = Product::approved()->firstOrFail();
        $book = (new ProductSheet)->workbook(true);
        $ws = $book->getSheetByName('Products');
        $row = collect(range(2, 400))->first(fn ($r) => $ws->getCell("A{$r}")->getValue() === $product->code);
        $ws->setCellValue("U{$row}", 'Mix 1 kg per 100 kg feed');
        $path = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);
        $before = Product::count();

        $result = (new ProductSheet)->import($path, null, $this->admin)->toArray();

        $this->assertSame($before, Product::count());
        $this->assertSame(0, $result['created']);
        $this->assertSame('Mix 1 kg per 100 kg feed', $product->fresh()->inclusion_or_dosage);
        $this->assertSame([], $result['errors']);
    }

    public function test_producers_upload_with_logos_and_update_by_code(): void
    {
        $existing = Producer::create(['name' => 'Kibo Feeds', 'code' => 'BR-KIB']);
        $path = $this->filled(new ProducerSheet, [
            ['name' => 'Nyota Vet', 'country' => 'Kenya', 'what_they_make' => 'Manufacturer (vaccines)', 'logo' => 'nyota.png', 'on_marketplace' => 'Yes'],
            ['code' => 'BR-KIB', 'name' => 'Kibo Feeds Ltd', 'hatchery_chicks' => 'Chicks and feed', 'active' => 'No'],
            ['name' => 'Broken', 'website' => 'not a link'],
        ]);

        Livewire::actingAs($this->admin)->test(ProducersIndex::class)
            ->set('excelFile', UploadedFile::fake()->createWithContent('producers.xlsx', file_get_contents($path)))
            ->set('excelZip', UploadedFile::fake()->createWithContent('logos.zip', file_get_contents($this->zip(['nyota.png']))))
            ->call('importExcel')
            ->assertSet('excelResult.created', 1)
            ->assertSet('excelResult.updated', 1)
            ->assertSee('Not saved');

        $nyota = Producer::where('name', 'Nyota Vet')->sole();
        $this->assertNotNull($nyota->logoUrl());
        $this->assertStringStartsWith('BR-NYO', $nyota->code);
        $this->assertSame('Kibo Feeds Ltd', $existing->fresh()->name);
        $this->assertSame(Producer::DEALS_CHICKS_FEED, $existing->fresh()->deals_in);
        $this->assertFalse($existing->fresh()->is_active);

        Livewire::actingAs($this->admin)->test(ProducersIndex::class)->call('downloadAll')->assertFileDownloaded();
    }

    public function test_categories_upload_creates_coded_subcategories(): void
    {
        $path = $this->filled(new CategorySheet, [
            ['name' => 'Bee keeping'],
            ['name' => 'Hives', 'main_category' => 'Bee keeping'],
            ['name' => 'Calcium supplements', 'main_category' => 'C20 · Supplements & additives'],
        ]);

        Livewire::actingAs($this->admin)->test(CategoriesIndex::class)
            ->set('excelFile', UploadedFile::fake()->createWithContent('c.xlsx', file_get_contents($path)))
            ->call('importExcel')
            ->assertSet('excelResult.created', 3);

        $bees = ProductCategory::where('name', 'Bee keeping')->sole();
        $this->assertNull($bees->parent_id);
        $this->assertSame($bees->id, ProductCategory::where('name', 'Hives')->value('parent_id'));
        $this->assertStringStartsWith('C20', ProductCategory::where('name', 'Calcium supplements')->value('code'));
    }

    public function test_animal_types_and_growth_stages_upload(): void
    {
        $sheet = new AnimalTypeSheet;
        $book = $sheet->workbook(false);
        $this->fillSheet($book, 'Animal types', [['name' => 'Bees'], ['name' => 'Honey bees', 'main_animal' => 'Bees']]);
        $this->fillSheet($book, AnimalTypeSheet::STAGES, [['name' => 'Swarm', 'animal' => 'Bees']]);
        $path = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        Livewire::actingAs($this->admin)->test(AnimalTypesIndex::class)
            ->set('excelFile', UploadedFile::fake()->createWithContent('a.xlsx', file_get_contents($path)))
            ->call('importExcel')
            ->assertSet('excelResult.created', 3);

        $bees = AnimalType::where('name', 'Bees')->sole();
        $this->assertSame($bees->id, AnimalType::where('name', 'Honey bees')->value('parent_id'));
        $this->assertSame($bees->id, LifeStage::where('name', 'Swarm')->value('animal_type_id'));
    }
}
