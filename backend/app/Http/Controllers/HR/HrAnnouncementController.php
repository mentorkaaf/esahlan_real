<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAnnouncement;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrEmployee;
use App\Services\HR\AnnouncementService;
use App\Services\HR\EmployeeNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = HrAnnouncement::with(['creator','department'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('hr.announcements.index', compact('announcements'));
    }

    public function create()
    {
        $departments = HrDepartment::orderBy('name')->get();
        return view('hr.announcements.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'body'          => 'required|string|max:5000',
            'audience'      => 'required|in:all,department',
            'department_id' => 'nullable|required_if:audience,department|exists:hr_departments,id',
            'publish_now'   => 'nullable|boolean',
        ]);

        $announcement = HrAnnouncement::create([
            'title'         => $data['title'],
            'body'          => $data['body'],
            'audience'      => $data['audience'],
            'department_id' => $data['audience'] === 'department' ? $data['department_id'] : null,
            'created_by'    => Auth::guard('hr')->id(),
            'published_at'  => null,
        ]);

        $sent = 0;
        if ($request->boolean('publish_now')) {
            $sent = AnnouncementService::publish($announcement);
        }

        $msg = "Announcement created.";
        if ($request->boolean('publish_now')) $msg .= " Published — {$sent} push notification(s) sent.";

        return redirect()->route('hr.announcements.index')->with('success', $msg);
    }

    public function publish(HrAnnouncement $announcement)
    {
        if ($announcement->isPublished()) {
            return back()->with('error', 'Already published.');
        }

        $sent = AnnouncementService::publish($announcement);

        // Create employee portal notifications for all active employees in target audience
        $empQuery = HrEmployee::where('status', 'active');
        if ($announcement->audience === 'department' && $announcement->department_id) {
            $empQuery->where('department_id', $announcement->department_id);
        }
        EmployeeNotifier::sendToAll(
            $empQuery->get(),
            'announcement',
            $announcement->title,
            \Illuminate\Support\Str::limit(strip_tags($announcement->body), 120),
            ['announcement_id' => $announcement->id],
            route('employee.announcements'),
        );

        return back()->with('success', "Published — {$sent} push notification(s) sent.");
    }

    public function destroy(HrAnnouncement $announcement)
    {
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }
}
