<div class="tabs mk-tabs">
    <a @class(['on' => $tab === 'automations']) href="{{ route('automations') }}">{{ __('Automations') }}</a>
    <a @class(['on' => $tab === 'broadcasts']) href="{{ route('broadcasts') }}">{{ __('Broadcasts') }}</a>
    <a @class(['on' => $tab === 'templates']) href="{{ route('templates') }}">{{ __('Message templates') }}</a>
    <a @class(['on' => $tab === 'activity']) href="{{ route('automations.activity') }}">{{ __('Activity log') }}</a>
</div>
