<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdgangsplatformenStatsService implements StatsServiceInterface
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
