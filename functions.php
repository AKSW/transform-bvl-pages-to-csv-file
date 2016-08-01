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
        $placeUri = generateBuildingUri(
            $placeEntry['Titel'],
            $placeEntry['Strasse'],
            $placeEntry['PLZ'],
            $placeEntry['Ort'],
            $bvlRootUrl
        );

        // title
        $placeEntry['Titel'] = str_replace('"', '', $placeEntry['Titel']);
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'titel'),
            new LiteralImpl($placeEntry['Titel'])
        );

        /*
         * address information
         */
        $placeEntry['Strasse'] = str_replace('"', '', $placeEntry['Strasse']);
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'strasse'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Strasse']))
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
            new LiteralImpl(
                (string)$placeEntry['Longitude'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($geoNs . 'latitude'),
            new LiteralImpl(
                (string)$placeEntry['Latitude'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );

        /*
         * entrance area
         */
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'eingangsbereichIstRollstuhlgerecht'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Eingangsbereich-rollstuhlgerecht']))
        );

        /*
         * lift
         */
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'personenaufzugVorhanden'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Personenaufzug-vorhanden']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'personenaufzugIstRollstuhlgerecht'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Personenaufzug-rollstuhlgerecht']))
        );

        /*
         * toilet information
         */
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'toiletteVorhanden'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Toilette-in-der-Einrichtung-vorhanden']))
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'toiletteRollstuhlgerecht'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['Toilette-rollstuhlgerecht']))
        );
    }

    // serialize statement array to n-triples and store it as file
    $serializerFactory = new SerializerFactoryImpl();
    $serializer = $serializerFactory->createSerializerFor('n-triples');
    $serializer->serializeIteratorToStream(
        new ArrayStatementIteratorImpl($stmtArray),
        __DIR__ . '/'. $filename
    );

    echo PHP_EOL . 'File '. $filename .' with '. count($stmtArray) .' triples created.';
}

/**
 * Generates RDF/Turtle file
 *
 * @param string $filename
 * @param array $infoArray
 */
