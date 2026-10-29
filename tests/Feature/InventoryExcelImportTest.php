<?php

namespace Tests\Feature;

use App\Livewire\Portal\Inventory\Index;
use App\Models\InventoryItem;
use App\Services\InventoryExcelTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class InventoryExcelImportTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    public function test_the_template_offers_the_catalog_as_a_dropdown(): void
    {
        $this->createSeller();

        $book = InventoryExcelTemplate::empty($this->merchant);
        $catalog = $book->getSheetByName(InventoryExcelTemplate::CATALOG_SHEET);

        $this->assertSame(InventoryExcelTemplate::catalogEntries($this->merchant->id)->count() + 1, $catalog->getHighestDataRow());
        $this->assertSame(DataValidation::TYPE_LIST, $book->getSheet(0)->getCell('A5')->getDataValidation()->getType());
    }

    public function test_rows_are_linked_to_the_catalog_and_unknown_products_are_skipped(): void
    {
        $this->createSeller();
        $entry = InventoryExcelTemplate::catalogEntries($this->merchant->id)->first();

        $book = InventoryExcelTemplate::empty($this->merchant);
        $sheet = $book->getSheet(0);
        $sheet->fromArray([$entry['label'], '', '', '', 12, 40000, 46000, 2, '', 'B-7'], null, 'A2', true);
        $sheet->fromArray(['Homemade mix', '', '', '', 5, 100, 200, 0, '', ''], null, 'A3', true);

        $this->actingAs($this->owner);
        $component = Livewire::test(Index::class)
            ->set('importFile', $this->upload($book))
            ->call('importExcel')
            ->assertHasNoErrors();

        $this->assertSame(0, InventoryItem::where('name', 'Homemade mix')->count());
        $item = InventoryItem::where('product_id', $entry['product']->id)->sole();
        $this->assertSame($entry['variant']?->id, $item->product_variant_id);
        $this->assertSame($this->store->id, $item->branch_id);
        $this->assertSame('12.00', (string) $item->quantity_on_hand);
        $this->assertSame('B-7', $item->batch_no);
        $this->assertNotNull($item->category_id);

        // The same product again only adds stock.
        $component->set('importFile', $this->upload($book))->call('importExcel');
        $this->assertSame('24.00', (string) $item->fresh()->quantity_on_hand);
        $this->assertSame(1, InventoryItem::where('product_id', $entry['product']->id)->count());
    }

    private function upload($book): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'inv').'.xlsx';
        (new Xlsx($book))->save($path);

        return UploadedFile::fake()->createWithContent('stock.xlsx', file_get_contents($path));
    }
}
