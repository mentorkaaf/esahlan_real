@extends('admin.layouts.app')
@section('title','TS Settings')
@section('content')
<div class="page-header" style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:900;color:#111827;">Trust & Safety Settings</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:2px;">Blocked terms, keywords, and domains</p>
</div>

@if(session('success'))
<div style="background:#dcfce7;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;">✓ {{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:400px 1fr;gap:20px;">

    {{-- Add Term --}}
    <div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;">
        <div style="font-size:15px;font-weight:800;color:#111827;margin-bottom:16px;">Add Blocked Term</div>
        <form method="POST" action="{{ route('admin.trust-safety.terms.store') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">Type</label>
                <select name="type" required style="width:100%;border:1.5px solid #eef0f6;border-radius:8px;padding:8px 10px;font-size:13px;">
                    <option value="keyword">Keyword</option>
                    <option value="hashtag">Hashtag</option>
                    <option value="domain">Domain</option>
                    <option value="pattern">Pattern (regex)</option>
                </select>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">Value</label>
                <input type="text" name="value" required placeholder="e.g. badword, spam.com" style="width:100%;border:1.5px solid #eef0f6;border-radius:8px;padding:8px 10px;font-size:13px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">Severity</label>
                <select name="severity" required style="width:100%;border:1.5px solid #eef0f6;border-radius:8px;padding:8px 10px;font-size:13px;">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </div>
            <button type="submit" style="width:100%;background:#FF8A00;color:#fff;border:none;padding:10px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">
                <i class="fas fa-plus" style="margin-right:6px;"></i>Add Term
            </button>
        </form>
    </div>

    {{-- Terms list --}}
    <div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;font-size:15px;font-weight:800;color:#111827;">
            Blocked Terms ({{ $terms->count() }})
        </div>
        <table style="width:100%;border-collapse:collapse;">
            <thead style="background:#f9fafb;">
                <tr style="font-size:11px;color:#9ca3af;font-weight:700;text-align:left;">
                    <th style="padding:10px 16px;">Type</th>
                    <th style="padding:10px 16px;">Value</th>
                    <th style="padding:10px 16px;">Severity</th>
                    <th style="padding:10px 16px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($terms as $t)
                @php $sevColor = ['low'=>['#dcfce7','#16a34a'],'medium'=>['#fef3c7','#d97706'],'high'=>['#fee2e2','#dc2626'],'critical'=>['#7f1d1d','#fff']][$t->severity] ?? ['#f3f4f6','#6b7280']; @endphp
                <tr style="border-top:1px solid #f3f4f6;">
                    <td style="padding:10px 16px;">
                        <span style="background:#eff6ff;color:#3b82f6;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">{{ ucfirst($t->type) }}</span>
                    </td>
                    <td style="padding:10px 16px;font-size:13px;font-weight:600;color:#111827;font-family:monospace;">{{ $t->value }}</td>
                    <td style="padding:10px 16px;">
                        <span style="background:{{ $sevColor[0] }};color:{{ $sevColor[1] }};padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">{{ ucfirst($t->severity) }}</span>
                    </td>
                    <td style="padding:10px 16px;">
                        <form method="POST" action="{{ route('admin.trust-safety.terms.delete', $t->id) }}">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('Remove this term?')" style="background:#fee2e2;color:#dc2626;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:30px;text-align:center;color:#9ca3af;">No blocked terms yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
