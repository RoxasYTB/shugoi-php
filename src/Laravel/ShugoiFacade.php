<?php
namespace Shugoi\Laravel;

use Illuminate\Support\Facades\Facade;

class ShugoiFacade extends Facade
{
    protected static function getFacadeAccessor(): string { return 'shugoi.middleware'; }
}
