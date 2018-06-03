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
		if (strlen(getConfig('import_file'))) {
			$file = getConfig('import_file');
		} else {
			echo 'Please specify a .tsv or .csv with the $import_file variable in config.php' . "\n";
			die();
		}
		$offset = false;
		if (!empty($_SERVER['argv'][2]) && is_numeric($_SERVER['argv'][2])) {
			$offset = $_SERVER['argv'][2];
		}
		importDataFromFile( $file, $offset );
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
 * Does not actually test everything. Sorry.
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

//run php import.php file
/**
 * The main controlling function that runs when the script is run with
 *	run php import.php file
 * at the command prompt.
 * @param string $file The filename (no path - it should be in /files) for the 
 * file to import. 
 * @param integer|false $offset A number of rows to skip (after the headers in row 0) 
 * before beginning processing. False to start at the beginning.
 */
function importDataFromFile( $file, $offset = false ){
	stopwatch("importDataFromFile");
	stopwatch("readDataFile");
	$data = readDataFile($file, $offset);
	stopwatch("readDataFile");
	if (!$data){
		echolog("No data read. Exiting");
		die();
	}
	
	//now, do some interactive column mapping for the file.
	$mapping = getConfig('mapping');
	if (!is_array( $mapping ) || empty( $mapping )){
		echolog("No column mapping defined. Building.");
		$mapping = mapColumnHeaders($data[0]);
		echolog("Data mapping for config file is as follows:");
		echolog(var_export($mapping, true));
	}
	
	//...and, some statements that should apply to all the lines, as if we added
	//another column to the import file
	$import_statements = getConfig('import_statements');
	if ((!is_array( $import_statements ) || empty( $import_statements )) && ($import_statements !== false)){
		$import_statements = defineImportStatements();
		echolog("Import statements for config file is as follows:");
		echolog(var_export($import_statements, true));
	}
	
	
	//may need to do some integrity checking here to see that all the columns in
	//the file are mapped. You know: Nice stuff.
	
	//find the primary matching column
	$primary_matching_column = false;
	foreach ( $mapping as $column => $information ){
		if( $information === 'ignore' ){
			continue;
		}
		if (array_key_exists('primary', $information) && $information['primary'] === true ){
			$primary_matching_column = $column;
			continue;
		}
	}
	
	$primary_matching_type = $mapping[$primary_matching_column]['type'];
	$primary_matching_language = $mapping[$primary_matching_column]['lang'];
	
	$max_edits = getConfig('max_edits');
	echolog("Preparing to edit a maximum of $max_edits item(s)");
	
	$edits = 0;
	$stop = false;
	for ($i=1; ($i < sizeof($data)) && (!$stop); ++$i){
		//check the primary column for data to match against in the live instance.
		//For every column we should have a type = [item|property|ID], and lang

		//check to see if we've handled this one already via line-squashing
		$handled = array();
		if ( array_key_exists($i, $handled) ){
			continue;
		}
		
		$editing = false;
		$message = '';
		
		//either add or grab the item we're trying to manipulate
		switch($primary_matching_type){
			case 'item':
				//ensure that the item with the label in the specified language doesn't already exist
				//TODO: Wire in the regex stuff here too.
				$matches = getWikibaseEntsByLabel( $data[$i][$primary_matching_column], $primary_matching_language, 'item', true );
				$editing_item = false;
				if ($matches === false){ //it's clean. Import the item.
					//hhhhhhhhmm.
					$editing_item = createItemObject( $data[$i][$primary_matching_column], $primary_matching_language );
					//echolog("Sanity check on item $editing_id, please");
					$editing = true;
					$message = "New Item '" . $data[$i][$primary_matching_column] . "' created. ";
				} else {
					//we did find a match. If we only found one, pull the id so we can add statements to it.
					if( !is_array($matches) ){
						//pick it up.
						$editing_item = getItem($matches);
						if($editing_item){
							$message = "Found existing item '" . $data[$i][$primary_matching_column] . "'. ";
						}
					} else {
						echolog("Found too many matches! Not sure what to do about this...");
						echolog($matches);
						//TODO: Add to file for potential merges to review and process?
						$message = "Confusing: '" . $data[$i][$primary_matching_column] . "' has multiple matches. Dropping on head. ";
					}
				}
				
				break;
			default:
				echolog("Sorry, I haven't handled primary matching type '$primary_matching_type' yet. Exiting.");
				die();
		}
		
		//if we have something to keep editing in the remaining columns...
		if ($editing_item !== false){
			
			//handle alt labels (AliasGroups) and Descriptions hereish.
			
			
			//time to add statements! woopwoop
			if (is_array($import_statements)){
				$statements = $import_statements;
			} else {
				$statements = array();
			}
			
			$line_statements = getStatementArrayFromDataLine($data[$i], $mapping, $primary_matching_column);
			if( is_array($line_statements) ){
				$statements = array_merge($statements, $line_statements);
			}
			
			//line-squashing: Looking for more statements in this file that 
			//apply to the $editing_item
			//Is this kills performance, I'll just wrap this loop in a setting.
			for( $j=$i+1; $j< count($data); ++$j ){
				$lines = 0;
				if( $data[$j][$primary_matching_column] === $data[$i][$primary_matching_column] ){
					$line_statements = getStatementArrayFromDataLine($data[$j], $mapping, $primary_matching_column);
					if( is_array($line_statements) ){
						$statements = array_merge($statements, $line_statements);
						++$lines;
						$handled[$j] = true;
					}
				}
			}
			
			if($lines > 0){
				$message .= "Collapsing $lines additional lines. ";
				//stop us from reprocessing when we get to the actual line in the main loop.
			}
			
			if ( !empty($statements) ) {
				//edit!
				$added_statements = addStatementsToItemObject($editing_item, $statements);
				if ( $added_statements !== false){
					//do the edit.
					$message .= "$added_statements new statements added. ";
					$editing = true;
				}
			}
		}
		
		if($editing){
			try {
				$item_id = editAddItemObject($editing_item);
			} catch (Exception $e){
				echolog("SKIPPING Item '" . $data[$i][$primary_matching_column] . "': Errors on save. See logs for more information." );
				//TODO: Consider adding the skips to yet another output file for ease of... something.
				continue;
			}
			echolog("$item_id: $message");
			++$edits;
		}
		
		if( $edits >= $max_edits){
			$stop = true;
		}
	}
	echolog("Edited $edits items this run.");
	stopwatch("importDataFromFile");
}

