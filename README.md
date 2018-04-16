plantdata_importer
=====

## Description

PHP client script to import TSV formatted data to a wikibase instance.
Depends on the wikibase-api library found here: 
https://github.com/addwiki/wikibase-api

## Installation

To use this script, clone this repo, and install the wikibase-api libraries under 
./libs/wikibase-api, or symlink the cloned installed wikibase-api repo to the 
same location.
Copy the config_example.php file, rename it to config.php, and add your own 
config information.

Now depends on EasyRDF, so I could parse those turtle (.ttl) files myself.
http://www.easyrdf.org/docs/getting-started

Currently, you will have to run composer install twice: Once for me, and once 
for the wikibase-api

Note: Developed against wikibase-api 2c3c19a5ac5da

## Work in Progress!
This doesn't actually do anything yet, aside from testing communications and 
basic searching on the wikibse instance specified in the config file.

Eventually, it will take a tsv file, use the first line to determine the 
property mappings for all the columns, and import the data accordingly.

## Usage
See above: Don't.

If you have to: run 'php import.php test_comms'. 