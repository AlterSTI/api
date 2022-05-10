<?php
declare(strict_types=1);

class Api
{
    protected string $method    = '';
    public array $requestUri    = [];
    public array $requestParams = [];
    protected string $action    = '';
    protected string $title     = '';
    protected string $apiKey    = '';
    protected int $year         = 0;


    /**
     *
     */
    public function __construct(string $apiKey = 'k_z2lcome7') {
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");

        $this->requestUri = explode('?', trim($_SERVER['REQUEST_URI'],'?/'));
        $this->apiKey = $apiKey;

        $this->method = $_SERVER['REQUEST_METHOD'];
        if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
                $this->method = 'DELETE';
            } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
                $this->method = 'PUT';
            } else {
                throw new RuntimeException("Unexpected Header");
            }
        }
        if ($this->method !== 'GET') {
            throw new RuntimeException("Not permitted method");
        }

        $this->requestParams = $_REQUEST;
        $this->title    = (string)  ($this->clearString($this->requestParams['title']  ?? ''));
        $this->year     = (int)     ($this->clearString($this->requestParams['year']   ?? ''));

        if (empty($this->title)) {
            throw new RuntimeException('Not param title in request');
        }
    }

    protected function clearString(string $comment): string
    {
        $answer = $comment;

        $answer = trim($answer);
        $answer = strip_tags($answer);
        $answer = htmlspecialchars($answer);

        return $answer;
    }

    public function run() {

        if(array_shift($this->requestUri) !== 'movies'){
            throw new RuntimeException('API Not Found', 404);
        }
        $dataCache = new DataCache($this->title.$this->year);
        $getDataFromCache = $dataCache->initCacheData();
        if ($getDataFromCache) {
            $answer = $dataCache->getCacheData();
        } else {
            try {

                $filmId = $this->getFilmId();
                $titleData = $this->getTitleData($filmId);
                $rating = $this->getRatingData($filmId);
            } catch (RuntimeException $exception) {
                return $this->response($exception->getMessage(), 404);
            }

            $answer = [
                'title' => $titleData['title'] ?? '',
                'year' => $titleData['year'] ?? '',
                'directorList' => $titleData['directorList'] ?? '',
                'genreList' => $titleData['genreList'] ?? '',
                'rating' => $rating,
            ];
            $dataCache->updateCacheData($answer);
        }

        return $this->response($answer, 200);
    }

    protected function getFilmId(): ?string
    {
        $filmId = '';
        $filmIds = [];
        $movieRequestUrl = "https://imdb-api.com/en/API/SearchMovie/{$this->apiKey}/{$this->title}";
        $movieRequestUrl = $this->year > 0 ? $movieRequestUrl.' '.$this->year : $movieRequestUrl;
        $films           = $this->requester($movieRequestUrl);
        if (!array_key_exists('results', $films)) {
            throw new RuntimeException('Not found key results in answer');
        }
        $count           = count($films['results']);
        if ($count === 0 || ($count > 1 && empty($this->year))) {
            throw new RuntimeException('Not found Films or count > 1 and year  is empty');
        }
        if ($count >= 1 && !empty($this->year)) {
            foreach ($films['results'] as $filmData) {
                $yearDescription = "($this->year)";
                if (array_key_exists('description', $filmData) &&
                    ($filmData['description'] === $yearDescription) &&
                    $filmData['title'] === $this->title) {

                    $filmIds[] = $filmData['id'];

                }
            }
            if (count ($filmIds) !== 1) {
                throw new RuntimeException('More then one results or null result');
            }
            $filmId = $filmIds[0] ?? '';
        }

        if (empty($filmId)) {
            throw new RuntimeException('Film id is empty');
        }

        return $filmId;
    }

    protected function getTitleData($filmId): array
    {
        $movieRequestUrl = "https://imdb-api.com/en/API/Title/{$this->apiKey}/$filmId";
        $data = $this->requester($movieRequestUrl);
        if (empty($data)) {
            throw new RuntimeException('Film data is empty');
        }
        return $data;

    }

    protected function getRatingData($filmId): float
    {
        $movieRequestUrl = "https://imdb-api.com/en/API/Ratings/{$this->apiKey}/$filmId";
        $data = $this->requester($movieRequestUrl);

        return (float)($data['imDb'] ?? 0);
    }

    protected function response($data, $status = 500)
    {
        header("HTTP/1.1 " . $status . " " . $this->requestStatus($status));
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($data);
    }

    private function requestStatus($code): string
    {
        $status = [
            200 => 'OK',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ];
        return ($status[$code])?:$status[500];
    }

    protected function requester(string $url): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);
        $result = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return $this->isJson($result) && $responseCode === 200 ? json_decode($result, true) : [];
    }

    /**
     * Check string for json data.
     *
     * @param string $string
     *
     * @return bool
     */
    public function isJson(string $string): bool
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
