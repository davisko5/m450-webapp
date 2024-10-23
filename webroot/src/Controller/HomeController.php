<?php

namespace App\Controller;

use App\Service\DB;
use GuzzleHttp\Client;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


/**
 * Diese Controller-Klasse liefert die Demo-Webseite und die zugehörigen Endpoints für
 * die Wetter-Demo-App.
 * 
 * Dieser Controller ist ABSICHTLICH "grausam" programmiert:
 * 
 * - riesige, unübersichtliche Methode(n)
 * - kein Separation of Concern
 * - Config-Daten direkt im Code
 * - Spaghetti-Code 
 * - fast untestbar
 * 
 * 
 * Ziel ist, dass diese Klasse im Verlauf des Moduls M450 auseinandergenommen und testbar
 * gemacht wird.
 * 
 * @package App\Controller
 */
class HomeController {
    
    public function getInputParams(ServerRequestInterface $request): array {
        return [
            'mode' => $request->getQueryParams()['mode'] ?? 'historic',
            'zip' => $request->getQueryParams()['zip'] ?? null,
            'date' => $request->getQueryParams()['date'] ?? null,
            'time' => $request->getQueryParams()['time'] ?? null
        ];
    }

    private function getWeatherDataFromApi(array $params): ?array {
        $apiKey = getenv('OPENWEATHER_KEY');
        $apiUrl = 'https://api.openweathermap.org/data/2.5/weather';
        $country = 'CH';
        $client = new Client();
        $response = $client->get($apiUrl, [
            'query' => [
                'zip' => "{$params['zip']},{$country}",
                'units' => 'metric',
                'lang' => 'de',
                'appid' => $apiKey
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode((string)$response->getBody());
            return [
                'ts' => date(DATE_W3C, $data->dt ?? null),
                'city' => $data->name ?? null,
                'longitude' => $data->coord->lon ?? null,
                'latitude' => $data->coord->lat ?? null,
                'description' => $data->weather[0]->description ?? null,
                'icon' => $data->weather[0]->icon ?? null,
                'temp' => $data->main->temp ?? null,
                'temp_feels_like' => $data->main->feels_like ?? null,
                'pressure' => $data->main->pressure ?? null,
                'humidity' => $data->main->humidity ?? null,
                'wind_speed' => $data->wind->speed ?? null,
                'wind_degree' => $data->wind->deg ?? null,
                'clouds_percentage' => $data->clouds->all ?? null,
                'sunrise' => date(DATE_W3C, $data->sys->sunrise ?? 0),
                'sunset' => date(DATE_W3C, $data->sys->sunset ?? 0)
            ];
        }
        return null;
    }

    private function getAirDataFromApi(array $params): ?array {
        $apiKey = getenv('OPENWEATHER_KEY');
        $geocodingApiUrl = 'http://api.openweathermap.org/geo/1.0/zip';
        $airPollutionApiUrl = 'http://api.openweathermap.org/data/2.5/air_pollution';
        $country = 'CH';
        $client = new Client();

        // Koordinaten ermitteln
        $response = $client->get($geocodingApiUrl, [
            'query' => [
                'zip' => "{$params['zip']},{$country}",
                'appid' => $apiKey
            ]
        ]);

        if ($response->getStatusCode() !== 200) return null;

        $data = json_decode((string)$response->getBody());
        $latitude = $data->lat;
        $longitude = $data->lon;

        // Luftdaten abrufen
        $response = $client->get($airPollutionApiUrl, [
            'query' => [
                'lat' => $latitude,
                'lon' => $longitude,
                'appid' => $apiKey
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode((string)$response->getBody());
            return [
                'zip' => $params['zip'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'aqi' => $data->list[0]->main->aqi ?? null,
                'components' => $data->list[0]->components ?? null
            ];
        }
        return null;
    }

    private function getWeatherDataFromDB(array $params): ?array {
        $dbConn = DB::conn();
        $dateStr = date('Y-m-d H:i', timestamp: strtotime("{$params['date']} {$params['time']}"));

        $query = "
            SELECT * FROM weather WHERE
            zip = :zip
            AND datetime(ts, 'localtime') >= datetime(:ts, '-900 second', 'localtime')
            AND datetime(ts, 'localtime') <= datetime(:ts, '+900 second', 'localtime')
            ORDER BY ts DESC
            LIMIT 1
        ";
        $stm = $dbConn->prepare($query);
        $stm->execute([
            'zip' => $params['zip'],
            'ts' => $dateStr
        ]);
        $weatherdata = $stm->fetch(PDO::FETCH_ASSOC);

        return $weatherdata ?: null;
    }

    private function getAirDataFromDB(array $params): ?array {
        $dbConn = DB::conn();
        $dateStr = date('Y-m-d H:i', strtotime("{$params['date']} {$params['time']}"));

        $query = "
            SELECT * FROM air_pollution WHERE
            zip = :zip
            AND datetime(ts, 'localtime') >= datetime(:ts, '-900 second', 'localtime')
            AND datetime(ts, 'localtime') <= datetime(:ts, '+900 second', 'localtime')
            ORDER BY ts DESC
            LIMIT 1
        ";
        $stm = $dbConn->prepare($query);
        $stm->execute([
            'zip' => $params['zip'],
            'ts' => $dateStr
        ]);
        $airdata = $stm->fetch(PDO::FETCH_ASSOC);

        return $airdata ?: null;
    }

    private function renderWeatherDataResponse(?array $weatherdata, ?array $airdata): string {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new \Twig\Environment($loader, ['cache' => false]);
        $template = $twig->load('home/weatherdata.tpl.html');

        return $template->render([
            'weatherdata' => $weatherdata,
            'airdata' => $airdata,
            'aqi_map' => [
                1 => "Gut",
                2 => "Angemessen",
                3 => "Mässig",
                4 => "Schlecht",
                5 => "Sehr schlecht",
            ]
        ]);
    }

    public function getWeatherDataHtml(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        // Parameter vom Frontend lesen
        $params = $this->getInputParams($request);

        // Daten von API oder von DB lesen
        $weatherdata = null;
        $airdata = null;
        switch ($params['mode']) {
            case 'actual':
                $weatherdata = $this->getWeatherDataFromApi($params);
                $airdata = $this->getAirDataFromApi($params);
                break;
            case 'historic':
                $weatherdata = $this->getWeatherDataFromDB($params);
                $airdata = $this->getAirDataFromDB($params);
                break;
            default:
                // Fehlerbehandlung oder Standardfall
                break;
        }

        // HTML-Antwort mittels Template erzeugen
        $html = $this->renderWeatherDataResponse($weatherdata, $airdata);

        // Ausgabe
        $response->getBody()->write($html);
        return $response;
    }


    




}
