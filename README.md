# Enriched CSV version of the building database from Behindertenverband Leipzig e.V.

This repository contains a simple PHP script, which transforms an exported CSV file with building information to an enriched version. It contains further information and normalized strings. Information are about buildings located in Leipzig and their degree of accessibility. The source data is being provided by the [Behindertenverband Leipzig e.V](http://www.le-online.de/).

The following areas are covered currently:
* Education: http://www.le-online.de/bildung.htm
* Services: http://www.le-online.de/dienst.htm
* Restaurants: http://www.le-online.de/gast.htm
* Health: http://www.le-online.de/gesund.htm
* Law: http://www.le-online.de/recht.htm
* Organizations: http://www.le-online.de/verband.htm
* Traffic: http://www.le-online.de/verkehr.htm

## License

Published CSV- and TTL files are licensed under the terms of [*Data licence Germany – attribution – version 2.0*](https://www.govdata.de/dl-de/by-2-0).

Software source code is licensed under the terms of [*GPL 3.0*](http://www.gnu.org/licenses/gpl-3.0.en.html).

## Usage infos

We used the closed building database maintain by the BVL to generate the CSV files. Without it, you can't use these scripts. In case you have it (`table.csv`), to run the script `create-files.php` you have to set your database connection in the `functions.php` file (look for the `R::setup` part). Furthermore, you need to run `composer update` to setup required vendors.
