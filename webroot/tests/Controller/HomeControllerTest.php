<?php

namespace Test\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require 'vendor/autoload.php';

class HomeControllerTest extends TestCase {
    public function testGetInputParams() {
        $ctrl = new HomeController();

        // Erstellen des Mocks für das ServerRequestInterface
        $reqStub = $this->createMock(ServerRequestInterface::class);

        // Konfigurieren des Rückgabewertes für getQueryParams()
        $reqStub->method('getQueryParams')
                ->willReturn(['mode' => 'actual', 'zip' => '8500', 'date' => '15.04.2023', 'time' => '14:00']);

        $res = $ctrl->getInputParams(request: $reqStub instanceof ServerRequestInterface ? $reqStub : null);

        // Vergleich des erwarteten Ergebnisses mit dem tatsächlichen
        $this->assertEquals([
            'mode' => 'actual',
            'plz' => '8500',
            'date' => '2023-04-15',
            'time' => '14:00',
            'timestamp' => '2024-04-15 14:00:00',
        ], $res);
    }

    public function testWrongInputParams() {
        $ctrl = new HomeController();

        // Erstellen des Stubs
        $reqStub = $this->createStub(ServerRequestInterface::class);
        $reqStub->method('getQueryParams')
                ->willReturn(['mode' => 'ahhhhhh', 'zip' => '-0.3', 'date' => 'heute', 'time' => 'oke']);

                $res = $ctrl->getInputParams(request: $reqStub instanceof ServerRequestInterface ? $reqStub : null);

        // Vergleich mit einem nicht erwarteten Ergebnis
        $this->assertNotEquals([
            'mode' => 'historic',
            'plz' => '8500',
            'date' => '2023-04-15',
            'time' => '14:00',
            'timestamp' => '2024-04-15 14:00:00',
        ], $res);
    }

    public function testRenderWeatherDataResponse() {
        // Mocking the Twig Loader and Environment
        $twigLoaderStub = $this->createMock(FilesystemLoader::class);
        $twigEnvMock = $this->createMock(Environment::class);

        // Mocking the render method to return expected HTML
        $twigEnvMock->expects($this->once())
            ->method('load')
            ->willReturn($twigEnvMock);
        
        $twigEnvMock->expects($this->once())
            ->method('render')
            ->with('home/weatherdata.tpl.html', $this->anything())
            ->willReturn('<div>Test HTML Output</div>');

        $controller = new HomeController();
        
        $weatherData = [
            'city' => 'Thurgau',
            'temp' => 20,
            // Weitere Testdaten hinzufügen
        ];

        $airData = [
            'zip' => '8500',
            'aqi' => 3,
            // Weitere Testdaten hinzufügen
        ];


        $reflection = new \ReflectionClass(HomeController::class);
        $method = $reflection->getMethod('renderWeatherDataResponse');
        $method->setAccessible(true); // Ermöglicht den Zugriff auf private Methoden


       // $actualHtml = $controller->renderWeatherDataResponse($weatherData, $airData);
        // Mocked HTML response
    $actualHtml = $method->invokeArgs($controller, [$weatherData, $airData]);
        
        // Compare the actual HTML with expected HTML
        $this->assertEquals('<div>Test HTML Output</div>', $actualHtml);
    }
}
