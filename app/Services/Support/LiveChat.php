<?php

namespace App\Services\Support;

use App\Models\Store;
use App\Models\StoreUser;
use App\Models\Support\Message;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Support\ReplySuggester;
use App\Support\TeamAlert;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Live chat (G43): store owners chat with the Growvia team from any page. A chat is a support
 * ticket with channel "chat", so it shares the store's history, attachments and internal notes.
 * Both sides poll every few seconds over the normal web service; no socket server is needed.
 */
class LiveChat
{
    /** Quick topics, mapped to ticket categories. */
    public const TOPICS = ['setup' => 'Bundle or offer setup', 'extension' => 'Checkout blocks', 'billing' => 'Billing', 'technical' => 'Something is broken', 'other' => 'Something else'];

    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** Team members count as online while their console has checked in this recently. */
    public const ONLINE_SECONDS = 120;

    /** Email the team when a chat waits this long with nobody answering. */
    public const ALERT_MINUTES = 5;

    /** Close an answered chat after this long without a reply from the store owner. */
    public const IDLE_MINUTES = 30;

    private const SETTINGS_KEY = 'chat-settings:v1';

    public function __construct(private readonly SupportDesk $desk, private readonly EmailSender $mail) {}

    /** Business hours from Internal Admin › Live chat. */
    public static function settings(): array
    {
        $saved = Cache::rememberForever(self::SETTINGS_KEY, function () {
            try {
                $value = DB::table('platform_settings')->where('key', 'chat')->value('value');
            } catch (Throwable) {
                return [];
            }

            return $value ? (array) json_decode($value, true) : [];
        });

        return array_merge(['days' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00', 'timezone' => 'Asia/Kolkata'], $saved);
    }

    public static function saveSettings(array $values): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => 'chat'], ['value' => json_encode($values), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::SETTINGS_KEY);
    }

    public static function withinHours(?CarbonInterface $at = null): bool
    {
        $s = self::settings();
        $local = Carbon::parse($at ?? now())->setTimezone($s['timezone']);
        $time = $local->format('H:i');

        return in_array($local->dayOfWeekIso, array_map('intval', $s['days']), true) && $time >= $s['from'] && $time < $s['to'];
    }

    /** "Mon–Fri, 09:00–18:00 IST" */
    public static function hoursText(): string
    {
        $s = self::settings();
        $days = array_map('intval', $s['days']);
        sort($days);
        $consecutive = $days !== [] && end($days) - $days[0] === count($days) - 1;
        $label = match (true) {
            $days === [] => 'Closed',
            count($days) === 7 => 'Every day',
            $consecutive && count($days) > 2 => self::DAYS[$days[0]].'–'.self::DAYS[end($days)],
            default => implode(', ', array_map(fn ($d) => self::DAYS[$d], $days)),
        };

        return $label.', '.$s['from'].'–'.$s['to'].' '.now()->setTimezone($s['timezone'])->format('T');
    }

    public function teamOnline(): int
    {
        return User::where('is_admin', true)->whereNull('disabled_at')->where('chat_status', 'online')
            ->where('chat_seen_at', '>=', now()->subSeconds(self::ONLINE_SECONDS))->count();
    }

    /** online: someone is at the console; away: business hours but nobody at the console; offline: outside business hours. */
    public function status(): string
    {
        if (! self::withinHours()) {
            return 'offline';
        }

        return $this->teamOnline() > 0 ? 'online' : 'away';
    }

    /** The person's chat that is still open, or closed in the last half hour (so they can rate it). */
    public function current(Store $store, ?StoreUser $user): ?Ticket
    {
        return Ticket::where('store_id', $store->id)->where('channel', 'chat')->where('store_user_id', $user?->id)
            ->where(fn ($q) => $q->where('chat_state', '!=', 'closed')->orWhere('closed_at', '>=', now()->subMinutes(30)))
            ->latest('id')->first();
    }

