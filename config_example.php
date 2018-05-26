<?php

/* 
 * Copyright (C) 2018 Katie Horn (katie@katiehorn.com)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

//URL of the API for the wikibase install you want to import data to
$wikibase_api_url = 'http://wikibase.mysite.com/w/api.php';

//Base URL for retrieving EntityData turtle files
$wikibase_entitydata_url = 'http://wikibase.mysite.com/wiki/Special:EntityData/';

//valid user creds for the account making the edits on the target wikibase 
//install
$wikibase_username = 'username';
$wikibase_password = 'password';

//known items in the specified wikibase instance to help test the search and 
//retrieval functionality.
//These should be changed to match things that you actually have in your 
//instance.

//The ID of an item that exists on your wikibase instance
$test_item = 'Q4';
//ID of a property that exists on your wikibase instance, which exists in a 
//statement on the $test_item
$test_property = 'P2';

//language to test label searching with
$test_language = 'en';
//An item label, in language $test_language, on an existing item 
$test_item_search = 'Uncle Ghost';
//A property label, in language $test_language, on an existing item 
$test_property_search = 'species';


/**
 * File Import Runs
 */

//.tsv or .csv file in the ./files directory, containing data to import.
//NOTE: Some programs (LibreOffice) will re-save .tsv as a .csv, with 
//tab-delimited data. At this time, the file parser takes the .c or .t pretty 
//literally
$import_file = 'Wikidata_Initial_Species_Items_Ordered_Destroy.tsv';

//Number of items the script will edit before halting.
//Safety first.
$max_edits = 5;

//edit summary line for all your edits. All of them.
$edit_summary = "Importing Species-level plants from Wikidata";

//references you want to add to all the statements in an import you're going to 
//run with this tool. 
//Keys in this array should correspond to the specific reference properties that
//exist in your instance, and values will be converted to reference values.
$reference_data = array(
	'P11' => 'http://www.hownottospellwinnebago.com',	//source URL
	'P15' => '5/23/2018',								//date retrieved
);


//Coulmn mapping for the import file. 
//Set to false to rebuild interactively at the command line, or set to an array 
//you built interactively in a previous run. 
//These are helpfully dumped in the terminal / logs once 
//you've built one interactively.
$mapping = array(
	0 =>
	array(
		'column_name' => 'item',
		'type' => 'property',
		'id' => 'P16',
		'item_statement' => 'ID',
		'regex' => 'Q[0-9]*$',
	),
	1 =>
	array(
		'column_name' => 'taxonName',
		'primary' => true,
		'type' => 'item',
		'lang' => 'en',
	),
);


//Statements to add to every new item, as if the import file contained a column 
//full of identical data.
//Set to false to rebuild interactively at the command line, or set to an array 
//you built interactively in a previous run. 
//These are helpfully dumped in the terminal / logs once 
//you've built one interactively.
$import_statements = array(
	0 =>
	array(
		'type' => 'property',
		'property_id' => 'P19',		//Taxon Rank
		'value' => 'Q9',			//Species
	),
);
