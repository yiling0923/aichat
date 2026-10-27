<aside class="side">
    <div class="ws">
        <span class="logo" style="width:32px;height:32px">{{ mb_substr($business->name, 0, 1) }}</span>
        <div><b>{{ $business->name }}</b><small>Staff area</small></div>
    </div>
    <div class="sec">Work</div>
    <a @class(['on' => $active === 'inbox']) href="{{ route('inbox') }}">Inbox</a>
    <a class="soon">Schedule (coming)</a>
    <a @class(['on' => $active === 'customers']) href="{{ route('customers') }}">Customers</a>
    @if (auth()->user()->role === 'owner')
        <div class="sec">Set up</div>
        <a @class(['on' => $active === 'menu']) href="{{ route('menu') }}">Menu builder</a>
    @endif
    <a class="soon">Settings (coming)</a>
    <div class="me">
        {{ auth()->user()->name }}<br><span style="opacity:.7">{{ auth()->user()->role }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
    </div>
</aside>
