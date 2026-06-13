<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliveryman;
use Illuminate\Http\Request;

class AdminDeliverymanController extends Controller
{
    public function index(Request $request)
    {
        $query = Deliveryman::with(['user', 'district'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->approval, fn($q) => $q->where('is_approved', $request->approval === 'approved'))
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
            ))
            ->latest();

        $deliverymen = $query->paginate(20);
        return view('admin.deliverymen.index', compact('deliverymen'));
    }

    public function show(Deliveryman $deliveryman)
    {
        $deliveryman->load(['user', 'district', 'orders' => fn($q) => $q->latest()->limit(10)]);
        return view('admin.deliverymen.show', compact('deliveryman'));
    }

    public function approve(Deliveryman $deliveryman)
    {
        $deliveryman->update(['is_approved' => true]);
        $deliveryman->user->update(['status' => 'active']);
        return back()->with('success', 'Deliveryman approved.');
    }

    public function reject(Request $request, Deliveryman $deliveryman)
    {
        $deliveryman->update(['is_approved' => false]);
        return back()->with('success', 'Deliveryman rejected.');
    }

    public function toggleBlock(Deliveryman $deliveryman)
    {
        $newStatus = $deliveryman->user->status === 'active' ? 'banned' : 'active';
        $deliveryman->user->update(['status' => $newStatus]);
        return back()->with('success', 'Deliveryman status updated.');
    }
}
