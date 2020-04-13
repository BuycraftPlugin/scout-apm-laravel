<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Scoutapm\Laravel\Database\QueryListener;
use Scoutapm\ScoutApmAgent;
use function microtime;

class QueryExecutedListener
{
    /**
     * @var ScoutApmAgent
     */
    private $agent;

    public function __construct(ScoutApmAgent $agent)
    {
        $this->agent = $agent;
    }

    public function handle(QueryExecuted $queryExecuted)
    {
        (new QueryListener($this->agent))->__invoke($queryExecuted);
    }
}