    /** A message from the store owner: starts a chat, or continues the one that is open. */
    public function fromMerchant(Store $store, ?StoreUser $user, string $body, ?string $topic, ?string $page, array $files = []): Ticket
    {
        $chat = $this->current($store, $user);
        if ($chat && $chat->chat_state !== 'closed') {
            $this->desk->reply($chat, 'merchant', $body, $files, false, $user);
            $chat->forceFill(['merchant_seen_at' => now(), 'merchant_typing_at' => null, 'status' => 'open'])->save();

            return $chat;
        }

        $status = $this->status();
        $topic = array_key_exists((string) $topic, self::TOPICS) ? $topic : 'other';
        $chat = $this->desk->open($store, $user, [
            'subject' => 'Chat: '.(mb_strlen(trim($body)) > 70 ? mb_substr(trim($body), 0, 67).'…' : trim($body)),
            'body' => $body, 'category' => $topic === 'other' ? 'setup' : $topic, 'priority' => $topic === 'technical' ? 'high' : 'normal',
        ], $files);
        $chat->forceFill([
            'channel' => 'chat', 'chat_state' => $status === 'offline' ? 'offline' : 'waiting',
            'page' => $page ? mb_substr(parse_url($page, PHP_URL_PATH) ?: $page, 0, 255) : null, 'merchant_seen_at' => now(),
        ])->save();

        // With nobody at the console, tell the team straight away; otherwise only if nobody picks it up in time.
        if ($status !== 'online') {
            $this->alertTeam($chat, $status === 'offline' ? 'outside business hours' : 'nobody is online');
        }

        return $chat;
    }

    /** A reply or internal note from the team. The first reply takes the chat. */
    public function fromTeam(Ticket $chat, User $by, string $body, array $files = [], bool $internal = false): Message
    {
        $message = $this->desk->reply($chat, 'team', $body, $files, $internal, $by);
        if (! $internal) {
            $chat->forceFill([
                'first_reply_at' => $chat->first_reply_at ?? now(), 'assigned_to' => $chat->assigned_to ?? $by->id,
                'chat_state' => $chat->chat_state === 'closed' ? 'closed' : 'active', 'team_typing_at' => null,
            ])->save();
            $this->emailReplyIfAway($chat, $message);
        }

        return $message;
    }

    public function typing(Ticket $chat, string $who): void
    {
        $chat->forceFill([$who === 'team' ? 'team_typing_at' : 'merchant_typing_at' => now()])->save();
    }

    public function close(Ticket $chat, bool $sendTranscript = true): void
    {
        if ($chat->chat_state === 'closed') {
            return;
        }
        $chat->forceFill(['chat_state' => 'closed', 'closed_at' => now(), 'status' => 'resolved', 'resolved_at' => $chat->resolved_at ?? now()])->save();
        if ($sendTranscript) {
            $this->emailTranscript($chat);
        }
    }

    /** Turn a chat into a normal ticket, for problems that need more time. */
    public function makeTicket(Ticket $chat): void
    {
        $chat->forceFill(['channel' => 'ticket', 'chat_state' => null, 'closed_at' => now(), 'status' => 'open', 'subject' => preg_replace('/^Chat: /', '', $chat->subject)])->save();
    }

    public function rate(Ticket $chat, int $rating): void
    {
        $chat->forceFill(['rating' => $rating > 0 ? 1 : -1])->save();
    }

    /** Place in line among waiting chats (1 = next). */
    public function position(Ticket $chat): int
    {
        return Ticket::where('channel', 'chat')->where('chat_state', 'waiting')->where('id', '<', $chat->id)->count() + 1;
    }

    /** Expected wait in minutes, from how fast the team answered recent chats. */
    public function waitMinutes(Ticket $chat): int
    {
        $recent = Ticket::where('channel', 'chat')->whereNotNull('first_reply_at')->where('created_at', '>=', now()->subDays(30))
            ->latest('id')->limit(20)->get(['created_at', 'first_reply_at']);
        $average = $recent->isEmpty() ? 180 : $recent->avg(fn ($t) => max(30, $t->created_at->diffInSeconds($t->first_reply_at)));

        return max(1, (int) ceil($average * $this->position($chat) / max(1, $this->teamOnline()) / 60));
    }

