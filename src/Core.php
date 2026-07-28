<?php

namespace Shugoi;

class Core
{
    private bool $validated = false;
    private bool $validationFailed = false;
    private float $lastValidationWarn = 0;
    private const VALIDATION_WARN_INTERVAL = 3600 * 3.6;

    public static function getBlockPage(): string
    {
        return "+---------------------------------------------+\n"
            . "|           BLOCKED BY SHUGOI                 |\n"
            . "+---------------------------------------------+\n"
            . "|  Bots, scrapers and headless clients        |\n"
            . "|  are blocked by Shugoi protection.          |\n"
            . "|                                             |\n"
            . "|  Use a standard browser to access           |\n"
            . "|  this site.                                 |\n"
            . "|                                             |\n"
            . "|  - contact: support@shugoi.com -            |\n"
            . "+---------------------------------------------+\n";
    }

    public function __construct(
        private readonly Config $config,
        private readonly ApiClient $api,
        private readonly ?ConfigCache $configCache = null,
        private readonly ?BotVerifier $botVerifier = null,
    ) {}

    public function ensureValidated(): void
    {
        if ($this->validated) return;
        if ($this->config->secret === null) {
            $this->validated = true;
            return;
        }
        try {
            $result = $this->api->validateKey();
            $this->validationFailed = !($result['valid'] ?? false);
            $this->validated = true;
        } catch (\Throwable $e) {
            $this->validationFailed = true;
            $this->validated = true;
        }
    }

    public function isAllowlisted(string $path): bool
    {
        foreach ($this->config->allowlist as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }

    public function isWhitelistedBot(string $ua): bool
    {
        foreach ($this->config->botWhitelist as $pattern) {
            if (preg_match($pattern, $ua)) return true;
        }
        return false;
    }

    /**
     * @param array{path: string, ua: string, ip: string, host?: string, acceptLanguage?: string, secFetchDest?: string, secFetchMode?: string} $ctx
     * @return array|null {block: true, status: int, contentType: string, body: string} or null to allow
     */
    public function evaluate(array $ctx): ?array
    {
        $this->ensureValidated();

        $path = $ctx['path'] ?? '/';
        $ua = $ctx['ua'] ?? '';
        $ip = $ctx['ip'] ?? '';

        if ($this->isAllowlisted($path)) return null;

        $config = $this->configCache?->get() ?? ['detectionFlags' => []];
        $flags = $config['detectionFlags'] ?? [];

        if ($flags['enableRateLimit'] ?? false) {
            $rl = $this->api->checkRateLimit($ip, ['host' => $ctx['host'] ?? '', 'path' => $path]);
            if (!($rl['allowed'] ?? true)) {
                $resetAt = $rl['resetAt'] ?? (time() + 60);
                // API returns resetAt in milliseconds — normalize to seconds
                if ($resetAt > 100000000000) $resetAt = (int)($resetAt / 1000);
                $remaining = max(1, $resetAt - time());
                $locale = LocaleResolver::resolve($this->config->locale, $ctx['acceptLanguage'] ?? null);
                return [
                    'block' => true,
                    'status' => 429,
                    'contentType' => 'text/html; charset=utf-8',
                    'body' => BlockPage::rateLimit([
                        'locale' => $locale,
                        'remainingSeconds' => $remaining,
                        'host' => $ctx['host'] ?? null,
                    ]),
                ];
            }
        }

        $enableHeadless = $flags['enableHeadlessCheck'] ?? true;
        if ($enableHeadless !== false) {
            foreach ($this->config->headlessPatterns as $pattern) {
                if (preg_match($pattern, $ua)) {
                    if ($this->isWhitelistedBot($ua)) {
                        $vb = $this->botVerifier?->verify($ua, $ip);
                        if ($vb !== false) break;
                    }
                    return [
                        'block' => true,
                        'status' => $this->config->blockStatus,
                        'contentType' => 'text/plain; charset=utf-8',
                        'body' => self::getBlockPage(),
                    ];
                }
            }
        }

        if ($enableHeadless !== false && preg_match('/Mozilla/i', $ua) && !$this->isWhitelistedBot($ua)) {
            $hasLanguage = !empty($ctx['acceptLanguage']);
            $hasSecFetch = !empty($ctx['secFetchDest']) || !empty($ctx['secFetchMode']);
            if (!$hasLanguage || !$hasSecFetch) {
                $locale = LocaleResolver::resolve($this->config->locale, $ctx['acceptLanguage'] ?? null);
                return [
                    'block' => true,
                    'status' => $this->config->blockStatus,
                    'contentType' => 'text/html; charset=utf-8',
                    'body' => BlockPage::headless(['locale' => $locale, 'host' => $ctx['host'] ?? null]),
                ];
            }
        }

        return null;
    }
}
