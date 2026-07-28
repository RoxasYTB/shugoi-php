<?php
namespace Shugoi;

class SkeletonGenerator
{
    public function __construct(
        private readonly TokenSigner $tokenSigner,
        private readonly ?Obfuscator $obfuscator = null,
    ) {}

    private static function jsStr(string $s): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
    }

    private static function blockCardHtml(): string
    {
        return "<!DOCTYPE html><head><meta charset=UTF-8><meta name=viewport content=width=device-width,initial-scale=1>"
            . "<style>@font-face{font-family:'Alex Brush';src:url(https://shugoi.com/alex-brush.woff2?v=2) format('woff2');font-display:swap}"
            . "*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html,body{height:100%;background:#fcf9f5}"
            . "body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;padding:1.2rem}"
            . "#c{max-width:460px;width:100%;background:#fff;border:4px solid #000;border-radius:28px 6px 32px 10px;box-shadow:12px 12px 0 #000;padding:3rem 2.4rem 2.8rem;text-align:center}"
            . "#c .l{width:80px;height:80px;pointer-events:none;transform:rotate(-2.5deg);margin:0 auto .6rem;display:block}"
            . "#c .b{display:block;margin:0 auto .2rem;pointer-events:none;max-width:100%;height:auto}"
            . "#c .bdg{display:inline-block;border:2px solid #000;border-radius:10px 2px 14px 4px;padding:.3rem .9rem;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#E87090;margin-bottom:1.4rem}"
            . "#c h2{font-family:'Alex Brush',Georgia,'Times New Roman',serif;font-size:2.2rem;color:#E87090;font-weight:400;margin:0 auto .6rem}"
            . "#c p.desc{font-size:.9rem;color:#555;line-height:1.8;max-width:380px;margin:0 auto}"
            . "#c p.ft{font-size:.55rem;color:#E87090;margin-top:1.8rem}"
            . "</style></head><body><div id=c>"
            . "<img src=https://shugoi.com/favicon.png class=l><img src=https://shugoi.com/brand.png class=b>"
            . "<div class=bdg>\"+(b||\"\")+\"</div><h2>\"+(t||\"\")+\"</h2><p class=desc>\"+(m||\"\")+\"</p>"
            . "<p class=ft>\"+location.hostname+\" \\u00b7 Shugoi</p></div></body>";
    }

    public function generate(
        string $token,
        array $guards,
        array $config,
        bool $restrictedAccess,
        string $locale,
        string $baseUrl,
        string $renderUrl,
        string $siteKey
    ): string {
        $winVars = "window.__sg_siteKey='{$siteKey}';window.__sg_baseUrl='{$baseUrl}';window.__sg_config=" . json_encode($config) . ";";
        if (!$restrictedAccess) {
            $winVars .= 'window.__sg_disableRestrictedAccess=true;';
        }

        $html = self::blockCardHtml();
        $showBlockFn = "window.__sg_showBlock=function(t,m,b){"
            . "document.open('text/html');document.write(\"{$html}\");document.close();};";

        $tamperTitle = self::jsStr(htmlentities(Locales::get($locale, 'tamperTitle'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $devtoolsMsg = self::jsStr(htmlentities(Locales::get($locale, 'devtoolsBody'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // No retry on error: 200+html=render, otherwise showBlock
        $rdFn = "function rd(p,n){"
            . "if(window.__sg_blocked||window.__sg_rendered){console.log('rd:early',{blocked:window.__sg_blocked,rendered:window.__sg_rendered});return;}"
            . "fetch(p+'?token={$token}&locale={$locale}').then(function(r){return r.json()})"
            . ".then(function(d){"
            . "console.log('rd:checks',{blocked:window.__sg_blocked,rendered:window.__sg_rendered,html:d.html,error:d.error});"
            . "var _ecr=(window.__sg_config?.detectionFlags?.enableContentReplacementCheck??window.__sg_config?.enableContentReplacementCheck);console.log('rd:ecr',_ecr);"
            . "if(window.__sg_blocked||window.__sg_rendered){console.log('rd:early2');return;}"
            . "if(d.html){console.log('rd:render');window.__sg_rendered=true;document.open('text/html');document.write(d.html);document.close();window.scrollTo(0,0)}"
            . "else if(d.error&&_ecr===true){console.log('rd:showBlock');window.__sg_showBlock('{$tamperTitle}','{$devtoolsMsg}','Tamper')}"
            . "else{console.log('rd:noaction',{_ecr})}})};";

        $gwFn = "var _gw=function(cb){if(window.__sg_guardsReady||window.__sg_blocked)cb();else setTimeout(function(){_gw(cb)},100)};"
            . "setTimeout(function(){_gw(function(){rd('{$renderUrl}',0)})},100);";

        $cleanupFn = "function _sgCl(){var g=[\"__sg_siteKey\",\"__sg_baseUrl\",\"__sg_config\",\"__sg_disableRestrictedAccess\",\"__sg_showBlock\",\"rd\",\"_sgCl\",\"_D\",\"_sgVerify\"];for(var i=0;i<g.length;i++){try{delete window[g[i]]}catch(e){window[g[i]]=undefined}}};";

        $combined = implode("\n", array_filter([$winVars, $guards['detect'] ?? '', $guards['guard'] ?? '', $showBlockFn, $rdFn, $gwFn, $cleanupFn]));

        if ($this->obfuscator) {
            $combined = $this->obfuscator->obfuscate($combined, $siteKey);
        }

        $encoded = $this->unicodeEncode($combined);

        $bootCode = "eval([...'" . $encoded . "'].map(function(x){return String.fromCodePoint(x.codePointAt(0)-917504)}).join(''))";

        return '<!DOCTYPE html><html lang="' . $locale . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Shugoi</title></head><body><script>' . $bootCode . '</script></body></html>';
    }

    private function unicodeEncode(string $code): string
    {
        $result = '';
        for ($i = 0; $i < strlen($code); $i++) {
            $result .= mb_chr(917504 + ord($code[$i]), 'UTF-8');
        }
        return $result;
    }
}
