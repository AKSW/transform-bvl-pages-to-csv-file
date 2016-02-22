<?php
/*
assuming:
subject[building] <- $data[$counter]
    - overload $counter with building URI
prediate[from table header] <- $keys[$counter]
    - needs mapping with URIs to get rid of these strings
object[table element] <- $data[$counter][$keys[$counter2]]
    - mostly needs cast to literal or true/false?
*/

/*
Reader for parsing CSV data into PHP associative Arrays

assuming proper table structure:
    - every line has the same number of elements
    - seperated by ','
*/
class csvReader
{
    private $keys;    
    private $data;
    
    /**
      * constructor, reading in csv file
      * catching file not found errors
    */
    public function __construct($path){
        //opening file handle
        $handle = fopen($path,'r');
        //initiating target array
        if($handle == FALSE){
            throw new Exception("csv file not found");
        }
        //table header -> associative array keys
        $this->keys = fgetcsv($handle, 1000, ",");
        $rowCounter = 0;
        //read in every line
        while (($dataLine = fgetcsv($handle, 1000, ",")) !== FALSE) {
            //fill in associative array
            $this->data[$rowCounter] = array();
            for($columnCounter = 0; $columnCounter < sizeof($dataLine); $columnCounter++){
                $this->data[$rowCounter][''.$this->keys[$columnCounter]] = $dataLine[$columnCounter];
            }
            $rowCounter++;
        }
        fclose($handle);
    }
    
    public function getData(){
        return $this->data;
    }
    
    public function getKeys(){
        return $this->keys;
    }
}

