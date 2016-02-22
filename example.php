<?php
include_once('csvReader.php');
try{
    $csvReader = new csvReader('le-online-extracted-places.csv');
}catch(Exception $e){
    exit("got an Exception: ".$e);
}

echo "keys = ".PHP_EOL;
echo var_dump($csvReader->getKeys()).PHP_EOL;
echo "data = ".PHP_EOL;
echo var_dump($csvReader->getData());PHP_EOL;
