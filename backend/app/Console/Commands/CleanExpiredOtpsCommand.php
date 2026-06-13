<?php
namespace App\Console\Commands;
use App\Models\OtpCode;
use Illuminate\Console\Command;
class CleanExpiredOtpsCommand extends Command {
    protected $signature = 'esahlan:clean-expired-otps';
    protected $description = 'Remove expired OTP codes';
    public function handle(): void {
        $deleted = OtpCode::where('expires_at','<',now())->delete();
        $this->info("Deleted {$deleted} expired OTPs.");
    }
}
