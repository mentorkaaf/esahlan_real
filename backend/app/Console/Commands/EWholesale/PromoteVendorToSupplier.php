<?php

namespace App\Console\Commands\EWholesale;

use App\Models\EWholesale\EWSupplier;
use App\Models\Vendor;
use Illuminate\Console\Command;

class PromoteVendorToSupplier extends Command
{
    protected $signature   = 'ewholesale:promote-vendor {vendor_id} {--name=} {--verification=unverified}';
    protected $description = 'Promote an existing vendor to a wholesale supplier';

    public function handle(): int
    {
        $vendor = Vendor::find($this->argument('vendor_id'));

        if (!$vendor) {
            $this->error("Vendor #{$this->argument('vendor_id')} not found.");
            return 1;
        }

        if (EWSupplier::where('vendor_id', $vendor->id)->exists()) {
            $this->warn("Vendor #{$vendor->id} ({$vendor->name}) is already a wholesale supplier.");
            return 0;
        }

        $supplier = EWSupplier::create([
            'vendor_id'    => $vendor->id,
            'display_name' => $this->option('name') ?: $vendor->name,
            'verification' => $this->option('verification'),
            'logo'         => $vendor->logo ?? null,
            'is_active'    => true,
        ]);

        $this->info("✅ Supplier created: [{$supplier->id}] {$supplier->display_name} (verification: {$supplier->verification})");
        return 0;
    }
}
