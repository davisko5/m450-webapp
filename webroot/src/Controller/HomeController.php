<?php

namespace App\Controller;

use App\Service\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Throwable;
use Twig\Loader\FilesystemLoader;
use Twig\TemplateWrapper;
use App\Service\Calculator;

/**
 * Diese Controller-Klasse liefert die Demo-Webseite und die zugehörigen Endpoints für
 * die Wetter-Demo-App.
 *
 * Ab Lektion 7/8 wurde dieser Controller umgebaut, um den Code besser testbar zu machen.
 * Diese Version wird als Anschauungsbeispiel abgegeben.
 *
 * Ziel ist, dass diese Klasse im Verlauf des Moduls M450 auseinandergenommen und testbar
 * gemacht wird.
 *
 * @package App\Controller
 */
class HomeController {
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $template = $this->configureTemplate('home/index.tpl.html');
        $orte = $this->getOrte();
        $html = $this->renderTemplate($template, ['now' => time(), 'zips' => $orte]);
        $response->getBody()->write($html);
        return $response;
    }

    public function configureTemplate(string $tplPath): TemplateWrapper {
        // Set up Twig, the Template engine:
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
        ]);
        return $twig->load($tplPath);
    }

    public function getOrte(): array {
        $dbConn = DB::conn();

        // Laden der PLZ-Einträge für Select
        return $dbConn
            ->query("SELECT DISTINCT zip, city FROM weather ORDER BY zip")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function renderTemplate(TemplateWrapper $tpl, array $data = []) {
        return $tpl->render($data);
    }


    /**
     * Endpoint für den Ajax-Call vom Frontend: Lädt Wetter- und
     * Luftdaten, entweder aus der Datenbank oder live von der API,
     * und liefert ein HTML-Snipped mit dem Ergebnis zurück.
     * HTML-Template: home/weatherdata.tpl.html
     *
     * Request GET parameter:
     * - mode: historic oder actual (Daten aus DB oder von API)
     * - zip: Postleitzahl
     * - date: Datum für historische Daten
     * - time: Zeit für historische Daten
     *
     * als Land wird immer CH angenommen.
     *
     * Die Methode ist der Einstiegspunkt für die /weatherdataHtml-Route: Sie
     * ruft interne Methoden auf, um diese Aufgaben zu erledigen.
     */
    public function getWeatherDataHtml(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 1. Auslesen der Parameter vom Frontend (mode, plz, date, time):
        $params = $this->getInputParams($request);

        // 2. Wetter- und Luftdaten holen / laden:
        $weatherdata = null;
        $airdata = null;
        switch ($params['mode']) {
                // entweder direkt live von der API:
            case 'actual':
                $weatherdata = $this->getWeatherDataFromApi($params['plz']);
                $airdata = $this->getAirDataFromApi($params['plz']);
                break;
                // oder aus einem Datenbank-Record:
            case 'historic':
                $weatherdata = $this->getWeatherDataFromDB($params['plz'], $params['timestamp']);
                $airdata = $this->getAirDataFromDB($params['plz'], $params['timestamp']);
                break;
            default:
        }

        // 3. Aufbereiten der Daten -> HTML-Generierung
        $html = $this->renderWeatherDataResponse($weatherdata, $airdata);

        // 4. Ausgabe des fertigen HTMLs an den Browser
        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Die Methode liest die benötigten / erwarteten Parameter vom Frontend für die
     * /getweatherdataHtml-Route aus.
     *
     * @param ServerRequestInterface $request
     * @return array Dictionnary:
     * [
     *      'mode' => 'actual' oder 'historic'
     *      'plz' => PLZ vom Frontend
     *      'date' => normalisiertes Datum vom Frontend
     *      'time' => normalisierte Zeit vom Frontend
     *      'timestamp' => zusammengesetzter Zeitstempel aus Datum + Zeit
     * ]
     */
    public function getInputParams(ServerRequestInterface $request): array {
        $params = $request->getQueryParams();
        $mode = $params['mode'] ?? 'historic';
        $plz = $params['zip'] ?? null;
        $date = date('Y-m-d', strtotime($params['date'] ?? ''));
        $time = date('H:i:s', strtotime($params['time'] ?? ''));
        $timestamp = "{$date} {$time}";
        return [
            'mode' => $mode,
            'plz' => $plz,
            'date' => $date,
            'time' => $time,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * holt die aktuellen Wetterdaten von openweathermap für eine
     * bestimmte CH-PLZ.
     *
     * Liefert die Daten als assoziativer Array zurück.
     *
     * @param string $zip
     * @return null|array
     * @throws GuzzleException
     */
    protected function getWeatherDataFromApi(string $zip): ?array {
        // Wetterdaten mit HTTP-Client holen:
        // Openweather Key: von Env-Variable (siehe docker-compose.yml)
        $apiKey = getenv('OPENWEATHER_KEY');
        $apiUrl = 'https://api.openweathermap.org/data/2.5/weather';
        $lang = 'de';
        $units = 'metric';
        $country = 'CH';
        $client = new Client();
        $apiResponse = $client->get($apiUrl, [
            'query' => [
                'zip' => "{$zip},{$country}",
                'units' => $units,
                'lang' => $lang,
                'appid' => $apiKey,
            ]
        ]);

        if ($apiResponse->getStatusCode() === 200) {
            $data = json_decode((string)$apiResponse->getBody());

            // Daten auslesen, normalisieren:
            return [
                'ts' => date(DATE_W3C, $data->dt ?? null) ?: null,
                'city' => $data->name ?? null,
                'longitude' => $data->coord->lon ?? null,
                'latitude' => $data->coord->lat ?? null,
                'description' => $data->weather[0]->description ?? null,
                'icon' => $data->weather[0]->icon ?? null,
                'temp' => $data->main->temp ?? null,
                'temp_feels_like' => $data->main->feels_like ?? null,
                'temp_min' => $data->main->temp_min ?? null,
                'temp_max' => $data->main->temp_max ?? null,
                'pressure' => $data->main->pressure ?? null,
                'humidity' => $data->main->humidity ?? null,
                'wind_speed' => $data->wind->speed ?? null,
                'wind_degree' => $data->wind->deg ?? null,
                'wind_gust' => $data->wind->gust ?? null,
                'clouds_percentage' => $data->clouds->all ?? null,
                'sunrise' => date(DATE_W3C, $data->sys->sunrise ?? 0),
                'sunset' => date(DATE_W3C, $data->sys->sunset ?? 0),
            ];
        } else {
            return null;
        }
    }

    /**
     * holt die aktuellen Luftdaten von openweathermap für eine
     * bestimmte CH-PLZ.
     *
     * Liefert die Daten als assoziativer Array zurück.
     *
     * @param string $zip
     * @return null|array
     * @throws GuzzleException
     */
    protected function getAirDataFromApi(string $zip): ?array {
        // -------------- Luftdaten ---------------------------
        $country = 'CH';
        $apiKey = getenv('OPENWEATHER_KEY');
        $geocodingApiUrl = 'http://api.openweathermap.org/geo/1.0/zip';
        $airPollutionApiUrl = 'http://api.openweathermap.org/data/2.5/air_pollution';

        // Koordinaten mit HTTP-Client holen:
        $client = new Client();
        $apiResponse = $client->get($geocodingApiUrl, [
            'query' => [
                'zip' => "{$zip},{$country}",
                'appid' => $apiKey,
            ]
        ]);

        $city = null;
        $latitude = null;
        $longitude = null;

        if ($apiResponse->getStatusCode() === 200) {
            $data = json_decode((string)$apiResponse->getBody());
            $city = $data->name;
            $longitude = $data->lon;
            $latitude = $data->lat;
        } else {
            return null;
        }

        // Luft-Daten mit HTTP-Client holen:
        $client = new Client();
        $apiResponse = $client->get($airPollutionApiUrl, [
            'query' => [
                'lat' => $latitude,
                'lon' => $longitude,
                'appid' => $apiKey,
            ]
        ]);

        if ($apiResponse->getStatusCode() === 200) {
            $data = json_decode((string)$apiResponse->getBody());

            // Daten auslesen, normalisieren:
            $airdata = [
                'zip' => $zip,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'ts' => date(DATE_W3C, $data->list[0]->dt ?? null) ?: null,
                'aqi' => $data->list[0]->main->aqi ?? null,
                'co' => $data->list[0]->components->co ?? null,
                'no' => $data->list[0]->components->no ?? null,
                'no2' => $data->list[0]->components->no2 ?? null,
                'o3' => $data->list[0]->components->o3 ?? null,
                'so2' => $data->list[0]->components->so2 ?? null,
                'pm2_5' => $data->list[0]->components->pm2_5 ?? null,
                'pm10' => $data->list[0]->components->pm10 ?? null,
                'nh3' => $data->list[0]->components->nh3 ?? null,
            ];
            return $airdata;
        }
        return null;
    }

    /**
     * Holt die historischen Wetterdaten zu einer PLZ und Datum aus der lokalen
     * DB.
     *
     * @param string $plz
     * @param string $timestamp
     * @return null|array
     * @throws PDOException
     */
    protected function getWeatherDataFromDB(string $plz, string $timestamp): ?array {
        // Wir suchen den einen Eintrag der gegebenen PLZ, welcher am nächsten zum gegebenen
        // Datum/Zeitstempel ist, aber nur innerhalb einer 30min-Distanz:
        $dbConn = $this->getDbConn();
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
            'zip' => $plz,
            'ts' => $timestamp
        ]);
        $weatherdata = $stm->fetchAll(PDO::FETCH_ASSOC);
        // 1. Record aus Result extrahieren:
        if (!empty($weatherdata)) {
            return $weatherdata[0];
        }
        return null;
    }

    /**
     * Holt die historischen Luftdaten zu einer PLZ und Datum aus der lokalen
     * DB.
     *
     * @param string $plz
     * @param string $timestamp
     * @return null|array
     * @throws PDOException
     */
    protected function getAirDataFromDB(string $plz, string $timestamp): ?array {
        // ... dasselbe suchen wir für die Luft-Daten:
        $dbConn = $this->getDbConn();
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
            'zip' => $plz,
            'ts' => $timestamp
        ]);
        $airdata = $stm->fetchAll(PDO::FETCH_ASSOC);
        // 1. Record aus Result extrahieren:
        if (!empty($airdata)) {
            return $airdata[0];
        }
        return null;
    }

    /**
     * Erzeugt aus Wetter- und Luftdaten das Output-HTML
     *
     * @param array|null $weatherdata
     * @param array|null $airdata
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Throwable
     */
    protected function renderWeatherDataResponse(array $weatherdata = null, array $airdata = null) {
        // Set up Twig, the Template engine:
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
        ]);
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

    protected function getDbConn() {
        return DB::conn();
    }

    public function getAvgDataHtml(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $query = 'SELECT zip, city, latitude, longitude, clouds_percentage, 
        temp, temp_feels_like, temp_min, temp_max, pressure, humidity, wind_speed, wind_gust
        FROM weather 
        WHERE zip = :zip AND DATE(ts) >= :fromDate AND DATE(ts) <= :toDate
        ORDER BY ts DESC';
        $dbConn = DB::conn();
        $stm = $dbConn->prepare($query);
        $plz = $request->getQueryParams()['zip'];
        $fromDate = $request->getQueryParams()['fromDate'] ?? null;
        $toDate = $request->getQueryParams()['toDate'] ?? null;
        $stm->execute([
            'zip' => $plz,
            'fromDate' => $fromDate,
            'toDate' => $toDate
        ]);

        $weatherdata = $stm->fetchAll(PDO::FETCH_ASSOC);
        $avgData = Calculator::averageValues($weatherdata);

        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
        ]);
        $template = $twig->load('home/avgdata.tpl.html');
        $html = $template->render(['avgData' => $avgData]);

        $response->getBody()->write($html);
        return $response;
    }
}