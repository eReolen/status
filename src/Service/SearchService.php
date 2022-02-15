<?php

/**
 * @file
 * Handle search at the data well to utilize hasCover relations.
 */

namespace App\Service;

use App\Exception\DataWellVendorException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class SearchService.
 */
class SearchService
{
    public const SEARCH_LIMIT = 50;

    private HttpClientInterface $client;

    private string $agency;
    private string $profile;
    private string $searchURL;
    private string $password;
    private string $user;

    /**
     * @param HttpClientInterface $httpClient
     *
     * @param string $bindDataWellAgency
     * @param string $bindDataWellProfile
     * @param string $bindDataWellUrl
     * @param string $bindDataWellUser
     * @param string $bindDataWellPassword
     */
    public function __construct(HttpClientInterface $httpClient, string $bindDataWellAgency, string $bindDataWellProfile, string $bindDataWellUrl, string $bindDataWellUser, string $bindDataWellPassword)
    {
        $this->client = $httpClient;

        $this->agency = $bindDataWellAgency;
        $this->profile = $bindDataWellProfile;
        $this->searchURL = $bindDataWellUrl;
        $this->user = $bindDataWellUser;
        $this->password = $bindDataWellPassword;
    }

    /**
     * Perform data well search for given ac source.
     *
     * @param string $query
     * @param int $offset
     *
     * @return array
     *
     * @throws DataWellVendorException
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function search(string $query, int $offset = 1): array
    {
        // Validate that the service configuration have been set.
        if (empty($this->searchURL) || empty($this->user) || empty($this->password)) {
            throw new DataWellVendorException('Missing data well access configuration');
        }

        $pidArray = [];

        try {
            $response = $this->client->request('POST', $this->searchURL, [
                'body' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:open="http://oss.dbc.dk/ns/opensearch">
                 <soapenv:Header/>
                 <soapenv:Body>
                    <open:searchRequest>
                       <open:query>' . $query . '</open:query>
                       <open:agency>' . $this->agency . '</open:agency>
                       <open:profile>' . $this->profile . '</open:profile>
                       <open:allObjects>0</open:allObjects>
                       <open:authentication>
                          <open:groupIdAut>775100</open:groupIdAut>
                          <open:passwordAut>' . $this->password . '</open:passwordAut>
                          <open:userIdAut>' . $this->user . '</open:userIdAut>
                       </open:authentication>
                       <open:objectFormat>dkabm</open:objectFormat>
                       <open:start>' . $offset . '</open:start>
                       <open:stepValue>' . $this::SEARCH_LIMIT . '</open:stepValue>
                       <open:allRelations>1</open:allRelations>
                    <open:relationData>uri</open:relationData>
                    <outputType>json</outputType>
                    </open:searchRequest>
                 </soapenv:Body>
              </soapenv:Envelope>',
            ]);

            $content = $response->getContent();
            $jsonResponse = json_decode($content, true);

            // Handle errors in the request.
            if (isset($jsonResponse['searchResponse']['error']['$'])) {
                throw new DataWellVendorException($jsonResponse['searchResponse']['error']['$']);
            }

            if (array_key_exists('searchResult', $jsonResponse['searchResponse']['result'])) {
                if ($jsonResponse['searchResponse']['result']['hitCount']['$']) {
                    $pidArray = $this->mergeData($jsonResponse);
                }

                // It seems that the "more" in the search result is always "false".
                $more = true;
            } else {
                $more = false;
            }
        } catch (ClientExceptionInterface $exception) {
            throw new DataWellVendorException($exception->getMessage(), $exception->getCode());
        }

        return [$pidArray, $more, $offset + self::SEARCH_LIMIT, $jsonResponse['searchResponse']['result']['statInfo']['time']['$']];
    }

    /**
     * Extract PIDs and matching cover urls from response.
     *
     * @param array $json
     *   Array of the json decoded data
     *
     * @return array
     *   Array of all pid => url pairs found in response
     */
    public function mergeData(array $json): array
    {
        $data = [];

        foreach ($json['searchResponse']['result']['searchResult'] as $item) {
            foreach ($item['collection']['object'] as $object) {
                $pid = $object['identifier']['$'];
                $data[$pid] = $object;
            }
        }

        return $data;
    }
}
