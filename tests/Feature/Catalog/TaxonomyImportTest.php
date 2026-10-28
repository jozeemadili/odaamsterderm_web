<?php

namespace Tests\Feature\Catalog;

use App\Models\AnimalType;
use App\Models\InventoryItem;
use App\Models\LifeStage;
use App\Models\Merchant;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SearchKeyword;
use App\Services\CatalogTaxonomyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_the_whole_taxonomy_from_the_workbook(): void
    {
        $this->artisan('catalog:import-taxonomy')->assertSuccessful();

        $this->assertSame(14, AnimalType::count());
        $this->assertSame(26, LifeStage::count());
        $this->assertSame(38, ProductCategory::count());
        $this->assertSame(44, ProductAttribute::count());
        $this->assertSame(118, ProductAttributeValue::count());
        $this->assertSame(75, SearchKeyword::count());

        $dairy = AnimalType::where('code', 'AN-DCT')->first();
        $this->assertSame('cattle', $dairy->parent->slug);
        $this->assertSame("Ng'ombe wa maziwa", $dairy->name_sw);

        $completeFeed = ProductCategory::where('code', 'C1010')->first();
        $this->assertSame('C10', $completeFeed->parent->code);
        $this->assertSame('Chakula kamili', $completeFeed->name_sw);
        $this->assertSame(['life_stages', 'crude_protein_pct', 'form'], $completeFeed->required_attributes);

        $pigGrower = LifeStage::where('code', 'LS-PIG-GRW')->first();
        $this->assertSame('pigs', $pigGrower->animalType->slug);
        $this->assertNull(LifeStage::where('code', 'LS-ALL')->value('animal_type_id'));

        $form = ProductAttribute::where('key', 'form')->first();
        $this->assertTrue($form->values->contains('code', 'pellets'));
        $this->assertTrue(ProductAttribute::where('key', 'animal_types')->value('is_multi_select'));

        $this->assertDatabaseHas('search_keywords', ['term' => 'kideri', 'target_type' => 'disease', 'target_code' => 'newcastle']);
    }

    public function test_command_imports_brands_and_products_with_their_attributes(): void
    {
        $this->artisan('catalog:import-taxonomy')->assertSuccessful();

        $this->assertSame(24, Producer::count());
        $this->assertSame(115, Product::count());
        $this->assertSame(115, Product::approved()->count());

        $afya = Producer::where('code', 'BR-AFM')->first();
        $this->assertSame('India', $afya->country);
        $this->assertTrue($afya->is_demo);
        $this->assertFalse(Producer::where('code', 'BR-SIL')->value('is_demo'));

        $albendazole = Product::where('code', 'P052')->first();
        $this->assertSame('Albendazole 10% Oral Suspension', $albendazole->name);
        $this->assertSame('C3020', $albendazole->category->code);
        $this->assertSame($afya->id, $albendazole->producer_id);
        $this->assertSame('Afya Mifugo Pharma (demo)', $albendazole->brand);
        $this->assertSame('AN-CAT', $albendazole->animalType->code);
        $this->assertEqualsCanonicalizing(['AN-CAT', 'AN-GTS'], $albendazole->animalTypes->pluck('code')->all());
        $this->assertSame(['LS-ALL'], $albendazole->lifeStages->pluck('code')->all());
        $this->assertSame(['oral_drench'], $albendazole->administration_route);
        $this->assertSame(14, $albendazole->withdrawal_meat_days);
        $this->assertSame(4, $albendazole->withdrawal_milk_days);
        $this->assertFalse($albendazole->prescription_required);
        $this->assertStringContainsString('minyoo', $albendazole->search_keywords);
        $this->assertTrue($albendazole->is_demo);
        // Two pack sizes: they live on the variants.
        $this->assertNull($albendazole->pack_size);
        $this->assertSame('bottle', $albendazole->unit);
        $oneLitre = $albendazole->variants->firstWhere('sku', 'P052-1L');
        $this->assertSame('L', $oneLitre->pack_size_unit);
        $this->assertSame('bottle', $oneLitre->selling_unit);
        $this->assertNotNull($oneLitre->reference_price);
        $this->assertSame(150, ProductVariant::count());
        $this->assertSame(50, ProductVariant::where('selling_unit', 'bird')->value('min_order_qty'));

        $newcastle = Product::where('code', 'P063')->first();
        $this->assertTrue($newcastle->prescription_required);
        $this->assertTrue($newcastle->cold_chain_required);
        $this->assertSame(['newcastle'], $newcastle->disease_target);
        $this->assertSame(['drinking_water', 'eye_nasal_drop'], $newcastle->administration_route);
        $this->assertSame('vaccine_biological', $newcastle->regulatory_class);
        $this->assertArrayHasKey('Cold chain', $newcastle->detailsForBuyers());

        // A single variant gives the product its pack size.
        $chickMash = Product::where('code', 'P004')->first();
        $this->assertNotNull($chickMash->pack_size);
        $this->assertNull($chickMash->administration_route);
        $this->assertEquals(20, $chickMash->crude_protein_pct);
    }

    public function test_original_catalogue_products_take_over_their_workbook_product(): void
    {
        $importer = app(CatalogTaxonomyImporter::class);
        $importer->import(products: false);
        $chicken = AnimalType::where('code', 'AN-CHK')->first();
        $cattle = AnimalType::where('code', 'AN-CAT')->first();
        $completeFeed = ProductCategory::where('code', 'C1010')->value('id');
        $medicines = ProductCategory::where('code', 'C30')->value('id');

        $grower = Product::create([
            'name' => 'Chicken Grower', 'pack_size' => '25KG', 'unit' => 'bag', 'status' => Product::STATUS_APPROVED,
            'animal_type_id' => $chicken->id, 'product_category_id' => $completeFeed,
            'producer_id' => Producer::create(['name' => 'Silverlands'])->id, // live row, no code yet
        ]);
        $grower->syncDefaultVariant(); // as the variants migration does for existing products
        $merchant = Merchant::create(['business_name' => 'Amsterdam Group', 'owner_name' => 'Owner', 'phone' => '+255700000001']);
        $growerStock = InventoryItem::create(['merchant_id' => $merchant->id, 'name' => 'Chicken Grower 25KG', 'product_id' => $grower->id]);
        $this->assertNotNull($growerStock->product_variant_id);

        $albendazole = Product::create([
            'name' => 'Albendazole Dewormer', 'pack_size' => '1L', 'unit' => 'bottle', 'status' => Product::STATUS_APPROVED,
            'animal_type_id' => $cattle->id, 'product_category_id' => $medicines,
        ]);

        $albendazoleStock = InventoryItem::create(['merchant_id' => $merchant->id, 'name' => 'Albendazole', 'product_id' => $albendazole->id]);
        $this->assertNull($albendazoleStock->product_variant_id); // no pack recorded

        $counts = $importer->import();

        $this->assertSame(115, Product::count());
        $this->assertSame(150, ProductVariant::count());
        $this->assertSame(2, $counts['live_products_linked']);

        $grower->refresh();
        $this->assertSame(24, Producer::count());
        $this->assertSame('BR-SIL', $grower->producer->code);
        $this->assertSame('P002', $grower->code);
        $this->assertSame('Broiler Grower Pellets', $grower->name);
        $this->assertFalse($grower->is_demo);
        // Two packs now: the pack size moves to the variants, and the original
        // 25KG pack (with its stock) became SKU P002-25KG.
        $this->assertNull($grower->pack_size);
        $this->assertSame(['P002-25KG', 'P002-50KG'], $grower->variants->pluck('sku')->all());
        $this->assertSame('P002-25KG', $growerStock->fresh()->variant->sku);
        $this->assertSame('Broiler Grower Pellets - 25 kg bag', $growerStock->fresh()->offerName());
        $this->assertSame('P052-1L', $albendazoleStock->fresh()->variant->sku);

        $albendazole->refresh();
        $this->assertSame('P052', $albendazole->code);
        $this->assertSame('C3020', $albendazole->category->code);
        $this->assertEqualsCanonicalizing(['AN-CAT', 'AN-GTS'], $albendazole->animalTypes->pluck('code')->all());

        // Re-running keeps the same rows.
        $importer->import();
        $this->assertSame(115, Product::count());
        $this->assertSame(150, ProductVariant::count());
    }

    public function test_animal_filter_matches_any_tagged_animal_and_its_family(): void
    {
        $this->artisan('catalog:import-taxonomy')->assertSuccessful();
        $codes = fn (string $animal) => Product::forAnimal(AnimalType::where('code', $animal)->value('id'))->pluck('code');

        // Albendazole is tagged Cattle + Goats & Sheep: goat farmers find it too.
        $this->assertContains('P052', $codes('AN-GOT'));
        $this->assertContains('P052', $codes('AN-CAT'));
        $this->assertNotContains('P052', $codes('AN-FSH'));

        // The chick drinker is tagged Poultry, so it shows under Chicken.
        $this->assertContains('P076', $codes('AN-CHK'));
    }

    public function test_reimporting_updates_rows_in_place(): void
    {
        $this->artisan('catalog:import-taxonomy')->assertSuccessful();
        ProductCategory::where('code', 'C3020')->update(['name' => 'Renamed by mistake']);

        $this->artisan('catalog:import-taxonomy')->assertSuccessful();

        $this->assertSame(38, ProductCategory::count());
        $this->assertSame(26, LifeStage::count());
        $this->assertSame('Dewormers', ProductCategory::where('code', 'C3020')->value('name'));
    }

    public function test_products_on_the_original_category_tree_move_to_the_new_one(): void
    {
        $poultry = AnimalType::create(['name' => 'Poultry', 'slug' => 'poultry']);
        $chicken = AnimalType::create(['name' => 'Chicken', 'slug' => 'chicken', 'parent_id' => $poultry->id]);
        $pigs = AnimalType::create(['name' => 'Pigs', 'slug' => 'pigs']);
        $feed = ProductCategory::create(['name' => 'Feed', 'slug' => 'feed']);
        $grower = ProductCategory::create(['name' => 'Grower', 'slug' => 'feed-grower', 'parent_id' => $feed->id]);
        $medicines = ProductCategory::create(['name' => 'Medicines', 'slug' => 'medicines']);
        $silverlands = Producer::create(['name' => 'Silverlands']);

        $product = fn (string $name, string $pack, AnimalType $animal, ProductCategory $category, ?Producer $producer = null) => Product::create([
            'name' => $name, 'pack_size' => $pack, 'slug' => str($name.' '.$pack)->slug(), 'unit' => 'bag',
            'animal_type_id' => $animal->id, 'product_category_id' => $category->id, 'producer_id' => $producer?->id,
            'status' => Product::STATUS_APPROVED,
        ]);
        $chickenGrower = $product('Chicken Grower', '25KG', $chicken, $grower, $silverlands);
        $sellerPigGrower = $product('Mkulima Pig Grower', '50KG', $pigs, $grower);
        $albendazole = $product('Albendazole Dewormer', '1L', AnimalType::create(['name' => 'Cattle', 'slug' => 'cattle']), $medicines);

        $counts = app(CatalogTaxonomyImporter::class)->import();

        $this->assertSame(3, $counts['products_relinked']);

        // Mapped through the workbook's Map_Live_Products row.
        $this->assertSame('C1010', $chickenGrower->fresh()->category->code);
        $this->assertSame(['LS-POU-GRW'], $chickenGrower->lifeStages->pluck('code')->all());
        $this->assertSame('C3020', $albendazole->fresh()->category->code);

        // Not in the map: the general rule picks the pig grower stage, not the chicken one.
        $this->assertSame('C1010', $sellerPigGrower->fresh()->category->code);
        $this->assertSame(['LS-PIG-GRW'], $sellerPigGrower->lifeStages->pluck('code')->all());

        // Original rows carry on under their codes; the retired stage category is gone.
        $this->assertSame('C10', $feed->fresh()->code);
        $this->assertSame('Feed', $feed->fresh()->name);
        $this->assertSame('C30', $medicines->fresh()->code);
        $this->assertNull($grower->fresh());
        $this->assertSame('AN-CHK', $chicken->fresh()->code);
    }
}
