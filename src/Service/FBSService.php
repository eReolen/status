<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FBSService implements ServiceInterface
{
    private HttpClientInterface $client;

    /**
     * @param HttpClientInterface $client
     */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function stats(): array
    {
        // TODO: Implement stats() method.

        return [];
    }
}
