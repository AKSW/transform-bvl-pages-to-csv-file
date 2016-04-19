<?php

use Saft\Data\SerializerFactoryImpl;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\StatementImpl;

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
 * Generates CSV file
 *
 * @param string $filename
 * @param array $infoArray
 */
function createCSVFile($filename, array $infoArray)
{
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

    $file = fopen($filename, 'w');
    foreach ($infoArray as $key => $value) {
        fputcsv(
            $file,
            str_replace(
                array('&auml;', '&ouml;', '&uuml;', '&szlig;', '&szlig;'),
                array('ä',      'ö',      'ü',      'ß',       '&'),
                $value
            ),
            ',', // delimiter
            '"'  // surrounds a datafield
        );
    }
    fclose($file);
    echo 'CSV-Datei '. $filename .' mit '. $key .' Einträgen erzeugt.' . PHP_EOL;
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

    foreach ($infoArray as $placeEntry) {

        /*
         * generate good title for the URL later on (URL encoded, but still human readable)
         */
        $placeUri = str_replace(
            array(
                ' ',     'ß',  'ä',  'ü',  'ö',  'ö',  '<br-/>', '&uuml;', '&auml;', '&ouml;', '"', 'eacute;', '/',
                'ouml;', 'auml;', 'uuml;', ',', "'", '>', '<', '`', '´'
            ),
            array(
                '-',     'ss', 'ae', 'ue', 'oe', 'oe', '',       'ue',     'ae',     'oe',     '',  'e',       '_',
                'oe',    'ae',    'ue',    '-', '_', '-', '-', '-', '-'
            ),
            strtolower(
                trim(
                    preg_replace('/\s\s+/', ' ', $placeEntry['title'])
                )
            )
        );
        $placeUri = $bvlRootUrl . str_replace(array('&', ), array('-and-',), $placeUri);

        // title
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'placeName'),
            new LiteralImpl($placeEntry['title'])
        );

        // address information
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'address'),
            new LiteralImpl(preg_replace('/\s\s+/', ' ', $placeEntry['street']))
        );

        // lift infos
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'lift-available'),
            new LiteralImpl('vorhanden' == $placeEntry['lift-persons-available'] ? 'yes' : 'no')
        );
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'lift-liftWithWheelChairSupportAvailable'),
            new LiteralImpl('ja' == $placeEntry['lift-wheelchair-support'] ? 'yes' : 'no')
        );

        // toilett
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'toilets-toiletForDisabledPeopleAvailable'),
            new LiteralImpl('' != $placeEntry['toilets-wheelchair-support'] ? 'yes' : 'no')
        );

        // parking slot
        $stmtArray[] = new StatementImpl(
            new NamedNodeImpl($placeUri),
            new NamedNodeImpl($bvlNamespaceUrl . 'parkingLot-lotsForDisabledPeopleAvailable'),
            new LiteralImpl('vorhanden' == $placeEntry['parkingLotForHandicapedPersons-support'] ? 'yes' : 'no')
        );
    }

    // serialize statement array to n-triples and store it as file
    $serializerFactory = new SerializerFactoryImpl();
    $serializer = $serializerFactory->createSerializerFor('n-triples');
    $serializer->serializeIteratorToStream(
        new ArrayStatementIteratorImpl($stmtArray),
        __DIR__ . '/'. $filename
    );
}
