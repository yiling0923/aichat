<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Booking\BookingRules;
use App\Booking\GoogleCalendar;
use App\Models\Practitioner;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owner-only: services, practitioners (with their Google Calendar), opening hours and booking rules.
 */
class BookingSetupController extends Controller
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function index(Request $request, GoogleCalendar $google): View
    {
        $business = $request->user()->business;

        return view('admin.booking-setup', [
            'active' => 'setup',
            'business' => $business,
            'services' => Service::query()->with('practitioners')->orderBy('sort')->orderBy('id')->get(),
            'practitioners' => Practitioner::query()->with('services')->orderBy('id')->get(),
            'rules' => BookingRules::for($business),
            'hours' => (array) ($business->settings['hours'] ?? []),
            'closedDates' => implode(', ', (array) ($business->settings['closed_dates'] ?? [])),
            'days' => self::DAYS,
            'googleOn' => $google->enabled(),
            'googleEmail' => $google->serviceAccountEmail(),
            'googleViaEnv' => (bool) (config('services.google_calendar.credentials') && is_file((string) config('services.google_calendar.credentials'))),
            'googleOwnKey' => is_file(GoogleCalendar::uploadedKeyPath()),
        ]);
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot_step_min' => ['required', 'integer', 'between:5,240'],
            'buffer_min' => ['required', 'integer', 'between:0,240'],
            'min_notice_hours' => ['required', 'integer', 'between:0,168'],
            'max_days_ahead' => ['required', 'integer', 'between:1,90'],
            'cancel_hours' => ['required', 'integer', 'between:0,168'],
            'closed_dates' => ['nullable', 'string', 'max:2000'],
            'hours' => ['array'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        $business = $request->user()->business;
        $settings = (array) $business->settings;

        $hours = [];
        foreach (self::DAYS as $day) {
            $open = $data['hours'][$day]['open'] ?? null;
            $close = $data['hours'][$day]['close'] ?? null;
            if ($open && $close && $open < $close) {
                $hours[$day] = [$open, $close];
            }
        }

        $settings['hours'] = $hours;
        $settings['closed_dates'] = array_values(array_filter(array_map('trim', explode(',', (string) ($data['closed_dates'] ?? ''))), fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)));
        $settings['booking'] = collect($data)->only(array_keys(BookingRules::DEFAULTS))->map(fn ($v) => (int) $v)->all();

        $business->update(['settings' => $settings]);

        return back()->with('status', __('Hours and booking rules saved.'));
    }

    public function storeService(Request $request): RedirectResponse
    {
        $service = Service::create($this->serviceData($request));
        $service->practitioners()->sync($this->practitionerIds($request));

        return back()->with('status', __('Service added.'));
    }

    public function updateService(Request $request, int $id): RedirectResponse
    {
        $service = Service::query()->findOrFail($id);
        $service->update($this->serviceData($request));
        $service->practitioners()->sync($this->practitionerIds($request));

        return back()->with('status', __('Service saved.'));
    }

    public function storePractitioner(Request $request): RedirectResponse
    {
        if (! app(Plans::class)->canAdd($request->user()->business, 'practitioners')) {
            return back()->withErrors(['name' => __('Your plan has reached its limit of practitioners. Upgrade under Plan & billing to add more.')]);
        }

        Practitioner::create($this->practitionerData($request));

        return back()->with('status', __('Practitioner added.'));
    }

    public function updatePractitioner(Request $request, int $id): RedirectResponse
    {
        Practitioner::query()->findOrFail($id)->update($this->practitionerData($request));

        return back()->with('status', __('Practitioner saved.'));
    }

    /** @return array<string, mixed> */
    private function serviceData(Request $request): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_zh' => ['nullable', 'string', 'max:100'],
            'duration_min' => ['required', 'integer', 'between:5,480'],
            'price' => ['nullable', 'numeric', 'between:0,1000000'],
        ]);

        return [
            'name' => array_filter(['en' => $data['name_en'], 'zh' => $data['name_zh'] ?? null]),
            'duration_min' => $data['duration_min'],
            'price' => $data['price'] ?? null,
            'active' => $request->boolean('active', true),
        ];
    }

    /** Only practitioners of this business (the scope enforces it). @return list<int> */
    private function practitionerIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('practitioners', []));

        return Practitioner::query()->whereIn('id', $ids)->pluck('id')->all();
    }

    /** @return array<string, mixed> */
    private function practitionerData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'google_calendar_id' => ['nullable', 'string', 'max:255'],
        ]);

        return ['name' => $data['name'], 'google_calendar_id' => ($data['google_calendar_id'] ?? null) ?: null, 'active' => $request->boolean('active', true)];
    }
}
