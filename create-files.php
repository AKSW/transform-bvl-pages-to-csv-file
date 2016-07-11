<?php

/**
 * This file creates a CSV file based on the different homepages about place information and
 * their degree of access for disabled people. These homepages where created by the
 * Behindertenverband Leipzig e.V (http://www.le-online.de/).
 *
 * The following implementation is a basic web scraper which analyses the HTML and tries to
 * extract as much information as possible about the places. Used images (e.g. 1111.gif) are
 * very helpful to determine exact thematical classification.
 */

require 'vendor/autoload.php';

/**
 * Unset empty array entries.
 *
 * @param array $array
 * @return array
 */
function unsetEmptyEntry($array)
{
    foreach ($array as $key => $entry) {
        if (is_array($entry)) {
            $array[$key] = unsetEmptyEntry($entry);

            if (empty($array[$key])) {
                unset($array[$key]);
            }

        } elseif (is_array($entry) && 0 == count($entry)) {
            unset($array[$key]);
        } elseif ('' == $entry || empty($entry)) {
            unset($array[$key]);
        }
    }

    if (is_array($array) && 0 == count($array)) {
        return '';
    } else {
        return $array;
    }
}

/**
 * @param string $filepath
 * @return array
 */
function loadCSVFileIntoArray($filepath)
{
    $file = fopen($filepath, 'r');
    $lines = array();
    while (($line = fgetcsv($file, 0, ';', '*')) !== FALSE) {
      $lines[] = $line;
    }
    fclose($file);
    return $lines;
}

/**
 * Helper function to handle invalid values such as 20000000 from outdated entries.
 *
 * @param int/string $value
 * @return string ja or nein
 */
function getBinaryAnswer($value)
{
    $value = (int)$value;
    return 1 == $value ? 'ja' : 'nein';
}

setlocale(LC_CTYPE, 'de_DE.UTF-8');

/*
 * Important variables
 */
$htmlPages = array(
    'http://www.le-online.de/bildung.htm' => 'Bildung',
    'http://www.le-online.de/dienst.htm' => 'Dienst',
    'http://www.le-online.de/gast.htm' => 'Gastwirtschaft',
    'http://www.le-online.de/gesund.htm' => 'Gesundheit',
    'http://www.le-online.de/recht.htm' => 'Recht',
    'http://www.le-online.de/verband.htm' => 'Verbände',
    'http://www.le-online.de/verkehr.htm' => 'Verkehr'
);

$extractedData = array();
$matches = null;

$fileContentArray = array();
$key = 0;
$curl = new Curl\Curl();
$mdbDatabaseCSVExport = loadCSVFileIntoArray('table.csv');

foreach ($htmlPages as $url => $category) {
    // use $url to ask for HTML to analyse later on
    $curl->get($url);

    // store HTML
    $fileContent = $curl->response;

    // create an array, based on HTML-areas separated by <h2> tags
    // there was no other way to determine each place.
    $fileContentArray = explode('<h2>', $fileContent);

    // go through list of entries (created by split HTML by <h2> tags)
    foreach ($fileContentArray as $a => $entry) {

        $extractedData[++$key] = array();

        // transform encoding to UTF-8
        $entry = iconv('iso-8859-1', 'utf-8', $entry);

        // remove <br> tags for better readability
        $entry = str_replace('<br>', '', $entry);

        $extractedData[$key]['category'] = $category;

        /*
         * title
         */
        preg_match(
            '/(.*)<\/h2>/si',
            $entry,
            $matches
        );

        if (isset($matches[1])) {
            $title = $matches[1];

            // search for <a name=""> </a> stuff and remove it
            preg_match(
                '/<a.*>(.*)<\/a>/si',
                $title,
                $matches
            );

            if (isset($matches[1])) {
                $title = $matches[1];
            }

            $extractedData[$key]['title'] = str_replace(
                array('&amp;', '&auml;', '&uuml;', '&ouml;', '&Auml;', '&Uuml;', '&Ouml;', '&szlig;', '&eacute;'),
                array('&', 'ä', 'ü', 'ö', 'Ä', 'Ü', 'Ö', 'ß', 'é'),
                trim($title)
            );
        } else {
            $extractedData[$key]['title'] = '';
        }

        if ('' == $extractedData[$key]['title']) {
            unset($extractedData[$key]);
        } else {
            /**
             * Exception handling of certain entries of Verkehr category, which will be kept
             */
            $exceptionalEntries = array(
                'Augustusplatz (Tiefgarage)',
                'Augustusplatz (zwischen Gewandhaus und Moritzbastei, vor dem Park)',
                'DRK Bahnhofsdienst',
                'Flughafen Leipzig - Halle GmbH',
                'Hauptbahnhof',
                'Lotterstr. / Martin-Luther-Ring (Neues Rathaus)',
                'Martin-Luther-Ring (Haupteingang Neues Rathaus)',
                'Mobilitätszentrum am Hauptbahnhof',
                'Neumarkt 1- 5 (Galeria Kaufhof)',
                'Neumarkt 9 (Städtisches Kaufhaus)',
                'Ökumenische Bahnhofsmission Leipzig',
                'Osthalle Hauptbahnhof (Parkhaus)',
                'Petersbogen (Parkhaus)',
                'Service-Center des MDV und der Leipziger Stadtwerke',
                'Westhalle Hauptbahnhof (Parkhaus)',
                'Zentraler Bushalt - Hbf. Osthalle',
                'ZOO (Parkhaus)',
            );
            if (!in_array($extractedData[$key]['title'], $exceptionalEntries) && 'Verkehr' == $category) {
                unset($extractedData[$key]);
            }
        }
    }
}

