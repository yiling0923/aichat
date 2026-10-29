@extends('admin.layout')
@section('title', 'Backup and export')
@section('content')
    <div class="page-head">
        <div><a class="mute back-link" href="{{ route('manage') }}">&larr; Settings</a><h1>Backup and export</h1>
            <p class="mute">Your data is yours. Download it as spreadsheets any time, for your own records or to move to another system.</p></div>
    </div>

    <section class="card set-card bk-all">
        <div class="bk-ico"><svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/></svg></div>
        <div class="bk-txt"><h3>Download everything</h3><p class="mute">One zip file with a spreadsheet for each list below. Open the files with Excel or Google Sheets. Chinese names show correctly. Private team notes are not included.</p></div>
        <a class="btn primary" href="{{ url('/admin/backup/zip') }}">Download all (zip)</a>
    </section>

    <section class="card set-card">
        <div class="set-head"><h3>Download one list</h3></div>
        <div class="bk-rows">
            @foreach ($sets as $key => $label)
                <div class="bk-row">
                    <div class="bk-name"><b>{{ $label }}</b><span class="mute">{{ number_format($counts[$key] ?? 0) }} {{ ($counts[$key] ?? 0) === 1 ? 'row' : 'rows' }}</span></div>
                    <a class="btn small" href="{{ url('/admin/backup/'.$key) }}">Download .csv</a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card set-card">
        <div class="set-head"><h3>Automatic server backup</h3><p class="mute">The server saves a copy of the whole database every night at 2:30 and keeps the last 14. This protects you if the computer breaks. It is separate from the downloads above.</p></div>
        @if ($lastBackup)
            <div class="bk-status ok"><span class="dot"></span><span>Last backup: <b>{{ $lastBackup['at']->format('j M Y, H:i') }}</b> ({{ $lastBackup['size'] }}). {{ $lastBackup['count'] }} {{ $lastBackup['count'] === 1 ? 'copy is' : 'copies are' }} kept.</span></div>
        @else
            <div class="bk-status warn"><span class="dot"></span><span>No server backup yet. It runs by itself once the scheduler is running (<code>php artisan schedule:work</code>), or you can run <code>php artisan aichat:backup</code> once to try it. The backup needs <code>mysqldump</code>, which comes with XAMPP.</span></div>
        @endif
        <p class="mute small-note" style="padding:0;margin-top:12px">Copies are in <code>storage/app/backups</code>. To keep one safe, copy the newest file to a USB stick or a cloud folder now and then.</p>
    </section>
@endsection
