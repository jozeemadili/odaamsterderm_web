<?php

namespace Tests\Feature\Catalog;

use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AmsterdamCatalogueImporter;
use App\Services\CatalogTaxonomyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AmsterdamCatalogueImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_needs_the_taxonomy_first(): void
    {
        $this->expectException(RuntimeException::class);

        app(AmsterdamCatalogueImporter::class)->import();
    }

    public function test_catalogue_adds_products_packs_and_photos_without_touching_stock(): void
    {
        app(CatalogTaxonomyImporter::class)->import();

        // Live stock of Vitamix Chick 100 g, photographed by the seller.
        $merchant = Merchant::create(['business_name' => 'Amsterdam Group', 'owner_name' => 'Owner', 'phone' => '+255700000000']);
        $chick = Product::where('code', 'P034')->sole();
        $stock = InventoryItem::create([
            'merchant_id' => $merchant->id, 'product_id' => $chick->id,
            'product_variant_id' => ProductVariant::where('sku', 'P034-100G')->value('id'),
            'name' => 'Vitamix Chick', 'unit_price' => 4000,
        ]);
        $stock->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('image');

        $counts = app(AmsterdamCatalogueImporter::class)->import();

        // A front for each of the 14 products plus the backs of Vitamix Chick/Plus, Clear Heat and the pig concentrate.
        $this->assertSame(['producers' => 3, 'products' => 14, 'product_variants' => 27, 'photos_added' => 18], $counts);

        $chick->refresh();
        $this->assertSame(['P034-30G', 'P034-100G', 'P034-250G', 'P034-1KG'], $chick->variants()->orderBy('sort_order')->pluck('sku')->all());
        $this->assertSame('Drinking water: 5 g per 20 L. Feed: 300 g per tonne.', $chick->inclusion_or_dosage);
        // The catalogue's front and back are what customers see, ahead of the seller's photo.
        $this->assertSame('vitamix-chick.png', $chick->image()->getCustomProperty('catalogue_file'));
        $this->assertSame('vitamix-chick-back.png', $chick->backImage()->getCustomProperty('catalogue_file'));
        $this->assertCount(2, $chick->imageUrls());
        $this->assertStringStartsWith('Essential chick starter premix', $chick->description);
        $this->assertSame('P034-100G', $stock->fresh()->variant->sku);

        $alpha = Product::where('code', 'P121')->sole();
        $this->assertSame('Amsterdam Group', $alpha->producer->name); // shown under the catalogue owner
        $this->assertSame('Netherlands', $alpha->country_of_origin); // made by Selko
        $this->assertSame('C2040', $alpha->category->code);
        $this->assertEqualsCanonicalizing(['AN-POU', 'AN-PIG'], $alpha->animalTypes->pluck('code')->all());
        $this->assertSame(['P121-1KG', 'P121-5KG', 'P121-25KG', 'P121-200KG'], $alpha->variants()->orderBy('sort_order')->pluck('sku')->all());
        $this->assertNull($alpha->pack_size);
        $this->assertNotNull($alpha->image());
        $this->assertFalse($alpha->is_demo);

        $this->assertSame('High Performance Protein Concentrate 20% (Pig)', Product::where('code', 'P018')->value('name'));
        $this->assertSame('50 kg bag', Product::where('code', 'P125')->value('pack_size'));
        $this->assertFalse(Producer::where('code', 'BR-OCP')->value('is_demo'));

        // Re-running changes nothing and adds no second photo.
        $again = app(AmsterdamCatalogueImporter::class)->import();
        $this->assertSame(0, $again['photos_added']);
        $this->assertSame(125, Product::count());
        $this->assertSame(166, ProductVariant::count());

        $this->get(route('market.product', $alpha->slug))->assertOk();
    }
}
