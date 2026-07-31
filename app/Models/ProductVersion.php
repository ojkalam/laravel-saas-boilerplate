<?php

namespace App\Models;

use Database\Factories\ProductVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A downloadable release of a product. Files live on the private disk
 * and are never served directly — see DownloadController.
 *
 * @property int $id
 * @property int $product_id
 * @property string $version
 * @property string|null $changelog
 * @property string $file_path
 * @property int $file_size
 * @property Carbon|null $released_at
 * @property-read Product $product
 */
#[Fillable(['version', 'changelog', 'file_path', 'file_size', 'released_at'])]
class ProductVersion extends Model
{
    /** @use HasFactory<ProductVersionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, covariant $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function formattedFileSize(): string
    {
        $size = $this->file_size;

        if ($size <= 0) {
            return '—';
        }

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024) {
                return round($size, 1).' '.$unit;
            }

            $size /= 1024;
        }

        return round($size, 1).' TB';
    }
}
