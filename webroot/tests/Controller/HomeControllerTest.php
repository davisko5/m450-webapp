<?php

namespace Test\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionMethod;

class HomeControllerTest extends TestCase {
    // Unser Unit-Test für die getInputParams-Methode
    #[DataProvider('getInputParamsProvider')]
    public function testGetInputParams($params, $expected) {
        // Wir erstellen eine Instanz des HomeControllers:
        $ctrl = new HomeController();

        // Wir erstellen ein Stub eines ServerRequestInterfaces:
        $reqStub = $this->createStub(ServerRequestInterface::class);

        // Wir konfigurieren die Funktionalität (Rückgabewert) der 'getQueryParams()'-Methode:
        $reqStub->method('getQueryParams')
            ->willReturn($params);

        // Wir rufen die gewünschte Methode mit unserem Stub auf:
        $res = $ctrl->getInputParams($reqStub);

        // wir vergleichen das Resultat:
        $this->assertEquals($expected, $res);
    }

    public static function getInputParamsProvider() {
        return [
            'correct1' => [
                'params' => ['mode' => 'actual', 'zip' => '8500', 'date' => '2023-04-15', 'time' => '14:00'],
                'expected' => [
                    'mode' => 'actual',
                    'plz' => '8500',
                    'date' => '2023-04-15',
                    'time' => '14:00:00',
                    'timestamp' => '2023-04-15 14:00:00',
                ]
            ],

            'europeanDate' => [
                'params' => ['mode' => 'actual', 'zip' => '8500', 'date' => '15.04.2023', 'time' => '14:00'],
                'expected' => [
                    'mode' => 'actual',
                    'plz' => '8500',
                    'date' => '2023-04-15',
                    'time' => '14:00:00',
                    'timestamp' => '2023-04-15 14:00:00',
                ]
            ],

            'wrong1' => [
                'params' => ['mode' => 'foo', 'zip' => '-1', 'date' => 'ungültig', 'time' => 'ungültig'],
                'expected' => [
                    'mode' => 'history',
                    'plz' => 0,
                    'date' => '1970-01-01',
                    'time' => '01:00:00',
                    'timestamp' => '1970-01-01 01:00:00'
                ]
            ]
        ];
    }

    public function testGetWeatherDataHtml() {
        // Wir erstellen eine Mock-Instanz unseres Home-Controllers:
        // Dabei sollen die intern aufgerufenen Methoden durch den Mock ersetzt werden:
        $ctrl = $this->getMockBuilder(HomeController::class)
        ->onlyMethods([
            'getInputParams',
            'getWeatherDataFromApi',
            'getAirDataFromApi',
            'getWeatherDataFromDb',
            'getAirDataFromDb',
            'renderWeatherDataResponse'
        ])
        ->getMock();

        // nun konfigurieren wir, wie die internen Methoden reagieren sollen:
        // Fall 1: Wetterdaten direkt von der API:
        // getInputParams soll von uns definierte Testdaten liefern:
        $ctrl->method('getInputParams')
            ->willReturn(['mode' => 'actual', 'plz' => '8500', 'date' => '2023-04-15', 'time' => '14:00']);
        $weatherData = ['test'];
        // getWeatherDataFromApi soll genau 1x aufgerufen werden und von uns definierte Wetterdaten liefern:
        $ctrl->expects($this->once())->method('getWeatherDataFromApi')->willReturn($weatherData);
        // ebenso getAirDataFromApi soll genau 1x aufgerufen werden und von uns definierte Wetterdaten liefern:
        $ctrl->expects($this->once())->method('getAirDataFromApi')->willReturn($weatherData);

        // Die get...FromDb-Funktionen sollen NICHT aufgerufen werden:
        $ctrl->expects($this->never())->method('getWeatherDataFromDb');
        $ctrl->expects($this->never())->method('getAirDataFromDb');

        // die renderWeatherDataResponse-Funktion soll genau 1x aufgerufen werden und eine HTML-Response liefern,
        // hier von uns gemockt:
        $htmlResponse = '<p>some html</p>';
        $ctrl->expects($this->once())->method('renderWeatherDataResponse')->with($weatherData, $weatherData)->willReturn($htmlResponse);

        // Für den Aufruf von HomeController::getWeatherDataHtml brauchen wir zwei Stubs:
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        // und unser $response-Objekt muss auf Aufruf von getBody() ein StreamInterface zurückgeben,
        // siehe im Original: $response->getBody()->write($html);
        // auf welchem dann write() aufgerufen werden kann:
        $outStream = $this->createMock(StreamInterface::class);
        $response->method('getBody')->willReturn($outStream);
        $outStream->method('write')->with($htmlResponse);

        // Der eigentliche Aufruf unserer Methode:
        $result = $ctrl->getWeatherDataHtml($request, $response, []);

        // kriegen wir unser response-Objekt wieder zurück?
        $this->assertSame($response, $result);

        // Damit haben wir den ABLAUF der Funktion getestet!
    }

    public function testRenderWeatherDataResponse() {
		// wir benötigen eine Instanz des HomeControllers:
		$ctrl = new HomeController();

		// wir benötigen ein Set aus Wetter- und Luftdaten als Input:
		// Diese haben wir als JSON-Daten in einer Datei vorbereitet:
		$weatherData = json_decode(file_get_contents(__DIR__.'/weatherdata-set-1.json'), true);
		$airData = json_decode(file_get_contents(__DIR__.'/airdata-set-1.json'), true);

		// Da die Methode renderWeatherDataResponse protected ist, können wir sie nicht direkt
		// aufrufen: Wir benötigen dazu Reflection:
		$renderMethod = new ReflectionMethod($ctrl, 'renderWeatherDataResponse');
		// wir "erlauben" den Zugriff auf die Methode:
		$renderMethod->setAccessible(true);
		// ... und rufen sie nun über das Reflection-Objekt auf. Sie sollte HTML zurückliefern:
		$html = $renderMethod->invoke($ctrl, $weatherData, $airData);

		// Kommt das erwartete HTML?
		// Wetter-Angaben:
		$this->assertStringContainsString('<h2>Wetterdaten</h2>', $html);
		$this->assertStringContainsString('<td>8400 Winterthur (47.4916 / 8.7295)</td>', $html);
		$this->assertStringContainsString('<td>23.05.2024 08:15</td>', $html);
		$this->assertStringContainsString('<td>Min: 10.93°C, Max: 13.57°C, gefühlt: 12.47°C</td>', $html);
		// Luft-Angaben:
		$this->assertStringContainsString('<h2>Luftqualität</h2>', $html);
		$this->assertStringContainsString('<td>240.33μg/m<sup>3</sup></td>', $html);
		// ... und so weiter!
		// Sie können auch gegen einen vorbereiteten Output testen: 
		$expected = file_get_contents(__DIR__.'/weatherdata-output-set-1.html');
		$this->assertEquals($expected, $html);
    }

}