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

//Summon the wikibase-api libraries.
require_once( __DIR__ . '/libs/wikibase-api/vendor/autoload.php' );

//we want to use these classes in various places.
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\ApiUser;
use Wikibase\Api\WikibaseFactory;
use Wikibase\DataModel\Entity\ItemId;

//local globals. Fight me.
$mw_api = '';
$wikibase_factory = '';
$log;

/**
 * what command line parameters do I want to use?
 * import file name (let's do the mapping in the header)
 * run size
 * dry run
 * 
 * ...I don't actually like this yet, but whatever.
 */
if (!empty($_SERVER['argv'][1])) {
	$filename = $_SERVER['argv'][1];
} else {
	echo "Please specify a file name\n";
	die();
}
if (!empty($_SERVER['argv'][2])) {
	$run_size = $_SERVER['argv'][2];
} else {
	echo "Please specify a run size\n";
	die();
}

if (!empty($_SERVER['argv'][3])) {
	$dry_run = true;
	echo "Running in dry-run mode - no data will be written";
}

/**
 * Now, do something.
 * Once I have all this worked out, I'll restructure a bit. For now I don't care.
 */

// The echologger class will both echo and log what we're doing out here, 
// for easier auditing. Should be able to handle a variety of inputs.
// set that up here:
require_once('logger.php');
$log = new echologger();

//...and use it.
$log->say("Running the importer on $filename, for $run_size entries.");

if (isset($dry_run) && $dry_run) {
	$log->say("This is a dry run.");
} else {
	$log->say("THIS IS REAL - not a dry run! Data may be changed.");
}

/**
 * What I will need to be able do:
 * Check to see if an item exists by label in a specific language: 
 *		Done with getWikibaseEntsByLabel() 
 * look up a property in much the same way
 *		Done exactly the same way.
 * Get specific property values on specific items
 *		Ehh, just use the... entity... thingy.
 * Read in a tsv
 * Add a new wikibase item
 * Find an item that already exists by label in a specific language, and add a property
 */

//...I give up.
require_once( 'scrappy_searcher.php' );

testEverything();

$log->say("Done for now...");

return;

/**
 * Logs in to the mediawiki API, and sets the $mw_api variable to type MediawikiApi
 * @global MediawikiApi $mw_api
 * @global echologger $log
 */
function loginToMediawiki(){
	global $mw_api, $log;
	
	$log->say("Logging in to Mediawiki instance specified in config");

	try {
		//pull in the config settings and make the connections
		$mw_api = new MediawikiApi(getConfig('wikibase_api_url'));
		$mw_api->login(new ApiUser(getConfig('wikibase_username'), getConfig('wikibase_password')));
	} catch (Exception $e) {
		$log->say('Caught ErrorException: ' . $e->getMessage());
		$log->lastError();
	}
}

/**
 * Does a bunch of... stuff... and returns a wikibase factory.
 * Class is defined in /libs/wikibase-api/src/Api/WikibaseFactory.php. Kind of.
 * @return WikibaseFactory WikibaseFactory
 */
function setupWBFactory(){
	global $mw_api, $wikibase_factory, $log;
	
	if ( gettype($mw_api) != 'MediawikiApi' ){
		loginToMediawiki();
	}
	
	$log->say("Setting up the Wikibase Factory");
	/**
	 * The following is copied code from the example here:
	 * https://github.com/addwiki/wikibase-api
	 */
	// Create our Factory, All services should be used through this!
	// You will need to add more or different datavalues here.
	// In the future Wikidata / Wikibase defaults will be provided in seperate a library.
	$dataValueClasses = array(
		'unknown' => 'DataValues\UnknownValue',
		'string' => 'DataValues\StringValue',
		'boolean' => 'DataValues\BooleanValue',
		'number' => 'DataValues\NumberValue',
		'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
		'monolingualtext' => 'DataValues\MonolingualTextValue',
		'multilingualtext' => 'DataValues\MultilingualTextValue',
		'quantity' => 'DataValues\QuantityValue',
		'time' => 'DataValues\TimeValue',
		'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
	);
	$wikibase_factory = new WikibaseFactory(
		$mw_api, new DataValues\Deserializers\DataValueDeserializer($dataValueClasses), new DataValues\Serializers\DataValueSerializer()
	);

	/**
	 * End copied example code
	 */
}

//yeah, maybe this will stay in. Potentially useful for everyone.
function testEverything(){
	testConnection();
	testItemSearchByLabel();
	testPropertySearchByLabel();
	testItemPropertyLookup();	
}

function testItemPropertyLookup(){
	global $log;
	$test_item = getConfig('test_item');
	
	$log->say("Attempting to fetch $test_item for a property value check");
	
	//works up to this point
	$obj = fetchObject($test_item);
	
	
}

/**
 * Just proving to myself that this is doing something.
 * @global MediawikiApi $mw_api
 * @global WikibaseFactory $wikibase_factory
 * @global echologger $log
 */
function testConnection(){
	global $mw_api, $wikibase_factory, $log;
	
	if ( gettype($wikibase_factory) != 'WikibaseFactory' ){
		setupWBFactory();
	}
	
	// You should be connected and everything by now. See if you can look up an item.

	//itemID is a what?
	// /wikibase-api/vendor/wikibase/data-model/src/Entity/ItemId.php
	$test_item = getConfig('test_item');
	$log->say("Testing the connection by looking up the labels and descriptions of $test_item");
	
	$itemId = new ItemId($test_item);
	$itemLookup = $wikibase_factory->newItemLookup();
	$termLookup = $wikibase_factory->newTermLookup();

	// and what is this?
	// Wikibase\DataModel\Entity\Item
	// /wikibase-api/vendor/wikibase/data-model/src/Entity/Item.php
	$item = $itemLookup->getItemForId($itemId);
	$enLabel = $termLookup->getLabel($itemId, getConfig('test_language'));

	//$item->getDescriptions() gives me an object of type TermList
	// Wikibase\DataModel\Term\TermList, so...
	$log->say($item->getLabels()->toTextArray());
	$log->say($item->getDescriptions()->toTextArray());
}

function testPropertySearchByLabel(){
	global $log;
	//I don't really mind this, because it's readable...
	$test_property_search = getConfig('test_property_search');
	$test_language = getConfig('test_language');

	$item = getWikibaseEntsByLabel($test_property_search, $test_language, 'property', true);

	if($item){
		if (is_array($item)){
			$log->say("Returned multiple results for '$test_property_search' in $test_language");
			$log->say($item);
		} else {
			$log->say("$item is an exact match for '$test_property_search' in $test_language");
		}
	} else {
		$log->say("Didn't find an exact match for '$test_property_search' in $test_language");
	}
}

function testItemSearchByLabel(){
	global $log;
	//I don't really mind this, because it's readable...
	$test_item_search = getConfig('test_item_search');
	$test_language = getConfig('test_language');

	$item = getWikibaseEntsByLabel($test_item_search, $test_language, 'item', true);

	if($item){
		if (is_array($item)){
			$log->say("Returned multiple results for '$test_item_search' in $test_language");
			$log->say($item);
		} else {
			$log->say("$item is an exact match for '$test_item_search' in $test_language");
		}
	} else {
		$log->say("Didn't find an exact match for '$test_item_search' in $test_language");
	}
}

//Tired of globals, but who needs 'em.
function getConfig($varname){
	require('config.php');
	return $$varname;
}
