<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;

class EmployeeController extends Controller
{
    /** module slug => [route name, font-awesome icon]. */
    public static array $moduleRoutes = [
        'efood'      => ['admin.module-data.efood.index', 'fa-utensils'],
        'eshop'      => ['admin.eshop.index',             'fa-shopping-bag'],
        'elaundry'   => ['admin.module-data.laundry',     'fa-tshirt'],
        'emoving'    => ['admin.module-data.moving',      'fa-truck-moving'],
        'eparcel'    => ['admin.module-data.parcel',      'fa-box'],
        'edata'      => ['admin.module-data.data',        'fa-wifi'],
        'eexchange'  => ['admin.module-data.exchange',    'fa-exchange-alt'],
        'ehealth'    => ['admin.module-data.health',      'fa-user-md'],
        'erent'      => ['admin.module-data.rent',        'fa-home'],
        'ewholesale' => ['admin.module-data.wholesale',   'fa-warehouse'],
        'egrocery'   => ['admin.module-data.grocery',     'fa-carrot'],
        'eticket'    => ['admin.module-data.ticket',      'fa-plane'],
        'elearning'  => ['admin.elearning.dashboard',     'fa-graduation-cap'],
    ];

    public function dashboard()
    {
        $user = auth()->user();

        $modules = $user->managedModules()->orderBy('sort_order')->get()->map(function ($m) {
            $cfg = self::$moduleRoutes[$m->slug] ?? null;
            return [
                'name' => $m->name,
                'slug' => $m->slug,
                'icon' => $cfg[1] ?? 'fa-th-large',
                'url'  => $cfg && \Illuminate\Support\Facades\Route::has($cfg[0])
                            ? route($cfg[0]) : null,
            ];
        });

        return view('employee.dashboard', compact('user', 'modules'));
    }
}
