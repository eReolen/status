<?php

namespace App\Service;

use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PublizonStatsService implements StatsServiceInterface
{
    private HttpClientInterface $client;
    private string $url;
    private int $retailerId;
    private string $retailerKeyCode;
    private string $clientId;
    private MetricsService $metricsService;

    /**
     * @param HttpClientInterface $client
     * @param string $bindPublizonUrl
     * @param int $bindPublizonRetailerId
     * @param string $bindPublizonRetailerKeyCode
     * @param string $bindPublizonClientId
     */
    public function __construct(HttpClientInterface $client, MetricsService $metricsService, string $bindPublizonUrl, int $bindPublizonRetailerId, string $bindPublizonRetailerKeyCode, string $bindPublizonClientId)
    {
        $this->client = $client;
        $this->url = $bindPublizonUrl;
        $this->retailerId = $bindPublizonRetailerId;
        $this->retailerKeyCode = $bindPublizonRetailerKeyCode;
        $this->clientId = $bindPublizonClientId;
        $this->metricsService = $metricsService;
    }

    /**
     * Try getting library profile.
     *
     * @return false|\SimpleXMLElement
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function getLibraryProfile()
    {
        $response = $this->client->request('POST', $this->url, [
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://pubhub.dk/GetLibraryProfile',
            ],
            'body' => '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                  <soap:Body>
                    <GetLibraryProfile xmlns="http://pubhub.dk/">
                      <retailerid>'.$this->retailerId.'</retailerid>
                      <retailerkeycode>'.$this->retailerKeyCode.'</retailerkeycode>
                      <clientid>'.$this->clientId.'</clientid>
                    </GetLibraryProfile>
                  </soap:Body>
                </soap:Envelope>',
        ]);
        $content = $response->getContent();

        return simplexml_load_string($content);
    }

    /**
     * @throws \Exception
     */
    public function stats(): array
    {
        try {
            $stopwatch = new Stopwatch(true);
            $stopwatch->start('request');
            $this->getLibraryProfile();
            $event = $stopwatch->stop('request');
            $seconds = $event->getDuration() / 1000;

            $this->metricsService->histogram('publizon_duration_seconds', '', $seconds);
            $this->metricsService->gauge('publizon_up', 'Is Publizon service online', 1);
        } catch (\Exception $exception) {
            $this->metricsService->gauge('publizon_up', 'Is Publizon service online', 0);

            throw $exception;
        }

        return ['request' => $seconds];
    }
}
