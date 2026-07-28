<?php
namespace Shugoi;

class BlockPage
{
    private const SITE = 'https://shugoi.com';

    public static function shield(string $locale, string $title, string $message, string $badge, ?string $host = null, ?int $remainingSeconds = null): string
    {
        $countdownScript = '';
        if ($remainingSeconds !== null) {
            $countdownScript = self::countdownScript($remainingSeconds);
        }
        $htmlLang = $locale;
        $htmlTitle = htmlspecialchars($title);
        $htmlBadge = htmlspecialchars($badge);
        $htmlHost = htmlspecialchars(($host ?: 'shugoi.com'));

        // Inject countdown span around the seconds in the message
        if ($remainingSeconds !== null) {
            $message = preg_replace('/(\d+)s/', '<span id=cd>$1s</span>', $message, 1);
        }
        $htmlDesc = $message;

        $fontFace = "@font-face{font-family:'Alex Brush';src:url(https://shugoi.com/alex-brush.woff2?v=2) format('woff2');font-display:swap}";
        $cssBody = "*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html,body{height:100%;background:#fcf9f5}body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;padding:1.2rem}";
        $cssCard = "#c{max-width:460px;width:100%;background:#fff;border:4px solid #000;border-radius:28px 6px 32px 10px;box-shadow:12px 12px 0 #000;padding:3rem 2.4rem 2.8rem;text-align:center}";
        $cssLogo = "#c .l{width:80px;height:80px;pointer-events:none;transform:rotate(-2.5deg);margin:0 auto .6rem;display:block}";
        $cssBrand = "#c .b{display:block;margin:0 auto .2rem;pointer-events:none;max-width:100%;height:auto}";
        $cssBdg = "#c .bdg{display:inline-block;border:2px solid #000;border-radius:10px 2px 14px 4px;padding:.3rem .9rem;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#E87090;margin-bottom:1.4rem}";
        $cssH2 = "#c h2{font-family:'Alex Brush',Georgia,\"Times New Roman\",serif;font-size:2.2rem;color:#E87090;font-weight:400;margin:0 auto .6rem}";
        $cssDesc = "#c p.desc{font-size:.9rem;color:#555;line-height:1.8;max-width:380px;margin:0 auto}";
        $cssFt = "#c p.ft{font-size:.55rem;color:#E87090;margin-top:1.8rem}";

        return '<!DOCTYPE html><html lang="' . $htmlLang . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
            . $fontFace . $cssBody . $cssCard . $cssLogo . $cssBrand . $cssBdg . $cssH2 . $cssDesc . $cssFt
            . '</style></head><body><div id=c>'
            . '<img src=https://shugoi.com/favicon.png alt class=l><img src=https://shugoi.com/brand.png alt class=b>'
            . '<div class=bdg>' . $htmlBadge . '</div>'
            . '<h2>' . $htmlTitle . '</h2>'
            . '<p class=desc>' . $htmlDesc . '</p>'
            . '<p class=ft>' . $htmlHost . ' · Shugoi</p>'
            . $countdownScript
            . '</div></body></html>';
    }

    private static function countdownScript(int $totalSeconds): string
    {
        return '<script>var s=' . $totalSeconds . ';var i=setInterval(function(){s--;var e=document.getElementById("cd");if(e){if(s<=0){e.innerHTML="0s";clearInterval(i);setTimeout(function(){location.reload()},500)}else{e.innerHTML=s+"s"}}},1000)</script>';
    }

    public static function blocked(array $ctx): string
    {
        $locale = $ctx['locale'] ?? 'en';
        return self::shield($locale, Locales::get($locale, 'blockedTitle'), Locales::get($locale, 'tamperBody'), Locales::get($locale, 'blockedBadge'), $ctx['host'] ?? null);
    }

    public static function rateLimit(array $ctx): string
    {
        $locale = $ctx['locale'] ?? 'en';
        $remaining = $ctx['remainingSeconds'] ?? 60;
        return self::shield($locale, Locales::get($locale, 'rateLimitTitle'), Locales::get($locale, 'rateLimitBody', self::formatTime($remaining, $locale)), Locales::get($locale, 'rateLimitBadge'), $ctx['host'] ?? null, $remaining);
    }

    public static function headless(array $ctx): string
    {
        $locale = $ctx['locale'] ?? 'en';
        return self::shield($locale, Locales::get($locale, 'blockedTitle'), Locales::get($locale, 'devtoolsBody'), Locales::get($locale, 'blockedBadge'), $ctx['host'] ?? null);
    }

    private static function formatTime(int $seconds, string $locale): string
    {
        if ($seconds >= 3600) {
            return sprintf('%dh %dmin', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
        }
        if ($seconds >= 60) {
            return sprintf('%d min %ds', intdiv($seconds, 60), $seconds % 60);
        }
        return "{$seconds}s";
    }
}
