<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['role'])
            ->when($request->role, fn($q) => $q->whereHas('role', fn($r) => $r->where('slug', $request->role)))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($s) =>
                $s->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->latest();

        $users = $query->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['role', 'wallet', 'orders' => fn($q) => $q->latest()->limit(10)]);
        return view('admin.users.show', compact('user'));
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate(['status' => 'required|in:active,inactive,banned']);
        $user->update(['status' => $request->status]);
        return back()->with('success', 'User status updated.');
    }

    public function destroy(User $user)
    {
        if ($user->role?->slug === 'super_admin') {
            return back()->with('error', 'Cannot delete super admin.');
        }
        $user->delete();
        return back()->with('success', 'User deleted.');
    }

    // API methods for datatables
    public function apiIndex(Request $request)
    {
        $users = User::with('role')
            ->when($request->role, fn($q) => $q->whereHas('role', fn($r) => $r->where('slug', $request->role)))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($s) =>
                $s->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
            ))
            ->paginate($request->per_page ?? 15);

        return $this->paginated($users, fn($u) => [
            'id'     => $u->id,
            'name'   => $u->name,
            'phone'  => $u->phone,
            'email'  => $u->email,
            'role'   => $u->role?->name,
            'status' => $u->status,
            'created_at' => $u->created_at->toDateString(),
        ]);
    }
}
