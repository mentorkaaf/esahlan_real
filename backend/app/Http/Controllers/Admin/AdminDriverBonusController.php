<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDriverBonusController extends Controller
{
    public function index()
    {
        $bonus = DB::table('delivery_bonus_settings')->first();
        return view('admin.drivers.bonus', compact('bonus'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'label'        => 'required|string|max:100',
            'bonus_amount' => 'required|numeric|min:0|max:100',
            'start_hour'   => 'required|integer|min:0|max:23',
            'end_hour'     => 'required|integer|min:1|max:24',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:1|max:7',
            'is_active'    => 'nullable|boolean',
            'description'  => 'nullable|string|max:255',
        ]);

        $days = implode(',', $request->input('days_of_week', [1,2,3,4,5,6,7]));

        $existing = DB::table('delivery_bonus_settings')->first();
        $data = [
            'label'        => $request->label,
            'bonus_amount' => $request->bonus_amount,
            'start_hour'   => $request->start_hour,
            'end_hour'     => $request->end_hour,
            'days_of_week' => $days,
            'is_active'    => $request->boolean('is_active'),
            'description'  => $request->description,
            'updated_at'   => now(),
        ];

        if ($existing) {
            DB::table('delivery_bonus_settings')->where('id', $existing->id)->update($data);
        } else {
            DB::table('delivery_bonus_settings')->insert(array_merge($data, ['created_at' => now()]));
        }

        return back()->with('success', 'Bonus settings saved successfully.');
    }
}
