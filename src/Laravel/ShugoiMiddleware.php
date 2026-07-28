<?php
namespace Shugoi\Laravel;

use Illuminate\Http\Request;
use Closure;
use Shugoi\Middleware as PsrMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Bridge\NyholmPsr7Bridge;

class ShugoiMiddleware
{
    public function __construct(private PsrMiddleware $middleware) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // Skip internal routes
        if (str_starts_with($request->path(), '__shugoi/')) {
            return $next($request);
        }

        $psrFactory = new Psr17Factory();
        $psrRequest = NyholmPsr7Bridge::fromLaravelRequest($request);

        $handler = new class($next) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(private $next) {}
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $laravelRequest = NyholmPsr7Bridge::toLaravelRequest($request);
                $response = ($this->next)($laravelRequest);
                return NyholmPsr7Bridge::fromLaravelResponse($response);
            }
        };

        $psrResponse = $this->middleware->process($psrRequest, $handler);
        return NyholmPsr7Bridge::toLaravelResponse($psrResponse);
    }
}
