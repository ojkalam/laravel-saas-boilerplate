<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A marketplace listing — a theme or an app. Catalog data is global
 * (not team-scoped): every team browses the same storefront. What is
 * team-scoped is what a team *bought* (orders, licenses, downloads).
 *
 * @property int $id
 * @property int|null $category_id
 * @property ProductType $type
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property int $price cents; 0 means free
 * @property ProductStatus $status
 * @property bool $featured
 * @property int $downloads_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'category_id', 'type', 'name', 'slug', 'summary',
    'description', 'price', 'status', 'featured',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product')
            ->logOnly(['name', 'price', 'status', 'featured', 'category_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'featured' => 'boolean',
            'price' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, covariant $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ProductVersion, covariant $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    /**
     * @return HasMany<ProductImage, covariant $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * The newest released version, used for "download latest" and the
     * update-check API.
     */
    public function latestVersion(): ?ProductVersion
    {
        return $this->versions()
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    /**
     * A product is only sellable once it has something to deliver.
     */
    public function isPurchasable(): bool
    {
        return $this->isPublished() && $this->versions()->exists();
    }

    public function formattedPrice(): string
    {
        return $this->isFree()
            ? __('Free')
            : '$'.number_format($this->price / 100, 2);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
