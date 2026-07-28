<?php
namespace Shugoi;

class Locales
{
    private const FR = [
        'rateLimitTitle' => 'Trop de requêtes',
        'rateLimitBody' => "Vous avez effectué trop de requêtes en peu de temps. Il reste %s avant de pouvoir réessayer.",
        'rateLimitBadge' => 'Rate Limit',
        'blockedTitle' => 'Accès bloqué',
        'blockedBadge' => 'Blocage',
        'tamperTitle' => 'Remplacement de contenu client détecté',
        'tamperBody' => "Nous avons remarqué que vous avez tenté de modifier manuellement le rendu client via les DevTools. Cette action n'est pas autorisée.",
        'devtoolsBody' => "L'utilisation des DevTools pour remplacer le contenu ou modifier les requêtes réseau a été détectée.",
        'retryInSeconds' => 'Il reste %ds avant de pouvoir réessayer.',
    ];
    private const EN = [
        'rateLimitTitle' => 'Too Many Requests',
        'rateLimitBody' => "You have made too many requests in a short time. %s remaining before you can try again.",
        'rateLimitBadge' => 'Rate Limit',
        'blockedTitle' => 'Access Blocked',
        'blockedBadge' => 'Blocked',
        'tamperTitle' => 'Client Content Replacement Detected',
        'tamperBody' => "We noticed you attempted to manually modify the client-side rendering via DevTools. This action is not permitted.",
        'devtoolsBody' => "Using DevTools to replace content or modify network requests has been detected.",
        'retryInSeconds' => 'Retry in %ds.',
    ];
    public static function get(string $locale, string $key, mixed ...$args): string
    {
        $messages = $locale === 'fr' ? self::FR : self::EN;
        $msg = $messages[$key] ?? $key;
        return empty($args) ? $msg : sprintf($msg, ...$args);
    }
}
