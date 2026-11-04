<?php

namespace App\Services\Mail;

/** One email to send. Without a from address, the platform's default sender is used. */
class OutgoingEmail
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public ?string $text = null,
        public ?string $toName = null,
        public ?string $fromName = null,
        public ?string $replyTo = null,
        public string $category = 'system',
        public ?int $storeId = null,
        // Files to attach: [['name' => 'x.pdf', 'mime' => 'application/pdf', 'data' => raw bytes], ...]
        public array $attachments = [],
    ) {}

    /** Attachments in a provider's JSON shape: $map(name, mime, base64) per file, or null without any. */
    public function attachmentsAs(callable $map): ?array
    {
        return $this->attachments ? array_map(fn ($a) => $map($a['name'], $a['mime'] ?? 'application/pdf', base64_encode($a['data'])), $this->attachments) : null;
    }

    /** Plain text (e.g. a workflow's email body) as simple, safe HTML. */
    public static function fromText(string $to, string $subject, string $text, array $extra = []): self
    {
        // Web addresses become links (after escaping, so nothing else turns into HTML).
        $link = fn (string $html) => preg_replace('#https://[^\s<>"]+#', '<a href="$0" style="color:#2c6ecb">$0</a>', $html);
        $paragraphs = array_map(fn ($p) => '<p style="margin:0 0 14px">'.$link(nl2br(e(trim($p)))).'</p>', preg_split('/\n\s*\n/', trim($text)) ?: []);
        $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#ffffff;color:#1a1a1a;font:15px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif">'
            .'<div style="max-width:560px;margin:0 auto">'.implode('', $paragraphs).'</div></body></html>';

        return new self($to, $subject, $html, $text, ...$extra);
    }

    /**
     * A designed email (lifecycle emails, G24): brand bar, heading, text, a checklist of steps
     * (done or still to do), one button and a footer. Every value is escaped.
     *
     * @param  array{heading: string, intro: string, steps?: list<array{0: string, 1: bool}>, cta?: array{0: string, 1: string}, help?: string, footer?: string, unsubscribe?: string}  $p
     */
    public static function designed(string $to, string $subject, array $p, array $extra = []): self
    {
        $e = fn ($v) => e((string) $v);
        $steps = '';
        foreach ($p['steps'] ?? [] as [$label, $done]) {
            $steps .= '<tr><td style="width:26px;padding:6px 0;vertical-align:top"><span style="display:inline-block;width:18px;height:18px;border-radius:50%;text-align:center;font:700 12px/18px Arial,sans-serif;'
                .($done ? 'background:#e3f4ea;color:#17663a">&#10003;' : 'border:2px solid #5b45f0;width:14px;height:14px;line-height:14px;color:#5b45f0">&nbsp;').'</span></td>'
                .'<td style="padding:6px 0;font-size:14px;color:'.($done ? '#55606b' : '#1b1830;font-weight:700').'">'.$e($label).'</td></tr>';
        }
        $html = '<!doctype html><html><body style="margin:0;padding:24px 12px;background:#f4f4f8;font:15px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;color:#1b1830">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e6e4ef;border-radius:14px">'
            .'<tr><td style="padding:18px 24px;border-bottom:1px solid #f0eef5;font:700 17px Arial,sans-serif;color:#1b1830"><span style="display:inline-block;width:22px;height:22px;margin-right:8px;border-radius:6px;background:#5b45f0;vertical-align:-5px"></span>Growvia</td></tr>'
            .'<tr><td style="padding:24px">'
            .'<h1 style="margin:0 0 10px;font:700 22px/1.25 Arial,sans-serif;color:#1b1830">'.$e($p['heading']).'</h1>'
            .'<p style="margin:0 0 16px">'.nl2br($e($p['intro'])).'</p>'
            .($steps !== '' ? '<p style="margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b6880">Your setup</p><table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px">'.$steps.'</table>' : '')
            .(! empty($p['cta']) ? '<p style="margin:0 0 14px"><a href="'.$e($p['cta'][1]).'" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#5b45f0;color:#ffffff;font-weight:700;text-decoration:none">'.$e($p['cta'][0]).'</a></p>' : '')
            .(! empty($p['help']) ? '<p style="margin:0;font-size:13px;color:#55606b">'.$e($p['help']).'</p>' : '')
            .'</td></tr>'
            .'<tr><td style="padding:14px 24px;border-top:1px solid #f0eef5;font-size:12px;color:#6b6880">'.$e($p['footer'] ?? '')
            .(! empty($p['unsubscribe']) ? ' <a href="'.$e($p['unsubscribe']).'" style="color:#4a36d6">'.$e($p['unsubscribe_label'] ?? 'Stop setup tips').'</a>' : '').'</td></tr>'
            .'</table></td></tr></table></body></html>';
        $text = $p['heading']."\n\n".$p['intro']."\n\n"
            .implode("\n", array_map(fn ($s) => ($s[1] ? '[x] ' : '[ ] ').$s[0], $p['steps'] ?? []))
            .(! empty($p['cta']) ? "\n\n{$p['cta'][0]}: {$p['cta'][1]}" : '').(! empty($p['help']) ? "\n\n{$p['help']}" : '')
            ."\n\n".($p['footer'] ?? '').(! empty($p['unsubscribe']) ? "\n".($p['unsubscribe_label'] ?? 'Stop setup tips').": {$p['unsubscribe']}" : '');

        return new self($to, $subject, $html, trim($text), ...$extra);
    }
}
