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

        $extractedData[$key]['Kategorie'] = $category;

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

            $extractedData[$key]['Titel'] = str_replace(
                array('&amp;', '&auml;', '&uuml;', '&ouml;', '&Auml;', '&Uuml;', '&Ouml;', '&szlig;', '&eacute;'),
                array('&', 'ä', 'ü', 'ö', 'Ä', 'Ü', 'Ö', 'ß', 'é'),
                trim($title)
            );
        } else {
            $extractedData[$key]['Titel'] = '';
        }

        // empty title or invalid? remove entry!
        $invalidTitles = array(
            '',
            'Anlagen/Parks',
        );

        if (in_array($extractedData[$key]['Titel'], $invalidTitles)) {
            unset($extractedData[$key]);
            continue;
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
            if (!in_array($extractedData[$key]['Titel'], $exceptionalEntries) && 'Verkehr' == $category) {
                unset($extractedData[$key]);
                continue;
            }
        }

        // if we reach this part, the entry is valid

        /*
         * support
         */

        // entry area fully accessible for wheelchair users
        if (false !== strpos($entry, '1111.gif')) {
            $extractedData[$key]['Eingangsbereich ist rollstuhlgerecht'] = 'vollständig';
        } else {
            // entry area partly accessible for wheelchair users
            if (false !== strpos($entry, '2222.gif')) {
                $extractedData[$key]['Eingangsbereich ist rollstuhlgerecht'] = 'teilweise';
            } else {
                $extractedData[$key]['Eingangsbereich ist rollstuhlgerecht'] = 'nein';
            }
        }

        // lift fully accessable for wheelchair users
        if (false !== strpos($entry, '3333.gif')) {
            $extractedData[$key]['Personenaufzug ist rollstuhlgerecht'] = 'ja';
        } else {
           $extractedData[$key]['Personenaufzug ist rollstuhlgerecht'] = 'nein';
        }

        // lift for persons available
        if (false !== strpos($entry, '4444.gif')) {
            $extractedData[$key]['Personenaufzug vorhanden'] = 'ja';
        } else {
            $extractedData[$key]['Personenaufzug vorhanden'] = 'nein';
        }

        // WC fully accessable for wheelchair users
        if (false !== strpos($entry, '5555.gif')) {
            $extractedData[$key]['Toilette ist rollstuhlgerecht'] = 'vollständig';
        } else {
            // WC partly accessable for wheelchair users
            if (false !== strpos($entry, '6666.gif')) {
                $extractedData[$key]['Toilette ist rollstuhlgerecht'] = 'teilweise';
            } else {
                $extractedData[$key]['Toilette ist rollstuhlgerecht'] = 'nein';
            }
        }
    }
}

$collectedEntries = array();
$finalData = array();
$i = 0;

echo PHP_EOL;
echo '###############' . PHP_EOL;
echo 'Fehlerprotokoll' . PHP_EOL;
echo '###############';
echo PHP_EOL;

