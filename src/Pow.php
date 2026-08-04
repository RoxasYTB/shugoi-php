<?php
namespace Shugoi;

/**
 * Proof-of-work anti-curl + cookies HMAC — parité avec core.ts (module Node) :
 *   salt = HMAC(secret, ts)
 *   proof = "ts:nonce" où SHA256(salt:nonce) a >= POW_DIFFICULTY bits à zéro en tête.
 *   __sg_ok        : ts:HMAC(secret, "sg_ok:ts")         — 30 jours, saute le pre-flight PoW
 *   __sg_authorized: ts:HMAC(secret, "sg_authorized:ts") — 120 s, protège les assets /assets/*
 */
class Pow
{
    public function __construct(private readonly Config $config) {}

    public function difficulty(): int
    {
        return $this->config->powDifficulty;
    }

    public function ttlMs(): int
    {
        return $this->config->powTtlMs;
    }

    public function secret(): string
    {
        try {
            return $this->config->getSigningSecret();
        } catch (\RuntimeException) {
            return '';
        }
    }

    /** Génère le challenge à injecter (window.__sg_pow). */
    public function challenge(): array
    {
        $ts = time();
        return ['ts' => $ts, 'salt' => $this->salt($ts), 'difficulty' => $this->difficulty()];
    }

    /** Vérifie un proof "ts:nonce" (fenêtre @ttlMs, comptage de bits CORRIGÉ). */
    public function isValid(string $proof): bool
    {
        if ($proof === '' || $this->secret() === '') return false;
        $sep = strpos($proof, ':');
        if ($sep <= 0) return false;
        $tsStr = substr($proof, 0, $sep);
        $solution = substr($proof, $sep + 1);
        if ($solution === false || $solution === '') return false;
        $ts = (int)$tsStr;
        if ($ts <= 0) return false;
        if (abs(($this->nowMs() - $ts * 1000)) > $this->ttlMs()) return false;

        $digest = hash('sha256', $this->salt($tsStr) . ':' . $solution);
        return $this->leadingZeroBits($digest) >= $this->difficulty();
    }

    /**
     * Nombre de bits à zéro en tête (comptage CORRIGÉ, audit 2026-08-03).
     * L'ancien comptage (décimal→binaire sans zéros de tête) sous-comptait les zéros
     * internes du premier nibble non-nul (`3` → '11' → 0 au lieu de 2). Ce comptage est
     * exact et DOIT rester synchrone avec core.ts (isPowValid), le challenge JS et le guard.
     */
    public function leadingZeroBits(string $hex): int
    {
        $leading = 0;
        $len = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $nib = hexdec($hex[$i]);
            if ($nib === 0) {
                $leading += 4;
                continue;
            }
            $leading += ($nib & 8) ? 0 : (($nib & 4) ? 1 : (($nib & 2) ? 2 : 3));
            break;
        }
        return $leading;
    }

    /** Valeur du cookie __sg_ok (HMAC serveur, 30 j). */
    public function sgOkValue(): string
    {
        $ts = time();
        return $ts . ':' . hash_hmac('sha256', 'sg_ok:' . $ts, $this->secret());
    }

    public function isSgOkValid(string $cookieVal): bool
    {
        if ($this->secret() === '' || $cookieVal === '') return false;
        $sep = strpos($cookieVal, ':');
        if ($sep <= 0) return false;
        $tsStr = substr($cookieVal, 0, $sep);
        $sig = substr($cookieVal, $sep + 1);
        $ts = (int)$tsStr;
        if ($ts <= 0) return false;
        if ($this->nowMs() - $ts * 1000 > $this->config->powOkTtlMs) return false;
        if ($ts * 1000 > $this->nowMs() + 60_000) return false;
        return hash_equals(hash_hmac('sha256', 'sg_ok:' . $tsStr, $this->secret()), $sig);
    }

    /** String Set-Cookie pour __sg_ok (posé par le middleware après une preuve valide). */
    public function sgOkCookie(string $proof): ?string
    {
        if (!$this->isValid($proof)) return null;
        $secure = $this->isProduction() ? '; Secure' : '';
        return '__sg_ok=' . $this->sgOkValue()
            . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . intdiv($this->config->powOkTtlMs, 1000) . $secure;
    }

    public function sgAuthorizedValue(): string
    {
        $ts = time();
        return $ts . ':' . hash_hmac('sha256', 'sg_authorized:' . $ts, $this->secret());
    }

    public function isSgAuthorizedValid(string $cookieVal): bool
    {
        if ($this->secret() === '' || $cookieVal === '') return false;
        $sep = strpos($cookieVal, ':');
        if ($sep <= 0) return false;
        $tsStr = substr($cookieVal, 0, $sep);
        $sig = substr($cookieVal, $sep + 1);
        $ts = (int)$tsStr;
        if ($ts <= 0) return false;
        if ($this->nowMs() - $ts * 1000 > 120_000) return false;
        if ($ts * 1000 > $this->nowMs() + 60_000) return false;
        return hash_equals(hash_hmac('sha256', 'sg_authorized:' . $tsStr, $this->secret()), $sig);
    }

    public function sgAuthorizedCookie(): string
    {
        $secure = $this->isProduction() ? '; Secure' : '';
        return '__sg_authorized=' . $this->sgAuthorizedValue()
            . '; Path=/; HttpOnly; SameSite=Strict; Max-Age=120' . $secure;
    }

    private function salt(string $ts): string
    {
        return hash_hmac('sha256', $ts, $this->secret());
    }

    private function nowMs(): int
    {
        return (int)(microtime(true) * 1000);
    }

    private function isProduction(): bool
    {
        $env = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? null) ?: ($_SERVER['NODE_ENV'] ?? null) ?: getenv('NODE_ENV');
        return $env === 'production';
    }
}
