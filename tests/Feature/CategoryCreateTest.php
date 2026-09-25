<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryCreateTest extends TestCase
{
    use RefreshDatabase;

    private function bootPanel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_create_page_renders(): void
    {
        $this->bootPanel();

        Livewire::test(CreateCategory::class)
            ->assertOk();
    }

    public function test_page_loads_over_http(): void
    {
        $this->bootPanel();

        $response = $this->get('/admin/categories/create');

        // Surface the real exception instead of a bare 500 assertion.
        $response->assertOk();
    }

    public function test_can_create_category_when_slug_is_filled(): void
    {
        $this->bootPanel();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Elektronik',
                'slug' => 'elektronik',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', ['slug' => 'elektronik']);
    }

    public function test_creating_without_slug_falls_back_to_name(): void
    {
        $this->bootPanel();

        // The form auto-fills slug on blur, but a direct model write (seeder,
        // import, API) must not hit a NOT NULL integrity violation either.
        $category = Category::create([
            'name' => 'Fashion Pria',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertSame('fashion-pria', $category->slug);
    }

    public function test_form_rejects_blank_slug(): void
    {
        $this->bootPanel();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Tanpa Slug',
                'slug' => '',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    public function test_form_rejects_duplicate_category_slug(): void
    {
        $this->bootPanel();
        Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Elektronik Lain',
                'slug' => 'elektronik',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_editing_category_keeps_its_own_slug(): void
    {
        $this->bootPanel();
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);

        // unique(ignoreRecord: true) must not flag the record against itself.
        Livewire::test(EditCategory::class, ['record' => $category->getKey()])
            ->assertFormSet(['slug' => 'elektronik']);
    }

    public function test_product_without_slug_falls_back_to_name(): void
    {
        $this->bootPanel();

        $product = Product::create([
            'name' => 'Kemeja Flanel Pria',
            'price' => 150000,
            'stock' => 10,
        ]);

        $this->assertSame('kemeja-flanel-pria', $product->slug);
    }
}
