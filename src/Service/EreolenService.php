<?php

namespace App\Service;

use App\Exception\EreolenException;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EreolenService implements ServiceInterface
{
    private HttpClientInterface $client;
    private string $ereolenUrl;
    private string $ereolenGoUrl;
    private string $feedURI;
    private MetricsService $metricsService;

    /**
     * @param HttpClientInterface $client
     */
    public function __construct(HttpClientInterface $client, MetricsService $metricsService, string $bindEreolenUrl, string $bindEreolenGoUrl, string $bindEreolenFeed)
    {
        $this->client = $client;

        // Feeds

        $this->ereolenUrl = $bindEreolenUrl;
        $this->ereolenGoUrl = $bindEreolenGoUrl;
        $this->feedURI = $bindEreolenFeed;
        $this->metricsService = $metricsService;
    }

    public function stats(): array
    {
        $stopwatch = new Stopwatch(true);

        try {
            $stopwatch->start('ereolen');
            $this->getHeaders($this->ereolenUrl);
            $event = $stopwatch->stop('ereolen');
            $ereolen = $event->getDuration() / 1000;

            $this->metricsService->histogram('ereolen_headers_duration_seconds', '', $ereolen);
            $this->metricsService->gauge('ereolen_up', '', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('ereolen_up', '', 0);
        }

        try {
            $stopwatch->start('ereolengo');
            $this->getHeaders($this->ereolenGoUrl);
            $event = $stopwatch->stop('ereolengo');
            $ereolengo = $event->getDuration() / 1000;

            $this->metricsService->histogram('ereolengo_headers_duration_seconds', '', $ereolengo);
            $this->metricsService->gauge('ereolengo_up', '', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('ereolengo_up', '', 0);
        }

        try {
            $stopwatch->start('ereolenFeed');
            $ereolenCount = $this->testFeed($this->ereolenUrl.$this->feedURI);
            $event = $stopwatch->stop('ereolenFeed');
            $ereolenFeedSec = $event->getDuration() / 1000;

            $this->metricsService->histogram('ereolen_feed_duration_seconds', '', $ereolenFeedSec);
            $this->metricsService->gauge('ereolen_feed_total', '', $ereolenCount);
            $this->metricsService->gauge('ereolen_feed_up', '', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('ereolen_feed_up', '', 0);
        }

        try {
            $stopwatch->start('ereolenGoFeed');
            $ereolenGoCount = $this->testFeed($this->ereolenGoUrl.$this->feedURI);
            $event = $stopwatch->stop('ereolenGoFeed');
            $ereolenGoFeedSec = $event->getDuration() / 1000;

            $this->metricsService->histogram('ereolengo_feed_duration_seconds', '', $ereolenGoFeedSec);
            $this->metricsService->gauge('ereolengo_feed_total', '', $ereolenGoCount);
            $this->metricsService->gauge('ereolengo_feed_up', '', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('ereolengo_feed_up', '', 0);
        }

        return ['request' => [
            'ereolen' => $ereolen ?? 0,
            'ereolengo' => $ereolengo ?? 0,
        ], 'feed' => [
            'ereolen' => [
                'time' => $ereolenFeedSec ?? 0,
                'count' => $ereolenCount ?? 0,
            ],
            'ereolengo' => [
                'time' => $ereolenGoFeedSec ?? 0,
                'count' => $ereolenGoCount ?? 0,
            ],
        ]];
    }

    /**
     * @param string $url
     *
     * @return void
     *
     * @throws EreolenException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function getHeaders(string $url): void
    {
        $response = $this->client->request('HEAD', $url, []);
        $code = $response->getStatusCode();
        if (200 !== $code) {
            throw new EreolenException('Connection error', $code);
        }

        // Get content (even for head request) to block execution until request is completed.
        $response->getContent();
    }

    /**
     * @param $url
     *
     * @return int
     *
     * @throws EreolenException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function testFeed($url): int
    {
        $response = $this->client->request('GET', $url, []);

        if (200 !== $response->getStatusCode()) {
            throw new EreolenException('Connection error');
        }

        $content = $response->getContent();
        $jsonResponse = json_decode($content, true);
        if (empty($jsonResponse)) {
            throw new EreolenException('Empty feed');
        }

        return count($jsonResponse);
    }
}
