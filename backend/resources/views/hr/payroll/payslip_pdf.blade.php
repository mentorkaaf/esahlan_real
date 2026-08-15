<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; }
.page { padding: 32px 40px; }

/* Header */
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1B1444; padding-bottom: 16px; margin-bottom: 20px; }
.brand { font-size: 22px; font-weight: bold; color: #1B1444; }
.brand span { color: #F7941D; }
.payslip-title { text-align: right; }
.payslip-title h2 { font-size: 14px; color: #1B1444; font-weight: bold; }
.payslip-title .period { font-size: 12px; color: #666; margin-top: 4px; }

/* Employee info */
.employee-section { display: flex; justify-content: space-between; background: #f8f8f8; padding: 14px 16px; border-radius: 6px; margin-bottom: 20px; }
.info-block h4 { font-size: 9px; text-transform: uppercase; color: #999; letter-spacing: 0.5px; margin-bottom: 3px; }
.info-block p { font-weight: bold; color: #1B1444; }

/* Tables */
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th { background: #1B1444; color: #fff; text-align: left; padding: 8px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
td { padding: 7px 12px; border-bottom: 1px solid #eee; }
.amount { text-align: right; font-weight: bold; font-family: monospace; }
tr.subtotal td { background: #f4f1ff; font-weight: bold; border-top: 2px solid #1B1444; }
tr.deduction td { color: #c0392b; }

/* Net pay */
.net-section { border: 2px solid #1B1444; border-radius: 6px; padding: 16px; text-align: center; margin-top: 20px; }
.net-section .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 4px; }
.net-section .amount { font-size: 28px; font-weight: bold; color: #1B1444; }

/* Footer */
.footer { margin-top: 32px; border-top: 1px solid #eee; padding-top: 12px; display: flex; justify-content: space-between; color: #aaa; font-size: 9px; }
.stamp { margin-top: 20px; text-align: right; }
.stamp .box { display: inline-block; border: 1px dashed #ccc; padding: 8px 24px; color: #ccc; font-size: 9px; }
</style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="brand">e<span>Sahlan</span></div>
            <div style="color:#666; font-size:10px; margin-top:4px;">eSahlan Platform · Mogadishu, Somalia</div>
        </div>
        <div class="payslip-title">
            <h2>PAYSLIP</h2>
            <div class="period">Period: {{ $payslip->run->period }}</div>
            @if($payslip->paid_at)
            <div style="color:#27ae60; font-size:10px; margin-top:4px;">✓ Paid via {{ strtoupper($payslip->payment_method ?? '') }}</div>
            @endif
        </div>
    </div>

    {{-- Employee Info --}}
    <div class="employee-section">
        <div class="info-block">
            <h4>Employee</h4>
            <p>{{ $payslip->employee->full_name }}</p>
        </div>
        <div class="info-block">
            <h4>Employee No</h4>
            <p>{{ $payslip->employee->employee_no }}</p>
        </div>
        <div class="info-block">
            <h4>Department</h4>
            <p>{{ $payslip->employee->department?->name ?? '—' }}</p>
        </div>
        <div class="info-block">
            <h4>Position</h4>
            <p>{{ $payslip->employee->position?->title ?? '—' }}</p>
        </div>
    </div>

    {{-- Earnings --}}
    <table>
        <thead>
            <tr><th>Description</th><th style="text-align:right">Amount (USD)</th></tr>
        </thead>
        <tbody>
            @foreach($payslip->items->where('type','earning')->sortBy('sort_order') as $item)
            <tr>
                <td>{{ $item->label }}</td>
                <td class="amount">${{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td>GROSS PAY</td>
                <td class="amount">${{ number_format($payslip->gross_pay, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Deductions --}}
    @if($payslip->items->where('type','deduction')->count())
    <table>
        <thead>
            <tr><th>Deductions</th><th style="text-align:right">Amount (USD)</th></tr>
        </thead>
        <tbody>
            @foreach($payslip->items->where('type','deduction')->sortBy('sort_order') as $item)
            <tr class="deduction">
                <td>{{ $item->label }}</td>
                <td class="amount">−${{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="subtotal" style="color:#c0392b;">
                <td>TOTAL DEDUCTIONS</td>
                <td class="amount">−${{ number_format($payslip->total_deductions, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Net Pay --}}
    <div class="net-section">
        <div class="label">Net Pay</div>
        <div class="amount">${{ number_format($payslip->net_pay, 2) }}</div>
    </div>

    {{-- Signature stamp --}}
    <div class="stamp">
        <div class="box">Authorized Signature</div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>eSahlan HR System · Confidential</div>
    </div>

</div>
</body>
</html>
