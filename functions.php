<?php

use RedBeanPHP\R;
use Saft\Data\SerializerFactoryImpl;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\StatementImpl;

// setup Database connection
try {
    R::setup('mysql:host=localhost;dbname=test', 'root', '');
} catch (RedBeanPHP\RedException $e) {}

/**
 * Generates CSV file
 *
 * @param string $filename
 * @param array $infoArray
 */
function createCSVFile($filename, array $infoArray)
{
    $file = fopen($filename, 'w');
    $copy = $infoArray;

    // set title
    fputcsv($file, array_keys(array_shift($copy)));
    $i = 0;
    foreach ($infoArray as $value) {
        fputcsv($file, $value);
        ++$i;
    }
    fclose($file);
    echo PHP_EOL . 'CSV-file '. $filename .' with '. $i .' entries created.';
}

/**
 * Generates RDF/Turtle file
 *
 * @param string $filename
 * @param array $infoArray
 */
function createRDFTurtleFile($filename, array $infoArray)
{
    $stmtArray = array();
    $bvlRootUrl = 'http://le-online.de/place/';
    $bvlNamespaceUrl = 'http://le-online.de/ontology/place/ns#';
    $geoNs = 'http://www.w3.org/2003/01/geo/wgs84_pos#';

    foreach ($infoArray as $placeEntry) {

        /*
         * generate good title for the URL later on (URL encoded, but still human readable)
         */
        $placeUri = str_replace(
            array(
                ' ',     'ß',  'ä',  'Ä',  'ü',  'Ü',  'ö',  'Ö',  '<br-/>', '&uuml;', '&auml;', '&ouml;', '"', 'eacute;', '/',
                'ouml;', 'auml;', 'uuml;', ',', "'", '>', '<', '`', '´', '(', ')'
            ),
            array(
                '-',     'ss', 'ae', 'ae', 'ue', 'ue', 'oe', 'oe', '',       'ue',     'ae',     'oe',     '',  'e',       '_',
                'oe',    'ae',    'ue',    '-', '_', '', '', '', '', '', ''
            ),
            trim(
                preg_replace('/\s\s+/', ' ', strtolower($placeEntry['Titel']))
            )
        );
        $placeUri = $bvlRootUrl . str_replace(array('&'), array('-and-'), $placeUri);

        // title
        $placeEntry['Titel'] = addslashes($placeEntry['Titel']);
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'titel'),
            new LiteralImpl($placeEntry['Titel'])
        );

        /*
         * address information
         */
        $placeEntry['Straße'] = addslashes($placeEntry['Straße']);
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'strasse'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Straße']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'postleitzahl'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['PLZ']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'stadt'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Ort']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($geoNs . 'long'),
            new LiteralImpl((string)$placeEntry['Longitude'])
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($geoNs . 'latitude'),
            new LiteralImpl((string)$placeEntry['Latitude'])
        );

        /*
         * entrance area
         */
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'eingangsbereichIstRollstuhlgerecht'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Eingangsbereich ist rollstuhlgerecht']))
        );

        /*
         * lift
         */
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'personenAufzugVorhanden'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Personenaufzug vorhanden']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'aufzugIstRollstuhlgerecht'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Aufzug ist rollstuhlgerecht']))
        );

        /*
         * toilet information
         */
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'toiletteVorhanden'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Toilette in der Einrichtung vorhanden']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'toiletteRollstuhlgerecht'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Toilette ist rollstuhlgerecht']))
        );
    }

    // serialize statement array to n-triples and store it as file
    $serializerFactory = new SerializerFactoryImpl();
    $serializer = $serializerFactory->createSerializerFor('n-triples');
    $serializer->serializeIteratorToStream(
        new ArrayStatementIteratorImpl($stmtArray),
        __DIR__ . '/'. $filename
    );

    echo PHP_EOL . 'N-Triples file '. $filename .' with '. count($stmtArray) .' triples created.';
}

/**
 * @param string $title Title of the building
 * @param string $street Street name with house number
 * @param string $zip Zip code
 * @param string $city City of the location
 * @return array Array with first element the longitude and second the latitude, if available.
 */
function getLongLatForAddress($title, $street, $zip, $city)
{
    if (empty($street) || empty($zip) || empty($city)) {
        return array('', '');
    }

    // try to find a building entry in our cache which has the given address
    $entry = R::findOne('buildinggeometry', ' street = ? AND zip = ? AND city = ? ', array(
        $street, $zip, $city
    ));

    // if entry is not already in our cache
    if (null == $entry) {
        $entry = R::dispense('buildinggeometry');
        $entry->title = $title;
        $entry->street = $street;
        $entry->zip = $zip;
        $entry->city = $city;
        $entry->lng = "";
        $entry->lat = "";
    }
    // if long and lat is not set, try to get it from google
    if ('' == $entry->long && '' == $entry->lat) {
        $curl = new Curl\Curl();

        // ask google for geometry information for a given address
        $curl->get('http://maps.googleapis.com/maps/api/geocode/json', array(
            'address' => $street . ' ' . $zip . ' ' . $city
        ));

        // store JSON result
        $latLongInformation = json_decode(json_encode($curl->response), true);

        if (isset($latLongInformation['results'][0]['geometry']['location']['lat'])
            && isset($latLongInformation['results'][0]['geometry']['location']['lng'])) {
            $entry->lng = $latLongInformation['results'][0]['geometry']['location']['lng'];
            $entry->lat = $latLongInformation['results'][0]['geometry']['location']['lat'];
            R::store($entry);
        } else {
            echo PHP_EOL . PHP_EOL . $title;
            echo PHP_EOL . $street .' '. $zip .' '. $city;
            echo PHP_EOL . 'no lat OR long';
            $entry->lng = '';
            $entry->lat = '';
        }
    }

    return array($entry->lng, $entry->lat);
}

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
