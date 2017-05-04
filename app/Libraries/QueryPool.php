<?php

namespace Application\Libraries;

use Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Class QueryPool
 */
class QueryPool
{
    /**
     * Store last error respose.
     * @var array|null
     */
    private $lastError;

    /**
     * Stored queries.
     * @var array[]
     */
    private $queries = [];

    /**
     * Callabres called after process.
     * @var callable[]
     */
    private $thenables = [];

    /**
     * Generate a error result from a response.
     * @param array|string $response Response data or error code.
     * @return array
     */
    static public function generateError($response): array
    {
        $responseError = is_array($response) ? $response['ErrorStatus'] : $response;

        return static::generateResult([ 'code' => $responseError ], false);
    }

    /**
     * Generate a result data.
     * @param array     $data      Result data.
     * @param bool|null $isSuccess Is result represents a success.
     * @return array
     */
    static public function generateResult($data, $isSuccess = null): array
    {
        return [
            'success' => $isSuccess !== false,
            'data'    => $data,
        ];
    }

    /**
     * Get a possible error response.
     * @param array $response Response data.
     * @return int|null
     */
    static public function getError($response): ?int
    {
        return $response['ErrorCode'] === 1 ? null : $response['ErrorCode'];
    }

    /**
     * Do a single query and return.
     * @param string   $uri        Query URI.
     * @param int|null $cacheHours How much hours cache should remains.
     * @return mixed
     */
    static public function unique($uri, $cacheHours = null)
    {
        /** @var mixed $outputResponse */
        $outputResponse = null;

        $queryPool = new static;
        $queryPool->addQuery($uri, $cacheHours, function ($response) use (&$outputResponse) {
            $outputResponse = $response;
        });

        if (!$queryPool->process()) {
            return $queryPool->getLastError();
        }

        return $outputResponse;
    }

    /**
     * Add a query.
     * @param string   $uri        Query URI.
     * @param int|null $cacheHours How much hours cache should remains.
     * @param callable $callable   A function to be called after load data.
     */
    public function addQuery($uri, $cacheHours = null, $callable = null): void
    {
        $queryURI      = 'https://bungie.net/platform' . $uri;
        $queryURICache = __CLASS__ . ';QueryCache.' . $uri;

        $this->queries[] = [
            'index'      => count($this->queries),
            'uri'        => $queryURI,
            'cacheName'  => $queryURICache,
            'cacheHours' => $cacheHours,
            'callable'   => $callable,
            'request'    => new Request('GET', $queryURI),
            'response'   => Cache::get($queryURICache),
        ];
    }

    /**
     * Returns the last error.
     * @return array|null
     */
    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    /**
     * Process all queries.
     */
    public function process(): bool
    {
        $this->lastError = null;

        $queryList = array_filter($this->queries, function ($query) {
            return !$query['response'];
        });

        if ($queryList) {
            $guzzleClient = new Client([
                'headers' => [ 'X-API-Key' => env('BUNGIE_KEY') ],
                'timeout' => 720,
            ]);

            /** @var Promise $promise */
            $promise = null;
            $pool    = new Pool($guzzleClient, array_pluck($queryList, 'request', 'index'), [
                'concurrency' => 5,
                'fulfilled'   => function (Response $response, $index) use (&$promise) {
                    $responseJson = json_decode($response->getBody()->getContents(), true);

                    if (QueryPool::getError($responseJson)) {
                        $this->lastError = static::generateError($responseJson);
                        $promise->cancel();

                        return;
                    }

                    $this->queries[$index]['response'] = $responseJson;

                    if ($this->queries[$index]['cacheHours'] !== null && !static::getError($responseJson)) {
                        Cache::put($this->queries[$index]['cacheName'], $responseJson, $this->queries[$index]['cacheHours'] * 60);
                    }
                },
                'rejected'    => function () use ($promise) {
                    $this->lastError = static::generateResult([ 'code' => 'ServerRequestException' ], false);
                    $promise->cancel();
                },
            ]);

            try {
                $promise = $pool->promise();
                $promise->wait();
            }
            catch (CancellationException $cancellationException) {
                return false;
            }
        }

        foreach ($this->queries as $query) {
            if ($query['callable']) {
                call_user_func($query['callable'], $query['response']);
            }
        }

        $queryArgs    = array_pluck($this->queries, 'response');
        $lastResponse = null;

        foreach ($this->thenables as $thenable) {
            $lastResponse = call_user_func_array($thenable, array_merge([ $lastResponse ], $queryArgs));
        }

        return true;
    }

    /**
     * Add a then (called after process sucessful all queries).
     * @param callable $callable A function to be called.
     */
    public function then($callable): void
    {
        $this->thenables[] = $callable;
    }
}
