<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $record->activity_type }} Form · {{ $record->student_name }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #142b4a; }
        body { margin: 0; background: #eef3f8; }
        main { width: min(900px, calc(100% - 32px)); margin: 32px auto; padding: 44px; background: white; box-shadow: 0 16px 50px rgba(17,49,99,.12); }
        header { text-align: center; border-bottom: 3px solid #113163; padding-bottom: 24px; }
        header p { margin: 4px 0; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 8px 0 0; font-family: Georgia, serif; font-size: 2.3rem; }
        .details { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 0; margin: 28px 0; border: 1px solid #bcc9d8; }
        .details div { padding: 11px 14px; border: 1px solid #d7e0ea; }
        dt { color: #607087; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        dd { margin: 5px 0 0; font-weight: 700; }
        section { margin-top: 28px; page-break-inside: avoid; }
        section h2 { margin: 0 0 10px; font-size: 1rem; text-transform: uppercase; }
        .answer { display: grid; grid-template-columns: minmax(180px, .7fr) 1fr; gap: 18px; padding: 10px 0; border-bottom: 1px solid #d7e0ea; }
        .answer strong { font-size: .88rem; }
        .answer span { white-space: pre-wrap; }
        .submitted { margin-top: 30px; color: #607087; font-size: .85rem; }
        .actions { display: flex; justify-content: flex-end; width: min(900px, calc(100% - 32px)); margin: 24px auto 0; }
        button { padding: 11px 18px; border: 0; border-radius: 6px; background: #0056b3; color: white; font-weight: 700; cursor: pointer; }
        @media print { body { background: white; } .actions { display: none; } main { width: auto; margin: 0; padding: 20px; box-shadow: none; } }
        @media (max-width: 620px) { main { padding: 24px; } .details { grid-template-columns: 1fr; } .answer { grid-template-columns: 1fr; gap: 5px; } }
    </style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">Print or save as PDF</button></div>
<main>
    <header>
        <p>University of La Salette Incorporated</p>
        <p>Christian Formation Office</p>
        <h1>{{ $record->activity_type }} Form</h1>
        <p>{{ $record->activity_type }} Details</p>
    </header>
    <dl class="details">
        <div><dt>Student name</dt><dd>{{ $record->student_name }}</dd></div>
        <div><dt>Student ID</dt><dd>{{ $record->university_id }}</dd></div>
        <div><dt>Department</dt><dd>{{ $record->department }}</dd></div>
        <div><dt>Course or program</dt><dd>{{ $record->course ?: $record->department }}{{ $record->major ? ' · Major in '.$record->major : '' }}</dd></div>
        <div><dt>Year and section</dt><dd>Year {{ $record->year }} · {{ $record->section }}</dd></div>
        <div><dt>Email</dt><dd>{{ $record->email ?: 'Not provided' }}</dd></div>
        <div><dt>Contact number</dt><dd>{{ $record->contact }}</dd></div>
        <div><dt>Activity</dt><dd>{{ $record->activity_title }}</dd></div>
        <div><dt>Activity date</dt><dd>{{ \Carbon\Carbon::parse($record->activity_date)->format('F j, Y') }}</dd></div>
    </dl>
    @foreach($answers as $sectionName => $sectionAnswers)
        <section>
            <h2>{{ $sectionName }}</h2>
            @foreach($sectionAnswers as $answer)<div class="answer"><strong>{{ $answer->label }}</strong><span>{{ $answer->answer ?: 'Not provided' }}</span></div>@endforeach
        </section>
    @endforeach
    <p class="submitted">Submitted {{ \Carbon\Carbon::parse($record->created_at)->timezone('Asia/Manila')->format('F j, Y · g:i A') }}</p>
</main>
</body>
</html>
