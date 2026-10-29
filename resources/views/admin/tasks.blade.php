@extends('admin.layout')
@section('title', __('Tasks'))
@section('content')
    @php
        $local = fn ($d) => $d?->copy()->setTimezone($tz);
        $quick = [__('Today 5pm') => now($tz)->setTime(17, 0), __('Tomorrow 10am') => now($tz)->addDay()->setTime(10, 0), __('Next week') => now($tz)->addWeek()->setTime(10, 0)];
    @endphp
    <div class="page-head">
        <div><h1>{{ __('Tasks') }}</h1><p class="mute">{{ __('Follow-ups for your team: call back, send a quote, check a payment. You can also add one from a chat or a customer.') }}</p></div>
        <button type="button" class="btn primary" id="new-task">{{ __('+ Add task') }}</button>
    </div>


    @php
        $filtered = $who !== 'all' || $due !== '' || $q !== '';
        $narrowed = $due !== '' || $q !== '' || ! in_array($who, ['all', 'me'], true);
        $keep = array_filter(['who' => $who === 'all' ? null : $who, 'due' => $due ?: null, 'q' => $q ?: null]);
    @endphp
    <form method="get" class="fbar">
        <div class="seg">
            <a href="{{ route('tasks', $keep) }}" @class(['on' => $show === 'open'])>{{ __('Open') }} ({{ $counts['open'] }})</a>
            <a href="{{ route('tasks', ['show' => 'done'] + $keep) }}" @class(['on' => $show === 'done'])>{{ __('Done') }} ({{ $counts['done'] }})</a>
        </div>
        <input type="hidden" name="show" value="{{ $show }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search tasks or customers') }}" aria-label="{{ __('Search') }}">
        <select name="who" aria-label="{{ __('Assigned to') }}">
            <option value="all" @selected($who === 'all')>{{ __('Everyone') }}</option>
            <option value="me" @selected($who === 'me')>{{ __('Only me') }}</option>
            @foreach ($team as $u)@if ($u->id !== auth()->id())<option value="{{ $u->id }}" @selected($who === (string) $u->id)>{{ $u->name }}</option>@endif @endforeach
        </select>
        @if ($show === 'open')
            <select name="due" aria-label="{{ __('Due') }}">
                @foreach (['' => 'Any time', 'overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Next 7 days', 'none' => 'No date'] as $k => $l)<option value="{{ $k }}" @selected($due === $k)>{{ __($l) }}</option>@endforeach
            </select>
        @endif
        @if ($filtered)<a class="btn" href="{{ route('tasks', ['show' => $show === 'done' ? 'done' : null]) }}">{{ __('Reset') }}</a>@endif
        @if ($counts['overdue'] > 0 && $show === 'open' && $due !== 'overdue')<a class="tk-overdue" href="{{ route('tasks', ['due' => 'overdue'] + array_diff_key($keep, ['due' => 1])) }}">{{ __(':n overdue', ['n' => $counts['overdue']]) }}</a>@endif
    </form>

    @if ($tasks->isEmpty())
        <div class="card empty-card"><b>{{ $narrowed ? __('No tasks match these filters') : ($show === 'done' ? __('Nothing finished yet') : ($mine ? __('Nothing assigned to you') : __('You are all caught up'))) }}</b>
            <p class="mute">{{ $narrowed ? __('Change or reset the filters above.') : ($show === 'done' ? __('Tasks you tick off appear here.') : __('Press + Add task when something needs doing.')) }}</p></div>
    @endif

    @php $sections = $show === 'open' ? $groups : ['Done' => $tasks->all()]; @endphp
    @foreach ($sections as $name => $list)
        @continue (empty($list))
        <div class="task-group">
            <h2 class="tg-title {{ $name === 'Overdue' ? 'hot' : '' }}">{{ __($name) }} <span class="mute">{{ count($list) }}</span></h2>
            <div class="card task-list">
                @foreach ($list as $t)
                    @php $due = $local($t->due_at); @endphp
                    <div class="task {{ $t->done_at ? 'done' : '' }}">
                        <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf
                            <button class="tick-btn" title="{{ $t->done_at ? __('Reopen') : __('Mark done') }}" aria-label="{{ $t->done_at ? __('Reopen') : __('Mark done') }}">{!! $t->done_at ? '&#10003;' : '' !!}</button></form>
                        <div class="task-main">
                            <b>{{ $t->title }}</b>
                            <div class="task-meta">
                                @if ($t->done_at)<span>{{ __('Done :when', ['when' => $local($t->done_at)->diffForHumans()]) }}</span>
                                @elseif ($due)<span class="{{ $name === 'Overdue' ? 'late' : '' }}">{{ \App\Support\Lang::fmt($due, 'D j M, H:i', 'n月j日 D H:i') }}</span>@endif
                                @if ($t->customer)<a href="{{ route('customers.show', $t->customer_id) }}">{{ $t->customer->name ?: $t->customer->phone }}</a>@endif
                                @if ($t->conversation_id)<a href="{{ route('inbox', ['c' => $t->conversation_id]) }}">{{ __('Open chat') }}</a>@endif
                            </div>
                        </div>
                        <span class="assignee" title="{{ __('Assigned to') }}">{{ $t->assignee ? $t->assignee->name : __('Anyone') }}</span>
                        <button type="button" class="btn small" data-edit data-action="{{ url('/admin/tasks/'.$t->id) }}" data-delete="{{ url('/admin/tasks/'.$t->id.'/delete') }}"
                            data-title="{{ $t->title }}" data-due="{{ $due?->format('Y-m-d\TH:i') }}" data-assignee="{{ $t->assigned_to }}">{{ __('Edit') }}</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <dialog id="task-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3 id="td-heading">{{ __('Add task') }}</h3>
        <form method="post" id="td-form" action="{{ url('/admin/tasks') }}">
            @csrf
            <label class="f" style="margin-top:12px"><span>{{ __('What needs doing?') }}</span><input type="text" name="title" id="td-title" required maxlength="200" placeholder="{{ __('Call Amy about her facial') }}"></label>
            <label class="f"><span>{{ __('Due') }}</span><input type="datetime-local" name="due_at" id="td-due"></label>
            <div class="task-quick" id="td-quick"><span class="mute">{{ __('Quick:') }}</span>@foreach ($quick as $label => $when)<button type="button" class="btn small" data-due="{{ $when->format('Y-m-d\TH:i') }}">{{ $label }}</button>@endforeach</div>
            <label class="f"><span>{{ __('Assigned to') }}</span><select name="assigned_to" id="td-assignee"><option value="">{{ __('Anyone') }}</option>@foreach ($team as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
            <div class="dlg-foot"><button class="btn primary" type="submit" id="td-save">{{ __('Add task') }}</button><button class="btn danger push" type="submit" id="td-delete" hidden onclick="return confirm({{ json_encode(__('Delete this task?'), JSON_UNESCAPED_UNICODE) }})">{{ __('Delete') }}</button></div>
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
    document.getElementById('td-heading').textContent = edit ? {{ json_encode(__('Edit task'), JSON_UNESCAPED_UNICODE) }} : {{ json_encode(__('Add task'), JSON_UNESCAPED_UNICODE) }};
    document.getElementById('td-save').textContent = edit ? {{ json_encode(__('Save'), JSON_UNESCAPED_UNICODE) }} : {{ json_encode(__('Add task'), JSON_UNESCAPED_UNICODE) }};
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
