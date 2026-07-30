<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Mail\OtpMail;
use App\Mail\WelcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminEmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::orderBy('name')->get();
        return view('admin.email_templates.index', compact('templates'));
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        return view('admin.email_templates.edit', ['template' => $emailTemplate]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'body'    => 'required|string',
        ]);

        $emailTemplate->update([
            'subject'   => $request->subject,
            'body'      => $request->body,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Template updated — live immediately.');
    }

    public function preview(EmailTemplate $emailTemplate)
    {
        $vars = ['name' => 'Preview User', 'code' => '123456', 'year' => date('Y')];
        $rendered = EmailTemplate::render($emailTemplate->key, $vars);
        return response($rendered['body'])->header('Content-Type', 'text/html');
    }

    public function sendTest(Request $request, EmailTemplate $emailTemplate)
    {
        $request->validate(['email' => 'required|email']);

        try {
            $vars     = ['name' => 'Test User', 'code' => '999888', 'year' => date('Y')];
            $rendered = EmailTemplate::render($emailTemplate->key, $vars);

            Mail::html($rendered['body'], function ($m) use ($request, $rendered) {
                $m->to($request->email)->subject('[TEST] ' . $rendered['subject']);
            });

            return back()->with('success', 'Test email sent to ' . $request->email);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }
    }
}
