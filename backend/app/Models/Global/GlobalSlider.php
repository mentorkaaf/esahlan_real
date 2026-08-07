<?php
namespace App\Models\Global;
use Illuminate\Database\Eloquent\Model;
class GlobalSlider extends Model {
    protected $fillable = ['title','subtitle','image_url','link_url','bg_color','button_text','sort_order','is_active'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