$infoArray = array();
$i = 0;
$collectedEntries = array();

foreach ($extractedData as $key => $extractedEntry) {
    foreach ($mdbDatabaseCSVExport as $key => $originalEntry) {
        $street = preg_replace('/(\(.*?\))/si', '', $originalEntry[7]);

        // if title of mdb-dataset matches with one of the online ones
        if ($originalEntry[5] == $extractedEntry['title']) {

            $titleStreet = $originalEntry[5] . $street;
            // ignore entries, which are already collected
            if (isset($collectedEntries[$titleStreet])){
                continue;
            } else {
                $collectedEntries[$titleStreet] = $titleStreet;
            }

            $infoArray[] = array(
                /**
                 * Allgemeine Informationen
                 */
                'Titel' => $originalEntry[5],
                'Straße' => $street,
                'PLZ' => $originalEntry[9],
                'Ort' => $originalEntry[10],
                'Oeffnungszeiten' => $originalEntry[19],

                /*
                 * Parkplatz
                 */
                'Parkplatz vorhanden' => getBinaryAnswer($originalEntry[20]),
                'Parkplatz vor Einrichtung vorhanden' => getBinaryAnswer($originalEntry[21]),
                'Anzahl Behindertenparkplätze auf Parkplatz vor Einrichtung' => $originalEntry[22],
                'Hauseigener Parkplatz vorhanden' => getBinaryAnswer($originalEntry[23]),
                'Anzahl Behindertenparkplätze auf hauseigenem Parkplatz' => $originalEntry[25],
                'Ort des hauseigenen Parkplatzes' => $originalEntry[24],
                /*
                 * Eingangsbereich
                 */
                'Stufen bis Eingang vorhanden' => getBinaryAnswer($originalEntry[26]),
                'Anzahl der Stufen bis Eingang' => $originalEntry[27],
                'Höhe einer Stufe bis Eingang (cm)' => $originalEntry[28],
                'Eingangsbereich: Handlauf durchgehend links vorhanden' => getBinaryAnswer($originalEntry[29]),
                'Eingangsbereich: Handlauf durchgehend rechts vorhanden' => getBinaryAnswer($originalEntry[30]),
                'Eingangsbereich: Farbliche Markierung der ersten und letzten Stufe vorhanden' => getBinaryAnswer($originalEntry[31]),
                'Alternativer Eingang für Rollstuhlfahrer vorhanden' => getBinaryAnswer($originalEntry[32]),
                'Ort des alternativen Eingangs für Rollstuhlfahrer' => $originalEntry[33],
                /*
                 * Rampe im Eingangsbereich
                 */
                'Rampe vor Eingang vorhanden' => getBinaryAnswer($originalEntry[34]),
                'Länge der Rampe (cm)' => $originalEntry[35],
                'Höhe der Rampe (cm)' => $originalEntry[36],
                'Breite der Rampe (cm)' => $originalEntry[37],
                'Rampe: Handlauf durchgehend links vorhanden' => getBinaryAnswer($originalEntry[38]),
                'Rampe: Handlauf durchgehend rechts vorhanden' => getBinaryAnswer($originalEntry[39]),
                'Rampe: Farbliche Markierung von Beginn und Ende der Rampe vorhanden' => getBinaryAnswer($originalEntry[40]),
                /*
                 * Klingel im Eingangsbereich
                 */
                'Eingangsbereich: Klingel vorhanden' => getBinaryAnswer($originalEntry[41]),
                'Eingangsbereich: Ort der Klingel' => $originalEntry[42],
                'Eingangsbereich: Klingel mit Wechselsprechanlage vorhanden' => getBinaryAnswer($originalEntry[43]),
                'Eingangsbereich: Höhe oberster Bedienknopf von Klingel (cm)' => $originalEntry[44],
                /*
                 * Tür im Eingangsbereich
                 */
                'Kleinste Türbreite bis Erreichen der Einrichtung (cm)' => $originalEntry[45],
                'Art der Tür im Eingangsbereich: Automatische Tür' => getBinaryAnswer($originalEntry[46]),
                'Art der Tür im Eingangsbereich: Halbautomatische Tür' => getBinaryAnswer($originalEntry[47]),
                'Art der Tür im Eingangsbereich: Drehtür' => getBinaryAnswer($originalEntry[48]),
                'Art der Tür im Eingangsbereich: Schiebetür' => getBinaryAnswer($originalEntry[49]),
                'Art der Tür im Eingangsbereich: Drehflügeltür' => getBinaryAnswer($originalEntry[50]),
                'Art der Tür im Eingangsbereich: Pendeltür' => getBinaryAnswer($originalEntry[51]),
                'Art der Tür im Eingangsbereich: andere Türart' => getBinaryAnswer($originalEntry[52]),
                /*
                 * Aufzug in der Einrichtung
                 */
                'Aufzug in der Einrichtung vorhanden' => getBinaryAnswer($originalEntry[53]),
                'Anzahl der Stufen bis Aufzug in der Einrichtung' => $originalEntry[54],
                'Stufen bis Aufzug in der Einrichtung vorhanden' => getBinaryAnswer($originalEntry[55]),
                'Aufzug: Türbreite (cm)' => $originalEntry[56],
                'Aufzug: Breite Innenkabine (cm)' => $originalEntry[57],
                'Aufzug: Tiefe Innenkabine (cm)' => $originalEntry[58],
                'Aufzug: Höhe oberster Bedienknopf in Innenkabine (cm)' => $originalEntry[59],
                'Aufzug: Höhe oberster Bedienknopf außerhalb (cm)' => $originalEntry[60],
                'Aufzug: Ort Aufenthaltsort Aufzugsberechtigter' => $originalEntry[61],
                /*
                 * Toilette in der Einrichtung
                 */
                'Toilette in der Einrichtung vorhanden' => getBinaryAnswer($originalEntry[63]),
                'Toilette ist mit Piktogramm als Behindertentoilette gekennzeichnet' => getBinaryAnswer($originalEntry[64]),
                'Stufen bis Toilette in Einrichtung vorhanden' => getBinaryAnswer($originalEntry[65]),
                'Anzahl Stufen bis Toilette in Einrichtung' => $originalEntry[66],
                'Höhe der Stufen bis Toilette in Einrichtung (cm)' => $originalEntry[67],
                'Stufen bis Toilette: Handlauf durchgehend links vorhanden' => getBinaryAnswer($originalEntry[68]),
                'Stufen bis Toilette: Handlauf durchgehend rechts vorhanden' => getBinaryAnswer($originalEntry[69]),
                'Stufen bis Toilette: Farbliche Markierung erste und letzte Stufe' => getBinaryAnswer($originalEntry[70]),
                'Türbreite der Toilettenkabine (cm)' => $originalEntry[71],
                'Toilettentür von außen entriegelbar' => getBinaryAnswer($originalEntry[72]),
                'Notklingel in Toilettenkabine vorhanden' => getBinaryAnswer($originalEntry[73]),
                'Höhe Notklingel in Toilettenkabine' => $originalEntry[74],
                'Bewegungsfläche vor WC: Tiefe (cm)' => $originalEntry[75],
                'Bewegungsfläche vor WC: Breite (cm)' => $originalEntry[76],
                'Bewegungsfläche links vom WC: Tiefe (cm)' => $originalEntry[77],
                'Bewegungsfläche links vom WC: Breite (cm)' => $originalEntry[78],
                'Bewegungsfläche rechts vom WC: Tiefe (cm)' => $originalEntry[79],
                'Bewegungsfläche rechts vom WC: Breite (cm)' => $originalEntry[80],
                'Aktivierung Amatur Waschbecken in Toilettenkabine über Fotozelle möglich' => getBinaryAnswer($originalEntry[84]),
                'Aktivierung Amatur Waschbecken in Toilettenkabine über Hebelarm möglich' => getBinaryAnswer($originalEntry[85]),
                /*
                 * Hilfestellungen
                 */
                'Besondere Hilfestellungen für Menschen mit Hörbehinderung vorhanden' => getBinaryAnswer($originalEntry[109]),
                'Besondere Hilfestellungen für Menschen mit Seebhinderung und Blinde vorhanden' => getBinaryAnswer($originalEntry[110]),
                'Allgemeine Hilfestellungen vor Ort vorhanden' => getBinaryAnswer($originalEntry[111]),
                'Beschreibung Hilfestellungen vor Ort' => $originalEntry[112],

                /**
                 * Art/Gewerbe
                 */
                'Kategorie' => $extractedEntry['category']
            );
        }
    }
}

// Generate CSV file
// createCSVFile('le-online-extracted-places.csv', $infoArray);

// Generate NT file
createRDFTurtleFile('le-online-extracted-places.nt', $infoArray);
