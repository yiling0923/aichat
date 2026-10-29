<?php

namespace App\Support;

use App\Http\Controllers\ReportsController;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\QuickReply;
use App\Models\Task;
use Carbon\CarbonInterface;
use ZipArchive;

/**
 * Everything of one business as spreadsheet files (CSV). Every query goes through the business scope,
 * so a business can only ever export its own data. Times are written in the business's own time zone.
 */
class BusinessExport
{
    /** @var array<string, string> key => label */
    public const SETS = [
        'customers' => 'Customers',
        'bookings' => 'Bookings',
        'orders' => 'Orders',
        'conversations' => 'Chats',
        'messages' => 'Chat messages',
        'tasks' => 'Tasks',
        'replies' => 'Saved replies',
    ];

    public function __construct(private Business $business) {}

    private function when(?CarbonInterface $t): string
    {
        return $t === null ? '' : $t->copy()->setTimezone($this->business->timezone ?: 'UTC')->format('Y-m-d H:i');
    }

    /** @return array<string, int> how many rows each file would have */
    public function counts(): array
    {
        return app(CurrentBusiness::class)->runAs($this->business, fn () => [
            'customers' => Customer::query()->count(),
            'bookings' => Booking::query()->count(),
            'orders' => Order::query()->count(),
            'conversations' => Conversation::query()->where('channel', '!=', 'preview')->count(),
            'messages' => Message::query()->where('is_private', false)->whereHas('conversation', fn ($q) => $q->where('channel', '!=', 'preview'))->count(),
            'tasks' => Task::query()->count(),
            'replies' => QuickReply::query()->count(),
        ]);
    }

    /** @return array{0: list<string>, 1: iterable<list<mixed>>} header and rows (rows are produced a few at a time) */
    public function dataset(string $key): array
    {
        $run = fn (callable $f) => app(CurrentBusiness::class)->runAs($this->business, $f);

        return match ($key) {
            'customers' => [
                ['ID', 'Name', 'Phone', 'Email', 'Language', 'Lead level', 'Interest', 'Tags', 'Notes', 'First seen', 'Marketing OK'],
                $this->each(Customer::query()->with('tags')->orderBy('id'), $run, fn (Customer $c) => [
                    $c->id, $c->name, $c->phone, $c->email, $c->language, $c->score, $c->interest, $c->tags->pluck('name')->implode(', '),
                    (string) $c->getAttribute('notes'), $this->when($c->created_at), $c->opted_out_at ? 'No' : 'Yes',
                ]),
            ],
            'bookings' => [
                ['ID', 'Customer', 'Phone', 'Service', 'Person', 'Starts', 'Ends', 'Status', 'Booked on'],
                $this->each(Booking::query()->with(['customer', 'service', 'practitioner'])->orderBy('starts_at'), $run, fn (Booking $b) => [
                    $b->id, $b->customer?->name, $b->customer?->phone, $b->service?->label('en'), $b->practitioner?->name,
                    $this->when($b->starts_at), $this->when($b->ends_at), $b->status, $this->when($b->created_at),
                ]),
            ],
            'orders' => [
                ['Reference', 'Customer', 'Phone', 'Items', 'Amount', 'Status', 'Date', 'Notes'],
                $this->each(Order::query()->with('customer')->orderBy('ordered_at'), $run, fn (Order $o) => [
                    $o->reference, $o->customer?->name, $o->customer?->phone, is_array($o->items) ? json_encode($o->items, JSON_UNESCAPED_UNICODE) : $o->items,
                    $o->amount, $o->status, $this->when($o->ordered_at), $o->notes,
                ]),
            ],
            'conversations' => [
                ['ID', 'Channel', 'Customer', 'Phone', 'Status', 'Answered by', 'Tags', 'Started', 'Last message'],
                $this->each(Conversation::query()->where('channel', '!=', 'preview')->with(['customer', 'tags'])->orderBy('id'), $run, fn (Conversation $c) => [
                    $c->id, $c->channel, $c->customer?->name ?: $c->contact_name, $c->customer?->phone, $c->status, $c->mode === 'human' ? 'Staff' : 'Bot',
                    $c->tags->pluck('name')->implode(', '), $this->when($c->created_at), $this->when($c->last_message_at),
                ]),
            ],
            'messages' => [
                ['Chat ID', 'Time', 'From', 'Message'],
                $this->each(Message::query()->where('is_private', false)->whereHas('conversation', fn ($q) => $q->where('channel', '!=', 'preview'))->orderBy('id'), $run, fn (Message $m) => [
                    $m->conversation_id, $this->when($m->created_at), $m->sender_type, $m->body,
                ]),
            ],
            'tasks' => [
                ['Task', 'Due', 'Done', 'Assigned to', 'Customer'],
                $this->each(Task::query()->with(['assignee', 'customer'])->orderBy('id'), $run, fn (Task $t) => [
                    $t->title, $this->when($t->due_at), $this->when($t->done_at), $t->assignee?->name, $t->customer?->name,
                ]),
            ],
            'replies' => [
                ['Title', 'Group', 'English', 'Chinese'],
                $this->each(QuickReply::query()->orderBy('category')->orderBy('title'), $run, fn (QuickReply $r) => [
                    $r->title, $r->category, $r->body['en'] ?? '', $r->body['zh'] ?? '',
                ]),
            ],
            default => abort(404),
        };
    }

    /** @return \Generator<int, list<mixed>> */
    private function each($query, callable $run, callable $row): \Generator
    {
        $lastId = 0;
        $table = $query->getModel()->getTable();

        // Read in pieces of 500 so a big business never fills the memory.
        while (true) {
            $chunk = $run(fn () => (clone $query)->reorder()->where($table.'.id', '>', $lastId)->orderBy($table.'.id')->limit(500)->get()->all());
            if ($chunk === []) {
                return;
            }
            foreach ($chunk as $item) {
                yield $run(fn () => $row($item));
                $lastId = $item->id;
            }
        }
    }

    /** Writes one file as CSV into an open stream (UTF-8 with a mark so Excel shows Chinese correctly). */
    public function writeCsv(string $key, $out): void
    {
        [$header, $rows] = $this->dataset($key);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, array_map([ReportsController::class, 'safe'], $row));
        }
    }

    /** A zip with one CSV per list. @return string path of a temporary file */
    public function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aichat-export');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $temps = [];

        foreach (array_keys(self::SETS) as $key) {
            $tmp = tempnam(sys_get_temp_dir(), 'aichat-csv');
            $handle = fopen($tmp, 'w');
            $this->writeCsv($key, $handle);
            fclose($handle);
            $zip->addFile($tmp, $key.'.csv');
            $temps[] = $tmp;
        }

        $zip->addFromString('README.txt', "Export of {$this->business->name}\nCreated ".now($this->business->timezone ?: 'UTC')->format('Y-m-d H:i')." (".($this->business->timezone ?: 'UTC').")\nOpen the .csv files with Excel or Google Sheets. Private notes are not included.\n");
        $zip->close();
        array_map('unlink', $temps);

        return $path;
    }
}
