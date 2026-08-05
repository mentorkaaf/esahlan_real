<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AdminGlobalHelpCenterController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('global_support_tickets')
            ->leftJoin('global_users', 'global_support_tickets.global_user_id', '=', 'global_users.id')
            ->select('global_support_tickets.*', 'global_users.name as user_name');

        if ($request->filled('status'))   { $query->where('global_support_tickets.status', $request->status); }
        if ($request->filled('priority')) { $query->where('priority', $request->priority); }
        if ($request->filled('category')) { $query->where('category', $request->category); }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('ticket_number','like',"%$s%")->orWhere('email','like',"%$s%")->orWhere('subject','like',"%$s%"));
        }

        $tickets = $query->orderByDesc('global_support_tickets.created_at')->paginate(20)->withQueryString();

        $stats = [
            'open'        => DB::table('global_support_tickets')->where('status','open')->count(),
            'in_progress' => DB::table('global_support_tickets')->where('status','in_progress')->count(),
            'urgent'      => DB::table('global_support_tickets')->where('priority','urgent')->where('status','!=','closed')->count(),
            'resolved'    => DB::table('global_support_tickets')->where('status','resolved')->count(),
        ];

        return view('admin.global.help-center.index', compact('tickets', 'stats'));
    }

    public function show($id)
    {
        $ticket  = DB::table('global_support_tickets')->where('id', $id)->firstOrFail();
        $replies = DB::table('global_support_replies')->where('ticket_id', $id)->orderBy('created_at')->get();
        return view('admin.global.help-center.show', compact('ticket', 'replies'));
    }

    public function reply(Request $request, $id)
    {
        $request->validate(['message' => 'required|string']);
        $ticket = DB::table('global_support_tickets')->where('id', $id)->firstOrFail();

        DB::table('global_support_replies')->insert([
            'ticket_id'  => $id,
            'message'    => $request->message,
            'is_admin'   => true,
            'admin_name' => auth()->user()->name ?? 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('global_support_tickets')->where('id', $id)->update([
            'status'     => 'in_progress',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Reply sent.');
    }

    public function close($id)
    {
        DB::table('global_support_tickets')->where('id', $id)->update([
            'status'      => 'closed',
            'resolved_at' => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Ticket closed.');
    }
}
