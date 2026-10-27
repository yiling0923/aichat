@extends('admin.layout')
@section('title', 'Tags')
@section('content')
    <h1>Tags</h1>
    <p class="mute">Customer tags label people (VIP, first visit). Chat tags label conversations (complaint, price question).</p>

    <div class="cols tags-cols">
        @foreach (['customer' => ['Customer tags', $customerTags, 'customers_count'], 'chat' => ['Chat tags', $chatTags, 'conversations_count']] as $type => [$title, $list, $count])
            <section class="card">
                <h3>{{ $title }} <span class="mute">({{ $list->count() }})</span></h3>

                <form method="post" action="{{ url('/admin/tags') }}" class="tag-add">
                    @csrf<input type="hidden" name="type" value="{{ $type }}">
                    <input type="color" name="color" value="#0f766e" aria-label="Colour">
                    <input type="text" name="name" placeholder="New {{ $type }} tag" required>
                    <button class="btn primary" type="submit">Add</button>
                </form>

                <div class="tag-list">
                    @forelse ($list as $t)
                        <details class="tag-item">
                            <summary>
                                <span class="chip" style="background:{{ $t->color }}">{{ $t->name }}</span>
                                <span class="mute">{{ $t->$count }} {{ $t->$count === 1 ? 'use' : 'uses' }}</span>
                                <span class="edit-link">Edit</span>
                            </summary>
                            <div class="tag-edit">
                                <form method="post" action="{{ url('/admin/tags/'.$t->id) }}" class="tag-add">
                                    @csrf
                                    <input type="color" name="color" value="{{ $t->color }}" aria-label="Colour">
                                    <input type="text" name="name" value="{{ $t->name }}" required>
                                    <button class="btn" type="submit">Save</button>
                                </form>
                                @if ($list->count() > 1)
                                    <form method="post" action="{{ url('/admin/tags/'.$t->id.'/merge') }}" class="tag-add">
                                        @csrf
                                        <span class="mute">Merge into</span>
                                        <select name="into">@foreach ($list as $o)@if ($o->id !== $t->id)<option value="{{ $o->id }}">{{ $o->name }}</option>@endif @endforeach</select>
                                        <button class="btn" type="submit" onclick="return confirm('Merge this tag into the chosen one? Everyone with it gets the other tag.')">Merge</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ url('/admin/tags/'.$t->id.'/delete') }}" onsubmit="return confirm('Delete this tag?')">
                                    @csrf<button class="btn danger small" type="submit">Delete tag</button>
                                </form>
                            </div>
                        </details>
                    @empty
                        <p class="mute" style="padding:12px 0">No {{ $type }} tags yet.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
@endsection
