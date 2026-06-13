<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
class Setting extends Model {
    protected $fillable = ['key','value','type','group'];
    public static function get(string $key, mixed $default = null): mixed {
        return Cache::remember("setting_{$key}", 3600, fn() => static::where('key',$key)->value('value') ?? $default);
    }
    public static function set(string $key, mixed $value): void {
        static::updateOrCreate(['key'=>$key],['value'=>$value]);
        Cache::forget("setting_{$key}");
    }
    public function getValueAttribute($val): mixed {
        return match($this->type) { 'boolean'=>(bool)$val, 'integer'=>(int)$val, 'decimal'=>(float)$val, 'json'=>json_decode($val,true), default=>$val };
    }
}
