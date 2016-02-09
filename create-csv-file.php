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

$infoArray = array();
$matches = null;

$fileContentArray = array();
$key = 0;
$curl = new Curl\Curl();

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

        $infoArray[++$key] = array();

        // transform encoding to UTF-8
        $entry = iconv('iso-8859-1', 'utf-8', $entry);

        // remove <br> tags for better readability
        $entry = str_replace('<br>', '', $entry);

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

            $infoArray[$key]['title'] = str_replace(
                array('&amp;'),
                array('&'),
                trim($title)
            );
        } else {
            $infoArray[$key]['title'] = '';
        }

        /*
         * street
         */
        if ('Verkehr' != $category) {
            $regex = '/\n([^<]*)([0-9]{5})([^\n]*)/si';
        } else {
            $regex = '/\n\s*[a-z]+(.*?)([0-9]{5})\s*([äöüßa-z\-\s]{0,})\n*/s';
        }

        $matches = array();
        $return = preg_match(
            $regex,
            $entry,
            $matches
        );

        $infoArray[$key]['street'] = '';

        if (isset($matches[1])) {
            $infoArray[$key]['street'] = trim($matches[1]);
        }
        if (isset($matches[2])) {
            $infoArray[$key]['street'] .= ' '. trim($matches[2]);
        }
        if (isset($matches[3])) {
            $infoArray[$key]['street'] .= ' '. trim($matches[3]);
        }

        $infoArray[$key]['street'] = strip_tags($infoArray[$key]['street']);

        if (!isset($matches[1]) && !isset($matches[2]) && !isset($matches[3]) && 'Verkehr' != $category) {
            unset($infoArray[$key]);
            continue;
        }

        $infoArray[$key]['street'] = str_replace('Postanschrift:', '', $infoArray[$key]['street']);

        /*
         * email
         */
        preg_match(
            '/E-Mail:\s*<a href="mailto:([a-z0-9\/\-\:\.#@]{0,})">[a-z0-9\/\-\:\.#@]{0,}<\/a>[\s|\n|<br>]{1}/mi',
            $entry,
            $matches
        );
        if (isset($matches[1])) {
            $infoArray[$key]['email'] = $matches[1];
        } else {
            $infoArray[$key]['email'] = '';
        }

        /*
         * webUrl
         */
        preg_match(
            '/Internet:\s*<a href="([a-z0-9\/\-\:\.#]{0,})">[a-z0-9\/\-\:\.#]{0,}<\/a>[\s|\n|<br>]{1}/mi',
            $entry,
            $matches
        );

        if (isset($matches[1])) {
            $infoArray[$key]['webUrl'] = $matches[1];
        } else {
            $infoArray[$key]['webUrl'] = '';
        }

        /*
         * phone
         */
        preg_match(
            '/Tel.:\s*(.*?)[,|\n]/si',
            $entry,
            $matches
        );

        if (isset($matches[1])) {
            $infoArray[$key]['phone'] = strip_tags($matches[1]);
            $infoArray[$key]['phone'] = preg_replace('/[\(\)]*/s', '', $infoArray[$key]['phone']);
        } else {
            $infoArray[$key]['phone'] = '';
        }

        /*
         * notification
         */
        preg_match(
            '/Hinweise:(.*)<a name=/si',
            $entry,
            $matches
        );

        if (isset($matches[1])) {
            $infoArray[$key]['notice'] = strip_tags($matches[1]);
            $infoArray[$key]['notice'] = preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", $infoArray[$key]['notice']);
        } else {
            $infoArray[$key]['notice'] = '';
        }

        /*
         * support
         */
        // entry area fully accessable for wheelchair users
        if (false !== strpos($entry, '1111.gif')) {
            $infoArray[$key]['entryArea-wheelchair-access'] = 'Zugang: ebenerdig (max. 3 cm) oder über Rampe mit <= 6% Steigung';
            $infoArray[$key]['entryArea-wheelchair-doorWidth'] = '>= 90 cm';
            $infoArray[$key]['entryArea-wheelchair-support'] = 'vollständig';
        } else {
            // entry area teilweisely accessable for wheelchair users
            if (false !== strpos($entry, '2222.gif')) {
                $infoArray[$key]['entryArea-wheelchair-access'] = 'maximal 1 Stufe oder über Rampe mit <= 12% Steigung';
                $infoArray[$key]['entryArea-wheelchair-doorWidth'] = '>= 70 cm';
                $infoArray[$key]['entryArea-wheelchair-support'] = 'teilweise';
            } else {
                $infoArray[$key]['entryArea-wheelchair-access'] = '';
                $infoArray[$key]['entryArea-wheelchair-doorWidth'] = '';
                $infoArray[$key]['entryArea-wheelchair-support'] = '';
            }
        }

        // lift fully accessable for wheelchair users
        if (false !== strpos($entry, '3333.gif')) {
            $infoArray[$key]['lift-wheelchair-doorWidth'] = '>= 90cm';
            $infoArray[$key]['lift-wheelchair-cageDepth'] = '>= 140cm';
            $infoArray[$key]['lift-wheelchair-cageWidth'] = '>= 110cm';
            $infoArray[$key]['lift-wheelchair-distanceFromGroundControlsOutsideInside'] = '70-115cm';
            $infoArray[$key]['lift-wheelchair-support'] = 'ja';
            $infoArray[$key]['lift-persons-available'] = 'vorhanden';
        } else {
            $infoArray[$key]['lift-wheelchair-doorWidth'] = '';
            $infoArray[$key]['lift-wheelchair-cageDepth'] = '';
            $infoArray[$key]['lift-wheelchair-cageWidth'] = '';
            $infoArray[$key]['lift-wheelchair-distanceFromGroundControlsOutsideInside'] = '';
            $infoArray[$key]['lift-wheelchair-support'] = '';

            // lift for persons vorhanden
            if (false !== strpos($entry, '4444.gif')) {
                $infoArray[$key]['lift-persons-available'] = 'vorhanden';
            } else {
                $infoArray[$key]['lift-persons-available'] = '';
            }
        }

        // WC fully accessable for wheelchair users
        if (false !== strpos($entry, '5555.gif')) {
            $infoArray[$key]['toilets-wheelchair-noSteps'] = 'ja';
            $infoArray[$key]['toilets-wheelchair-doorWidth'] = '';
            $infoArray[$key]['toilets-wheelchair-spaceLeftOfWC'] = '>= 95cm';
            $infoArray[$key]['toilets-wheelchair-spaceRightOfWC'] = '>= 95cm';
            $infoArray[$key]['toilets-wheelchair-spaceBeforeWC'] = '>= Breite: 150cm, Tiefe: 150cm';
            $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceLeftOfWCAvailable'] = 'ja';
            $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceRightOfWCAvailable'] = 'ja';
            $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceLeftOrRightOfWCAvailable'] = 'ja';
            $infoArray[$key]['toilets-wheelchair-support'] = 'vollständig';
        } else {
            // WC partly accessable for wheelchair users
            if (false !== strpos($entry, '6666.gif')) {
                $infoArray[$key]['toilets-wheelchair-noSteps'] = '';
                $infoArray[$key]['toilets-wheelchair-doorWidth'] = '>= 70cm';
                $infoArray[$key]['toilets-wheelchair-spaceLeftOfWC'] = '>= 70cm';
                $infoArray[$key]['toilets-wheelchair-spaceRightOfWC'] = '>= 70cm';
                $infoArray[$key]['toilets-wheelchair-spaceBeforeWC'] = '>= Breite: 100cm, Tiefe: 100cm';
                $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceLeftOfWCAvailable'] = '';
                $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceRightOfWCAvailable'] = '';
                $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceLeftOrRightOfWCAvailable'] = 'ja';
                $infoArray[$key]['toilets-wheelchair-support'] = 'teilweise';
            } else {
                $infoArray[$key]['toilets-wheelchair-noSteps'] = '';
                $infoArray[$key]['toilets-wheelchair-doorWidth'] = '';
                $infoArray[$key]['toilets-wheelchair-spaceLeftOfWC'] = '';
                $infoArray[$key]['toilets-wheelchair-spaceRightOfWC'] = '';
                $infoArray[$key]['toilets-wheelchair-spaceBeforeWC'] = '';
                $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceLeftOfWCAvailable'] = '';
                $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceRightOfWCAvailable'] = '';
                $infoArray[$key]['toilets-wheelchair-foldableHoldingDeviceLeftOrRightOfWCAvailable'] = '';
                $infoArray[$key]['toilets-wheelchair-support'] = '';
            }
        }

        // hearing impaired
        if (false !== strpos($entry, '7777.gif')) {
            $infoArray[$key]['hearingImpaired-support'] = 'vorhanden';
        } else {
            $infoArray[$key]['hearingImpaired-support'] = '';
        }

        // blind and teilweisely blind
        if (false !== strpos($entry, 'aaaa.gif')) {
            $infoArray[$key]['blindAndPartiallyBlind-support'] = 'vorhanden';
        } else {
            $infoArray[$key]['blindAndPartiallyBlind-support'] = '';
        }

        // parking lots for handicaped persons
        if (false !== strpos($entry, '8888.gif')) {
            $infoArray[$key]['parkingLotForHandicapedPersons-support'] = 'vorhanden';
        } else {
            $infoArray[$key]['parkingLotForHandicapedPersons-support'] = '';
        }

        // special or general support for handicaped persons
        if (false !== strpos($entry, '9999.gif')) {
            $infoArray[$key]['specialOrGeneralSupportForHandicapedPersons-support'] = 'vorhanden';
        } else {
            $infoArray[$key]['specialOrGeneralSupportForHandicapedPersons-support'] = '';
        }

        // category of the building
        $infoArray[$key]['category'] = $category;

        // unset empty entries
        // $infoArray[$key] = unsetEmptyEntry($infoArray[$key]);

        // remove entries which only have one entry, which means its usually something like:
        // <a name="sport"> Sportanlagen </a></h2>
        // <p></p><p></p>
        if (2 > count($infoArray[$key])) {
            echo 'ÜRKS - Eintrag '. $key .' gelöscht.';
            unset($infoArray[$key]);
        }


        /**
         * Exception handling of certain entries of Verkehr category
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

        if (!in_array($infoArray[$key]['title'], $exceptionalEntries) && 'Verkehr' == $category) {
            unset($infoArray[$key]);
        }
    }
}

/**
 * CSV
 */

