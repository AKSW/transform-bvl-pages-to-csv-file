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

if (false === file_exists(__DIR__ . '/table.csv')) {
    throw new Exception('File ' . __DIR__ . '/table.csv not found. Aborting ...');
    return;
}

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
            $extractedData[$key]['Eingangsbereich-rollstuhlgerecht'] = 'vollständig';
        } else {
            // entry area partly accessible for wheelchair users
            if (false !== strpos($entry, '2222.gif')) {
                $extractedData[$key]['Eingangsbereich-rollstuhlgerecht'] = 'teilweise';
            } else {
                $extractedData[$key]['Eingangsbereich-rollstuhlgerecht'] = 'nein';
            }
        }

        // lift fully accessable for wheelchair users
        if (false !== strpos($entry, '3333.gif')) {
            $extractedData[$key]['Personenaufzug-rollstuhlgerecht'] = 'ja';
        } else {
           $extractedData[$key]['Personenaufzug-rollstuhlgerecht'] = 'nein';
        }

        // lift for persons available
        if (false !== strpos($entry, '4444.gif')) {
            $extractedData[$key]['Personenaufzug-vorhanden'] = 'ja';
        } else {
            $extractedData[$key]['Personenaufzug-vorhanden'] = 'nein';

            if ('ja' == $extractedData[$key]['Personenaufzug-rollstuhlgerecht']) {
                $extractedData[$key]['Personenaufzug-vorhanden'] = 'ja';
            }
        }

        // WC fully accessable for wheelchair users
        if (false !== strpos($entry, '5555.gif')) {
            $extractedData[$key]['Toilette-rollstuhlgerecht'] = 'vollständig';
        } else {
            // WC partly accessable for wheelchair users
            if (false !== strpos($entry, '6666.gif')) {
                $extractedData[$key]['Toilette-rollstuhlgerecht'] = 'teilweise';
            } else {
                $extractedData[$key]['Toilette-rollstuhlgerecht'] = 'nein';
            }
        }
    }
}

$collectedEntries = array();
$finalData = array();
$i = 0;

echo PHP_EOL;
echo '#########' . PHP_EOL;
echo 'Protokoll' . PHP_EOL;
echo '#########';
echo PHP_EOL;

foreach ($extractedData as $key => $extractedEntry) {
    foreach ($mdbDatabaseCSVExport as $key => $originalEntry) {
        if (!isset($originalEntry[5]) || !isset($originalEntry[7])) {
            var_dump($originalEntry);
            echo PHP_EOL . 'Data seems corrupt, essential fields are unset. Aborting ...';
            return;
        }

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
            $extractedEntry['Strasse'] = $street;
            $extractedEntry['PLZ'] = $originalEntry[9];
            $extractedEntry['Ort'] = $originalEntry[10];
            $extractedEntry['Oeffnungszeiten'] = $originalEntry[19];

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

            $extractedEntry['ID'] = generateBuildingUniqueIdentifier(
                $extractedEntry['Titel'],
                $extractedEntry['Strasse'],
                $extractedEntry['PLZ'],
                $extractedEntry['Ort'],
                $i++
            );

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
            $extractedEntry['Rampe-Farbliche-Markierung-an-Beginn-u-Ende-der-Rampe-vorhanden']
                = getBinaryAnswer($originalEntry[40]);

            // compute degree of the ramp
            $rampHeight = transformStringToFloat($originalEntry[36]);
            $rampLength = transformStringToFloat($originalEntry[35]);
            if (0 < $rampHeight && 0 < $rampLength) {
                $extractedEntry['Rampe-Steigung'] = round(asin($rampHeight / $rampLength)*100, 2);
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

            if ('ja' == getBinaryAnswer($originalEntry[46])) {
                $doorUri = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#AutomaticDoor';
            } elseif ('ja' == getBinaryAnswer($originalEntry[47])) {
                $doorUri = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#SemiautomaticDoor';
            } elseif ('ja' == getBinaryAnswer($originalEntry[48])) {
                $doorUri = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#CirclingLeafDoor';
            } elseif ('ja' == getBinaryAnswer($originalEntry[49])) {
                $doorUri = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#SlideDoor';
            } elseif ('ja' == getBinaryAnswer($originalEntry[51])) {
                $doorUri = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#SwingDoor';
            } elseif ('ja' == getBinaryAnswer($originalEntry[50])
                || 'ja' == getBinaryAnswer($originalEntry[52])) {
                $doorUri = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#SomeDoor';
            }
            $extractedEntry['Tuerart-am-Eingang'] = $doorUri;

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
            if (false !== strpos($originalEntry[33], 'geringen Wendekreis beachten!')) {
                $extractedEntry['Aufzug-Wendekreis-bei-Ausstieg'] = 'gering';
                $extractedEntry['Personenaufzug-rollstuhlgerecht'] = 'teilweise';
            } else {
                $extractedEntry['Aufzug-Wendekreis-bei-Ausstieg'] = 'ausreichend';
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

            if ('ja' == getBinaryAnswer($originalEntry[84])) {
                $type = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#Phototube';
            } elseif ('ja' == getBinaryAnswer($originalEntry[85])) {
                $type = 'https://github.com/AKSW/leds-asp-f-ontologies/raw/master/ontologies/building/ontology.ttl#LeverArm';
            } else {
                $type = '';
            }
            $extractedEntry['Aktivierung-Amatur-Waschbecken-Toilettenkabine'] = $type;

            $extractedEntry['Aktivierung-Amatur-Waschbecken-Toilettenkabine-mit-Fotozelle'] = getBinaryAnswer($originalEntry[84]);
            $extractedEntry['Aktivierung-Amatur-Waschbecken-Toilettenkabine-mit-Hebelarm'] = getBinaryAnswer($originalEntry[85]);
            /*
             * Hilfestellungen
             */
            $extractedEntry['Besondere-Hilfestellungen-f-Menschen-m-Hoerbehinderung-vorhanden'] = getBinaryAnswer($originalEntry[109]);
            $extractedEntry['Besondere-Hilfestellungen-f-Menschen-m-Seebhind-Blinde-vorhanden'] = getBinaryAnswer($originalEntry[110]);
            $extractedEntry['Allgemeine-Hilfestellungen-vor-Ort-vorhanden'] = getBinaryAnswer($originalEntry[111]);
            $extractedEntry['Beschreibung-Hilfestellungen-vor-Ort'] = $originalEntry[112];

            $finalData[] = $extractedEntry;

            echo PHP_EOL . $extractedEntry['Titel'] . ' finished' . PHP_EOL;

            break;
        }
    }
}

echo PHP_EOL . PHP_EOL . '----------';

// Generate CSV file
createCSVFile('le-online-extracted-places.csv', $finalData);

echo PHP_EOL;
echo PHP_EOL;
