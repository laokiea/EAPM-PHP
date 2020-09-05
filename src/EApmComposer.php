<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <sashengpeng@blued.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace EApmPhp;

use EApmPhp\EApmMiddleware;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Transaction\EApmTransaction;

/**
 * Class EApmComposer
 * EApm-Php composer
 *
 */
class EApmComposer
{
    /**
     * EApmMiddleware object
     *
     */
    protected $middleware;

    /**
     * EApmDistributeTrace object
     *
     */
    protected $distributeTrace;

    /**
     * EApmTransaction object
     *
     */
    protected $transaction;

    /**
     * EApmComposer constructor.
     *
     */
    public function __construct(array $defaultMiddwareOpts = [])
    {
        // distribute trace
        $this->setDistributeTrace(new EApmDistributeTrace());
        $this->getDistributeTrace()->setComposer($this);
        // middleware
        $this->setMiddleware(new EApmMiddleware($defaultMiddwareOpts));
        $this->getMiddleware()->setDistributeTrace($this->getDistributeTrace());
        $this->getMiddleware()->parseDefaultMiddlewareOptions();
        //transaction
        $this->setTransaction(new EApmTransaction());
        $this->getTransaction()->setComposer($this);
    }

    /**
     * Set middleware object
     *
     * @var \EApmPhp\EApmMiddleware
     */
    public function setMiddleware(EApmMiddleware $middleware) : void
    {
        $this->middleware = $middleware;
    }

    /**
     * Get middleware object
     *
     * @return \EApmPhp\EApmMiddleware
     */
    public function getMiddleware() : EApmMiddleware
    {
        return $this->middleware;
    }

    /**
     * Set distribute trace object
     *
     * @var \EApmPhp\Trace\EApmDistributeTrace
     */
    public function setDistributeTrace(EApmDistributeTrace $distributeTrace) : void
    {
        $this->distributeTrace = $distributeTrace;
    }

    /**
     * Get distribute trace object
     *
     * @return \EApmPhp\Trace\EApmDistributeTrace
     */
    public function getDistributeTrace() : EApmDistributeTrace
    {
        return $this->distributeTrace;
    }

    /**
     * Set transaction object
     *
     * @var \EApmPhp\Transaction\EApmTransaction
     */
    public function setTransaction(EApmTransaction $transaction) : void
    {
        $this->transaction = $transaction;
    }

    /**
     * Get transaction object
     *
     * @return \EApmPhp\Transaction\EApmTransaction
     */
    public function getTransaction() : EApmTransaction
    {
        return $this->transaction;
    }

    /**
     * project
     *
     */
    public function EApmUse(callable $call = null) {
        $this->getMiddleware()->middlewareInvoke($call);
    }

    /**
     * Get elastic apm config value specifically
     *
     * @return string|null
     */
    public function getEApmConfig(string $configName) : ?string
    {
        if (!($config = ini_get("elastic_apm.$configName"))) {
            if (!($config = getenv("ELASTIC_APM_".strtoupper($configName)))) {
                $config = null;
            }
        }

        return $config;
    }

    /**
     * Get combined tracestate header
     * The new key-value pair MUST be added to the beginning (left) of the list.
     *
     * @return string
     */
    public function getCombinedTracestateHeader() : string
    {
        $tracestate = $this->getDistributeTrace()->getValidTracestate();
        $serviceName = $this->getEApmConfig("service_name");
        $this->getDistributeTrace()->addValidTracestate($serviceName,
            base64_encode($this->getTransaction()->getCurrentTransactionSpanId()));

        $getTracestateLength = function($tracestate):int {
            $tracestateLength = 0;
            array_walk($tracestate, function($v, $k) use($tracestateLength) {
                $tracestateLength += strlen("$k=$v,");
            });
            return $tracestateLength - 1;
        };

        while ($getTracestateLength($tracestate) > EApmDistributeTrace::TRACESTATE_COMBINED_HEADER_MAX_LENGTH) {
            array_pop($tracestate);
        }

        $combinedHeader = "";
        foreach ($tracestate as $memberName => $member) {
            $combinedHeader .= "$memberName=$member,";
        }
        $combinedHeader = substr($combinedHeader, 0, -1);

        return $combinedHeader;
    }
}