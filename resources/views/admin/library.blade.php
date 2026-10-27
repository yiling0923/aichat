@extends('admin.layout')
@section('title', 'Library')
@section('content')
    <h1>Library</h1>
    <p class="mute">Saved replies. In the Inbox, pick one from "Insert a saved reply" to paste it into your answer.
        @unless ($canEdit) Only an owner or manager can edit this list. @endunless</p>
    @foreach ($replies as $r)
        <form method="post" action="{{ url('/admin/library/'.$r->id) }}" class="card row">
            @csrf
            <label class="f"><span>Title</span><input type="text" name="title" value="{{ $r->title }}" @disabled(! $canEdit)></label>
            <label class="f" style="flex:2"><span>English</span><textarea name="body_en" rows="2" @disabled(! $canEdit)>{{ $r->body['en'] ?? '' }}</textarea></label>
            <label class="f" style="flex:2"><span>中文</span><textarea name="body_zh" rows="2" @disabled(! $canEdit)>{{ $r->body['zh'] ?? '' }}</textarea></label>
            @if ($canEdit)
                <button class="btn" type="submit">Save</button>
                <button class="btn danger" type="submit" formaction="{{ url('/admin/library/'.$r->id.'/delete') }}" onclick="return confirm('Delete this reply?')">Delete</button>
            @endif
        </form>
    @endforeach
    @if ($canEdit)
        <form method="post" action="{{ url('/admin/library') }}" class="card row">
            @csrf
            <label class="f"><span>New reply: title</span><input type="text" name="title" required></label>
            <label class="f" style="flex:2"><span>English</span><textarea name="body_en" rows="2" required></textarea></label>
            <label class="f" style="flex:2"><span>中文</span><textarea name="body_zh" rows="2"></textarea></label>
            <button class="btn primary" type="submit">Add reply</button>
        </form>
    @endif
@endsection