function createEnrichedRDFFile($filename, array $infoArray)
{
    $stmtArray = array();
    $rootUri = 'http://le-online.de/place/';
    $rdfUri = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    $liftOntologyUri = 'https://raw.githubusercontent.com/AKSW/leds-asp-f-ontologies/'
        . 'master/ontologies/elevator/ontology.ttl#';

    foreach ($infoArray as $placeEntry) {

        // ignore buildings with no elevator
        if ('nein' == $placeEntry['Aufzug-in-der-Einrichtung-vorhanden']) {
            continue;
        }

        /*
         * Basic structure:
         * Building => Elevator => ElevatorDoor
         *                      => ElevatorShaft
         *                      => ElevatorWall
         *                      => ElevatorCabine
         */
        $buildingUri = generateBuildingUri(
            $placeEntry['Titel'],
            $placeEntry['Strasse'],
            $placeEntry['PLZ'],
            $placeEntry['Ort'],
            $rootUri
        );
        $elevatorUri = $buildingUri . '/elevator';
        $elevatorShaftUri = $buildingUri . '/elevator/shaft';
        $elevatorCabineUri = $buildingUri . '/elevator/cabine';
        $elevatorDoorUri = $buildingUri . '/elevator/door';
        $elevatorWallUri = $buildingUri . '/elevator/wall';

        // b rdf:type :Building
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($buildingUri),
            new NamedNodeImpl($rdfUri . 'type'),
            new NamedNodeImpl($liftOntologyUri . 'Building')
        );

        // e rdf:type :Elevator
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorUri),
            new NamedNodeImpl($rdfUri . 'type'),
            new NamedNodeImpl($liftOntologyUri . 'Elevator')
        );

        // s rdf:type :ElevatorShaft
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorShaftUri),
            new NamedNodeImpl($rdfUri . 'type'),
            new NamedNodeImpl($liftOntologyUri . 'ElevatorShaft')
        );

        // c rdf:type :ElevatorCabine
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorCabineUri),
            new NamedNodeImpl($rdfUri . 'type'),
            new NamedNodeImpl($liftOntologyUri . 'ElevatorCabine')
        );

        // d rdf:type :ElevatorDoor
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorDoorUri),
            new NamedNodeImpl($rdfUri . 'type'),
            new NamedNodeImpl($liftOntologyUri . 'ElevatorDoor')
        );

        // w rdf:type :ElevatorWall
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorWallUri),
            new NamedNodeImpl($rdfUri . 'type'),
            new NamedNodeImpl($liftOntologyUri . 'ElevatorWall')
        );

        /*
         * relations
         */

        // b hasElevator e
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($buildingUri),
            new NamedNodeImpl($liftOntologyUri . 'hasElevator'),
            new NamedNodeImpl($elevatorUri)
        );

        // e hasElevatorShaft s
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorUri),
            new NamedNodeImpl($liftOntologyUri . 'hasElevatorShaft'),
            new NamedNodeImpl($elevatorShaftUri)
        );

        // e hasElevatorShaft c
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorUri),
            new NamedNodeImpl($liftOntologyUri . 'hasElevatorCabine'),
            new NamedNodeImpl($elevatorCabineUri)
        );

        // e hasElevatorShaft d
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorUri),
            new NamedNodeImpl($liftOntologyUri . 'hasElevatorDoor'),
            new NamedNodeImpl($elevatorDoorUri)
        );

        // e hasElevatorShaft w
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorUri),
            new NamedNodeImpl($liftOntologyUri . 'hasElevatorWall'),
            new NamedNodeImpl($elevatorWallUri)
        );

        /*
         * properties
         */
        // elevator door
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorDoorUri),
            new NamedNodeImpl($liftOntologyUri . 'width'),
            new LiteralImpl(
                (string)$placeEntry['Aufzug-Tuerbreite-cm'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );
        // elevator cabine
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorCabineUri),
            new NamedNodeImpl($liftOntologyUri . 'width'),
            new LiteralImpl(
                (string)$placeEntry['Aufzug-Breite-Innenkabine-cm'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorCabineUri),
            new NamedNodeImpl($liftOntologyUri . 'depth'),
            new LiteralImpl(
                (string)$placeEntry['Aufzug-Tiefe-Innenkabine-cm'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorCabineUri),
            new NamedNodeImpl($liftOntologyUri . 'highestHeightOfControlPanelButton'),
            new LiteralImpl(
                (string)$placeEntry['Aufzug-Hoehe-oberster-Bedienknopf-in-Innenkabine-cm'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );
        // before elevator cabine
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($elevatorCabineUri),
            new NamedNodeImpl($liftOntologyUri . 'highestHeightOfControlPanelButtonBeforeCabine'),
            new LiteralImpl(
                (string)$placeEntry['Aufzug-Hoehe-oberster-Bedienknopf-in-Innenkabine-cm'],
                new NamedNodeImpl('http://www.w3.org/2001/XMLSchema#float')
            )
        );
    }

    // serialize statement array to n-triples and store it as file
    $serializerFactory = new SerializerFactoryImpl();
    $serializer = $serializerFactory->createSerializerFor('n-triples');
    $serializer->serializeIteratorToStream(
        new ArrayStatementIteratorImpl($stmtArray),
        __DIR__ . '/'. $filename
    );

    echo PHP_EOL . 'File '. $filename .' with '. count($stmtArray) .' triples created.';
}

/**
 * By given root URI and raw building title, an URI will be generated.
 *
 * @param string $rawTitle
 * @param string $rootUri
 * @return string Building URI
 */
function generateBuildingUri($title, $street, $zip, $city, $rootUri)
{
    /*
     * generate good title for the URL later on (URL encoded, but still human readable)
     */
    $buildingUri = simplifyUriPart($title)
        . '-' . simplifyUriPart($street)
        . '-' . simplifyUriPart($zip)
        . '-' . simplifyUriPart($city);

    return $rootUri . str_replace(array('&'), array('-and-'), simplifyUriPart($buildingUri));
}

function simplifyUriPart($string)
{
    /*
    return urlencode(trim(
        preg_replace('/\s\s+/', ' ', strtolower($string))
    ));*/

    return str_replace(
        array(
            ' ',     'ß',  'ä',  'Ä',  'ü',  'Ü',  'ö',  'Ö',  '<br-/>', '&uuml;', '&auml;', '&ouml;', '"', 'eacute;', '/',     '\\',
            'ouml;', 'auml;', 'uuml;', ',', "'", '>', '<', '`', '´', '(', ')'
        ),
        array(
            '-',     'ss', 'ae', 'ae', 'ue', 'ue', 'oe', 'oe', '',       'ue',     'ae',     'oe',     '',
            'é',       '_',     '_',
            'oe',    'ae',    'ue',    '-', '_', '',  '',  '',  '',  '',  ''
        ),
        trim(
            preg_replace('/\s\s+/', ' ', strtolower($string))
        )
    );
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
