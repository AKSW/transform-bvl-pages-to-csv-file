<?php

/*
 * this file uses the raw CSV export provided by the Behindertenverband Leipzig e.V. containing
 * information about Leipzig buildings and their degree of accessibility. it enriches data, cleans
 * it and provides a CSV file with more clear information, aligned values and stable schema.
 */

require 'vendor/autoload.php';

\setlocale(LC_CTYPE, 'de_DE.UTF-8');

$fileContentArray = array();
$key = 0;

if (false === \file_exists(__DIR__ . '/table.csv')) {
    throw new Exception('File ' . __DIR__ . '/table.csv not found. Aborting ...');
    return;
}

$collectedEntries = array();
$finalData = array();
$i = 0;

echo PHP_EOL;
echo '#########' . PHP_EOL;
echo 'Protokoll' . PHP_EOL;
echo '#########';
echo PHP_EOL;

$category_info = loadCategoryInformation();

$mdbDatabaseCSVExport = loadCSVFileIntoArray('table.csv');

// remove first line with column titles
unset($mdbDatabaseCSVExport[0]);

foreach ($mdbDatabaseCSVExport as $key => $originalEntry) {
    // covers title and street
    if (!isset($originalEntry[5]) || !isset($originalEntry[7])) {
        var_dump($originalEntry);
        echo PHP_EOL . 'Data seems corrupt, essential fields are unset. Aborting ...';
        return;
    }

    $extractedEntry = [
        'Titel' => $originalEntry[5],
    ];

    // field B10 reflects if this entry is meant to be public
    if ('1' == $originalEntry[15]) {
        $extractedEntry['Freigegeben'] = 'true';
    } else {
        $extractedEntry['Freigegeben'] = 'false';
    }

    // main and secondary category
    if (isset($category_info[$originalEntry[2]])) {
        $extractedEntry['Hauptkategorie'] = $category_info[$originalEntry[2]]['main_category'];
        $extractedEntry['Nebenkategorie'] = $category_info[$originalEntry[2]]['secondary_category'];

    } else {
        echo PHP_EOL.PHP_EOL.'ERROR: Found no category information for: '. $extractedEntry['Titel'];

        $extractedEntry['Hauptkategorie'] = '';
        $extractedEntry['Nebenkategorie'] = '';
    }

    $street = \preg_replace('/(\(.*?\))/si', '', $originalEntry[7]);

    $titleStreet = $originalEntry[5] . $street;
    // ignore entries, which are already collected
    if (isset($collectedEntries[$titleStreet])){
        echo PHP_EOL;
        echo PHP_EOL;
        echo $titleStreet . ' exists already. Entry will be ignored!';
        continue;
    } else {
        $collectedEntries[$titleStreet] = $titleStreet;
    }

    /**
     * General information
     */
    $extractedEntry['Strasse'] = $street;
    $extractedEntry['PLZ'] = $originalEntry[9];
    $extractedEntry['Ort'] = $originalEntry[10];
    $extractedEntry['Oeffnungszeiten'] = $originalEntry[19];

    /*
     * Contact information
     */
    $extractedEntry['Ansprechpartner-Name'] = $originalEntry[13];
    $extractedEntry['Ansprechpartner-Telefon'] = $originalEntry[13];
    $extractedEntry['Webseite-Einrichtung'] = $originalEntry[6];
    $extractedEntry['E-Mail-Einrichtung'] = $originalEntry[6];
    $extractedEntry['Telefon-Einrichtung'] = $originalEntry[11];
    $extractedEntry['Fax-Einrichtung'] = $originalEntry[12];
    $extractedEntry['Interviewer-Name'] = $originalEntry[17];
    $extractedEntry['Datum-letzter-Befragung'] = $originalEntry[18];

    /**
     * Enrich data with adress information
     */
    // ask for long and lat for a given address (cached access)
    list($long, $lat) = getLongLatForAddress(
        $extractedEntry['Titel'],
        $extractedEntry['Strasse'],
        $extractedEntry['PLZ'],
        $extractedEntry['Ort']
    );

    $extractedEntry['Longitude'] = $long;
    $extractedEntry['Latitude'] = $lat;

    /*
     * Parkplatz
     */
    $extractedEntry['Parkplatz-vorhanden'] = getBinaryAnswer($originalEntry[20]);
    $extractedEntry['Parkplatz-vor-Einrichtung-vorhanden'] = getBinaryAnswer($originalEntry[21]);
    $extractedEntry['Anzahl-Behindertenparkplaetze-v-Einrichtung'] = $originalEntry[22];
    $extractedEntry['Hauseigener-Parkplatz-vorhanden'] = getBinaryAnswer($originalEntry[23]);
    $extractedEntry['Anzahl-Behindertenparkplaetze-auf-hauseigenem-Parkplatz'] = $originalEntry[25];
    $extractedEntry['Ort-hauseigener-Parkplatz'] = $originalEntry[24];
    /*
     * Eingangsbereich
     */
    $extractedEntry['Stufen-bis-Eingang-vorhanden'] = getBinaryAnswer($originalEntry[26]);
    $extractedEntry['Anzahl-der-Stufen-bis-Eingang'] = $originalEntry[27];
    $extractedEntry['Hoehe-einer-Stufe-bis-Eingang-cm'] = transformStringToFloat($originalEntry[28]);
    $extractedEntry['Eingang-Handlauf-durchgehend-links-vorhanden'] = getBinaryAnswer($originalEntry[29]);
    $extractedEntry['Eingang-Handlauf-durchgehend-rechts-vorhanden'] = getBinaryAnswer($originalEntry[30]);
    $extractedEntry['Eingang-Farbliche-Markierung-erste-u-letzte-Stufe-vorhanden'] = getBinaryAnswer($originalEntry[31]);
    $extractedEntry['Alternativer-Eingang-fuer-Rollstuhlfahrer-vorhanden'] = getBinaryAnswer($originalEntry[32]);
    $extractedEntry['Ort-alternativer-Eingang-fuer-Rollstuhlfahrer'] = $originalEntry[33];
    /*
     * Rampe im Eingangsbereich
     */
    $extractedEntry['Rampe-vor-Eingang-vorhanden'] = getBinaryAnswer($originalEntry[34]);
    $extractedEntry['Laenge-der-Rampe-cm'] = transformStringToFloat($originalEntry[35]);
    $extractedEntry['Hoehe-der-Rampe-cm'] = transformStringToFloat($originalEntry[36]);
    $extractedEntry['Breite-der-Rampe-cm'] = transformStringToFloat($originalEntry[37]);
    $extractedEntry['Rampe-Handlauf-durchgehend-links-vorhanden'] = getBinaryAnswer($originalEntry[38]);
    $extractedEntry['Rampe-Handlauf-durchgehend-rechts-vorhanden'] = getBinaryAnswer($originalEntry[39]);
    $extractedEntry['Rampe-Farbliche-Markierung-an-Beginn-u-Ende-der-Rampe-vorhanden'] = getBinaryAnswer($originalEntry[40]);

    // compute degree of the ramp
    $rampHeight = transformStringToFloat($originalEntry[36]);
    $rampLength = transformStringToFloat($originalEntry[35]);
    if (0 < $rampHeight && 0 < $rampLength) {
        $extractedEntry['Rampe-Steigung'] = \round(\asin($rampHeight / $rampLength)*100, 2);
    } else {
        $extractedEntry['Rampe-Steigung'] = '-';
    }

    /*
     * Klingel im Eingangsbereich
     */
    $extractedEntry['Eingang-Klingel-vorhanden'] = getBinaryAnswer($originalEntry[41]);
    $extractedEntry['Eingang-Ort-der-Klingel'] = $originalEntry[42];
    $extractedEntry['Eingang-Klingel-mit-Wechselsprechanlage-vorhanden'] = getBinaryAnswer($originalEntry[43]);
    $extractedEntry['Eingang-Hoehe-oberster-Bedienknopf-von-Klingel-cm'] = transformStringToFloat($originalEntry[44]);
    /*
     * Tuer im Eingangsbereich
     */
    $extractedEntry['Kleinste-Tuerbreite-bis-Erreichen-der-Einrichtung-cm'] = transformStringToFloat($originalEntry[45]);

    if ('true' == getBinaryAnswer($originalEntry[46])) {
        $door = 'AutomaticDoor';
    } elseif ('true' == getBinaryAnswer($originalEntry[47])) {
        $door = 'SemiautomaticDoor';
    } elseif ('true' == getBinaryAnswer($originalEntry[48])) {
        $door = 'CirclingLeafDoor';
    } elseif ('true' == getBinaryAnswer($originalEntry[49])) {
        $door = 'SlideDoor';
    } elseif ('true' == getBinaryAnswer($originalEntry[51])) {
        $door = 'SwingDoor';
    } elseif ('true' == getBinaryAnswer($originalEntry[50])
        || 'true' == getBinaryAnswer($originalEntry[52])) {
        $door = 'SomeDoor';
    }
    $extractedEntry['Tuerart-am-Eingang'] = $door;

    $extractedEntry['Tuerart-am-Eingang-Automatische-Tuer'] = getBinaryAnswer($originalEntry[46]);
    $extractedEntry['Tuerart-am-Eingang-Halbautomatische-Tuer'] = getBinaryAnswer($originalEntry[47]);
    $extractedEntry['Tuerart-am-Eingang-Drehtuer'] = getBinaryAnswer($originalEntry[48]);
    $extractedEntry['Tuerart-am-Eingang-Schiebetuer'] = getBinaryAnswer($originalEntry[49]);
    $extractedEntry['Tuerart-am-Eingang-Drehfluegeltuer'] = getBinaryAnswer($originalEntry[50]);
    $extractedEntry['Tuerart-am-Eingang-Pendeltuer'] = getBinaryAnswer($originalEntry[51]);
    $extractedEntry['Tuerart-am-Eingang-andere-Tuerart'] = getBinaryAnswer($originalEntry[52]);

    /*
     * Aufzug in der Einrichtung
     */
    $extractedEntry['Aufzug-in-der-Einrichtung-vorhanden'] = getBinaryAnswer($originalEntry[53]);
    $extractedEntry['Anzahl-der-Stufen-bis-Aufzug-in-der-Einrichtung'] = $originalEntry[54];
    $extractedEntry['Aufzug-Tuerbreite-cm'] = transformStringToFloat($originalEntry[56]);
    $extractedEntry['Stufen-bis-Aufzug-in-der-Einrichtung-vorhanden'] = getBinaryAnswer($originalEntry[55]);
    $extractedEntry['Aufzug-Breite-Innenkabine-cm'] = transformStringToFloat($originalEntry[57]);
    $extractedEntry['Aufzug-Tiefe-Innenkabine-cm'] = transformStringToFloat($originalEntry[58]);
    $extractedEntry['Aufzug-Hoehe-oberster-Bedienknopf-in-Innenkabine-cm'] = transformStringToFloat($originalEntry[59]);
    $extractedEntry['Aufzug-Hoehe-oberster-Bedienknopf-außerhalb-cm'] = transformStringToFloat($originalEntry[60]);
    $extractedEntry['Aufzug-Ort-Aufenthaltsort-Aufzugsberechtigter'] = $originalEntry[61];
    // check for "geringen Wendekreis beachten!", which means that there is not sufficient space when
    // leaving a lift. because of this fact, lifts are not fully accessible, if value here is "gering"
    if (false !== \strpos($originalEntry[33], 'geringen Wendekreis beachten!')) {
        $extractedEntry['Aufzug-Wendekreis-bei-Ausstieg'] = 'not sufficient';
        $extractedEntry['Aufzug-rollstuhlgerecht'] = 'partly';
    } else {
        $extractedEntry['Aufzug-Wendekreis-bei-Ausstieg'] = 'sufficient';
    }

    /*
     * Toilette in der Einrichtung
     */
    $extractedEntry['Toilette-in-der-Einrichtung-vorhanden'] = getBinaryAnswer($originalEntry[63]);
    $extractedEntry['Toilette-mit-Piktogramm-als-Behindertentoilette-gekennzeichnet'] = getBinaryAnswer($originalEntry[64]);
    $extractedEntry['Stufen-bis-Toilette-in-Einrichtung-vorhanden'] = getBinaryAnswer($originalEntry[65]);
    $extractedEntry['Anzahl-Stufen-bis-Toilette-in-Einrichtung'] = $originalEntry[66];
    $extractedEntry['Hoehe-der-Stufen-bis-Toilette-in-Einrichtung-cm'] = transformStringToFloat($originalEntry[67]);
    $extractedEntry['Stufen-bis-Toilette:-Handlauf-durchgehend-links-vorhanden'] = getBinaryAnswer($originalEntry[68]);
    $extractedEntry['Stufen-bis-Toilette:-Handlauf-durchgehend-rechts-vorhanden'] = getBinaryAnswer($originalEntry[69]);
    $extractedEntry['Stufen-bis-Toilette-Farbliche-Markierung-erste-u-letzte-Stufe'] = getBinaryAnswer($originalEntry[70]);
    $extractedEntry['Tuerbreite-der-Toilettenkabine-cm'] = transformStringToFloat($originalEntry[71]);
    $extractedEntry['ToilettenTuer-von-außen-entriegelbar'] = getBinaryAnswer($originalEntry[72]);
    $extractedEntry['Notklingel-in-Toilettenkabine-vorhanden'] = getBinaryAnswer($originalEntry[73]);
    $extractedEntry['Hoehe-Notklingel-in-Toilettenkabine'] = $originalEntry[74];
    $extractedEntry['Bewegungsflaeche-vor-WC-Tiefe-cm'] = transformStringToFloat($originalEntry[75]);
    $extractedEntry['Bewegungsflaeche-vor-WC-Breite-cm'] = transformStringToFloat($originalEntry[76]);
    $extractedEntry['Bewegungsflaeche-links-vom-WC:-Tiefe-cm'] = transformStringToFloat($originalEntry[77]);
    $extractedEntry['Bewegungsflaeche-links-vom-WC:-Breite-cm'] = transformStringToFloat($originalEntry[78]);
    $extractedEntry['Bewegungsflaeche-rechts-vom-WC:-Tiefe-cm'] = transformStringToFloat($originalEntry[79]);
    $extractedEntry['Bewegungsflaeche-rechts-vom-WC:-Breite-cm'] = transformStringToFloat($originalEntry[80]);
    $extractedEntry['Stuetzgriff-neben-WC-vorhanden'] = getBinaryAnswer($originalEntry[81]);
    $extractedEntry['Stuetzgriff-neben-WC-links-klappbar'] = getBinaryAnswer($originalEntry[82]);
    $extractedEntry['Stuetzgriff-neben-WC-rechts-klappbar'] = getBinaryAnswer($originalEntry[83]);

    if ('true' == getBinaryAnswer($originalEntry[84])) {
        $type = 'Phototube';
    } elseif ('true' == getBinaryAnswer($originalEntry[85])) {
        $type = 'LeverArm';
    } else {
        $type = 'Unknown';
    }
    $extractedEntry['Aktivierung-Amatur-Waschbecken-Toilettenkabine'] = $type;

    $extractedEntry['Aktivierung-Amatur-Waschbecken-Toilettenkabine-mit-Fotozelle'] = getBinaryAnswer($originalEntry[84]);
    $extractedEntry['Aktivierung-Amatur-Waschbecken-Toilettenkabine-mit-Hebelarm'] = getBinaryAnswer($originalEntry[85]);

    /*
     * Local support
     */
    $extractedEntry['Besondere-Hilfestellungen-f-Menschen-m-Hoerbehinderung-vorhanden'] = getBinaryAnswer($originalEntry[109]);
    $extractedEntry['Besondere-Hilfestellungen-f-Menschen-m-Seebhind-Blinde-vorhanden'] = getBinaryAnswer($originalEntry[110]);
    $extractedEntry['Allgemeine-Hilfestellungen-vor-Ort-vorhanden'] = getBinaryAnswer($originalEntry[111]);
    $extractedEntry['Beschreibung-Hilfestellungen-vor-Ort'] = $originalEntry[112];

    /*
     * set fields which give a summary of wheelchair support for elevator, entrance and
     */
    // elevator
    if (90 <= (float)$extractedEntry['Aufzug-Tuerbreite-cm']
        && 110 <= (float)$extractedEntry['Aufzug-Breite-Innenkabine-cm']
        && 140 <= (float)$extractedEntry['Aufzug-Tiefe-Innenkabine-cm']
        // buttons inside
        && 70 <= (float)$extractedEntry['Aufzug-Hoehe-oberster-Bedienknopf-in-Innenkabine-cm']
            && 115 >= (float)$extractedEntry['Aufzug-Hoehe-oberster-Bedienknopf-in-Innenkabine-cm']
        // buttons outside
        && 70 <= (float)$extractedEntry['Aufzug-Hoehe-oberster-Bedienknopf-außerhalb-cm']
            && 115 >= (float)$extractedEntry['Aufzug-Hoehe-oberster-Bedienknopf-außerhalb-cm']
    ) {
        $extractedEntry['Aufzug-rollstuhlgerecht'] = 'partly';
    } else {
        $extractedEntry['Aufzug-rollstuhlgerecht'] = 'false';
    }

    // entrance
    if ((
            0 == (int)$extractedEntry['Anzahl-der-Stufen-bis-Eingang']
            || 3 >= (float)$extractedEntry['Hoehe-einer-Stufe-bis-Eingang-cm']
        )
        || (
            6 >= (float)$extractedEntry['Rampe-Steigung']
            && 90 <= (float)$extractedEntry['Kleinste-Tuerbreite-bis-Erreichen-der-Einrichtung-cm']
        )
    ) {
         $extractedEntry['Eingangsbereich-rollstuhlgerecht'] = 'fully';

    } elseif (1 >= (int)$extractedEntry['Anzahl-der-Stufen-bis-Eingang']
        || 12 >= (float)$extractedEntry['Rampe-Steigung']
        || 70 <= (float)$extractedEntry['Kleinste-Tuerbreite-bis-Erreichen-der-Einrichtung-cm']
    ) {
        $extractedEntry['Eingangsbereich-rollstuhlgerecht'] = 'partly';

    } else {
        $extractedEntry['Eingangsbereich-rollstuhlgerecht'] = 'false';
    }

    // toilet
    if ('false' == $extractedEntry['Stufen-bis-Toilette-in-Einrichtung-vorhanden']
        && 90 <= (float)$extractedEntry['Tuerbreite-der-Toilettenkabine-cm']
        && 90 <= (float)$extractedEntry['Bewegungsflaeche-links-vom-WC:-Tiefe-cm']
        && 90 <= (float)$extractedEntry['Bewegungsflaeche-rechts-vom-WC:-Tiefe-cm']
        && 150 <= (float)$extractedEntry['Bewegungsflaeche-vor-WC-Tiefe-cm']
            && 150 <= (float)$extractedEntry['Bewegungsflaeche-vor-WC-Breite-cm']
        && 'true' == $extractedEntry['Stuetzgriff-neben-WC-links-klappbar']
        && 'true' == $extractedEntry['Stuetzgriff-neben-WC-rechts-klappbar']
    ) {
        $extractedEntry['Toilette-rollstuhlgerecht'] = 'fully';

    } elseif (70 <= (float)$extractedEntry['Tuerbreite-der-Toilettenkabine-cm']
        && (
            70 <= (float)$extractedEntry['Bewegungsflaeche-links-vom-WC:-Tiefe-cm']
            || 70 <= (float)$extractedEntry['Bewegungsflaeche-rechts-vom-WC:-Tiefe-cm']
        )
        && 100 <= (float)$extractedEntry['Bewegungsflaeche-vor-WC-Tiefe-cm']
            && 100 <= (float)$extractedEntry['Bewegungsflaeche-vor-WC-Breite-cm']
        && 'true' == $extractedEntry['Stuetzgriff-neben-WC-vorhanden']
    ) {
        $extractedEntry['Toilette-rollstuhlgerecht'] = 'partly';

    } else {
        $extractedEntry['Toilette-rollstuhlgerecht'] = 'false';
    }

    // unique ID
    $extractedEntry['ID'] = generateBuildingUniqueIdentifier(
        $extractedEntry['Titel'],
        $extractedEntry['Strasse'],
        $extractedEntry['PLZ'],
        $extractedEntry['Ort'],
        // certain accessibility features of the place
        $extractedEntry['Beschreibung-Hilfestellungen-vor-Ort'],
        $extractedEntry['Eingangsbereich-rollstuhlgerecht'],
        $extractedEntry['Aufzug-rollstuhlgerecht'],
        $extractedEntry['Toilette-rollstuhlgerecht']
    );

    $extractedEntry['Notizen'] = $originalEntry[1];

    $finalData[] = $extractedEntry;
}

echo PHP_EOL . PHP_EOL . '----------';

// Generate CSV file
createCSVFile('all_places.csv', $finalData);

echo PHP_EOL;
echo PHP_EOL;
