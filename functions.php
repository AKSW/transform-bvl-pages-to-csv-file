<?php

use Saft\Data\SerializerFactoryImpl;
use Saft\Rdf\ArrayStatementIteratorImpl;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\StatementImpl;

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
    foreach ($infoArray as $key => $value) {
        fputcsv($file, $value);
    }
    fclose($file);
    echo 'CSV-file '. $filename .' with '. $key .' entries created.' . PHP_EOL;
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

    echo 'N-Triples file '. $filename .' with '. count($stmtArray) .' triples created.' . PHP_EOL;
}
