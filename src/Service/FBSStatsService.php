<?php

namespace App\Service;

use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FBSStatsService implements StatsServiceInterface
{
    private HttpClientInterface $client;
    private MetricsService $metricsService;

    private string $FBSs2Url;
    private string $FBSs2Username;
    private string $FBSs2Password;
    private string $FBSApiUrl;
    private string $FBSApiUsername;
    private string $FBSApiPassword;

    /**
     * @param HttpClientInterface $client
     * @param MetricsService $metricsService
     * @param string $bindFBSsip2Url
     * @param string $bindFBSsip2Username
     * @param string $bindFBSsip2Password
     * @param string $bindFBSApiUrl
     * @param string $bindFBSApiUsername
     * @param string $bindFBSApiPassword
     */
    public function __construct(HttpClientInterface $client, MetricsService $metricsService, string $bindFBSsip2Url, string $bindFBSsip2Username, string $bindFBSsip2Password, string $bindFBSApiUrl, string $bindFBSApiUsername, string $bindFBSApiPassword)
    {
        $this->client = $client;
        $this->FBSs2Url = $bindFBSsip2Url;
        $this->FBSs2Username = $bindFBSsip2Username;
        $this->FBSs2Password = $bindFBSsip2Password;
        $this->metricsService = $metricsService;
        $this->FBSApiUrl = $bindFBSApiUrl;
        $this->FBSApiUsername = $bindFBSApiUsername;
        $this->FBSApiPassword = $bindFBSApiPassword;
    }

    /**
     * {@inheritDoc}
     */
    public function stats(): array
    {
        $exceptions = [];

        try {
            $stopwatch = new Stopwatch(true);
            $stopwatch->start('sip2');
            $this->sendSIP2Message();
            $event = $stopwatch->stop('sip2');
            $seconds = $event->getDuration() / 1000;

            $this->metricsService->histogram('fbs_sip2_duration_seconds', '', $seconds);
            $this->metricsService->gauge('fbs_sip2_up', 'Is FBS SIP2 service online', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('fbs_sip2_up', 'Is FBS SIP2 service online', 0);
            $exceptions[] = $exception;
        }

        try {
            $stopwatch = new Stopwatch(true);
            $stopwatch->start('rest');
            $this->sendRestRequest();
            $event = $stopwatch->stop('rest');
            $restSeconds = $event->getDuration() / 1000;

            $this->metricsService->histogram('fbs_rest_duration_seconds', '', $restSeconds);
            $this->metricsService->gauge('fbs_rest_up', 'Is FBS SIP2 service online', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('fbs_rest_up', 'Is FBS SIP2 service online', 0);
            $exceptions[] = $exception;
        }

        if (!empty($exceptions)) {
            throw array_pop($exceptions);
        }

        return [
            'sip2' => $seconds,
            'rest' => $restSeconds,
        ];
    }

    /**
     * Send FBS rest API request.
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function sendRestRequest(): void
    {
        $response = $this->client->request('POST', $this->FBSApiUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'password' => $this->FBSApiPassword,
                'username' => $this->FBSApiUsername,
            ],
        ]);

        $content = $response->getContent();
        $jsonResponse = json_decode($content, true);

        if (!isset($jsonResponse['sessionKey'])) {
            throw new \Exception('Session key not found', 500);
        }
    }

    /**
     * Send message to FBS via SIP2 protocol.
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function sendSIP2Message(): void
    {
        $response = $this->client->request('POST', $this->FBSs2Url, [
            'headers' => [
                'User-Agent' => 'eReolen status',
                'Content-Type' => 'application/xml',
            ],
            'body' => $this->sip2message(),
        ]);

        $content = $response->getContent();

        // Check for error message.
        preg_match('/.*<error>(.*)<\/error>.*/', $content, $matches);
        if (2 === count($matches)) {
            throw new \Exception($matches[1], '500');
        }

        preg_match('/.*<response>(98)YYYYYY.*/', $content, $matches);
        if (2 === count($matches) && 98 !== (int) $matches[1]) {
            throw new \Exception('SIP2 message response was not 98', '500');
        }
    }

    /**
     * Build SIP2 99 message.
     */
    private function sip2message(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
            <ns1:sip password="'.$this->FBSs2Password.'" login="'.$this->FBSs2Username.'" xsi:schemaLocation="http://axiell.com/Schema/sip.xsd" xmlns:ns1="http://axiell.com/Schema/sip.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
              <request>990xxx2.00</request>
            </ns1:sip>';
    }
}
