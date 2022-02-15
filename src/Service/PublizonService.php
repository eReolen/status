<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PublizonService
{
    private HttpClientInterface $client;
    private string $url;
    private int $retailerId;
    private string $retailerKeyCode;
    private string $clientId;

    /**
     * @param HttpClientInterface $client
     * @param string $bindPublizonUrl
     * @param int $bindPublizonRetailerId
     * @param string $bindPublizonRetailerKeyCode
     * @param string $bindPublizonClientId
     */
    public function __construct(HttpClientInterface $client, string $bindPublizonUrl, int $bindPublizonRetailerId, string $bindPublizonRetailerKeyCode, string $bindPublizonClientId)
    {
        $this->client = $client;
        $this->url = $bindPublizonUrl;
        $this->retailerId = $bindPublizonRetailerId;
        $this->retailerKeyCode = $bindPublizonRetailerKeyCode;
        $this->clientId = $bindPublizonClientId;
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
    public function getLibraryProfile()
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
}