/**
 * Ugly interactive command-line function that walks the user through building 
 * out a column mapping for the file they're trying to import.
 * @param array $headers Array containing the column headers in the first row of
 *  .csv/.tsv data
 * @return array Column mapping array
 */
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
			$ask = "Use regular expression to parse ID?";
			$regex = getUserYN($ask);
			if($regex) {
				$ask = "Please input regex to use against column '" . $headers[$primary_column] . "' data";
				$regex = readLine($ask . " ");
				$return[$primary_column]['regex'] = $regex;
			}

			break;
	}
	
	//now, we know what to do with that primary column. Go through all the others.
	
	foreach ($headers as $rownum => $header){
		if (!is_array($return[$rownum]) && ($return[$rownum] !== 'ignore')){
			//find out what's in this column
			$ask = "What kind of data does column '$header' map to?";
			$choices = array(
				'0' => "Property value",
				'1' => "Item Description",
				'2' => "Item Alternate Label (AKA)",
				'3' => "Ignore Column '$header'",
			);
			
			$choice = getUserChoice($ask, $choices);
			
			switch($choice){
				case 0: //property
					$entity= false;
					//TODO: Move to its own function
					while( $entity === false){
						$ask = "Enter a property ID for column '$header'";
						$mapme = readLine($ask . " ");

						//look up the property ID, verify it exists, and save the ID
						$entity = getWikibaseEntsByID($mapme, 'en');

						if($entity === false){
							echolog("Property ID '$mapme' not found.");
						}
						echolog("Found property labeled '$entity' for id '$mapme'");
						$return[$rownum] = array(
							'column_name' => $header,
							'type' => 'property',
							'id' => $mapme,
						);
					}
					
					break;
				case 1: //Item description
					$return[$rownum] = array(
						'column_name' => $header,
						'type' => 'description',
					);
					break;
				case 2:	//Item AKA
					$return[$rownum] = array(
						'column_name' => $header,
						'type' => 'altlabel',
					);
					break;
				case 3:
					echolog("Ignoring column '$header'");
					$return[$rownum] = "ignore";
					continue;
			}
			
			//language?
			//oh hey, look at that. I think I can get the data type of the item to figure that out now.
			//TODO: Get rid of this ask, and just go ahead and know if they need a language or not.
			$ask = "Is column '$header' language-specific?";
			if (getUserYN($ask)) {
				$ask = "Same language for the whole file, or specified in another column?";
				$choices = array(
					'0' => 'Single language for whole column',
					'1' => 'Get language from another column',
				);
				$choice = getUserChoice($ask, $choices);
				switch ($choice) {
					case 0: 
						$ask = "What language is the data in column '$header'?";
						$lang = getUserLanguageChoice($ask);
						$return[$rownum]['lang'] = $lang;
						break;
					case 1:
						$ask = "What column contains the language for '$header'?";
						$lang_column = getUserChoice($ask, $headers);
						//$return[$rownum]['lang'] = $lang;
						$return[$lang_column] = "ignore";
						$return[$rownum]['lang'] = "Column $lang_column";
						break;
				}
			}

			$ask = "Use regular expression against column '$header'?";
			$regex = getUserYN($ask);
			if($regex) {
				$ask = "Please input regex to use against column '" . $header . "' data";
				$regex = readLine($ask . " ");
				$return[$rownum]['regex'] = $regex;
			}
		}
	}
	
	return $return;
}

