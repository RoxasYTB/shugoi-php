<?php
namespace Shugoi;

class TokenSigner
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function sign(int $timestamp, ?string $secretOverride = null): string
    {
        $secret = $secretOverride ?? $this->config->getSigningSecret();
        $nonce = bin2hex(random_bytes(8));
        $payload = $this->config->siteKey . ':' . $timestamp . ':' . $nonce;
        $sig = hash_hmac('sha256', $payload, $secret);
        return $payload . ':' . $sig;
    }

    public function verify(string $token, ?string $secretOverride = null): ?array
    {
        $secret = $secretOverride ?? $this->config->getSigningSecret();
        $parts = explode(':', $token);
        if (count($parts) !== 4) return null;
        [$siteKey, $timestamp, $nonce, $sig] = $parts;
        $payload = $siteKey . ':' . $timestamp . ':' . $nonce;
        $expected = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $sig)) return null;
        return [
            'siteKey' => $siteKey,
            'timestamp' => (int)$timestamp,
            'nonce' => $nonce,
            'sig' => $sig,
        ];
    }
}
