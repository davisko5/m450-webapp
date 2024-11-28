<?php

// Datei: webroot/tests/Service/CalculatorTest.php

namespace Test\Service;

use App\Service\Calculator;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;

// Unsere Klasse muss [Etwas]Test heissen
// und von PHPUnit\Framework\TestCase erben:
class CalculatorTest extends TestCase {

    // erster Test: Funktioniert 0° celsius?
    // der Methodenname muss mit "test" beginnen:
    public function testCToF() {
        // Wir erstellen eine Instanz:
        $calc = new Calculator();

        // wir fragen nach 0°C:
        $result = $calc->cToF(0);

        // erhalten wir eine Float-Antwort?
        $this->assertIsFloat($result);
        // ... und stimmt sie auch?
        $this->assertSame(32.0, $result);
    }

    public function testWithStrings() {
        $calc = new Calculator();
        $result = $calc->cToF("12");
        $this->assertIsFloat($result);
        $this->assertSame(53.6, $result);
    }

    public function testWithBigNumbers() {
        $calc = new Calculator();
        $result = $calc->cToF(10000000000000);
        $this->assertIsFloat($result);
        $this->assertSame(18000000000032.0, $result);
    }

    public function testWithArray() {
        $calc = new Calculator();
        $result = $calc->cToF([0, 12, 10000000000000]);
        $this->assertIsArray($result);
        $this->assertSame([32.0, 53.6, 18000000000032.0], $result);
    }

    public function testExistAverageFunction() {
        $calc = new Calculator();
        $this->assertTrue(method_exists($calc, 'averageValues'));
    }

    public function testAverageValues() {


    $data = json_decode('[
  {
    "zip": "8500", "city": "Gerlikon", "latitude": "47.5349", "longitude": "8.8801",
    "clouds_percentage": "92", "temp": "22.19", "temp_feels_like": "22.53", "temp_min": "19.72",
    "temp_max": "26.11", "pressure": "1020", "humidity": "79", "wind_speed": "1.14", "wind_gust": "1.59"
  },
  {
    "zip": "8500", "city": "Gerlikon", "latitude": "47.5349", "longitude": "8.8801",
    "clouds_percentage": "13", "temp": "24.65", "temp_feels_like": "24.22", "temp_min": "21.67",
    "temp_max": "26.06", "pressure": "1014", "humidity": "40", "wind_speed": "4.6", "wind_gust": "4.17"
  },
  {
    "zip": "8500", "city": "Gerlikon", "latitude": "47.5349", "longitude": "8.8801",
    "clouds_percentage": "1", "temp": "24.44", "temp_feels_like": "24.01", "temp_min": "20.92",
    "temp_max": "26.06", "pressure": "1015", "humidity": "41", "wind_speed": "3.99", "wind_gust": "4.06"
  }
]');

$expected = json_decode(
    '[
    {
    "cloud_percentage": 35.333333333333336,
    "temp": 23.426666666666666,
    "temp_feels_like": 23.253333333333334,
    "temp_min": 20.436666666666667,
    "temp_max": 26.076666666666668,
    "pressure": 1016.3333333333334,
    "humidity": 53.333333333333336,
    "wind_speed": 3.243333333333333,
    "wind_gust": 3.6066666666666664
    }
    ]'
);

        // Durchschnittswerte berechnen:
        $calc = new Calculator();
        // Hier rufen wir unsere Durchnitts-Funktion mit den Rohdaten auf:
        $avg = $calc->averageValues($data);
        assertEquals($expected, $avg);
    }
}
