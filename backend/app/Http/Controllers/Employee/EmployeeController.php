<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;

class EmployeeController extends Controller
{
    private static array $icons = [
        'efood'      => 'fa-utensils',
        'eshop'      => 'fa-shopping-bag',
        'elaundry'   => 'fa-tshirt',
        'emoving'    => 'fa-truck-moving',
        'eparcel'    => 'fa-box',
        'edata'      => 'fa-wifi',
        'eexchange'  => 'fa-exchange-alt',
        'ehealth'    => 'fa-user-md',
        'erent'      => 'fa-home',
        'ewholesale' => 'fa-warehouse',
        'egrocery'   => 'fa-carrot',
        'eticket'    => 'fa-plane',
        'elearning'  => 'fa-graduation-cap',
    ];

    public function dashboard()
    {
        $user = auth()->user();

        $modules = $user->managedModules()->orderBy('sort_order')->get()->map(fn($m) => [
            'name' => $m->name,
            'slug' => $m->slug,
            'icon' => self::$icons[$m->slug] ?? 'fa-th-large',
        ]);

        return view('employee.dashboard', compact('user', 'modules'));
    }
}
