<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'name_so'             => $this->name_so,
            'description'         => $this->description,
            'price'               => $this->price,
            'sale_price'          => $this->sale_price,
            'effective_price'     => $this->sale_price ?? $this->price,
            'discount_percentage' => $this->sale_price
                ? round((($this->price - $this->sale_price) / $this->price) * 100)
                : 0,
            'sku'                 => $this->sku,
            'stock_quantity'      => $this->stock_quantity,
            'is_available'        => (bool)$this->is_available,
            'rating'              => round((float)$this->rating, 1),
            'total_reviews'       => $this->total_reviews,
            'thumbnail'           => $this->thumbnail ? asset('storage/'.$this->thumbnail) : null,
            'images'              => $this->whenLoaded('images', fn() => $this->images->map(fn($img) => [
                'url'        => $img->url,
                'is_primary' => $img->is_primary,
            ])),
            'variants'            => $this->whenLoaded('variants'),
            'addons'              => $this->whenLoaded('addons'),
            'category'            => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'vendor'              => $this->whenLoaded('vendor', fn() => [
                'id'   => $this->vendor->id,
                'name' => $this->vendor->name,
            ]),
            'in_wishlist'         => $this->when(isset($this->in_wishlist), fn() => (bool)$this->in_wishlist),
            'created_at'          => $this->created_at?->toISOString(),
        ];
    }
}
