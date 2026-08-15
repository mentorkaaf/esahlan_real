<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $warning->title }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1a1a1a; margin: 0; padding: 40px; line-height: 1.6; }
    .header { border-bottom: 2px solid #cc2222; padding-bottom: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
    .company { font-size: 18pt; font-weight: bold; color: #cc2222; }
    .doc-label { font-size: 10pt; color: #666; text-align: right; }
    h2 { font-size: 14pt; text-align: center; text-transform: uppercase; letter-spacing: 1px; color: #cc2222; margin: 20px 0; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.info td { padding: 5px 8px; font-size: 10.5pt; }
    table.info td:first-child { font-weight: bold; color: #555; width: 160px; }
    .body-text { background: #f9f9f9; border-left: 3px solid #cc2222; padding: 16px; margin: 20px 0; white-space: pre-wrap; font-size: 10.5pt; }
    .signature { margin-top: 50px; display: flex; justify-content: space-between; }
    .sig-block { width: 45%; }
    .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 6px; font-size: 10pt; }
    .footer { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 12px; text-align: center; font-size: 9pt; color: #888; }
    .confidential { text-align: center; font-size: 9pt; color: #cc2222; font-weight: bold; letter-spacing: 1px; margin-bottom: 20px; }
</style>
</head>
<body>

<div class="header">
    <div class="company">eSahlan</div>
    <div class="doc-label">
        Document No: W-{{ str_pad($warning->id, 5, '0', STR_PAD_LEFT) }}<br>
        Date: {{ now()->format('d F Y') }}<br>
        Confidential
    </div>
</div>

<div class="confidential">CONFIDENTIAL — EMPLOYEE WARNING LETTER</div>

<h2>{{ ucfirst($warning->type) }} Warning</h2>

<table class="info">
    <tr><td>Employee Name:</td><td>{{ $warning->employee->full_name }}</td></tr>
    <tr><td>Employee ID:</td><td>EMP-{{ str_pad($warning->employee->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
    <tr><td>Department:</td><td>{{ $warning->employee->department?->name ?? 'N/A' }}</td></tr>
    <tr><td>Position:</td><td>{{ $warning->employee->position?->title ?? 'N/A' }}</td></tr>
    <tr><td>Issued By:</td><td>{{ $warning->issuedBy?->first_name }} {{ $warning->issuedBy?->last_name }}</td></tr>
    <tr><td>Date Issued:</td><td>{{ $warning->created_at->format('d F Y') }}</td></tr>
    <tr><td>Case Reference:</td><td>DISC-{{ str_pad($warning->case_?->id ?? 0, 5, '0', STR_PAD_LEFT) }}</td></tr>
</table>

<div class="body-text">{{ $warning->body }}</div>

<div class="signature">
    <div class="sig-block">
        <div class="sig-line">
            Issued by (HR)<br>
            {{ $warning->issuedBy?->first_name }} {{ $warning->issuedBy?->last_name }}
        </div>
    </div>
    <div class="sig-block">
        <div class="sig-line">
            Received by (Employee)<br>
            {{ $warning->employee->full_name }}
        </div>
    </div>
</div>

<div class="footer">
    This document is confidential and forms part of the employee's disciplinary record.<br>
    eSahlan Human Resources Department — {{ now()->format('Y') }}
</div>

</body>
</html>
