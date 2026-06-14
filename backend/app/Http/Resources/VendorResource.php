<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'slug'                 => $this->slug,
            'description'          => $this->description,
            'logo'                 => $this->logo_url,
            'logo_url'             => $this->logo_url,
            'cover_image'          => $this->cover_image_url,
            'cover_url'            => $this->cover_image_url,
            'cover'                => $this->cover_image_url,
            'phone'                => $this->phone,
            'email'                => $this->email,
            'address'              => $this->address,
            'latitude'             => $this->latitude,
            'longitude'            => $this->longitude,
            'rating'               => round((float)$this->rating, 1),
            'total_reviews'        => $this->total_reviews,
            'reviews_count'        => $this->total_reviews,
            'is_active'            => (bool)$this->is_active,
            'is_featured'          => (bool)$this->is_featured,
            'temporarily_closed'   => (bool)$this->temporarily_closed,
            'is_open'              => $this->isCurrentlyOpen(),
            'module_slug'          => $this->module_slug,
            'min_order_amount'     => $this->minimum_order ?? $this->min_order_amount,
            'minimum_order'        => $this->minimum_order ?? $this->min_order_amount,
            'delivery_fee'         => $this->delivery_fee,
            'delivery_time'        => $this->delivery_time ?? $this->estimated_delivery_time,
            'estimated_delivery_time' => $this->delivery_time ?? $this->estimated_delivery_time,
            'module'               => $this->whenLoaded('module', fn() => [
                'id'   => $this->module->id,
                'name' => $this->module->name,
                'slug' => $this->module->slug,
            ]),
            'district'             => $this->whenLoaded('district', fn() => [
                'id'   => $this->district->id,
                'name' => $this->district->name,
            ]),
            'schedules'            => $this->whenLoaded('schedules'),
            'created_at'           => $this->created_at?->toISOString(),
        ];
    }
}
