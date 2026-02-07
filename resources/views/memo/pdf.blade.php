<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Memorandum</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: DejaVu Sans, sans-serif; 
            font-size: 12px; 
            color: #111827; 
            margin: 40px; 
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #111827;
            padding-bottom: 15px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .memo-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .memo-info {
            margin-bottom: 20px;
        }
        .memo-info-row {
            margin-bottom: 8px;
        }
        .memo-info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        .subject {
            font-weight: bold;
            font-size: 14px;
            margin: 25px 0 15px 0;
        }
        .memo-body {
            margin: 20px 0;
            text-align: justify;
        }
        .memo-body p {
            margin-bottom: 12px;
        }
        .count-highlight {
            font-weight: bold;
            font-size: 13px;
            color: #991b1b;
        }
        .notes-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9fafb;
            border-left: 4px solid #3b82f6;
        }
        .notes-section strong {
            display: block;
            margin-bottom: 8px;
        }
        .signature-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }
        .signature-line {
            margin-top: 50px;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">HRNexus</div>
    </div>

    <div class="memo-title">MEMORANDUM</div>

    <div class="memo-info">
        <div class="memo-info-row">
            <span class="memo-info-label">Date:</span>
            <span>{{ \Illuminate\Support\Carbon::parse($memo->created_at)->format('F d, Y') }}</span>
        </div>
        <div class="memo-info-row">
            <span class="memo-info-label">To:</span>
            <span>{{ $employee->full_name }} ({{ $employee->employee_code }})</span>
        </div>
        <div class="memo-info-row">
            <span class="memo-info-label"></span>
            <span>{{ $employee->department?->name ?? 'N/A' }} - {{ $employee->position?->name ?? 'N/A' }}</span>
        </div>
        <div class="memo-info-row">
            <span class="memo-info-label">From:</span>
            <span>{{ $sentBy->name }}</span>
        </div>
        <div class="memo-info-row">
            <span class="memo-info-label"></span>
            <span>Human Resources Department</span>
        </div>
        <div class="memo-info-row">
            <span class="memo-info-label">Contact:</span>
            <span>{{ $sentBy->email }}</span>
        </div>
    </div>

    <div class="subject">
        SUBJECT: MEMORANDUM - {{ $subjectTitle }}
    </div>

    <div class="memo-body">
        <p>
            This memorandum serves as a formal notice regarding your {{ $violationType }}.
        </p>
        <p>
            You have accumulated <span class="count-highlight">{{ $memo->count_at_time ?? 0 }} {{ $violationCountText }}</span> {{ $periodText }}.
        </p>
        <p>
            This is a serious violation of company attendance policies. Consistent attendance and punctuality are essential for maintaining productivity and meeting organizational goals.
        </p>
        @if($memo->notes)
        <div class="notes-section">
            <strong>Additional Notes:</strong>
            <div>{{ $memo->notes }}</div>
        </div>
        @endif
        <p>
            <strong>Action Required:</strong> Please contact the Human Resources Department immediately to discuss this matter and provide an explanation for your {{ $violationType }}. Failure to address this issue may result in further disciplinary action.
        </p>
        <p>
            You are expected to improve your attendance record immediately. We trust that you will take this matter seriously and make the necessary adjustments to comply with company policies.
        </p>
    </div>


    <div class="signature-section">
        <div class="signature-line">
            <div class="signature-label">{{ $sentBy->name }}</div>
            <div style="margin-top: 40px; border-top: 1px solid #111827; width: 250px;"></div>
            <div style="font-size: 11px; margin-top: 5px;">Human Resources Department</div>
        </div>
    </div>

    <div class="footer">
        <p>This is an official memorandum from HRNexus Human Resources Department.</p>
        <p>Generated on: {{ \Illuminate\Support\Carbon::now()->format('F d, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>

