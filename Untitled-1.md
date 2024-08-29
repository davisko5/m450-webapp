Szenario: Software auf Herz und Nieren prüfen. Wir müssen testen


Wie gehen sie vor?
- Überlegen, Sinn von Software?
- Was soll Software machen?
- Was sind die Anforderungen?

Woher wissen Sie, was Sie alles testen müssen?
- Anforderungen
- Spezifikationen
- Dokumentation

Wann wissen Sie, dass Sie fertig sind?
- Wenn alle Anforderungen erfüllt sind
- Wenn alle Testfälle durchgeführt wurden
- Wenn alle Fehler behoben wurden

Wie testen Sie?
- Testfälle erstellen
- Testfälle durchführen
- Testergebnisse dokumentieren


03 - Wetter am Zeitpunkt/Ort X abrufen
Ziel	Der User sieht auf einer Webseite die Wetter- und Luftqualitätsdaten für einen bestimmten Zeitpunkt
Akteure	User
Auslöser	Der Benutzer ruft die Wetter-Infoseite auf
Nachbedingung	-
Essentielle Schritte
Der Benutzer ruft die Daten-Abrufseite auf.

Der Benutzer wählt den Ort (PLZ) aus einer vorgefertigten Liste aus

Der Benutzer wählt ein Datum und eine Zeit

Der Benutzer klickt auf "Daten laden" (o.ä.)

Die Webseite fragt die Daten vom Server ab und stellt die Daten für den gewählten Zeitpunkt tabellarisch dar. Es werden sowohl Wetter- wie auch Luftqualitätsdaten ausgegeben, und zwar folgende Werte:
Wetter:

Zeitstempel der Messung
Land
PLZ, Ort
Koordinaten
Wetter-Beschreibung, auf Deutsch
Icon
Temperatur aktuell, Tages-Minimum/Maximum, in °C und in °F
gefühlte Temperatur, in °C und in °F
Luftdruck, Luftfeuchtigkeit
Angaben zum Wind
Sonnen-Auf- und Untergangszeit
Luft:

Zeitstempel der Messung
Land
PLZ, Ort
Luftqualitäts-Index
Partikelzahlen (ug/m3) für:
CO
NO
NO2
O3
SO2
PM2_5
PM10
NH3
Erweiterungen
3a. Der Benutzer wählt kein Datum/Zeit: Als Vorgabe ist das aktuelle Datum / die aktuelle Zeit abgefüllt.

5a. Für den gewählten Zeitpunkt (+/- 30min) sind keine Wetter- und/oder Luft-Daten vorhanden. Die Webseite zeigt dies mit einer Fehlermeldung an.


TestObjekt: Wetter- und Luftqualitätsdaten
TestFall: Wetter- und Luftqualitätsdaten abrufen
