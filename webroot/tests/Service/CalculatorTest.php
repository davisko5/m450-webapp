<?php

// Datei: webroot/tests/Service/CalculatorTest.php

namespace Test\Service;

use App\Service\Calculator;
use PHPUnit\Framework\TestCase;

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
}
