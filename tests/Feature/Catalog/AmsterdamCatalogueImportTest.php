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
        // Amsterdam: 14 products, 27 packs, 18 photos. Silverlands: 8 products, 12 packs, 6 photos.
        // RIC Agrovet: 10 products, 20 packs, 10 photos (and 2 producers, RIC Agrovet and Medisel).
        $this->assertSame(['producers' => 6, 'products' => 34, 'product_variants' => 61, 'photos_added' => 35], $counts);

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

        // Silverlands' range keeps its maker; its workbook duplicates are updated, not repeated.
        $starter = Product::where('code', 'P001')->sole();
        $this->assertSame('Broiler Starter Crumble', $starter->name);
        $this->assertSame('Silverlands', $starter->brand);
        $this->assertSame('silverlands-broiler-starter-crumble.jpg', $starter->image()->getCustomProperty('catalogue_file'));
        $this->assertSame(['P001-25KG', 'P001-50KG'], $starter->variants()->orderBy('sort_order')->pluck('sku')->all());
        $this->assertSame(1, Product::where('name', 'Complete Layer Mash')->count());
        $this->assertSame('Silverlands', Product::where('code', 'P129')->value('brand'));
        $this->assertNull(Product::where('code', 'P126')->sole()->image()); // no photo on their site

        // Re-running changes nothing and adds no second photo.
        $again = app(AmsterdamCatalogueImporter::class)->import();
        $this->assertSame(0, $again['photos_added']);
        $this->assertSame(140, Product::count());
        $this->assertSame(191, ProductVariant::count());

        $this->get(route('market.product', $alpha->slug))->assertOk();
    }

    public function test_ric_agrovet_products_keep_their_maker_logos_and_prescription_flags(): void
    {
        app(CatalogTaxonomyImporter::class)->import();
        app(AmsterdamCatalogueImporter::class)->import();

        $ric = Producer::where('code', 'BR-RIC')->sole();
        $medisel = Producer::where('code', 'BR-MEDISEL')->sole();
        $this->assertNotNull($ric->logoUrl());
        $this->assertNotNull($medisel->logoUrl());

        // Shown under their own makers, not under the Amsterdam Group catalogue owner.
        $this->assertSame(9, Product::where('producer_id', $ric->id)->count());
        $petD = Product::where('code', 'P136')->sole();
        $this->assertSame($medisel->id, $petD->producer_id);
        $this->assertNotNull($petD->image());

        $this->assertTrue(Product::where('code', 'P131')->sole()->prescription_required);
        $this->assertTrue(Product::where('code', 'P133')->sole()->prescription_required);

        // What each medicine treats, how to give it and what is in it.
        $ampro = Product::where('code', 'P133')->sole();
        $this->assertSame(['coccidiosis'], $ampro->use_cases);
        $this->assertSame(['drinking_water'], $ampro->administration_route);
        $this->assertSame('Amprolium HCl 20%', $ampro->active_ingredients);
        $this->assertSame(['10% · 100 ml bottle', '10% · 250 ml bottle', '20% · 100 ml bottle'],
            Product::where('code', 'P131')->sole()->variants()->orderBy('sort_order')->pluck('name')->all());

        // A pack of unknown size doesn't change the product's name.
        $this->assertSame('RIC Neoxy Plus', Product::where('code', 'P130')->sole()->displayName());

        // Running again changes nothing (same files, same photos and logos).
        $again = app(AmsterdamCatalogueImporter::class)->import();
        $this->assertSame(0, $again['photos_added']);
        $this->assertSame(1, $ric->fresh()->getMedia('logo')->count());
    }
}
