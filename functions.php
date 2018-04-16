<?php

use RedBeanPHP\R;
use Saft\Data\SerializerFactoryImpl;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\StatementImpl;

// setup Database connection
try {
    R::setup('sqlite:./geo-info.sqlite3');
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

    if (0 == count($infoArray)) {
        throw new Exception('Given $infoArray parameter is empty. Aborting CSV file generation...');
    }

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
 * By given root URI and raw building title, an URI will be generated.
 *
 * @param string $title
 * @param string $street
 * @param string $zip
 * @param string $city
 * @param string $position
 */
function generateBuildingUniqueIdentifier($title, $street, $zip, $city, $position)
{
    /*
     * generate good title for the URL later on (URL encoded, but still human readable)
     */
    $buildingUri = $position . '-' . simplifyUriPart($title)
        . '-' . simplifyUriPart($street)
        . '-' . simplifyUriPart($zip)
        . '-' . simplifyUriPart($city);

    return str_replace(array('&'), array('-and-'), simplifyUriPart($buildingUri));
}

function simplifyUriPart($string)
{
    $string = trim(
        preg_replace('/\s\s+/', '-', strtolower($string))
    );

    $string = str_replace(
        array(
            ' ',     'ß',  'ä',  'Ä',  'ü',  'Ü',  'ö',  'Ö',  '<br-/>', '&uuml;', '&auml;', '&ouml;', '"', 'eacute;', '/',     '\\',
            'ouml;', 'auml;', 'uuml;', ',', "'", '>', '<', '`', '´', '(', ')'
        ),
        array(
            '-',     'ss', 'ae', 'ae', 'ue', 'ue', 'oe', 'oe', '',       'ue',     'ae',     'oe',     '',
            'é',       '_',     '_',
            'oe',    'ae',    'ue',    '-', '_', '',  '',  '',  '',  '',  ''
        ),
        $string
    );

    // transform multiple dashes to one
    return preg_replace('/--+/', '-', $string);
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
    // if lng and lat is not set, try to get it from google
    if (1 > (float)$entry->lng || 1 > (float)$entry->lat) {
        $curl = new Curl\Curl();

        // ask google for geometry information for a given address
        $curl->get('http://maps.googleapis.com/maps/api/geocode/json', array(
            'address' => $street . ' ' . $zip . ' ' . $city
        ));

        // store JSON result
        $latLongInformation = json_decode(json_encode($curl->response), true);

        if (isset($latLongInformation['results'][0]['geometry']['location']['lat'])) {
            $lat = $latLongInformation['results'][0]['geometry']['location']['lat'];
        } else {
            $lat = 0;
        }

        if (isset($latLongInformation['results'][0]['geometry']['location']['lng'])) {
            $lng = $latLongInformation['results'][0]['geometry']['location']['lng'];
        } else {
            $lng = 0;
        }

        if (0 < (float)$lat && 0 < (float)$lng) {
            $entry->lng = $lng;
            $entry->lat = $lat;
            R::store($entry);
        } else {
            echo PHP_EOL . PHP_EOL . $title;
            echo PHP_EOL . $street .' '. $zip .' '. $city;
            echo PHP_EOL . 'no lat OR lng';
            $entry->lng = $lng;
            $entry->lat = $lat;
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
 * @return string true or false
 */
function getBinaryAnswer($value)
{
    $value = (int)$value;
    return 1 == $value ? 'true' : 'false';
}

/**
 * Transforms strings like 7.2769482308e+01 to a real float.
 *
 * @param string $value
 * @return null|float
 */
function transformStringToFloat($value)
{
    if (is_string($value) && false !== strpos($value, 'e')) {
        return (0 + str_replace(',', '.', $value));
    }

    return $value;
}