foreach ($extractedData as $key => $extractedEntry) {
    foreach ($mdbDatabaseCSVExport as $key => $originalEntry) {
        $street = preg_replace('/(\(.*?\))/si', '', $originalEntry[7]);

        // if title of mdb-dataset matches with one of the online ones
        if ($originalEntry[5] == $extractedEntry['Titel']) {

            $titleStreet = $originalEntry[5] . $street;
            // ignore entries, which are already collected
            if (isset($collectedEntries[$titleStreet])){
                continue;
            } else {
                $collectedEntries[$titleStreet] = $titleStreet;
            }

            /**
             * Allgemeine Informationen
             */
            $extractedEntry['Straße'] = $street;
            $extractedEntry['PLZ'] = $originalEntry[9];
            $extractedEntry['Ort'] = $originalEntry[10];
            $extractedEntry['Oeffnungszeiten'] = $originalEntry[19];

            /**
             * Enrich data with adress information
             */
            // ask for long and lat for a given address (cached access)
            list($long, $lat) = getLongLatForAddress(
                $extractedEntry['Titel'],
                $extractedEntry['Straße'],
                $extractedEntry['PLZ'],
                $extractedEntry['Ort']
            );

            $extractedEntry['Longitude'] = $long;
            $extractedEntry['Latitude'] = $lat;

            /*
             * Parkplatz
             */
            $extractedEntry['Parkplatz vorhanden'] = getBinaryAnswer($originalEntry[20]);
            $extractedEntry['Parkplatz vor Einrichtung vorhanden'] = getBinaryAnswer($originalEntry[21]);
            $extractedEntry['Anzahl Behindertenparkplätze auf Parkplatz vor Einrichtung'] = $originalEntry[22];
            $extractedEntry['Hauseigener Parkplatz vorhanden'] = getBinaryAnswer($originalEntry[23]);
            $extractedEntry['Anzahl Behindertenparkplätze auf hauseigenem Parkplatz'] = $originalEntry[25];
            $extractedEntry['Ort des hauseigenen Parkplatzes'] = $originalEntry[24];
            /*
             * Eingangsbereich
             */
            $extractedEntry['Stufen bis Eingang vorhanden'] = getBinaryAnswer($originalEntry[26]);
            $extractedEntry['Anzahl der Stufen bis Eingang'] = $originalEntry[27];
            $extractedEntry['Höhe einer Stufe bis Eingang (cm)'] = $originalEntry[28];
            $extractedEntry['Eingangsbereich: Handlauf durchgehend links vorhanden'] = getBinaryAnswer($originalEntry[29]);
            $extractedEntry['Eingangsbereich: Handlauf durchgehend rechts vorhanden'] = getBinaryAnswer($originalEntry[30]);
            $extractedEntry['Eingangsbereich: Farbliche Markierung der ersten und letzten Stufe vorhanden'] = getBinaryAnswer($originalEntry[31]);
            $extractedEntry['Alternativer Eingang für Rollstuhlfahrer vorhanden'] = getBinaryAnswer($originalEntry[32]);
            $extractedEntry['Ort des alternativen Eingangs für Rollstuhlfahrer'] = $originalEntry[33];
            /*
             * Rampe im Eingangsbereich
             */
            $extractedEntry['Rampe vor Eingang vorhanden'] = getBinaryAnswer($originalEntry[34]);
            $extractedEntry['Länge der Rampe (cm)'] = $originalEntry[35];
            $extractedEntry['Höhe der Rampe (cm)'] = $originalEntry[36];
            $extractedEntry['Breite der Rampe (cm)'] = $originalEntry[37];
            $extractedEntry['Rampe: Handlauf durchgehend links vorhanden'] = getBinaryAnswer($originalEntry[38]);
            $extractedEntry['Rampe: Handlauf durchgehend rechts vorhanden'] = getBinaryAnswer($originalEntry[39]);
            $extractedEntry['Rampe: Farbliche Markierung von Beginn und Ende der Rampe vorhanden'] = getBinaryAnswer($originalEntry[40]);
            /*
             * Klingel im Eingangsbereich
             */
            $extractedEntry['Eingangsbereich: Klingel vorhanden'] = getBinaryAnswer($originalEntry[41]);
            $extractedEntry['Eingangsbereich: Ort der Klingel'] = $originalEntry[42];
            $extractedEntry['Eingangsbereich: Klingel mit Wechselsprechanlage vorhanden'] = getBinaryAnswer($originalEntry[43]);
            $extractedEntry['Eingangsbereich: Höhe oberster Bedienknopf von Klingel (cm)'] = $originalEntry[44];
            /*
             * Tür im Eingangsbereich
             */
            $extractedEntry['Kleinste Türbreite bis Erreichen der Einrichtung (cm)'] = $originalEntry[45];
            $extractedEntry['Art der Tür im Eingangsbereich: Automatische Tür'] = getBinaryAnswer($originalEntry[46]);
            $extractedEntry['Art der Tür im Eingangsbereich: Halbautomatische Tür'] = getBinaryAnswer($originalEntry[47]);
            $extractedEntry['Art der Tür im Eingangsbereich: Drehtür'] = getBinaryAnswer($originalEntry[48]);
            $extractedEntry['Art der Tür im Eingangsbereich: Schiebetür'] = getBinaryAnswer($originalEntry[49]);
            $extractedEntry['Art der Tür im Eingangsbereich: Drehflügeltür'] = getBinaryAnswer($originalEntry[50]);
            $extractedEntry['Art der Tür im Eingangsbereich: Pendeltür'] = getBinaryAnswer($originalEntry[51]);
            $extractedEntry['Art der Tür im Eingangsbereich: andere Türart'] = getBinaryAnswer($originalEntry[52]);
            /*
             * Aufzug in der Einrichtung
             */
            $extractedEntry['Aufzug in der Einrichtung vorhanden'] = getBinaryAnswer($originalEntry[53]);
            $extractedEntry['Anzahl der Stufen bis Aufzug in der Einrichtung'] = $originalEntry[54];
            $extractedEntry['Aufzug: Türbreite (cm)'] = $originalEntry[56];
            $extractedEntry['Stufen bis Aufzug in der Einrichtung vorhanden'] = getBinaryAnswer($originalEntry[55]);
            $extractedEntry['Aufzug: Breite Innenkabine (cm)'] = $originalEntry[57];
            $extractedEntry['Aufzug: Tiefe Innenkabine (cm)'] = $originalEntry[58];
            $extractedEntry['Aufzug: Höhe oberster Bedienknopf in Innenkabine (cm)'] = $originalEntry[59];
            $extractedEntry['Aufzug: Höhe oberster Bedienknopf außerhalb (cm)'] = $originalEntry[60];
            $extractedEntry['Aufzug: Ort Aufenthaltsort Aufzugsberechtigter'] = $originalEntry[61];
            /*
             * Toilette in der Einrichtung
             */
            $extractedEntry['Toilette in der Einrichtung vorhanden'] = getBinaryAnswer($originalEntry[63]);
            $extractedEntry['Toilette ist mit Piktogramm als Behindertentoilette gekennzeichnet'] = getBinaryAnswer($originalEntry[64]);
            $extractedEntry['Stufen bis Toilette in Einrichtung vorhanden'] = getBinaryAnswer($originalEntry[65]);
            $extractedEntry['Anzahl Stufen bis Toilette in Einrichtung'] = $originalEntry[66];
            $extractedEntry['Höhe der Stufen bis Toilette in Einrichtung (cm)'] = $originalEntry[67];
            $extractedEntry['Stufen bis Toilette: Handlauf durchgehend links vorhanden'] = getBinaryAnswer($originalEntry[68]);
            $extractedEntry['Stufen bis Toilette: Handlauf durchgehend rechts vorhanden'] = getBinaryAnswer($originalEntry[69]);
            $extractedEntry['Stufen bis Toilette: Farbliche Markierung erste und letzte Stufe'] = getBinaryAnswer($originalEntry[70]);
            $extractedEntry['Türbreite der Toilettenkabine (cm)'] = $originalEntry[71];
            $extractedEntry['Toilettentür von außen entriegelbar'] = getBinaryAnswer($originalEntry[72]);
            $extractedEntry['Notklingel in Toilettenkabine vorhanden'] = getBinaryAnswer($originalEntry[73]);
            $extractedEntry['Höhe Notklingel in Toilettenkabine'] = $originalEntry[74];
            $extractedEntry['Bewegungsfläche vor WC: Tiefe (cm)'] = $originalEntry[75];
            $extractedEntry['Bewegungsfläche vor WC: Breite (cm)'] = $originalEntry[76];
            $extractedEntry['Bewegungsfläche links vom WC: Tiefe (cm)'] = $originalEntry[77];
            $extractedEntry['Bewegungsfläche links vom WC: Breite (cm)'] = $originalEntry[78];
            $extractedEntry['Bewegungsfläche rechts vom WC: Tiefe (cm)'] = $originalEntry[79];
            $extractedEntry['Bewegungsfläche rechts vom WC: Breite (cm)'] = $originalEntry[80];
            $extractedEntry['Aktivierung Amatur Waschbecken in Toilettenkabine über Fotozelle möglich'] = getBinaryAnswer($originalEntry[84]);
            $extractedEntry['Aktivierung Amatur Waschbecken in Toilettenkabine über Hebelarm möglich'] = getBinaryAnswer($originalEntry[85]);
            /*
             * Hilfestellungen
             */
            $extractedEntry['Besondere Hilfestellungen für Menschen mit Hörbehinderung vorhanden'] = getBinaryAnswer($originalEntry[109]);
            $extractedEntry['Besondere Hilfestellungen für Menschen mit Seebhinderung und Blinde vorhanden'] = getBinaryAnswer($originalEntry[110]);
            $extractedEntry['Allgemeine Hilfestellungen vor Ort vorhanden'] = getBinaryAnswer($originalEntry[111]);
            $extractedEntry['Beschreibung Hilfestellungen vor Ort'] = $originalEntry[112];

            $finalData[] = $extractedEntry;

            break;
        }
    }
}

echo PHP_EOL . PHP_EOL . '----------';

// Generate CSV file
createCSVFile('le-online-extracted-places.csv', $finalData);

// Generate NT file
createRDFTurtleFile('le-online-extracted-places.nt', $finalData);
