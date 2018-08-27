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

## R2RML-Mapping (CSV => RDF)

The [R2RML](https://www.w3.org/TR/r2rml/)-mapping ([file](https://github.com/AKSW/transform-bvl-pages-to-csv-file/blob/master/r2rml-mapping.ttl)) allows the transformation of the CSV file to RDF using R2RML tools like [sparqlmap](https://github.com/tomatophantastico/sparqlmap). Its purpose is to lower the barrier to convert original data, modeled as a table, in further formats.

Our R2RML mapping is based on a couple of controlled vocabularies, such as [Dublin Core](http://purl.org/dc/elements/1.1/) and [The vocabulary for (L)OD description of wheelchair accessibility](http://semweb.mmlab.be/ns/wa#) as well as two vocabularies, inspired by and based on the data model of the building database, developed by ourselves.

* The [place ontology](https://github.com/AKSW/leds-asp-f-ontologies/tree/master/ontologies/place) contains properties and classes about buildings in general.
* The [place accessibility ontology](https://github.com/AKSW/leds-asp-f-ontologies/tree/master/ontologies/place-accessibility) contains properties to model accessibility information of a building.

The file [places.n3](places.n3) was generated using sparqlmap with our R2RML-mapping.

If you want to transform the CSV file a RDF file yourself, you can use this [docker-container](https://github.com/k00ni/sparqlmap-docker).

## SHACL rules

We also developed, based on the building accessibility ontology, an extended set of SHACL rules. [SHACL](https://en.wikipedia.org/wiki/SHACL) can be used to formulate rules about RDF graphs. Using a SHACL processor helps evaluating, if a given dataset is valid or not.

You can found the SHACL rules here: https://github.com/AKSW/shacl-shapes/tree/master/shape-groups/accessible-place

## Usage infos

We used the closed building database maintained by the BVL to generate the CSV files. Without it, you can't use these scripts. In case you have it (`table.csv`) run the script `enrich-table-csv.php`. Furthermore, you need to run `composer update` to setup required vendors.

## License

CSV and RDF (n3) files are licensed under the terms of [*Data licence Germany – attribution – version 2.0*](https://www.govdata.de/dl-de/by-2-0). Please use the following text as attribution: `Behindertenverband Leipzig e.V., www.le-online.de`.

Software source code is licensed under the terms of [*GPL 3.0*](http://www.gnu.org/licenses/gpl-3.0.en.html).

The rest of the repository is licensed under the terms of the [CC-BY 4.0](https://creativecommons.org/licenses/by/4.0/).
