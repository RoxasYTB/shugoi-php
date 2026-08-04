<?php
namespace Shugoi;

/**
 * Injection de la notice de consentement dans le HTML rendu.
 * Parité avec injectNoticeScript (render.ts du module Node) : overlay bloquant z-index max,
 * MutationObserver anti-bypass (garde _applying + mo.takeRecords()), seul OK ferme
 * (ack serveur via POST /notice, lié au machineId). Les placeholders mid/sk/base sont
 * remplacés à l'injection.
 */
class Notice
{
    public const SCRIPT = <<<'JS'
<script>
(function(){
  var mid=window.__sg_mid||'';
  var sk=window.__sg_siteKey||'';
  if(!mid||!sk||window.__sg_noticeEnabled===false)return;
  var base=window.__sg_baseUrl||'';
  var origin=base.replace(/\/api\/v1\/?$/,'');
  var _OVERLAY_CSS='position:fixed!important;inset:0!important;z-index:2147483647!important;background:rgba(0,0,0,.6)!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:1.2rem!important;pointer-events:auto!important';
  var _CARD_CSS='background:#fff!important;border:4px solid #000!important;border-radius:28px 6px 32px 10px!important;box-shadow:14px 14px 0 #000!important;padding:0!important;max-width:720px!important;width:100%!important;text-align:center!important;font-family:Arial,sans-serif!important;display:flex!important;overflow:hidden!important';
  var _CARD_HTML='<div style="flex:0 0 320px;display:flex;align-items:center;justify-content:center;padding:1.5rem 1rem 1.5rem 3rem;overflow:hidden"><img src="'+origin+'/favicon.png" alt="" style="width:100%;height:auto;max-width:220px;pointer-events:none"></div><div style="flex:1;padding:1.6rem 1.8rem;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center"><img src="'+origin+'/brand-block.png" alt="Shugoi" style="display:block;margin:0 0 .3rem;pointer-events:none;max-width:100%;height:auto;max-height:40px"><div style="border:2px solid #000;display:inline-block;border-radius:8px 2px 12px 4px;padding:.2rem .6rem;font-size:.5rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#E87090;margin-bottom:.6rem">Protection anti-abus</div><p style="font-size:.8rem;color:#555;line-height:1.7;margin:0 .4rem .6rem;max-width:280px">Ce site utilise Shugoi pour se protéger contre les abus et la fraude. Des caractéristiques techniques de votre navigateur sont analysées pour détecter les scripts automatisés, Tor, les VPN et les environnements virtuels. Aucune donnée personnelle n\'est collectée.</p><div style="margin-top:.5rem"><button id="__sg_ok" style="background:#E87090;color:#fff;border:3px solid #000;border-radius:12px 3px 14px 5px;padding:.4rem 2rem;font-size:.8rem;font-weight:700;cursor:pointer">OK</button></div><div style="margin-top:.5rem;font-size:.5rem;color:#ccc"><a href="'+origin+'/legal/shugoi-notice" target="_blank" style="color:#E87090;text-decoration:underline">En savoir plus · shugoi.com</a></div></div>';
  var _closed=false;
  var mo=null;
  var _applying=false;
  function ack(){try{fetch(base+'/notice',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({machineId:mid,siteKey:sk}),keepalive:true,signal:AbortSignal.timeout(4000)}).catch(function(){})}catch(e){}}
  function buildOverlay(){
    var o=document.createElement('div');o.id='__sg_o';o.style.cssText=_OVERLAY_CSS;
    var c=document.createElement('div');c.id='__sg_cd';c.style.cssText=_CARD_CSS;
    c.innerHTML=_CARD_HTML;
    o.appendChild(c);return o;
  }
  function close(){_closed=true;try{if(mo)mo.disconnect()}catch(e){}var el=document.getElementById('__sg_o');if(el&&el.parentNode)el.parentNode.removeChild(el);document.body.style.overflow='';document.documentElement.style.overflow='';}
  function okHandler(){ack();close();}
  function rebind(){var b=document.getElementById('__sg_ok');if(b)b.onclick=okHandler;}
  function enforce(){
    if(_closed||_applying)return;
    _applying=true;
    try{
      var o=document.getElementById('__sg_o');
      if(!o){o=buildOverlay();document.documentElement.appendChild(o);}
      if(o.style.cssText!==_OVERLAY_CSS)o.style.cssText=_OVERLAY_CSS;
      var c=document.getElementById('__sg_cd');
      if(!c){o.innerHTML='';o.appendChild(buildOverlay().firstChild);}
      else{
        if(c.style.cssText!==_CARD_CSS)c.style.cssText=_CARD_CSS;
        if(c.innerHTML!==_CARD_HTML)c.innerHTML=_CARD_HTML;
      }
      rebind();
      if(document.body.style.overflow!=='hidden')document.body.style.overflow='hidden';
      if(document.documentElement.style.overflow!=='hidden')document.documentElement.style.overflow='hidden';
      try{
        if(o&&!o.__sgObserved){o.__sgObserved=true;mo&&mo.observe(o,{childList:true,subtree:true,attributes:true,characterData:true,attributeFilter:['style','class','id']});}
      }catch(e){}
    }finally{
      _applying=false;
      try{if(mo)mo.takeRecords();}catch(e){}
    }
  }
  function show(){
    _closed=false;
    var o=buildOverlay();document.documentElement.appendChild(o);
    document.body.style.overflow='hidden';document.documentElement.style.overflow='hidden';
    rebind();
    try{
      mo=new MutationObserver(function(){enforce();});
      mo.observe(document.documentElement,{childList:true});
      try{o.__sgObserved=true;mo.observe(o,{childList:true,subtree:true,attributes:true,characterData:true,attributeFilter:['style','class','id']});}catch(e){}
    }catch(e){}
  }
  function init(){if(document.body)show();else if(document.addEventListener)document.addEventListener('DOMContentLoaded',show);else setTimeout(init,50)}
  fetch(base+'/notice?machineId='+encodeURIComponent(mid)+'&siteKey='+encodeURIComponent(sk),{signal:AbortSignal.timeout(4000)}).then(function(r){return r.json()}).then(function(d){if(!d.acknowledged)init()}).catch(function(){init()});
})();
</script>
JS;

    /**
     * Injecte la notice dans le HTML rendu (avant </body>).
     * @param string $html HTML rendu
     * @param string $mid machineId du client
     * @param string $siteKey siteKey
     * @param string $baseUrl base URL de l'API (injectée : window.__sg_baseUrl est nettoyé
     *   par _sgCl côté client après ~1,5 s — sinon la notice appellerait /notice relatif
     *   et l'ack ne passerait jamais)
     */
    public static function inject(string $html, string $mid, string $siteKey, string $baseUrl = ''): string
    {
        $script = self::SCRIPT;
        $script = str_replace(
            "var mid=window.__sg_mid||'';",
            'var mid=' . json_encode($mid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "||'';",
            $script
        );
        $script = str_replace(
            "var sk=window.__sg_siteKey||'';",
            'var sk=' . json_encode($siteKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "||'';",
            $script
        );
        $script = str_replace(
            "var base=window.__sg_baseUrl||'';",
            'var base=' . json_encode($baseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "||window.__sg_baseUrl||'';",
            $script
        );
        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script . '</body>', $html);
        }
        return $html . $script;
    }

    /**
     * Anti-fuite du grant (parité injectReferrerPolicy de render.ts) : strict-origin-when-
     * cross-origin (PAS no-referrer — casserait les embeds YouTube 153).
     */
    public static function injectReferrerPolicy(string $html): string
    {
        $meta = '<meta name="referrer" content="strict-origin-when-cross-origin">';
        if (str_contains($html, '<head>')) {
            return str_replace('<head>', '<head>' . $meta, $html);
        }
        if (preg_match('/<html[^>]*>/', $html, $m)) {
            return str_replace($m[0], $m[0] . $meta, $html);
        }
        return $meta . $html;
    }
}
