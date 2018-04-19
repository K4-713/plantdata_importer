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

if (!empty($_SERVER['argv'][1])) {
	$function = $_SERVER['argv'][1];
} else {
	echo "Please specify a function to run\n";
	die();
}

require_once('logger.php');
require_once('scrappy_searcher.php');
require_once('wikibase_apii.php'); //the extra "i" is *also* for "Interface"

switch( $function ){
	case 'test_comms':
		testEverything();
		break;
	case 'file':
		if (!empty($_SERVER['argv'][2])) {
			$file = $_SERVER['argv'][2];
		} else {
			echo "Please specify a .tsv file in the files directory to read\n";
			die();
		}
		importDataFromFile( $file );
		break;
	default: 
		//need a -h output here so it's minimally helpful.
		echolog("$function is not an established function. Try again with something else.");
}

/**
 * What I still need to build:
 * Read in a tsv
 *  * Map column headers to maybe existing items or properties. This should be interactive.
 * Add a new wikibase item
 * Find an item that already exists by label in a specific language, and add a property
 */

echolog("Done for now...");

return;


/**
 * Run php import.php test_comms at the command line to test basic connectivity 
 * and searching on the wikibase instance specified in config.
 */
function testEverything(){
	testAPIConnection();
	testItemSearchByLabel();
	testPropertySearchByLabel();
	testItemPropertyLookup();
	
	//this next call was just me noodling around, and should go away when I 
	//have all the relevant pieces written out nicely in the ii.
	//testReferenceSetter();
}

//run php import.php file [filename] 
function importDataFromFile( $file ){
	$data = readDataFile($file);
	
	//now, do some interactive column mapping for everything in line zero.
	$mapping = mapColumnHeaders($data[0]);
	
	echolog("Data mapping is as follows:");
	echolog($mapping);
	
	//assume that the reference is the same for all items in a run. 
	
}

//$headers contains a numerically indexed array of the column names
function mapColumnHeaders($headers){
/**
 * First: Identify what I'm going to call the Primary Matching Column.
 * Then, we'll need to know what to match it against. Possibilities?
 * * Item label in a specific language
 * * Item property value in a specific language? Does this make sense?
 * * Item ID (unlikely, or we are all working too hard)
 * 
 * Then, go through all the other columns and id property/language mappings, and
 * if there are any qualifiers.
 * I think it's actually as simple as that, so for now, let's assume that's the
 * case.
 * Not all properties will have a language, (numeric) and some properties will
 * want to match to an item. 
 */
	//this return var will also help us remember what to set down the line
	$return = array();
	foreach( $headers as $ind => $idc){
		$return[$ind] = false;
	}
	
	$ask = "Please identify the primary matching column:";
	$primary_column = getUserChoice($ask, $headers);
	
	$return[$primary_column] = array(
		'column_name' => $headers[$primary_column],
		'primary' => true
	);
	
	$ask = "What is $headers[$primary_column] matching on?";
	$primary_matching_style = array(
		0 => 'Item label in a specific language',
		1 => 'Item property in a specific language',
		2 => 'Item ID',
	);
	
	$matching_on = getUserChoice($ask, $primary_matching_style);
	
	switch ( $matching_on ){ //in case this list expands...
		case 0:
			$return[$primary_column]['type'] = 'item';
			$ask = "What language should we use for item label matching?";
			$language = getUserLanguageChoice($ask);
			$return[$primary_column]['lang'] = $language;
			break;
		case 1:
			$return[$primary_column]['type'] = 'property';
			$ask = "What language should we use for property matching?";
			$language = getUserLanguageChoice($ask);
			$return[$primary_column]['lang'] = $language;
			break;
		case 2:
			$return[$primary_column]['type'] = 'ID';
			break;
	}
	
	//now, we know what to do with that primary column. Go through all the others.
	
	foreach ($headers as $rownum => $header){
		if (!is_array($return[$rownum])){
			//find out what property to map to
			$ask = "What should we do with column '$header'?";
			echolog($ask);
			
			$mapme = readline("Enter a property label, property ID, or 'Ignore': ");
			
			if ( strtoupper($mapme) === "IGNORE" || strtoupper($mapme) === "I"){
				echolog("Ignoring column '$header'");
				$return[$rownum] = "ignore";
				continue;
			}
			
			//look up the property label or ID, verify it exists, and save the ID
			if ( (strpos($mapme, 'P') === 0 ) && (is_numeric(trim($mapme, "P"))) ){
				//that there is an ID.
				$entity = getWikibaseEntsByID($mapme, 'en');
				echolog("Found property labeled '$entity' for id '$mapme'");
				$return[$rownum] = array(
					'column_name' => $header,
					'type' => 'property',
					'id' => $mapme,
				);
			} else {
				//looking up text
				$entity = getWikibaseEntsByLabel($mapme, 'en', 'property', true);
				//potential problem: This can return multiple results.
				//TODO: If it's an array, ask the user which one they mean.
				echolog("Found property id '$entity' for label '$mapme'");
				$return[$rownum] = array(
					'column_name' => $header,
					'type' => 'property',
					'id' => $entity,
				);
			}
			
			//language?
			$ask = "Do you need to specify a language for data in column '$header'?";
			if (getUserYN($ask)) {
				$ask = "What language is the data in column '$header'?";
				$lang = getUserLanguageChoice($ask);
				$return[$rownum]['lang'] = $lang;
			}

			//qualifier?
//			$ask = "Does column '$header' get a qualifier?";
//			if (getUserYN($ask)) {
//				$ask = "TODO: Figure out what kind of data can go in a qualifier. What are you even doing. Y/Y";
//			}

			//Are we trying to map the value to another item (By label, or item ID)
			$ask = "Does the column '$header' contain data that will map to a wikibase item?";
			if( getUserYN($ask)){
				$choices = array(
					0 => 'labels',
					1 => 'IDs',
				);
				$ask = "Does this column contain item labels, or IDs?";
				$choice = getUserChoice($ask, $choices);
				if ( $choice === 0  ){
					$return[$rownum]['item_statement'] = 'label';
				} else {
					$return[$rownum]['item_statement'] = 'ID';
				}
			}
			
		}
	}
	
	return $return;
}

