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

/**
 * APII = Application Programming Interface Interface.
 * My intention here, is to encapsulate existing Mediawiki and Wikibase API
 * functionality in a way such that consuming code can be read and used more 
 * intuitively from the outside.
 */

//Summon the wikibase-api libraries.
require_once( __DIR__ . '/libs/wikibase-api/vendor/autoload.php' );

//These classes are required in various places.
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\ApiUser;
use Mediawiki\DataModel\EditInfo;
use Wikibase\Api\WikibaseFactory;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\StringValue;
use DataValues\TimeValue;

function getMediwaikiAPI(){
	static $mw_api = null;
	if (is_null($mw_api)){
		//do the thing.
		echolog("Logging in to Mediawiki instance specified in config");

		try {
			//pull in the config settings and make the connections
			$mw_api = new MediawikiApi(getConfig('wikibase_api_url'));
			$mw_api->login(new ApiUser(getConfig('wikibase_username'), getConfig('wikibase_password')));
		} catch (Exception $e) {
			echolog('Caught ErrorException: ' . $e->getMessage(), true);
		}
	}
	return $mw_api;
}

/**
 * Does a bunch of... stuff... and returns a wikibase factory.
 * Class is defined in /libs/wikibase-api/src/Api/WikibaseFactory.php. Kind of.
 * @return WikibaseFactory WikibaseFactory
 */
function getWikibaseFactory(){
	static $wikibase_factory = null;
		if (is_null($wikibase_factory)){
		//do the thing.
		echolog("Setting up the Wikibase Factory");
		
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
			getMediwaikiAPI(),
			new DataValues\Deserializers\DataValueDeserializer($dataValueClasses),
			new DataValues\Serializers\DataValueSerializer()
		);

		/**
		 * End mostly copied example code. The Wikibase Factory is ready now.
		 */
	}
	
	return $wikibase_factory;
}

/**
 * Just proving to myself that this is doing something.
 */
function testAPIConnection(){
	$wikibase_factory = getWikibaseFactory();
	
	// You should be connected and everything by now. See if you can look up an item.

	//itemID is a what?
	// /wikibase-api/vendor/wikibase/data-model/src/Entity/ItemId.php
	$test_item = getConfig('test_item');
	echolog("Testing the connection by looking up the labels and descriptions of $test_item");
	
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
	echolog($item->getLabels()->toTextArray());
	echolog($item->getDescriptions()->toTextArray());
}


/**
 * Probably shouldn't run this directly- This was sort of a proof-of-concept 
 * exercise to see if I could get it to work at all (yes).
 */
function testReferenceSetter(){
	//please untangle this hedgemaze...
	//Let's use the searcher to get a statement and then add a reference to it.

	$match = getWikibaseEntsByLabel(getConfig('test_item_search'), getConfig('test_language'), 'item', true);
	//now, $match is a string containing Q-something.

	//use the wikibase api thingy to get the item
	$itemId = new ItemId($match);
	
	$wikibase_factory = getWikibaseFactory();
	$itemLookup = $wikibase_factory->newItemLookup();
	$termLookup = $wikibase_factory->newTermLookup();

	// and what is this?
	// Wikibase\DataModel\Entity\Item
	// /wikibase-api/vendor/wikibase/data-model/src/Entity/Item.php
	$item = $itemLookup->getItemForId($itemId);
	$langLabel = $termLookup->getLabel($itemId, getConfig('test_language'));

	//$item->getStatements() is a StatementList class, which is very close to the reference stuff we need.
	$statementList = $item->getStatements();
	$count = $statementList->count();

	echolog("Got $count statements in our list.");

	//let's see if we can see all the snaks. I guess.
	//I can get the property, but not the value?
	$statements = $statementList->toArray();
	foreach ($statements as $iter => $statement){
		//now, what can we accomplish with a statement object?
		$jfc = $statement->getMainSnak();
		$value = $jfc->getDataValue()->getValue(); // o_O;

		//can we also say what the property value is on the main snak?
		//yes, but there's a shortcut.
		$pid = $statement->getPropertyId();
		echolog("Statement $iter main snak says: $pid = $value");  //This works.

		//now. References. Apparently this returns a ReferenceList class object...
		$refs = $statement->getReferences();

		$refcount = $refs->count();
		echolog("Statement $iter has $refcount references.");

		if ($refcount === 0){
			//there's a reference setter in the factory, but no getter. Curious.
			$refsetter = $wikibase_factory->newReferenceSetter();
			
			//to actually set the reference, we need the following:			
			//Reference $reference, $statement, $targetReference = null, EditInfo $editInfo = null
			
			//I *think* what they're trying to say with $targetReference is if you want to replace the old reference?
			//editInfo just passes through to the post request, but is of course another object.

			//let's try this...
			$ref_data = getConfig('reference_data');
			$ref_snaks = array();
			foreach ($ref_data as $ref_property => $ref_value){
				$valueObj = typecastDataForProperty($ref_property, $ref_value);
				$ref_snaks[] = new PropertyValueSnak(
					PropertyId::newFromNumber( trim($ref_property, 'P') ),
					$valueObj
				);
			}

			$refObject = new Reference($ref_snaks);

			$refsetter->set(
					$refObject, 
					$statement, 
					null, 
					new EditInfo("Testing my importer's ability to set a reference.", false, true)
			);
		} else {
			//next trick: Can we read the references that are already there?
			//Probably, but that's a different problem, and one I'm not entirely 
			//sure I need to solve for a mass data importer. 
			echolog("Oh look, we already had a reference on that statement.");
		}
	}
}

