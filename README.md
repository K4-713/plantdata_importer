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
Note: Developed against wikibase-api 2c3c19a5ac5da

## Work in Progress!
This doesn't actually do anything yet, 
aside from using the API to log into an instance of wikibase, 
and instantiate the wikibase api.

Eventually, it will take a tsv file, use the first line to determine the 
property mappings for all the columns, and import the data accordingly.

## Usage
See above: Don't.
If you have to: run import.php with a filename for the TSV you want to import, 
and an integer number of items you want to import on this run. This will 
eventually assume you have a valid .tsv file in the ./files directory. Logs for 
each run will be written to the ./logs directory.