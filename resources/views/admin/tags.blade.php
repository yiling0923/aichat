@extends('admin.layout')
@section('title', 'Tags')
@section('content')
    @php
        $ink = function (string $hex): string {
            $h = ltrim($hex, '#'); [$r, $g, $b] = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
            return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#15292a' : '#ffffff';
        };
        $sets = ['customer' => ['People', $customerTags, 'customers_count', 'customer', 'customers'], 'chat' => ['Chats', $chatTags, 'conversations_count', 'chat', 'conversations']];
        $all = collect($customerTags)->merge($chatTags)->map(fn ($t) => ['id' => $t->id, 'type' => $t->type, 'name' => $t->name, 'color' => $t->color])->values();
    @endphp
    <div class="page-head">
        <div><h1>Tags</h1><p class="mute">Short labels that help you find things. Tag <b>people</b> (VIP, first visit) and <b>chats</b> (complaint, price question).</p></div>
        <div class="seg" id="tag-tabs">
            @foreach ($sets as $key => [$name, $list])<a href="{{ route('tags', ['tab' => $key, 'sort' => $sort]) }}" data-tab="{{ $key }}" @class(['on' => $tab === $key])>{{ $name }} ({{ $list->count() }})</a>@endforeach
        </div>
    </div>

    @foreach ($sets as $type => [$name, $list, $count, $typeKey, $things])
        <section class="tag-panel" data-panel="{{ $type }}" @if ($tab !== $type) hidden @endif>
            <form method="post" action="{{ url('/admin/tags') }}" class="card tag-new">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="color" value="{{ $palette[0] }}" class="pick-color">
                <label class="f grow"><span>New {{ $type === 'customer' ? 'people' : 'chat' }} tag</span>
                    <input type="text" name="name" placeholder="{{ $type === 'customer' ? 'VIP' : 'Price question' }}" required maxlength="60"></label>
                <div class="swatches" role="radiogroup" aria-label="Colour">
                    @foreach ($palette as $i => $c)<button type="button" class="sw {{ $i === 0 ? 'on' : '' }}" data-color="{{ $c }}" style="background: {{ $c }}" aria-label="Colour {{ $c }}"></button>@endforeach
                </div>
                <button class="btn primary" type="submit">Add tag</button>
            </form>

            @if ($list->isNotEmpty())
                <div class="tag-tools">
                    <input type="search" class="tag-find" placeholder="Find a tag" aria-label="Find a tag">
                    <span class="mute">Sort:</span>
                    <div class="seg"><a href="{{ route('tags', ['tab' => $type, 'sort' => 'name']) }}" @class(['on' => $sort === 'name'])>A to Z</a><a href="{{ route('tags', ['tab' => $type, 'sort' => 'uses']) }}" @class(['on' => $sort === 'uses'])>Most used</a></div>
                </div>
                <div class="card tag-rows">
                    @foreach ($list as $t)
                        <div class="tag-row" data-name="{{ mb_strtolower($t->name) }}">
                            <span class="chip" style="background: {{ $t->color }}; color: {{ $ink($t->color) }}">{{ $t->name }}</span>
                            <span class="tag-uses">
                                @if ($t->$count > 0)<a href="{{ $type === 'customer' ? route('customers', ['tag' => $t->id]) : route('conversations', ['tag' => $t->id]) }}">{{ $t->$count }} {{ $t->$count === 1 ? \Illuminate\Support\Str::singular($things) : $things }}</a>
                                @else<span class="mute">Not used yet</span>@endif
                            </span>
                            <button type="button" class="btn small" data-edit="{{ $t->id }}">Edit</button>
                        </div>
                    @endforeach
                    <p class="mute tag-none" hidden>No tag matches.</p>
                </div>
            @endif

            @php $have = $list->pluck('name')->map(fn ($n) => mb_strtolower($n)); $ideas = collect($suggestions[$type])->reject(fn ($s) => $have->contains(mb_strtolower($s))); @endphp
            @if ($ideas->isNotEmpty())
                <div class="card ideas">
                    <b>{{ $list->isEmpty() ? 'Start with a few' : 'Ideas' }}</b>
                    <div class="idea-row">
                        @foreach ($ideas as $idea)
                            <form method="post" action="{{ url('/admin/tags') }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="name" value="{{ $idea }}"><input type="hidden" name="color" value="{{ $palette[$loop->index % count($palette)] }}"><button class="btn small" type="submit">+ {{ $idea }}</button></form>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endforeach

    <dialog id="tag-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3>Edit tag</h3>
        <form method="post" id="td-form" action="">
            @csrf
            <input type="hidden" name="color" id="td-color">
            <label class="f" style="margin-top:12px"><span>Name</span><input type="text" name="name" id="td-name" required maxlength="60"></label>
            <div class="f"><span>Colour</span>
                <div class="swatches" id="td-swatches">
                    @foreach ($palette as $c)<button type="button" class="sw" data-color="{{ $c }}" style="background: {{ $c }}" aria-label="Colour {{ $c }}"></button>@endforeach
                    <input type="color" id="td-custom" aria-label="Other colour">
                </div></div>
            <div class="dlg-foot"><button class="btn primary" type="submit">Save</button></div>
        </form>
        <form method="post" id="td-merge" action="" class="td-box">
            @csrf
            <b>Merge into another tag</b>
            <p class="mute small-note" style="padding:0">Everything with this tag gets the other one, and this tag is removed.</p>
            <div class="copy-row"><select name="into" id="td-into"></select><button class="btn small" type="submit" onclick="return confirm('Merge this tag into the chosen one?')">Merge</button></div>
        </form>
        <form method="post" id="td-delete" action="" class="td-box" onsubmit="return confirm('Delete this tag? It is removed from everyone who has it.')">
            @csrf<button class="btn danger small" type="submit">Delete tag</button>
        </form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  var TAGS = @json($all);
  var base = '{{ url('/admin/tags') }}';

  // Tabs without a reload.
  document.querySelectorAll('#tag-tabs a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelectorAll('#tag-tabs a').forEach(function (x) { x.classList.toggle('on', x === a); });
      document.querySelectorAll('.tag-panel').forEach(function (p) { p.hidden = p.dataset.panel !== a.dataset.tab; });
    });
  });

  // Colour swatches in the "new tag" forms.
  document.querySelectorAll('.tag-new').forEach(function (f) {
    f.querySelectorAll('.sw').forEach(function (s) {
      s.addEventListener('click', function () { f.querySelector('.pick-color').value = s.dataset.color; f.querySelectorAll('.sw').forEach(function (x) { x.classList.toggle('on', x === s); }); });
    });
  });

  // Find a tag.
  document.querySelectorAll('.tag-find').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase(), panel = inp.closest('.tag-panel'), shown = 0;
      panel.querySelectorAll('.tag-row').forEach(function (r) { var ok = !q || r.dataset.name.indexOf(q) !== -1; r.hidden = !ok; if (ok) { shown++; } });
      panel.querySelector('.tag-none').hidden = shown > 0;
    });
  });

  // Edit pop-up.
  var dlg = document.getElementById('tag-dialog'), colorIn = document.getElementById('td-color'), custom = document.getElementById('td-custom');
  function setColor(c) {
    colorIn.value = c; custom.value = c;
    document.querySelectorAll('#td-swatches .sw').forEach(function (s) { s.classList.toggle('on', s.dataset.color.toLowerCase() === c.toLowerCase()); });
  }
  document.querySelectorAll('#td-swatches .sw').forEach(function (s) { s.addEventListener('click', function () { setColor(s.dataset.color); }); });
  custom.addEventListener('input', function () { setColor(custom.value); });
  document.querySelectorAll('[data-edit]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = TAGS.filter(function (x) { return String(x.id) === b.dataset.edit; })[0];
      document.getElementById('td-form').action = base + '/' + t.id;
      document.getElementById('td-merge').action = base + '/' + t.id + '/merge';
      document.getElementById('td-delete').action = base + '/' + t.id + '/delete';
      document.getElementById('td-name').value = t.name; setColor(t.color);
      var sel = document.getElementById('td-into'); sel.innerHTML = '';
      TAGS.filter(function (x) { return x.type === t.type && x.id !== t.id; }).forEach(function (x) { sel.appendChild(new Option(x.name, x.id)); });
      document.getElementById('td-merge').hidden = sel.options.length === 0;
      dlg.showModal(); document.getElementById('td-name').focus();
    });
  });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
})();
</script>
@endpush