    /** Everything the store owner's chat panel shows. */
    public function forMerchant(Store $store, ?StoreUser $user, bool $open = false): array
    {
        $status = $this->status();
        $chat = $this->current($store, $user);
        $payload = [
            'status' => $status, 'hours' => self::hoursText(),
            'statusText' => match ($status) {
                'online' => 'Online now. We usually reply in a few minutes.',
                'away' => 'We\'ll be with you soon. If you leave, we reply here and by email.',
                default => 'We\'re offline ('.self::hoursText().'). Leave a message and we\'ll reply by email.',
            },
            'topics' => collect(self::TOPICS)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
            'chat' => null, 'unread' => 0,
        ];
        if (! $chat) {
            return $payload;
        }

        $messages = $chat->messages()->where('internal', false)->with('attachments')->get();
        $lastTeam = (int) $messages->where('author', 'team')->max('id');
        if ($open) {
            $chat->forceFill(['merchant_seen_at' => now(), 'merchant_read_id' => max($chat->merchant_read_id, $lastTeam)])->save();
        }
        $payload['unread'] = $messages->where('author', 'team')->where('id', '>', $chat->merchant_read_id)->count();
        $waiting = in_array($chat->chat_state, ['waiting', 'offline'], true) && ! $chat->first_reply_at;
        $payload['chat'] = [
            'id' => $chat->id, 'state' => $chat->chat_state, 'rating' => $chat->rating,
            'typing' => $chat->team_typing_at && $chat->team_typing_at->gt(now()->subSeconds(6)),
            'position' => $chat->chat_state === 'waiting' ? $this->position($chat) : null,
            'wait' => $chat->chat_state === 'waiting' && $status !== 'offline' ? $this->waitMinutes($chat) : null,
            'articles' => $waiting ? app(ReplySuggester::class)->articles((self::TOPICS[$chat->category] ?? '').' '.($messages->first()?->body ?? '')) : [],
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id, 'team' => $m->author === 'team', 'body' => $m->body, 'at' => $m->created_at->toIso8601String(),
                'author' => $m->author === 'team' ? ($m->author_name ? strtok($m->author_name, ' ') : 'Growvia team') : 'You',
                'attachments' => $m->attachments->map(fn ($a) => ['filename' => $a->filename, 'kb' => (int) round($a->size / 1024),
                    'url' => route('app.support.attachment', ['attachment' => $a->id], false)])->values(),
            ])->values(),
        ];

        return $payload;
    }

    /**
     * Every minute: email the team about chats nobody picked up, and close answered chats that went quiet.
     *
     * @return array{alerted: int, closed: int}
     */
    public function sweep(): array
    {
        $alerted = 0;
        Ticket::with('store')->where('channel', 'chat')->where('chat_state', 'waiting')->whereNull('first_reply_at')->whereNull('alerted_at')
            ->where('created_at', '<=', now()->subMinutes(self::ALERT_MINUTES))->get()
            ->each(function (Ticket $chat) use (&$alerted) {
                $alerted += (int) $this->alertTeam($chat, 'waiting '.self::ALERT_MINUTES.' minutes with no reply');
            });

        $closed = 0;
        Ticket::where('channel', 'chat')->where('chat_state', 'active')->where('last_reply_by', 'team')
            ->where('last_reply_at', '<=', now()->subMinutes(self::IDLE_MINUTES))->get()
            ->each(function (Ticket $chat) use (&$closed) {
                $this->close($chat);
                $closed++;
            });

        return ['alerted' => $alerted, 'closed' => $closed];
    }

    private function alertTeam(Ticket $chat, string $why): bool
    {
        $chat->forceFill(['alerted_at' => now()])->save();
        $first = $chat->messages()->value('body');

        return TeamAlert::send(
            "Live chat from {$chat->store->shop_domain}: {$why}",
            "{$chat->store->shop_domain} started a chat ({$why}).\n\n\"".mb_substr((string) $first, 0, 500)."\"\n\nAnswer it: ".route('admin.chat', ['chat' => $chat->id]),
            'chat:'.$chat->id, 60,
        );
    }

    /** A team reply also goes by email when the store owner isn't looking at the chat. */
    private function emailReplyIfAway(Ticket $chat, Message $message): void
    {
        $to = $chat->requester?->email;
        $away = $chat->chat_state === 'offline' || ! $chat->merchant_seen_at || $chat->merchant_seen_at->lt(now()->subSeconds(self::ONLINE_SECONDS));
        if (! $to || ! $away || ! Cache::add('chat-email:'.$chat->id, true, now()->addMinutes(10))) {
            return;
        }
        $name = $chat->requester->first_name ?: 'there';
        $this->mail->send(OutgoingEmail::fromText($to, 'New reply from Growvia support', "Hi {$name},\n\n".$message->body
            ."\n\nReply in Growvia: open the app in your Shopify admin and press the chat button at the bottom-right.\n\nGrowvia Support",
            ['category' => 'chat_reply', 'storeId' => $chat->store_id]));
    }

    private function emailTranscript(Ticket $chat): void
    {
        $to = $chat->requester?->email;
        if (! $to) {
            return;
        }
        $lines = $chat->messages()->where('internal', false)->get()->map(fn (Message $m) => '['.$m->created_at->setTimezone($chat->store->timezone ?: 'UTC')->format('j M, H:i').'] '
            .($m->author === 'team' ? 'Growvia'.($m->author_name ? ' ('.strtok($m->author_name, ' ').')' : '') : 'You').":\n".$m->body);
        $this->mail->send(OutgoingEmail::fromText($to, 'Your chat with Growvia support', 'Hi '.($chat->requester->first_name ?: 'there').",\n\nHere is a copy of your chat. It is also in Growvia under Support.\n\n"
            .$lines->implode("\n\n")."\n\nGrowvia Support", ['category' => 'chat_transcript', 'storeId' => $chat->store_id]));
    }
}
