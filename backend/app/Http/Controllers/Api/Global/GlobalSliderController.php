<?php
namespace App\Http\Controllers\Api\Global;
use App\Http\Controllers\Controller;
use App\Models\Global\GlobalSlider;

class GlobalSliderController extends Controller
{
    public function index() {
        $sliders = GlobalSlider::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id','title','subtitle','image_url','link_url','bg_color','button_text']);
        return response()->json(['sliders' => $sliders]);
    }
}
