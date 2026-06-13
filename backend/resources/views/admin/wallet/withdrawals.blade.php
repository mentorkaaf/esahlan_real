@extends('admin.layouts.app')
@section('title', 'Withdrawal Requests')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Withdrawal Requests</h1>
        <ul class="breadcrumb"><li><a href="{{ route('admin.wallet.index') }}">Wallets</a></li><li><span>Withdrawals</span></li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f8f9fa;font-size:11px;color:#8A8A9A;font-weight:700;text-transform:uppercase">
                <th style="padding:12px 16px;text-align:left">User</th>
                <th style="padding:12px 16px;text-align:right">Amount</th>
                <th style="padding:12px 16px;text-align:left">Method</th>
                <th style="padding:12px 16px;text-align:left">Account</th>
                <th style="padding:12px 16px;text-align:center">Status</th>
                <th style="padding:12px 16px;text-align:center">Date</th>
                <th style="padding:12px 16px;text-align:center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $req)
            <tr style="border-top:1px solid #f0f1f5">
                <td style="padding:12px 16px">
                    <div style="font-weight:700;color:#07003B">{{ $req->user_name }}</div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $req->user_phone }}</div>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:#E74C3C">${{ number_format($req->amount, 2) }}</td>
                <td style="padding:12px 16px">{{ strtoupper($req->payment_method) }}</td>
                <td style="padding:12px 16px">
                    <div>{{ $req->account_name }}</div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $req->account_number }}</div>
                </td>
                <td style="padding:12px 16px;text-align:center">
                    @php $colors = ['pending'=>['#FFF8E1','#F57F17'],'approved'=>['#E8F5E9','#27AE60'],'rejected'=>['#FFEBEE','#E74C3C'],'processed'=>['#E3F2FD','#1565C0']] @endphp
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $colors[$req->status][0] ?? '#f0f0f0' }};color:{{ $colors[$req->status][1] ?? '#555' }}">
                        {{ strtoupper($req->status) }}
                    </span>
                </td>
                <td style="padding:12px 16px;text-align:center;color:#8A8A9A">{{ \Carbon\Carbon::parse($req->created_at)->format('M d') }}</td>
                <td style="padding:12px 16px;text-align:center">
                    @if($req->status === 'pending')
                    <div style="display:flex;gap:6px;justify-content:center">
                        <form method="POST" action="{{ route('admin.wallet.withdrawal.approve', $req->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-success" onclick="return confirm('Approve this withdrawal?')"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" action="{{ route('admin.wallet.withdrawal.reject', $req->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Reject and refund this withdrawal?')"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                    @else —
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px">{{ $requests->links() }}</div>
</div>
@endsection
