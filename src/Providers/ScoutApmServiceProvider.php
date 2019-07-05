<?php

namespace Scoutapm\Laravel\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

use Scoutapm\Agent;
use Psr\Log\LoggerInterface;

use Scoutapm\Laravel\Events\ViewComposer;
use Scoutapm\Laravel\Events\ViewCreator;
use Scoutapm\Laravel\Providers\EventServiceProvider;
use Scoutapm\Laravel\Middleware\ActionInstrument;
use Scoutapm\Laravel\Middleware\MiddlewareInstrument;
use Scoutapm\Laravel\Middleware\SendRequestToScout;



class ScoutApmServiceProvider extends ServiceProvider
{
    public function register()
    {
        // No events currently mapped. May be needed in the future.
        // $this->app->register(EventServiceProvider::class);

        $this->app->singleton(Agent::class, function ($app) {
            return new Agent();
        });

        $this->app->alias(Agent::class, 'scoutapm');
    }

    public function boot(Kernel $kernel, Agent $agent, LoggerInterface $log)
    {
        $agent->setLogger($log);

        $this->installInstruments();
    }

    //////////////
    // This installs all the instruments right here. If/when the laravel
    // specific instruments grow, we should extract them to a dedicated
    // instrument manager as we add more.
    public function installInstruments() {
        // View::composer('*', ViewComposer::class);
        // View::creator('*', ViewCreator::class);

        DB::listen(function (QueryExecuted $query) use ($agent) {
            $startingTime = microtime(true) - ($query->time / 1000);
            $span = $agent->startSpan('SQL/Query', $startingTime);
            $span->tag('db.statement', $query->sql);
            $agent->stopSpan();

            // TODO: figure out how to capture code location in a generic way (also specifically for db calls)
            // From Laravel DebugBar - https://github.com/barryvdh/laravel-debugbar/blob/master/src/DataCollector/QueryCollector.php#L193
        });

        $kernel->prependMiddleware(MiddlewareInstrument::class);
        $kernel->pushMiddleware(ActionInstrument::class);

        // Must be outside any other scout
        // instruments. When this middleware's
        // terminate is called, it will complete the
        // request, and send it to the CoreAgent.
        $kernel->prependMiddleware(SendRequestToScout::class);
    }
}
