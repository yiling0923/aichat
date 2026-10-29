@extends('admin.layout')
@section('title', 'Tasks')
@section('content')
    @php
        $local = fn ($d) => $d?->copy()->setTimezone($tz);
        $quick = ['Today 5pm' => now($tz)->setTime(17, 0), 'Tomorrow 10am' => now($tz)->addDay()->setTime(10, 0), 'Next week' => now($tz)->addWeek()->setTime(10, 0)];
    @endphp
    <div class="page-head">
        <div><h1>Tasks</h1><p class="mute">Follow-ups for your team: call back, send a quote, check a payment. You can also add one from a chat or a customer.</p></div>
        <button type="button" class="btn primary" id="new-task">+ Add task</button>
    </div>


    <div class="filters">
        <div class="seg">
            <a href="{{ route('tasks', ['mine' => $mine ? 1 : 0]) }}" @class(['on' => $show === 'open'])>Open ({{ $counts['open'] }})</a>
            <a href="{{ route('tasks', ['show' => 'done', 'mine' => $mine ? 1 : 0]) }}" @class(['on' => $show === 'done'])>Done ({{ $counts['done'] }})</a>
        </div>
        <div class="seg">
            <a href="{{ route('tasks', ['show' => $show, 'mine' => 0]) }}" @class(['on' => ! $mine])>Everyone</a>
            <a href="{{ route('tasks', ['show' => $show, 'mine' => 1]) }}" @class(['on' => $mine])>Only mine</a>
        </div>
        <span class="grow"></span>
        @if ($counts['overdue'] > 0 && $show === 'open')<span class="tag hot">{{ $counts['overdue'] }} overdue</span>@endif
    </div>

    @if ($tasks->isEmpty())
        <div class="card empty-card"><b>{{ $show === 'done' ? 'Nothing finished yet' : ($mine ? 'Nothing assigned to you' : 'You are all caught up') }}</b>
            <p class="mute">{{ $show === 'done' ? 'Tasks you tick off appear here.' : 'Add a follow-up above when something needs doing.' }}</p></div>
    @endif

    @php $sections = $show === 'open' ? $groups : ['Done' => $tasks->all()]; @endphp
    @foreach ($sections as $name => $list)
        @continue (empty($list))
        <div class="task-group">
            <h2 class="tg-title {{ $name === 'Overdue' ? 'hot' : '' }}">{{ $name }} <span class="mute">{{ count($list) }}</span></h2>
            <div class="card task-list">
                @foreach ($list as $t)
                    @php $due = $local($t->due_at); @endphp
                    <div class="task {{ $t->done_at ? 'done' : '' }}">
                        <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf
                            <button class="tick-btn" title="{{ $t->done_at ? 'Reopen' : 'Mark done' }}" aria-label="{{ $t->done_at ? 'Reopen' : 'Mark done' }}">{!! $t->done_at ? '&#10003;' : '' !!}</button></form>
                        <div class="task-main">
                            <b>{{ $t->title }}</b>
                            <div class="task-meta">
                                @if ($t->done_at)<span>Done {{ $local($t->done_at)->diffForHumans() }}</span>
                                @elseif ($due)<span class="{{ $name === 'Overdue' ? 'late' : '' }}">{{ $due->format('D j M, H:i') }}</span>@endif
                                @if ($t->customer)<a href="{{ route('customers.show', $t->customer_id) }}">{{ $t->customer->name ?: $t->customer->phone }}</a>@endif
                                @if ($t->conversation_id)<a href="{{ route('inbox', ['c' => $t->conversation_id]) }}">Open chat</a>@endif
                            </div>
                        </div>
                        <span class="assignee" title="Assigned to">{{ $t->assignee ? $t->assignee->name : 'Anyone' }}</span>
                        <button type="button" class="btn small" data-edit data-action="{{ url('/admin/tasks/'.$t->id) }}" data-delete="{{ url('/admin/tasks/'.$t->id.'/delete') }}"
                            data-title="{{ $t->title }}" data-due="{{ $due?->format('Y-m-d\TH:i') }}" data-assignee="{{ $t->assigned_to }}">Edit</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <dialog id="task-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3 id="td-heading">Add task</h3>
        <form method="post" id="td-form" action="{{ url('/admin/tasks') }}">
            @csrf
            <label class="f" style="margin-top:12px"><span>What needs doing?</span><input type="text" name="title" id="td-title" required maxlength="200" placeholder="Call Amy about her facial"></label>
            <label class="f"><span>Due</span><input type="datetime-local" name="due_at" id="td-due"></label>
            <div class="task-quick" id="td-quick"><span class="mute">Quick:</span>@foreach ($quick as $label => $when)<button type="button" class="btn small" data-due="{{ $when->format('Y-m-d\TH:i') }}">{{ $label }}</button>@endforeach</div>
            <label class="f"><span>Assigned to</span><select name="assigned_to" id="td-assignee"><option value="">Anyone</option>@foreach ($team as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
            <div class="dlg-foot"><button class="btn primary" type="submit" id="td-save">Add task</button><button class="btn danger push" type="submit" id="td-delete" hidden onclick="return confirm('Delete this task?')">Delete</button></div>
        </form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  var dlg = document.getElementById('task-dialog'), form = document.getElementById('td-form'), me = {{ auth()->id() }};
  var title = document.getElementById('td-title'), due = document.getElementById('td-due'), who = document.getElementById('td-assignee'), del = document.getElementById('td-delete');
  function open(d) {
    var edit = d && d.action;
    document.getElementById('td-heading').textContent = edit ? 'Edit task' : 'Add task';
    document.getElementById('td-save').textContent = edit ? 'Save' : 'Add task';
    form.action = edit ? d.action : '{{ url('/admin/tasks') }}';
    title.value = edit ? d.title : ''; due.value = edit ? (d.due || '') : ''; who.value = edit ? (d.assignee || '') : String(me);
    del.hidden = !edit; if (edit) { del.setAttribute('formaction', d.delete); }
    dlg.showModal(); title.focus();
  }
  document.getElementById('new-task').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  document.querySelectorAll('#td-quick [data-due]').forEach(function (b) { b.addEventListener('click', function () { due.value = b.dataset.due; }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  @if (request()->boolean('new')) history.replaceState(null, '', location.pathname); @endif
  @if ($errors->any() || request()->boolean('new')) open(null); @endif
})();
</script>
@endpush
