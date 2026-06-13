@extends('admin.layouts.app')
@section('title', 'eHealth — Doctors')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-user-md" style="color:var(--primary)"></i> eHealth — Doctors</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">eHealth</li>
        </ol>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Doctor
    </button>
</div>

<div class="card">
    <div class="card-header">Doctors ({{ count($doctors) }})</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Specialization</th>
                    <th>Experience</th>
                    <th>Consultation Fee</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($doctors as $doc)
                <tr>
                    <td>
                        @if($doc->avatar)
                            <img src="{{ $doc->avatar }}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" alt="">
                        @else
                            <div style="width:40px;height:40px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-user-md" style="color:#ccc;"></i>
                            </div>
                        @endif
                    </td>
                    <td><strong>Dr. {{ $doc->name }}</strong></td>
                    <td><span class="badge badge-info">{{ $doc->specialization }}</span></td>
                    <td>{{ $doc->experience_years ?? '-' }} yrs</td>
                    <td><strong class="text-success">${{ number_format($doc->consultation_fee ?? 0, 2) }}</strong></td>
                    <td>
                        <span class="badge {{ $doc->is_available ? 'badge-success' : 'badge-danger' }}">
                            {{ $doc->is_available ? 'Available' : 'Unavailable' }}
                        </span>
                    </td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-secondary" onclick='openEdit({{ json_encode($doc) }})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="/admin/module-data/health/doctors/{{ $doc->id }}" method="POST" onsubmit="return confirm('Delete doctor?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:#888;">No doctors added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title">Add Doctor</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.health.doctor.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="Dr. Ahmed Ali">
            </div>
            <div class="form-group">
                <label class="form-label">Specialization *</label>
                <select name="specialization" class="form-control" required>
                    <option value="">Select...</option>
                    @foreach(['General', 'Pediatrics', 'Cardiology', 'Orthopedics', 'Dermatology', 'Gynecology', 'Neurology', 'Psychiatry', 'Ophthalmology', 'Dentistry', 'ENT', 'Radiology', 'Surgery', 'Urology'] as $spec)
                        <option value="{{ $spec }}">{{ $spec }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Experience (years)</label>
                    <input type="number" name="experience_years" class="form-control" min="0" placeholder="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Consultation Fee ($)</label>
                    <input type="number" name="consultation_fee" class="form-control" step="0.01" placeholder="20.00">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Doctor Photo</label>
                <div onclick="document.getElementById('addDoc_avatarFile').click()" style="border:2px dashed #FF8A00;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff9f2;min-height:80px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="addDoc_avatarPreview" src="" style="display:none;max-height:70px;border-radius:50%;object-fit:cover;">
                    <span id="addDoc_avatarPlaceholder" style="color:#FF8A00;font-size:12px;">👨‍⚕️ Upload Photo</span>
                </div>
                <input type="file" id="addDoc_avatarFile" name="avatar_file" accept="image/*" style="display:none" onchange="previewImage(this,'addDoc_avatarPreview','addDoc_avatarPlaceholder')">
                <input type="text" name="avatar" class="form-control" placeholder="Or paste avatar URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'addDoc_avatarPreview','addDoc_avatarPlaceholder')">
            </div>
            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="2" placeholder="Short bio..."></textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_available" value="1" checked> Available for appointments
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Doctor</button>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Doctor</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" id="dName" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Specialization *</label>
                <select name="specialization" id="dSpec" class="form-control" required>
                    @foreach(['General', 'Pediatrics', 'Cardiology', 'Orthopedics', 'Dermatology', 'Gynecology', 'Neurology', 'Psychiatry', 'Ophthalmology', 'Dentistry', 'ENT', 'Radiology', 'Surgery', 'Urology'] as $spec)
                        <option value="{{ $spec }}">{{ $spec }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Experience (years)</label>
                    <input type="number" name="experience_years" id="dExp" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Consultation Fee ($)</label>
                    <input type="number" name="consultation_fee" id="dFee" class="form-control" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Doctor Photo</label>
                <div onclick="document.getElementById('editDoc_avatarFile').click()" style="border:2px dashed #FF8A00;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff9f2;min-height:80px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="editDoc_avatarPreview" src="" style="display:none;max-height:70px;border-radius:50%;object-fit:cover;">
                    <span id="editDoc_avatarPlaceholder" style="color:#FF8A00;font-size:12px;">👨‍⚕️ Click to change photo</span>
                </div>
                <input type="file" id="editDoc_avatarFile" name="avatar_file" accept="image/*" style="display:none" onchange="previewImage(this,'editDoc_avatarPreview','editDoc_avatarPlaceholder')">
                <input type="text" name="avatar" id="dAvatar" class="form-control" placeholder="Or paste avatar URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'editDoc_avatarPreview','editDoc_avatarPlaceholder')">
            </div>
            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" id="dBio" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_available" id="dAvail" value="1"> Available for appointments
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEdit(doc) {
    document.getElementById('editForm').action = `/admin/module-data/health/doctors/${doc.id}`;
    document.getElementById('dName').value  = doc.name;
    document.getElementById('dSpec').value  = doc.specialization;
    document.getElementById('dExp').value   = doc.experience_years || '';
    document.getElementById('dFee').value   = doc.consultation_fee || '';
    document.getElementById('dAvatar').value = doc.avatar || '';
    document.getElementById('dBio').value   = doc.bio || '';
    document.getElementById('dAvail').checked = doc.is_available == 1;
    if (doc.avatar) previewFromUrl(doc.avatar, 'editDoc_avatarPreview', 'editDoc_avatarPlaceholder');
    else { document.getElementById('editDoc_avatarPreview').style.display='none'; document.getElementById('editDoc_avatarPlaceholder').style.display='block'; }
    openModal('editModal');
}
function previewImage(input, prevId, phId) {
    const file = input.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = e => { const p=document.getElementById(prevId); p.src=e.target.result; p.style.display='block'; const ph=document.getElementById(phId); if(ph) ph.style.display='none'; };
    reader.readAsDataURL(file);
}
function previewFromUrl(url, prevId, phId) {
    const p=document.getElementById(prevId); const ph=document.getElementById(phId);
    if (url && url.startsWith('http')) { p.src=url; p.style.display='block'; if(ph) ph.style.display='none'; }
    else { p.style.display='none'; if(ph) ph.style.display='block'; }
}
</script>
@endpush
@endsection
