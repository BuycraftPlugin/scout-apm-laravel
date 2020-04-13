<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Router;
use Scoutapm\Events\Span\Span;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;
use Throwable;

final class ActionInstrument
{
    /** @var ScoutApmAgent */
    private $agent;

    /** @var FilteredLogLevelDecorator */
    private $logger;

    /** @var Router */
    private $router;

    public function __construct(ScoutApmAgent $agent, FilteredLogLevelDecorator $logger, Router $router)
    {
        $this->agent  = $agent;
        $this->logger = $logger;
        $this->router = $router;
    }

    /**
     * @return mixed
     *
     * @throws Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        $this->logger->debug('Handle ActionInstrument');

        return $this->agent->webTransaction(
            'unknown',
            /** @return mixed */
            function (Span $span) use ($request, $next) {
                try {
                    $response = $next($request);
                } catch (Throwable $e) {
                    $this->agent->tagRequest('error', 'true');
                    throw $e;
                }

                $span->updateName($this->automaticallyDetermineControllerName($request));

                return $response;
            }
        );
    }

    /**
     * Lumen uses a different router, so...
     */
    private function automaticallyDetermineControllerName(Request $request) : string
    {
        $name = 'unknown';

        try {
            $router = $this->router;
            $method = $request->getMethod();
            $pathInfo = $request->getPathInfo();

            $routes = $router->getRoutes();

            $matchedRoute = null;
            foreach ($routes as $route => $data) {
                $route = preg_replace("%\{.*?\}%", "([^/]+?)", $route);
                if (preg_match("%^{$route}$%", $method.$pathInfo)) {
                    $matchedRoute = $data;
                    break;
                }
            }
            if ($matchedRoute !== null) {
                $name = $matchedRoute['action']['as'] ?? $matchedRoute['action']['uses'];
            }
        } catch (Throwable $e) {
            $this->logger->debug(
                'Exception obtaining name of endpoint: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return 'Controller/' . $name;
    }
}
