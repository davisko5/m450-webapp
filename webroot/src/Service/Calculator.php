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
    
    
    public static function averageValues(array $values): array
    {
        if (empty($values)) {
            return [];
        }
    
        $totals = [];
        $counts = 0;
    
        foreach ($values as $entry) {
            foreach ($entry as $key => $val) {
                // Only process numeric values for averaging
                if (is_numeric($val)) {
                    $totals[$key] = ($totals[$key] ?? 0) + (float)$val;
                }
            }
            $counts++;
        }
    
        // Calculate averages for numeric fields
        $averages = [];
        foreach ($totals as $key => $sum) {
            $averages[$key] = $sum / $counts;
        }
    
        return $averages;
    }
    

}