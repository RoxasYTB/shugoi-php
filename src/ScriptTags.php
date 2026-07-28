<?php
namespace Shugoi;

class ScriptTags
{
    public function __construct(
        private readonly Config $config,
        private readonly TokenSigner $tokenSigner,
        private readonly GuardCache $guardCache,
        private readonly ConfigCache $configCache,
        private readonly ?SkeletonGenerator $skeletonGenerator = null,
    ) {}

    public function generate(?string $locale = null): array
    {
        $locale = $locale ?? $this->config->locale ?? 'en';
        $guards = $this->guardCache->get();
        $whitelistConfig = $this->configCache->get();
        $ts = (int)(microtime(true) * 1000);
        $token = $this->tokenSigner->sign($ts);

        $skeletonGen = $this->skeletonGenerator ?? new SkeletonGenerator($this->tokenSigner);
        $skeleton = $skeletonGen->generate($token, $guards, $whitelistConfig, $this->config->restrictedAccess, $locale, $this->config->baseUrl);

        return [
            'guardDetect' => $skeleton,
            'guard' => '',
            'whitelistConfig' => $this->config->restrictedAccess ? '<script>window.__sg_disableRestrictedAccess=true;</script>' : '',
            'token' => $token,
        ];
    }
}
