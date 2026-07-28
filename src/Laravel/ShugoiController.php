<?php
namespace Shugoi\Laravel;

use Illuminate\Http\Request;
use Shugoi\HtmlStore;
use Shugoi\Config;

class ShugoiController
{
    public function __construct(private HtmlStore $htmlStore, private Config $config) {}

    public function render(Request $request)
    {
        $token = $request->query('token', '');
        if (empty($token)) return response()->json(['error' => 'missing_token'], 400);

        $entry = $this->htmlStore->retrieve($token);
        if ($entry !== null) return response()->json(['html' => $entry['html']]);

        $fresh = $this->htmlStore->hasFreshToken($this->config->siteKey);
        if ($fresh !== null) return response()->json(['html' => $fresh['html']]);

        return response()->json(['error' => 'not_found'], 404);
    }
}
