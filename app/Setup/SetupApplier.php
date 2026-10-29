<?php

namespace App\Setup;

use App\Ai\Knowledge;
use App\Bot\DefaultFlow;
use App\Bot\FlowRepository;
use App\Billing\Plans;
use App\Models\Business;
use App\Models\KnowledgeEntry;
use App\Models\Practitioner;
use App\Models\Service;
use App\Support\CurrentBusiness;

/** Turns what the owner approved into real data: services, answers for the AI, and the menu texts. */
class SetupApplier
{
    private const DAYS = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];

    private const DAYS_ZH = ['mon' => '周一', 'tue' => '周二', 'wed' => '周三', 'thu' => '周四', 'fri' => '周五', 'sat' => '周六', 'sun' => '周日'];

    public function __construct(private CurrentBusiness $current, private FlowRepository $flows, private Plans $plans) {}

    /** The starter menu without the made-up sample clinic content. */
    public static function neutralFlow(): array
    {
        $flow = DefaultFlow::make();
        $t = fn (string $en, string $zh): array => ['en' => $en, 'zh' => $zh];
        $flow['nodes']['prices']['text'] = $t('Our team will share prices here soon. Tap "Talk to staff" to ask now.', '价格信息即将更新。您可以点击“联系员工”直接询问。');
        $flow['nodes']['hours']['text'] = $t('Please ask our team for our opening hours.', '请联系我们的团队了解营业时间。');
        $flow['nodes']['location']['text'] = $t('Please ask our team for our address.', '请联系我们的团队了解地址。');
        unset($flow['nodes']['location']['link']);
        $flow['nodes']['promo']['text'] = $t('Ask our team about current promotions.', '请向我们的团队咨询当前优惠。');

        return $flow;
    }

    /**
     * @param  list<array{name_en: string, name_zh: string, duration_min: int, price: float|int|null}>  $services
     * @param  list<array{title: string, answer: string}>  $faqs
     * @return array{services: int, faqs: int}
     */
    public function apply(Business $business, array $services, array $faqs, ?string $address): array
    {
        return $this->current->runAs($business, function () use ($business, $services, $faqs, $address): array {
            $addedServices = 0;
            $sort = (int) Service::query()->max('sort');
            $existing = Service::query()->get()->map(fn (Service $s) => mb_strtolower($s->label('en')))->all();

            $practitioners = Practitioner::query()->get();
            if ($practitioners->isEmpty() && $this->plans->canAdd($business, 'practitioners')) {
                $practitioners = collect([Practitioner::create(['name' => 'Team'])]);
            }

            foreach ($services as $row) {
                $name = trim($row['name_en']);
                if ($name === '' || in_array(mb_strtolower($name), $existing, true)) {
                    continue;
                }
                $service = Service::create([
                    'name' => array_filter(['en' => $name, 'zh' => trim($row['name_zh'] ?? '')]),
                    'duration_min' => max(5, (int) $row['duration_min']),
                    'price' => $row['price'],
                    'sort' => ++$sort,
                ]);
                $service->practitioners()->sync($practitioners->pluck('id')->all());
                $existing[] = mb_strtolower($name);
                $addedServices++;
            }

            $addedFaqs = 0;
            foreach ($faqs as $faq) {
                if (trim($faq['title']) === '' || trim($faq['answer']) === '') {
                    continue;
                }
                KnowledgeEntry::create(['title' => trim($faq['title']), 'answer' => ['en' => trim($faq['answer'])], 'active' => true]);
                $addedFaqs++;
            }

            $this->updateMenuTexts($business, $address);

            return ['services' => $addedServices, 'faqs' => $addedFaqs];
        });
    }

    /** Rewrite the prices, hours and address screens of the menu (draft and published) from the real data. */
    public function updateMenuTexts(Business $business, ?string $address): void
    {
        $record = $this->flows->forBusiness($business);
        $currency = (string) ($business->settings['currency'] ?? 'RM');

        $services = Service::query()->where('active', true)->orderBy('sort')->get();
        $money = fn (Service $s) => $s->price !== null && (float) $s->price > 0 ? $currency.' '.rtrim(rtrim(number_format((float) $s->price, 2, '.', ''), '0'), '.') : null;
        $lines = $services->map(fn (Service $s) => '- '.$s->label('en').': '.($money($s) ?? 'ask us').' ('.$s->duration_min.' min)');
        $linesZh = $services->map(fn (Service $s) => '- '.$s->label('zh').'：'.($money($s) ?? '请咨询').'（'.$s->duration_min.' 分钟）');

        $hours = [];
        $hoursZh = [];
        foreach (self::DAYS as $key => $label) {
            $h = $business->settings['hours'][$key] ?? null;
            $open = is_array($h) && count($h) === 2;
            $hours[] = $label.' '.($open ? $h[0].' - '.$h[1] : 'closed');
            $hoursZh[] = self::DAYS_ZH[$key].' '.($open ? $h[0].' - '.$h[1] : '休息');
        }

        foreach (['draft', 'published'] as $which) {
            $flow = $record->{$which};
            if ($lines->isNotEmpty() && isset($flow['nodes']['prices'])) {
                $flow['nodes']['prices']['text'] = ['en' => "Our prices:\n".$lines->implode("\n"), 'zh' => "我们的价格：\n".$linesZh->implode("\n")];
            }
            if (isset($flow['nodes']['hours'])) {
                $flow['nodes']['hours']['text'] = ['en' => "We are open:\n".implode("\n", $hours), 'zh' => "营业时间：\n".implode("\n", $hoursZh)];
            }
            if ($address !== null && trim($address) !== '' && isset($flow['nodes']['location'])) {
                $flow['nodes']['location']['text'] = ['en' => 'We are at '.trim($address).'.', 'zh' => '我们的地址：'.trim($address).'。'];
                unset($flow['nodes']['location']['link']);
            }
            $record->{$which} = $flow;
        }

        $record->published_at = now();
        $record->save();
    }
}
