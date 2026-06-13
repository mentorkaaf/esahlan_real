<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'avatar'             => $this->avatar_url,
            'role'               => $this->role?->slug,
            'role_name'          => $this->role?->name,
            'status'             => $this->status,
            'phone_verified'     => (bool)$this->phone_verified_at,
            'preferred_language' => $this->preferred_language,
            'dark_mode'          => (bool)$this->dark_mode,
            'referral_code'      => $this->referral_code,
            'district'           => $this->whenLoaded('district', fn() => [
                'id'   => $this->district->id,
                'name' => $this->district->name,
            ]),
            'wallet_balance'     => $this->when(
                $this->relationLoaded('wallet'),
                fn() => $this->wallet?->balance ?? 0
            ),
            'loyalty_points'     => $this->when(
                isset($this->loyalty_points_balance),
                fn() => $this->loyalty_points_balance
            ),
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}
