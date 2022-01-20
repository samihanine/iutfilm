<?php
namespace App\Service;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FilmAPI 
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function getDescription(string $name): string
    {
        $response = $this->client->request('GET', 'http://www.omdbapi.com?apikey=a33a50b3&t='.$name);
        $content = $response->toArray();

        $plot = "";

        if (isset($content["Error"]) == false) {
            $plot = $content["Plot"];
        }

        return $plot;
    }
}