<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Cards</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #18181b; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        .sheet { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .card-cell { width: 33.333%; height: 88mm; padding: 1mm; vertical-align: top; }
        .id-card { position: relative; width: 54mm; height: 86mm; overflow: hidden; border: 0.35mm solid #1e3a8a; border-radius: 2.5mm; background: #fff; }
        .card-header { height: 18mm; padding: 3mm 2mm 2mm; color: #fff; text-align: center; background: #1e3a8a; }
        .school-logo { position: absolute; top: 2mm; left: 2mm; width: 10mm; height: 10mm; object-fit: contain; border-radius: 50%; background: #fff; }
        .school-name { margin: 0; font-size: 11px; font-weight: bold; line-height: 1.25; }
        .school-meta { margin-top: 1mm; font-size: 6.5px; }
        .card-title { display: inline-block; margin-top: 1.5mm; padding: 0.5mm 3mm; color: #1e3a8a; font-size: 7px; font-weight: bold; letter-spacing: 0.5px; background: #fff; border-radius: 2mm; }
        .photo-wrap { height: 28mm; padding-top: 3mm; text-align: center; }
        .photo { width: 21mm; height: 25mm; object-fit: cover; border: 0.4mm solid #1e3a8a; border-radius: 1.5mm; }
        .photo-placeholder { display: inline-block; width: 21mm; height: 25mm; padding-top: 10mm; color: #71717a; border: 0.4mm solid #a1a1aa; border-radius: 1.5mm; background: #f4f4f5; }
        .student-name { margin: 1mm 2mm 1.5mm; overflow: hidden; color: #1e3a8a; font-size: 10px; font-weight: bold; line-height: 1.2; text-align: center; white-space: nowrap; }
        .details { width: 32mm; margin-left: 2.5mm; border-collapse: collapse; font-size: 7px; }
        .details td { padding: 0.45mm 0; vertical-align: top; }
        .details .label { width: 11mm; color: #52525b; font-weight: bold; }
        .qr { position: absolute; right: 2mm; bottom: 7mm; width: 15mm; height: 15mm; }
        .card-footer { position: absolute; right: 0; bottom: 0; left: 0; height: 5mm; padding-top: 1.3mm; color: #fff; font-size: 6px; text-align: center; background: #1e3a8a; }
        .empty-cell { width: 33.333%; height: 88mm; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="sheet">
        @foreach (array_chunk($cards, 3) as $row)
            <tr>
                @foreach ($row as $card)
                    @php($student = $card['student'])
                    @php($enrollment = $student->currentEnrollment)
                    <td class="card-cell">
                        <div class="id-card">
                            <div class="card-header">
                                @if ($schoolLogo)
                                    <img class="school-logo" src="{{ $schoolLogo }}" alt="School logo">
                                @endif
                                <div class="school-name">{{ $school?->name_en ?? config('app.name') }}</div>
                                <div class="school-meta">
                                    @if ($school?->eiin) EIIN: {{ $school->eiin }} @endif
                                    @if ($school?->phone) &nbsp;|&nbsp; {{ $school->phone }} @endif
                                </div>
                                <div class="card-title">STUDENT ID CARD</div>
                            </div>
                            <div class="photo-wrap">
                                @if ($card['photo'])
                                    <img class="photo" src="{{ $card['photo'] }}" alt="Student photo">
                                @else
                                    <div class="photo-placeholder">PHOTO</div>
                                @endif
                            </div>
                            <div class="student-name">{{ $student->name_en }}</div>
                            <table class="details">
                                <tr><td class="label">ID No.</td><td>{{ $student->admission_no }}</td></tr>
                                <tr><td class="label">Class</td><td>{{ $enrollment?->schoolClass?->name ?? '—' }}</td></tr>
                                <tr><td class="label">Section</td><td>{{ $enrollment?->section?->name ?? '—' }}</td></tr>
                                <tr><td class="label">Roll</td><td>{{ $enrollment?->class_roll ?? '—' }}</td></tr>
                                <tr><td class="label">Blood</td><td>{{ $student->blood_group?->value ?? '—' }}</td></tr>
                            </table>
                            <img class="qr" src="{{ $card['qr'] }}" alt="QR code">
                            <div class="card-footer">If found, please return to the school office.</div>
                        </div>
                    </td>
                @endforeach
                @for ($empty = count($row); $empty < 3; $empty++)
                    <td class="empty-cell"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
