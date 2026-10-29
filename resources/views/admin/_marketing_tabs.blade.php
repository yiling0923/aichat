<div class="tabs mk-tabs">
    <a @class(['on' => $tab === 'automations']) href="{{ route('automations') }}">Automations</a>
    <a @class(['on' => $tab === 'broadcasts']) href="{{ route('broadcasts') }}">Broadcasts</a>
    <a @class(['on' => $tab === 'templates']) href="{{ route('templates') }}">Message templates</a>
    <a @class(['on' => $tab === 'activity']) href="{{ route('automations.activity') }}">Activity log</a>
</div>