function getUserYN($ask){	
	$choice = null;
	//oh, blarf. I don't have an array of languages. For now, I'll just do this:
	while ( is_null($choice) ){
		$textchoice = readLine($ask . " ");
		switch (strtoupper($textchoice)) {
			case 'Y':
			case 'YES':
				$choice = true;
				break;
			case 'N':
			case 'NO':
				$choice = false;
				break;
			default:
				echolog("Er, what? I don't understand '$textchoice'");
		}
	}
	
	return $choice;
}

//maybe someday I'll get our valid languages in here.
function getUserLanguageChoice($ask){
	$language = false;
	//oh, blarf. I don't have an array of languages. For now, I'll just do this:
	while ( ($language === false) || strlen($language) !== 2 ){
		$language = readLine($ask . " ");
	}
	
	return $language;
}

//return the choice corresponding to a valid index in the options array
function getUserChoice($ask, $options ){
	$out = "$ask\n";
	foreach ($options as $index => $value){
		$out .= "\t[$index] - $value\n";
	}

	$choice = false;
	while(($choice === false) || !array_key_exists($choice, $options)){
		if ($choice !== false){
			echolog("Your choice $choice does not appear to exist.");
		}
		echolog($out);
		$choice = readline("Your choice: ");
	}
	
	return $choice;
}

function readDataFile( $file ){
	$ext = explode('.', $file);
	$ext = strtoupper($ext[1]);
	$mode = false;
	
	switch ($ext){
		case "TSV":
		case "CSV":
			$mode = $ext;
			break;
		default:
			echolog("File extension not supported: $ext");
			return;
	}
	
	if (!$mode){
		echolog("Couldn't find the right file mode. Exiting.");
		return;
	}
	
	//now, based on $mode, define appropriate fgetcsv parameters.
	switch($mode){
		case 'TSV':
			$delimiter = "\t";
			//$enclosure = "";
			//$escape = "";
			break;
		case 'CSV':
			$delimiter = ",";
			//$enclosure = '"';
			//$escape = "";
			break;	
	}
	
	$handle = fopen("./files/$file", 'r');
	
	$data = false;
	if($handle){
		$data = array();
		while ( ($line = fgetcsv( $handle, 0, $delimiter)) !== FALSE){ //may need those other two params
			array_push($data, $line);
		}
		
		$count = count($data);
		echolog("Parsed file $file, read $count lines");
		
		//TODO: need debug/verbose log level
//		echolog("First 5 lines:");
//		for ($i=0; $i<5; ++$i){
//			echolog("Line $i");
//			echolog($data[$i]);
//		}
	}
	return $data; //and there may be a heck of a lot of it...
}

function testItemPropertyLookup(){
	$test_item = getConfig('test_item');
	$test_property = getConfig('test_property');
	
	echolog("Attempting to fetch $test_item for a $test_property property value check");
	
	//works up to this point
	$property_vals = getWikibaseObjectPropertyValues($test_item, $test_property);
	
	if($property_vals){
		if (is_array($property_vals)){
			echolog("Returned multiple results for property $test_property on item $test_item");
			echolog($property_vals);
		} else {
			echolog("$property_vals is the only value for property $test_property on item $test_item");
		}
	} else {
		echolog("Didn't find an exact match for property $test_property on item $test_item");
	}
	
}

function testPropertySearchByLabel(){
	//I don't really mind this, because it's readable...
	$test_property_search = getConfig('test_property_search');
	$test_language = getConfig('test_language');

	$item = getWikibaseEntsByLabel($test_property_search, $test_language, 'property', true);

	if($item){
		if (is_array($item)){
			echolog("Returned multiple results for '$test_property_search' in $test_language");
			echolog($item);
		} else {
			echolog("$item is an exact match for '$test_property_search' in $test_language");
		}
	} else {
		echolog("Didn't find an exact match for '$test_property_search' in $test_language");
	}
}

function testItemSearchByLabel(){
	//I don't really mind this, because it's readable...
	$test_item_search = getConfig('test_item_search');
	$test_language = getConfig('test_language');

	$item = getWikibaseEntsByLabel($test_item_search, $test_language, 'item', true);

	if($item){
		if (is_array($item)){
			echolog("Returned multiple results for '$test_item_search' in $test_language");
			echolog($item);
		} else {
			echolog("$item is an exact match for '$test_item_search' in $test_language");
		}
	} else {
		echolog("Didn't find an exact match for '$test_item_search' in $test_language");
	}
}

//Tired of globals, but who needs 'em.
function getConfig($varname){
	require('config.php');
	return $$varname;
}
