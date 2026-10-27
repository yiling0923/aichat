@extends('admin.layout')
@section('title', 'Tags')
@section('content')
    <h1>Tags</h1>
    <p class="mute">Customer tags label people (VIP, facial fan). Chat tags label conversations (complaint, price question).</p>
    @foreach (['customer' => ['Customer tags', $customerTags, 'customers_count'], 'chat' => ['Chat tags', $chatTags, 'conversations_count']] as $type => [$title, $list, $count])
        <h2>{{ $title }}</h2>
        @foreach ($list as $t)
            <div class="card row">
                <form method="post" action="{{ url('/admin/tags/'.$t->id) }}" class="row" style="display:contents">
                    @csrf
                    <label class="f"><span>Name</span><input type="text" name="name" value="{{ $t->name }}"></label>
                    <label class="f" style="flex:0 0 70px"><span>Colour</span><input type="color" name="color" value="{{ $t->color }}"></label>
                    <span class="mute">{{ $t->$count }} used</span>
                    <button class="btn" type="submit">Save</button>
                </form>
                <form method="post" action="{{ url('/admin/tags/'.$t->id.'/merge') }}" class="inline">
                    @csrf
                    <select name="into" class="small">@foreach ($list as $o)@if ($o->id !== $t->id)<option value="{{ $o->id }}">{{ $o->name }}</option>@endif @endforeach</select>
                    <button class="btn small" type="submit" onclick="return confirm('Merge this tag into the chosen one?')">Merge into</button>
                </form>
                <form method="post" action="{{ url('/admin/tags/'.$t->id.'/delete') }}" class="inline" onsubmit="return confirm('Delete this tag?')">@csrf<button class="btn small danger">Delete</button></form>
            </div>
        @endforeach
        <form method="post" action="{{ url('/admin/tags') }}" class="card row">
            @csrf<input type="hidden" name="type" value="{{ $type }}">
            <label class="f"><span>New {{ $type }} tag</span><input type="text" name="name" required></label>
            <label class="f" style="flex:0 0 70px"><span>Colour</span><input type="color" name="color" value="#0f766e"></label>
            <button class="btn primary" type="submit">Add</button>
        </form>
    @endforeach
@endsection