/**
 * Interactive prompts to the user which build out an array representing the 
 * statements that should be imported with every item in a file run. Used by 
 * importDataFromFile.
 * @return array
 */
function defineImportStatements() {
	$return = array();
		
	$ask = "Define statements to add to every item in the whole import? \nAnalogous to creating an additional column filled with identical information for each line.";
	$import_statements = getUserYN($ask);
	
	if ($import_statements){
		$import_statements = array();
		$keep_going = true;
		while ($keep_going){
			if (count($import_statements) > 0){
				echolog("Current every-item import statements:");
				echolog($import_statements);
				$ask = "Add every-item import statement?";
				$keep_going = getUserYN($ask);
			}

			if($keep_going){
				$temparr = array( 'type' => 'property' );

				$ask = "Enter a property for the new import statemet";
				$temparr['property_id'] = readLine($ask . " ");
				
				$ask = "Enter a value for the new import statemet";
				$temparr['value'] = readLine($ask . " ");
				
				$ask = "Is this value language-specific?";
				if (getUserYN($ask)) {
					$ask = "What language is this data value associated with?";
					$temparr['language'] = getUserLanguageChoice($ask);
				}
				
				$import_statements[] = $temparr;
			}
		}
	}
	
	return $import_statements;
}

/**
 * Interactive command-line promots the user for a yes or no answer.
 * @param string $ask A hopefully descriptive prompt describing the question we 
 * want the user to answer with a yes or no.
 * @return boolean True if the user chose yes, false if they chose no.
 */
