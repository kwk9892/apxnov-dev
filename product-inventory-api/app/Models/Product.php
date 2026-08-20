<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class);
    }

    /**
     * Accessor: human-readable stock status derived from the raw stock count.
     */
    protected function stockStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->stock <= 0 => 'out_of_stock',
                $this->stock <= 10 => 'low_stock',
                default => 'in_stock',
            },
        );
    }

    /**
     * Mutator: keep SKU normalized (uppercase, no surrounding whitespace) regardless
     * of how callers submit it.
     */
    protected function sku(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::upper(trim($value)),
        );
    }

    public function scopeCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopePriceBetween(Builder $query, ?float $min, ?float $max): Builder
    {
        if (! is_null($min)) {
            $query->where('price', '>=', $min);
        }

        if (! is_null($max)) {
            $query->where('price', '<=', $max);
        }

        return $query;
    }

    public function scopeStockLevel(Builder $query, string $level): Builder
    {
        return match ($level) {
            'out_of_stock' => $query->where('stock', '<=', 0),
            'low_stock' => $query->whereBetween('stock', [1, 10]),
            'in_stock' => $query->where('stock', '>', 10),
            default => $query,
        };
    }
}
