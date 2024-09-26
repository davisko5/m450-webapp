<?php

// Datei: webroot/src/Service/Calculator.php

namespace App\Service;

class Calculator {
    // Wandelt (einen oder mehrere) Celsius-Wert(e) in Fahrenheit um
    public function cToF(float|array $celsius): float|array {
        // TODO: Celsius to Fahrenheit!
        if(is_array($celsius)) {
            $fahrenheit = [];
            foreach($celsius as $c) {
                $fahrenheit[] = $c * 9 / 5 + 32;
            }
            return $fahrenheit;
        } else {
            return $celsius * 9 / 5 + 32;
        }
    }
}