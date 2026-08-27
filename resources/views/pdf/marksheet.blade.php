<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Marksheet</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .marksheet { min-height: 270mm; position: relative; page-break-after: always; }
        .marksheet:last-child { page-break-after: auto; }
        .header { border-bottom: 2px solid #1e3a8a; min-height: 70px; padding: 0 0 10px; text-align: center; }
        .logo { float: left; height: 62px; width: 62px; object-fit: contain; }
        .school-name { color: #1e3a8a; font-size: 22px; font-weight: bold; margin: 0 70px 4px; }
        .school-meta { color: #4b5563; margin: 2px 70px; }
        .report-title { background: #1e3a8a; color: #fff; display: inline-block; font-size: 14px; font-weight: bold; margin-top: 9px; padding: 5px 24px; }
        .info { border-collapse: collapse; margin: 14px 0 12px; width: 100%; }
        .info td { border: 1px solid #cbd5e1; padding: 6px 8px; width: 25%; }
        .label { color: #475569; font-weight: bold; }
        .marks { border-collapse: collapse; width: 100%; }
        .marks th { background: #e0e7ff; color: #1e3a8a; font-size: 9px; padding: 7px 4px; }
        .marks td { padding: 7px 4px; text-align: center; }
        .marks th, .marks td { border: 1px solid #94a3b8; }
        .marks td.subject { text-align: left; }
        .failed { color: #b91c1c; font-weight: bold; }
        .summary { border: 2px solid #1e3a8a; margin-top: 14px; padding: 10px; width: 100%; }
        .summary td { font-size: 12px; padding: 3px 8px; text-align: center; width: 25%; }
        .summary strong { color: #1e3a8a; display: block; font-size: 15px; margin-top: 3px; }
        .signatures { bottom: 8mm; position: absolute; width: 100%; }
        .signature { display: inline-block; text-align: center; width: 32%; }
        .signature-line { border-top: 1px solid #111827; display: inline-block; padding-top: 5px; width: 75%; }
        .clear { clear: both; }
    </style>
</head>
<body>
@foreach ($marksheets as $marksheet)
    <div class="marksheet">
        <div class="header">
            @if ($marksheet['school']['logo'])<img class="logo" src="{{ $marksheet['school']['logo'] }}" alt="School logo">@endif
            <div class="school-name">{{ $marksheet['school']['name_en'] }}</div>
            <div class="school-meta">{{ $marksheet['school']['address_en'] }}</div>
            <div class="school-meta">EIIN: {{ $marksheet['school']['eiin'] ?: '—' }} &nbsp; | &nbsp; {{ $marksheet['school']['phone'] }}</div>
            <div class="report-title">ACADEMIC PROGRESS REPORT</div>
            <div class="clear"></div>
        </div>

        <table class="info">
            <tr><td><span class="label">Student:</span> {{ $marksheet['student']['name_en'] }}</td><td><span class="label">Admission No:</span> {{ $marksheet['student']['admission_no'] }}</td><td><span class="label">Roll:</span> {{ $marksheet['enrollment']['class_roll'] ?: '—' }}</td><td><span class="label">Session:</span> {{ $marksheet['exam']['session'] }}</td></tr>
            <tr><td><span class="label">Class:</span> {{ $marksheet['enrollment']['class'] }}</td><td><span class="label">Section:</span> {{ $marksheet['enrollment']['section'] }}</td><td><span class="label">Shift:</span> {{ $marksheet['enrollment']['shift'] ?: '—' }}</td><td><span class="label">Exam:</span> {{ $marksheet['exam']['name'] }}</td></tr>
        </table>

        <table class="marks">
            <thead><tr><th>Subject</th><th>Full Marks</th><th>Written</th><th>MCQ</th><th>Practical</th><th>Total Earned</th><th>Grade</th><th>GPA</th></tr></thead>
            <tbody>
            @foreach ($marksheet['subjects'] as $subject)
                <tr class="{{ $subject['is_failing'] ? 'failed' : '' }}">
                    <td class="subject">{{ $subject['subject'] }}{{ $subject['is_optional'] ? ' (Optional)' : '' }}</td>
                    <td>{{ number_format($subject['full_marks'], 0) }}</td>
                    <td>{{ $subject['is_absent'] ? 'Absent' : ($subject['cq'] ?? '—') }}</td>
                    <td>{{ $subject['mcq'] ?? '—' }}</td><td>{{ $subject['practical'] ?? '—' }}</td>
                    <td>{{ number_format($subject['obtained_marks'], 2) }}</td><td>{{ $subject['grade'] }}</td><td>{{ number_format($subject['gpa'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="summary"><tr><td>Total Marks<strong>{{ number_format($marksheet['result']['total_obtained_marks'], 2) }} / {{ number_format($marksheet['result']['total_full_marks'], 2) }}</strong></td><td>Final GPA<strong>{{ $marksheet['result']['gpa'] }}</strong></td><td>Final Grade<strong class="{{ $marksheet['result']['is_failed'] ? 'failed' : '' }}">{{ $marksheet['result']['grade'] }}</strong></td><td>Result<strong>{{ $marksheet['result']['status'] }}</strong></td></tr></table>

        <div class="signatures"><div class="signature"><span class="signature-line">Guardian</span></div><div class="signature"><span class="signature-line">Class Teacher</span></div><div class="signature"><span class="signature-line">Principal / Head Teacher</span></div></div>
    </div>
@endforeach
</body>
</html>
