<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 20px; }
        h1 { font-size: 20px; margin: 0 0 8px 0; }
        h2 { font-size: 14px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .no-border td { border: none; padding: 3px 0; }
        .muted { color: #6b7280; font-size: 11px; }
        .avatar-box { width: 150px; height: 150px; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 0 auto; }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        .badge-ok { background: #d1fae5; color: #065f46; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .small-table td { padding: 4px 6px; }
        .profile-table td { border: none; padding: 6px 10px; vertical-align: top; }
        .avatar-cell { width: 35%; text-align: center; }
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-present { background: #d1fae5; color: #065f46; }
        .status-late { background: #ffedd5; color: #9a3412; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-leave { background: #dbeafe; color: #1d4ed8; }
        .status-holiday { background: #f3e8ff; color: #6b21a8; }
        .status-default { background: #e5e7eb; color: #374151; }
    </style>
</head>
<body>
    <h1>Attendance Report</h1>
    <p class="muted">Period: {{ $period_label }} | Generated at: {{ $generated_at }}</p>

    <table class="profile-table" style="margin-bottom: 12px;">
        <tr>
            <td class="avatar-cell">
                <div class="avatar-box">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="Avatar">
                    @else
                        <span class="muted">No Photo</span>
                    @endif
                </div>
            </td>
            <td style="width: 65%;">
                <table class="no-border">
                    <tr><td><strong>Name:</strong> {{ $employee->full_name }}</td></tr>
                    <tr><td><strong>Employee Code:</strong> {{ $employee->employee_code }}</td></tr>
                    <tr><td><strong>Department:</strong> {{ $department->name ?? 'N/A' }}</td></tr>
                    <tr><td><strong>Position:</strong> {{ $position->name ?? 'N/A' }}</td></tr>
                    <tr><td><strong>Email:</strong> {{ $email ?? 'N/A' }}</td></tr>
                    <tr><td><strong>Contact No.:</strong> {{ $contact_number ?? 'N/A' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h2>Summary</h2>
    <table class="small-table" style="margin-bottom: 12px;">
        <tr>
            <th>Period</th>
            <th>Date Range</th>
            <th>Working Days</th>
            <th>Holidays</th>
            <th>Total Hours Worked</th>
            <th>Lates</th>
            <th>Absents</th>
            <th>Leaves</th>
        </tr>
        <tr>
            <td>{{ $period_label }}</td>
            <td>{{ $date_range }}</td>
            <td>{{ $working_days }}</td>
            <td>{{ $holiday_count }}</td>
            <td>{{ number_format($total_hours, 2) }}</td>
            <td>{{ $late_count }}</td>
            <td>{{ $absent_count }}</td>
            <td>{{ $leave_count }}</td>
        </tr>
    </table>

    <table class="small-table" style="margin-bottom: 16px;">
        <tr>
            <th>Threshold Flags</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Late Warning</td>
            <td>
                @if($late_warning_hit)
                    <span class="badge badge-warn">Reached</span>
                @else
                    <span class="badge badge-ok">Not reached</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Late Memo</td>
            <td>
                @if($late_memo_hit)
                    <span class="badge badge-danger">Reached</span>
                @else
                    <span class="badge badge-ok">Not reached</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Absent Warning</td>
            <td>
                @if($absent_warning_hit)
                    <span class="badge badge-warn">Reached</span>
                @else
                    <span class="badge badge-ok">Not reached</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Absent Memo</td>
            <td>
                @if($absent_memo_hit)
                    <span class="badge badge-danger">Reached</span>
                @else
                    <span class="badge badge-ok">Not reached</span>
                @endif
            </td>
        </tr>
    </table>

    <h2>Daily Time Record (DTR)</h2>
    <table>
        <tr>
            <th>Date</th>
            <th>Day</th>
            <th colspan="2">Morning Session</th>
            <th colspan="2">Afternoon Session</th>
            <th colspan="2">Overtime</th>
            <th>Status</th>
            <th>OT</th>
            <th>Hours</th>
        </tr>
        <tr>
            <th></th>
            <th></th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th></th>
            <th>✓</th>
            <th></th>
        </tr>
        @foreach($rows as $row)
            @php
                $normalizedStatus = strtolower($row['status']);
                $statusClass = match ($normalizedStatus) {
                    'present' => 'status-present',
                    'late' => 'status-late',
                    'absent' => 'status-absent',
                    'leave' => 'status-leave',
                    'holiday' => 'status-holiday',
                    default => 'status-default',
                };
            @endphp
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M d, Y') }}</td>
                <td>{{ $row['day'] ?? \Illuminate\Support\Carbon::parse($row['date'])->format('D') }}</td>
                <td>{{ $row['morning_time_in'] ?? '-' }}</td>
                <td>{{ $row['morning_time_out'] ?? '-' }}</td>
                <td>{{ $row['afternoon_time_in'] ?? '-' }}</td>
                <td>{{ $row['afternoon_time_out'] ?? '-' }}</td>
                <td>{{ $row['overtime_time_in'] ?? '-' }}</td>
                <td>{{ $row['overtime_time_out'] ?? '-' }}</td>
                <td><span class="status-badge {{ $statusClass }}">{{ $row['status'] }}</span></td>
                <td style="text-align: center;">
                    @if(!empty($row['overtime_time_in']) && !empty($row['overtime_time_out']))
                        ✓
                    @endif
                </td>
                <td>{{ number_format($row['hours'], 2) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

