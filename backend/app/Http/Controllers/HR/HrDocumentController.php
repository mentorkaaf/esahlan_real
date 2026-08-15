<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrDocumentController extends Controller
{
    public function create(HrEmployee $employee)
    {
        return view('hr.documents.create', compact('employee'));
    }

    public function store(Request $request, HrEmployee $employee)
    {
        $data = $request->validate([
            'type'       => 'required|in:id,contract,certificate,cv,photo,other',
            'title'      => 'required|string|max:150',
            'expires_at' => 'nullable|date',
            'notes'      => 'nullable|string|max:500',
            'file'       => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $data['file_path']   = $request->file('file')->store('hr/documents', 'public');
        $data['employee_id'] = $employee->id;

        HrDocument::create($data);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Document uploaded.');
    }

    public function show(HrDocument $document)
    {
        return redirect(Storage::disk('public')->url($document->file_path));
    }

    public function edit(HrDocument $document)
    {
        return view('hr.documents.edit', compact('document'));
    }

    public function update(Request $request, HrDocument $document)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:150',
            'expires_at' => 'nullable|date',
            'notes'      => 'nullable|string|max:500',
        ]);

        $document->update($data);

        return redirect()->route('hr.employees.show', $document->employee)
            ->with('success', 'Document updated.');
    }

    public function destroy(HrDocument $document)
    {
        $employee = $document->employee;
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Document deleted.');
    }
}