function getUserYN($ask){	
	$choice = null;

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

/**
 * Interactive command-line promots the user for a language code.
 * TODO: Something more friendly than restricting the entry to 2 characters.
 * @param string $ask A hopefully descriptive prompt describing why the user 
 * should supply a language code.
 * @return string A 2-character lowercase string that might be a language code.
 */
function getUserLanguageChoice($ask){
	$language = false;
	//oh, blarf. I don't have an array of languages. For now, I'll just do this:
	while ( ($language === false) || strlen($language) !== 2 ){
		$language = readLine($ask . " ");
	}
	
	return strtolower($language);
}

/**
 * Interactive command-line promots the user with the text in $ask and the 
 * options defined in the $options array. Loops until the user enters a choice 
 * that actuall exists, and returns that choice.
 * @param string $ask Descriptive user prompt for the options being offered
 * @param array $options A key-value array describing available options.
 * @return string The index of the user's eventual valid choice.
 */
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

/**
 * Uses fgetcsv to read crazyhuge files into an array in various ways.
 * @param string $file The name of the fine to open and read into an array
 * @param integer|false $offset A number of rows to skip (after the headers in row 0) 
 * before beginning processing. False to start at the beginning.
 * @return array|false An array of the file data, or false if it didn't work
 */
function readDataFile( $file, $offset ){
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

	$fullpath = "./files/$file";
	if (file_exists($fullpath) ){
		$handle = fopen($fullpath, 'r');
	} else {
		echolog("File '$fullpath' does not exist.");
		return false;
	}
	
	
	$data = false;
	if($handle){
		$data = array();
		$limit = getConfig('file_read_limit');
		$stop = false;
		$linecounter = 0;
		if (!$offset){
			$offset = 0;
		}
		
		while ((($line = fgetcsv( $handle, 0, $delimiter)) !== FALSE) && (!$stop)){
			if (($linecounter === 0) || ($linecounter > $offset)){
				array_push($data, $line);
				if( $limit && (count($data) >= $limit) ){
					$stop = true;
				}
			}
			++$linecounter;
		}
		
		$count = count($data);
		echolog("Parsed file $file, read $count lines");
		
	} else {
		echolog("Problem opening file '$fullpath'.");
	}
	return $data; //and there may be a heck of a lot of it...
}

/**
 * Testing the getWikibaseObjectPropertyValues function in the scrappy_searcher.
 * Uses $test_item and $test_property in config.
 */
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

/**
 * Testing the getWikibaseEntsByLabel function in the scrappy_searcher.
 * Uses $test_property_search and $test_language in config.
 */
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

/**
 * Testing the getWikibaseEntsByLabel function in the scrappy_searcher.
 * Uses $test_item_search and $test_language in config.
 */
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

/**
 * Nobody likes globals anyway.
 * Finds and returns the variable in the config file that corresponds to the 
 * $varname passed in.
 * @param string $varname The name of the variable in the config file to return.
 * @return mixed The value of the config variable, or null if a variable of that 
 * name is not set. 
 */
function getConfig($varname){
	require('config.php');
	if (!isset( $$varname )){
		return null;
	}
	return $$varname;
}


function getStatementArrayFromDataLine($linedata, $mapping, $primary_matching_column){
	$statements = array();
	foreach( $linedata as $column => $value ){
		if (($column === $primary_matching_column) || $mapping[$column] === 'ignore') {
			//no stuff to do.
			continue;
		}

		//stuff to do!
		$column_type = $mapping[$column]['type'];
		switch($column_type){
			case 'property':
				$property_id = $mapping[$column]['id'];
				$language = false;
				if( array_key_exists('lang', $mapping[$column])){
					if (strpos($mapping[$column]['lang'], 'Column') === 0){
						$langsplode = explode(' ', $mapping[$column]['lang']);
						$language = $linedata[$langsplode[1]];
					} else {
						$language = $mapping[$column]['lang'];
					}
				}

				if( array_key_exists('regex', $mapping[$column])){
					$value = getFirstRegexMatch($value, $mapping[$column]['regex']);
				}
				
				$property_type_id = getPropertyTypeID($property_id);

				//TODO: Move to its own function.
				if ($property_type_id === 'commonsMedia'){
					$value = urldecode($value);
				}

				$statements[] =array(
					'property_id' => $property_id,
					'value' => $value,
					'language' => $language
				);
				break;
			default:
				echolog("Sorry, I haven't handled column type '$column_type' yet. Exiting. " . __FUNCTION__);
				die();
		}
	}
	
	if( empty($statements) ){
		return false;
	}
	return $statements;
}

/**
 * Returns the first regex match in $value, matching the pattern in $regex
 * @param string $value The string value to try to match on
 * @param string $regex A regular expression pattern string
 * @return string|false Returns the matching portion of the string, or false if 
 * none found.
 */
function getFirstRegexMatch($value, $regex){
	$pregs = array();
	$regex = trim($regex, '/');
	preg_match('/' . $regex . '/', $value, $pregs);
	if ( count($pregs) > 0 ){
		return $pregs[0];
	}
	return false;
}