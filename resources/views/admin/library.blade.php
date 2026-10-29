@extends('admin.layout')
@section('title', 'Saved replies')
@section('content')
    @php $cats = $replies->groupBy(fn ($r) => $r->category ?: 'Other')->map->count(); @endphp
    <div class="page-head">
        <div><h1>Saved replies</h1>
            <p class="mute">Ready-made answers. In the Inbox, choose one under "Insert a saved reply": it goes in the customer's language, and <code>{name}</code> and <code>{business}</code> are filled in.
                @unless ($canEdit) Only an owner or manager can change this list. @endunless</p></div>
        @if ($canEdit)<button type="button" class="btn primary" id="new-reply">New reply</button>@endif
    </div>

    <div class="fbar">
        <input type="search" id="lib-search" placeholder="Search titles and text" aria-label="Search">
        <div class="seg" id="lib-cats">
            <a href="#" data-cat="" class="on">All ({{ $replies->count() }})</a>
            @foreach ($cats as $name => $n)<a href="#" data-cat="{{ $name }}">{{ $name }} ({{ $n }})</a>@endforeach
        </div>
    </div>

    @if ($replies->isEmpty())
        <div class="card empty-card"><b>No saved replies yet</b><p class="mute">Add the answers your team types again and again: opening hours, prices, directions, aftercare.</p></div>
    @endif

    <div class="masonry" id="lib-list">
        @foreach ($replies as $r)
            <div class="card reply" data-cat="{{ $r->category ?: 'Other' }}" data-text="{{ mb_strtolower($r->title.' '.($r->body['en'] ?? '').' '.($r->body['zh'] ?? '')) }}">
                <div class="reply-top"><b>{{ $r->title }}</b><span class="tag">{{ $r->category ?: 'Other' }}</span></div>
                <p class="reply-text"><span class="lang">EN</span>{{ $r->body['en'] ?? '' }}</p>
                @if (! empty($r->body['zh']))<p class="reply-text"><span class="lang">中文</span>{{ $r->body['zh'] }}</p>@endif
                <div class="reply-actions">
                    <button type="button" class="btn small" data-copy-text="{{ $r->body['en'] ?? '' }}">Copy English</button>
                    @if ($canEdit)
                        <button type="button" class="btn small" data-edit
                            data-action="{{ url('/admin/library/'.$r->id) }}" data-delete="{{ url('/admin/library/'.$r->id.'/delete') }}"
                            data-title="{{ $r->title }}" data-category="{{ $r->category }}" data-en="{{ $r->body['en'] ?? '' }}" data-zh="{{ $r->body['zh'] ?? '' }}">Edit</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <p class="mute" id="lib-none" hidden style="text-align:center;margin-top:16px">No reply matches your search.</p>

    @if ($canEdit)
        <dialog id="reply-dialog" class="dlg wide">
            <button type="button" class="dlg-x" aria-label="Close">&times;</button>
            <h3 id="rd-title">New reply</h3>
            <form method="post" id="rd-form" action="{{ url('/admin/library') }}">
                @csrf
                <div class="grid2" style="margin-top:12px">
                    <label class="f"><span>Title</span><input type="text" name="title" id="rd-name" required maxlength="80" placeholder="Opening hours"></label>
                    <label class="f"><span>Group</span>
                        <input type="text" name="category" id="rd-cat" list="rd-cats" maxlength="40" placeholder="Pick or type">
                        <datalist id="rd-cats">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist></label>
                </div>
                <label class="f"><span>English</span><textarea name="body_en" id="rd-en" rows="4" required maxlength="1500"></textarea></label>
                <label class="f"><span>中文 (optional)</span><textarea name="body_zh" id="rd-zh" rows="4" maxlength="1500"></textarea></label>
                <div class="mute" style="font-size:12px;margin:-4px 0 12px">Add the customer's name or your business name:
                    <button type="button" class="btn small" data-var="{name}">{name}</button> <button type="button" class="btn small" data-var="{business}">{business}</button></div>
                <div class="dlg-foot">
                    <button class="btn primary" type="submit">Save</button>
                    <button class="btn danger push" type="submit" id="rd-delete" formaction="" hidden onclick="return confirm('Delete this reply?')">Delete</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
@push('scripts')
<script>
(function () {
  var search = document.getElementById('lib-search'), cats = document.querySelectorAll('#lib-cats a'), cards = document.querySelectorAll('#lib-list .reply');
  var cat = '', none = document.getElementById('lib-none');
  function filter() {
    var q = search.value.trim().toLowerCase(), shown = 0;
    cards.forEach(function (c) {
      var ok = (!cat || c.dataset.cat === cat) && (!q || c.dataset.text.indexOf(q) !== -1);
      c.hidden = !ok; if (ok) { shown++; }
    });
    none.hidden = shown > 0 || cards.length === 0;
  }
  search.addEventListener('input', filter);
  cats.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); cat = a.dataset.cat; cats.forEach(function (x) { x.classList.toggle('on', x === a); }); filter(); }); });

  document.querySelectorAll('[data-copy-text]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.dataset.copyText, done = function () { var o = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = o; }, 1300); };
      if (navigator.clipboard) { navigator.clipboard.writeText(t).then(done); } else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); done(); }
    });
  });

  var dlg = document.getElementById('reply-dialog');
  if (!dlg) { return; }
  var form = document.getElementById('rd-form'), del = document.getElementById('rd-delete'), last = null;
  var f = { name: document.getElementById('rd-name'), cat: document.getElementById('rd-cat'), en: document.getElementById('rd-en'), zh: document.getElementById('rd-zh') };
  function open(data) {
    document.getElementById('rd-title').textContent = data ? 'Edit reply' : 'New reply';
    form.action = data ? data.action : '{{ url('/admin/library') }}';
    f.name.value = data ? data.title : ''; f.cat.value = data ? (data.category || '') : ''; f.en.value = data ? data.en : ''; f.zh.value = data ? data.zh : '';
    del.hidden = !data; if (data) { del.setAttribute('formaction', data.delete); }
    dlg.showModal(); f.name.focus();
  }
  document.getElementById('new-reply').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(b.dataset); }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  dlg.querySelectorAll('textarea').forEach(function (t) { t.addEventListener('focus', function () { last = t; }); });
  dlg.querySelectorAll('[data-var]').forEach(function (b) {
    b.addEventListener('click', function () { var t = last || f.en; var s = t.selectionStart || t.value.length; t.value = t.value.slice(0, s) + b.dataset.var + t.value.slice(t.selectionEnd || s); t.focus(); });
  });
})();
</script>
@endpush
