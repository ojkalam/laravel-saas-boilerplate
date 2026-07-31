<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deterministic demo catalog so a fresh clone has something to browse.
 * Release files are placeholder zips written to the private disk.
 */
class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            'Ecommerce', 'Marketing', 'Analytics', 'Productivity',
        ])->mapWithKeys(fn (string $name) => [
            $name => ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            ),
        ]);

        $catalog = [
            [
                'name' => 'Aurora Storefront',
                'type' => ProductType::Theme,
                'category' => 'Ecommerce',
                'price' => 4900,
                'featured' => true,
                'summary' => 'A fast, accessible storefront theme with a built-in checkout flow.',
                'versions' => ['1.0.0' => 'Initial release.', '1.2.0' => 'Faster cart, dark mode.'],
            ],
            [
                'name' => 'Nimbus Admin',
                'type' => ProductType::Theme,
                'category' => 'Analytics',
                'price' => 6900,
                'featured' => true,
                'summary' => 'Dashboard theme with 30+ chart layouts.',
                'versions' => ['2.0.0' => 'Rebuilt on Tailwind 4.'],
            ],
            [
                'name' => 'Beacon Newsletter',
                'type' => ProductType::App,
                'category' => 'Marketing',
                'price' => 2900,
                'summary' => 'Capture subscribers and send campaigns without leaving your app.',
                'versions' => ['1.1.0' => 'Double opt-in support.'],
            ],
            [
                'name' => 'Ledger Invoices',
                'type' => ProductType::App,
                'category' => 'Productivity',
                'price' => 3900,
                'summary' => 'Invoicing, reminders, and tax summaries.',
                'versions' => ['1.0.0' => 'Initial release.'],
            ],
            [
                'name' => 'Pulse Starter',
                'type' => ProductType::Theme,
                'category' => 'Productivity',
                'price' => 0,
                'summary' => 'A free starter theme to try the marketplace end to end.',
                'versions' => ['1.0.0' => 'Initial release.'],
            ],
        ];

        $disk = Storage::disk(config('marketplace.releases_disk'));

        foreach ($catalog as $entry) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug($entry['name'])],
                [
                    'category_id' => $categories[$entry['category']]->id,
                    'type' => $entry['type'],
                    'name' => $entry['name'],
                    'summary' => $entry['summary'],
                    'description' => $entry['summary']."\n\n"
                        .'Includes twelve months of updates and email support. '
                        .'Download the latest release any time from your purchases page.',
                    'price' => $entry['price'],
                    'status' => ProductStatus::Published,
                    'featured' => $entry['featured'] ?? false,
                ],
            );

            foreach ($entry['versions'] as $version => $changelog) {
                $path = 'releases/'.$product->slug.'-'.$version.'.zip';

                if (! $disk->exists($path)) {
                    $disk->put($path, 'Placeholder release for '.$product->name.' v'.$version);
                }

                $product->versions()->updateOrCreate(
                    ['version' => $version],
                    [
                        'changelog' => $changelog,
                        'file_path' => $path,
                        'file_size' => $disk->size($path),
                        'released_at' => now()->subDays(array_search($version, array_keys($entry['versions']), true) * 30),
                    ],
                );
            }
        }
    }
}