function typecastDataForProperty($property_id, $data){
	//first, look up what kind of data the property wants.
	$property = getProperty($property_id);
	$data_type_id = $property->getDataTypeId();
	//echolog("$data_type_id is the data type ID for $property_id");
	//then, stuff the data into the right class and return it.
	
	$obj = null;
	switch( $data_type_id ){
		case 'url':
		case 'string':
			$obj = new StringValue( $data );
			break;
		case 'time':
			//of course, this is horrifyingly complicated.
			/**TimeValue wants:
			* @param string $timestamp Timestamp in a format resembling ISO 8601.
			* @param int $timezone Time zone offset from UTC in minutes.
			* @param int $before Number of units given by the precision.
			* @param int $after Number of units given by the precision.
			* @param int $precision One of the self::PRECISION_... constants.
			* @param string $calendarModel An URI identifying the calendar model .
			*/
			$ts_unix = strtotime($data);
			
			//This next line causes the TimeValue class to complain that I'm not
			//passing in a 8601-formatted date.
			//TODO: File a bug with someone, and spin around for a while trying 
			//to figure out what they thought 8601 required.
			//http://php.net/manual/en/function.date.php
			$ts_iso8601 = date('c', $ts_unix);
			
			//Wild guess based on the extra timezone params, they're not 
			//expecting that to come with the timestamp?
			$tz_start = strpos($ts_iso8601, '+');
			if (!$tz_start){
				$tz_start = strpos($ts_iso8601, '-', 10); //the "10" is for how many characters the date takes up
			}
			//echolog("tz start position in tz string $ts_iso8601 is $tz_start ");
			
			//peeking at the data class, they seem to want this 8601-esque 
			//format to  start with a sign [+|-], and end with a "Z". k.
			//https://en.wikipedia.org/wiki/ISO_8601
			$ts_iso8601 = substr($ts_iso8601, 0, $tz_start) . 'Z';
			$ts_iso8601 = '+' . $ts_iso8601;
			//echolog("New timestamp is $ts_iso8601");
			
			//PHP gives you seconds for free, but Minutes.
			$tz_minutes = date('Z', $ts_unix)/60;
			
			//uhh
			$before = 1;
			$after = 1;
			
			//WHY DON'T YOU TELL ME THE PRECISION.
			$precision = TimeValue::PRECISION_YEAR;
			if( date('n', $ts_unix ) > 0 ){
				$precision = TimeValue::PRECISION_MONTH;
			}
			if( date('j', $ts_unix ) > 0 ){
				$precision = TimeValue::PRECISION_DAY;
			}
			if( date('G', $ts_unix ) > 0 ){
				$precision = TimeValue::PRECISION_HOUR;
			}
			if( date('i', $ts_unix ) > 0 ){
				$precision = TimeValue::PRECISION_MINUTE;
			}
			if( date('s', $ts_unix ) > 0 ){
				$precision = TimeValue::PRECISION_SECOND;
			}
			
			$calendarModel = TimeValue::CALENDAR_GREGORIAN;
			
			//echolog("$ts_iso8601, $tz_minutes, $precision");
			$obj = new TimeValue ( $ts_iso8601, $tz_minutes, $before, $after, $precision, $calendarModel );
			break;
		default:
			echolog("Unhandled data type id '$data_type_id' for property $property_id.");
			echolog("Please add the relevant case statement to " . __FUNCTION__ . ' in ' . __FILE__);
	}
	return $obj;
}

function getProperty($property_id){
	//static here to prevent multiple lookups
	$propertyLookup = getWikibaseFactory()->newPropertyLookup();
	$propId_Obj = new PropertyId($property_id);
	$property = $propertyLookup->getPropertyForId($propId_Obj);
	return $property;
}

function getItem(){
	//static here to prevent multiple lookups
	
}