$infoArray = array_merge(array(array(
    "Titel",
    "Straße",
    "E-Mail",
    "Homepage",
    "Telefonnummer",
    "Hinweis",
    "Eingangsbereich: Zugang",
    "Eingangsbereich: Türbreite",
    "Eingangsbereich: Rollstuhl geeignet",
    "Aufzug: Türbreite",
    "Aufzug: Kabinen-Tiefe",
    "Aufzug: Kabinen-Breite",
    "Aufzug: Höhe der Bedienelemente außen, innen",
    "Aufzug: Rollstuhl-geeignet",
    "Aufzug: vorhanden?",
    "Behinderten-Toilette: stufenlos erreichbar",
    "Behinderten-Toilette: Türbreite",
    "Behinderten-Toilette: Platz links vom WC",
    "Behinderten-Toilette: Platz rechts vom WC",
    "Behinderten-Toilette: Platz vor dem WC",
    "Behinderten-Toilette: Stützgriffe links klappbar",
    "Behinderten-Toilette: Stützgriffe rechts klappbar",
    "Behinderten-Toilette: Stützgriffe links oder rechts klappbar",
    "Behinderten-Toilette: Rollstuhl geeignet",
    "Hilfen für hörgeschädigte Menschen",
    "Hilfen für blinde oder sehbehinderte Menschen",
    "Markierte Behindertenparkplätze sind vorhanden ",
    "Spezielle und persönliche Hilfeleistungen für Menschen mit Behinderungen",
    "Kategorie"
)), $infoArray);

$filename = 'le-online-extracted-places.csv';
$file = fopen($filename, 'w');
foreach ($infoArray as $key => $value) {
    fputcsv(
        $file,
        str_replace(
            array('&auml;', '&ouml;', '&uuml;', '&szlig;'),
            array('ä', 'ö', 'ü', 'ß'),
            $value
        ),
        ',',
        '"'
    );
}
fclose($file);
echo 'CSV-Datei '. $filename .' mit '. $key .' Einträgen erzeugt.
';
