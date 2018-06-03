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
use Mediawiki\DataModel\Revision;
use Wikibase\Api\WikibaseFactory;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\ItemContent;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\PropertyId;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use DataValues\TimeValue;

/**
 * Returns this session's MediawikiApi object.
 * @staticvar type $mw_api MediawikiApi object
 * @return MediawikiApi
 */
function getMediwaikiAPI(){
	static $mw_api = null;
	if (is_null($mw_api)){
		//do the thing.
		//echolog("Logging in to Mediawiki instance specified in config");

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
 * Class is defined in /libs/wikibase-api/src/Api/WikibaseFactory.php. 
 * Or, at least, that's the top of the rabbit hole.
 * @return WikibaseFactory WikibaseFactory
 */
function getWikibaseFactory(){
	static $wikibase_factory = null;
		if (is_null($wikibase_factory)){
			//do the thing.
			//echolog("Setting up the Wikibase Factory");

			/**
			 * The following is mostly copied code from the example here:
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
 * Adds a new item to the wikibase instance defined in the config file, under 
 * the user account also specified in the config file.
 * Edit summary also built from config.
 * Returns the newly added item ID as a string.
 * THIS FUNCTION DOES PERFORM LIVE EDITS
 * @param string $label The label of the new item to add
 * @param string $language Language code for the language that $label is written 
 * in.
 * @return string The item ID.
 */
function editAddNewItem( $label, $language ){
	static $saver = null;
	if (is_null($saver)){
		$saver = getWikibaseFactory()->newRevisionSaver();
	}
	
	$newItem = createItemObject($label, $language);

	$edit = new Revision(
		new ItemContent( $newItem )
	);
	stopwatch('RevisionSaver Save');
	$resultingItem = $saver->save( $edit, getEditSummaryFromConfig() );
	stopwatch('RevisionSaver Save');

	// You can get the ItemId object of the created item by doing the following
	$itemIdString = $resultingItem->getId()->__toString();
	return $itemIdString;
}

function createItemObject( $label, $language ){
	$newItem = Item::newEmpty();
	$newItem->setLabel( $language, $label );
	return $newItem;
}

/**
 * Adds the item to the instance of wikibase specified in config.
 * THIS FUNCTION DOES PERFORM LIVE EDITS
 * @staticvar RevisionSaver $saver 
 * @param Item $itemObj
 * @return string|boolean The saved item's ID, or false
 * @throws Exception Passes on exception thrown by the Wikibase-api
 */
function editAddItemObject( $itemObj ){
	static $saver = null;
	if (is_null($saver)){
		$saver = getWikibaseFactory()->newRevisionSaver();
	}

	$edit = new Revision(
		new ItemContent( $itemObj )
	);
	stopwatch('RevisionSaver Save');
	try {
		$resultingItem = $saver->save( $edit, getEditSummaryFromConfig() );
	} catch (Exception $e) {
		stopwatch('RevisionSaver Save');
		echolog('Could not save new item. ' . $e->getMessage(), true);
		throw $e;
	}
	stopwatch('RevisionSaver Save');

	// You can get the ItemId object of the created item by doing the following
	$itemIdString = $resultingItem->getId()->__toString();
	return $itemIdString;
}

/**
 * Adds multiple statements to an item on the wikibase instance defined in the 
 * config file, under the user account also specified in the config file.
 * Edit summary and references also built from config.
 * This functions spends a lot of time verifying that these statements we're 
 * about to add, don't already exist.
 * THIS FUNCTION DOES PERFORM LIVE EDITS
 * @param string $item_id Item ID we are adding statements to
 * @param array $statements An array of arrays which correspond to a statement.
 * Each statement array should contain 'property_id',  'value', and 'language' 
 * key-value pairs
 * @return boolean true of any edits were made, false if none.
 */
function editAddStatementsToItem($item_id, $statements){
	//grab the existing statements on this item
	$itemObj = getItem($item_id);
	$statementList = $itemObj->getStatements();
	
	//check to see if there even are any. With this being an import tool and 
	//everything, there's a good chance there won't be.
	$count = $statementList->count();
	
	if ($count > 0){
		//check to see if the statements already exist.
		foreach ($statements as $i => $statement_data){
			$property_id = $statement_data['property_id'];
			//get the proeprty statements that exist on this item. Unset if
			//there is an exact match.
			
			//statementList is a StatementList class...
			$statementList_Smaller = $statementList->getByPropertyId( new PropertyId($property_id));
			if ($statementList_Smaller->count() > 0){
				foreach ($statementList_Smaller as $j => $statementObj){
					if ($statement_data['value'] === getStatementObjectValue($statementObj)){
						unset($statements[$i]);
					}
				}
			}
			//NO LANGUAGE ANYWHERE. What the...
			//maybe it's buried in someone's main snak somewhere. Hargh.
		}
	}
	
	if(count($statements) === 0){
		return false;
	}
	
	foreach ($statements as $i => $statement_data){
		//add statements.
		$property_id = $statement_data['property_id'];
		$value = $statement_data['value'];
		$language = false;
		if(array_key_exists('language', $statement_data)){
			$language = $statement_data['language'];
		}

		//now, all that remains should be to actually make the edit here.
		editAddSingleStatementToItem($item_id, $property_id, $value, $language);
	}
	return true;
	
}

function addStatementsToItemObject(&$itemObj, $statements){
	//grab the existing statements on this item
	$statementList = $itemObj->getStatements();
	
	//check to see if there even are any. With this being an import tool and 
	//everything, there's a good chance there won't be.
	$count = $statementList->count();
	
	if ($count > 0){
		//check to see if the statements already exist.
		foreach ($statements as $i => $statement_data){
			$property_id = $statement_data['property_id'];
			
			//get the proeprty statements that exist on this item. Unset if
			//there is an exact match.
			
			//statementList is a StatementList class...
			$statementList_Smaller = $statementList->getByPropertyId( new PropertyId($property_id));
			if ($statementList_Smaller->count() > 0){
				foreach ($statementList_Smaller as $j => $statementObj){
					if ($statement_data['value'] === getStatementObjectValue($statementObj)){
						unset($statements[$i]);
					}
				}
			}
			//NO LANGUAGE ANYWHERE. What the...
			//maybe it's buried in someone's main snak somewhere. Hargh.
		}
	}
	
	if(count($statements) === 0){
		return false;
	}
	
	$count = 0;
	foreach ($statements as $i => $statement_data){
		//add statements.
		$property_id = $statement_data['property_id'];
		$value = $statement_data['value'];
		$language = false;
		if(array_key_exists('language', $statement_data)){
			$language = $statement_data['language'];
		}
		$statementList->addStatement(createStatementObject($property_id, $value, $language));
		++$count;
	}
	
	$itemObj->setStatements($statementList);
	return $count;
	
}

/**
 * Adds a single statement to an item on the wikibase instance defined in the 
 * config file, under the user account also specified in the config file.
 * Edit summary and references also built from config.
 * Returns the newly added item ID as a string.
 * THIS FUNCTION DOES PERFORM LIVE EDITS
 * @param string $item The item ID to add the statement to
 * @param string $property_id The property ID for the statement
 * @param string $value The value of $property_id in $item
 * @param string $language If relevent, the language $value is written in.
 */
function editAddSingleStatementToItem($item, $property_id, $value, $language){
	stopwatch('AddSingleStatement');
	static $statementCreator = null;
	if (is_null($statementCreator)){
		$statementCreator = getWikibaseFactory()->newStatementCreator();
	}
	
	$claim_guid = $statementCreator->create(
        new PropertyValueSnak(
            PropertyId::newFromNumber( trim($property_id, 'P') ),
            typecastDataForProperty($property_id, $value, $language)
        ),
        $item
    );
	//do we need to actually use the saver? Because it looks like no.
	stopwatch('AddReferences');
	editAddStatementReferences( $claim_guid );
	stopwatch('AddReferences');
	stopwatch('AddSingleStatement');
}

function createStatementObject($property_id, $value, $language){
	$refList = new ReferenceList();
	$refList->addReference(getReferencesFromConfig());
	
	$statement = new Statement(
		new PropertyValueSnak(
            PropertyId::newFromNumber( trim($property_id, 'P') ),
            typecastDataForProperty($property_id, $value, $language)
        ),
		null,
		$refList,
		null
	);

	return $statement;
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
	$item = getItem(getConfig('test_item'));

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

			editAddStatementReferences( $statement );
			
		} else {
			//next trick: Can we read the references that are already there?
			//Probably, but that's a different problem, and one I'm not entirely 
			//sure I need to solve for a mass data importer. 
			echolog("Oh look, we already had a reference on that statement.");
		}
	}
}


/**
 * Finds and creates the wikibase_api data object class required to create a 
 * statement with the supplied data value on the specified property.
 * @param string $property_id ID of the property to use
 * @param string $data The data we'd like to assign to the property, presumably 
 * in a statement.
 * @param string $language language code, if necessary.
 * @return Object of various origins that should be readily assignable to a 
 * statement object involving the specified property.
 */
function typecastDataForProperty($property_id, $data, $language = false){
	//first, look up what kind of data the property wants.
	$data_type_id = getPropertyTypeID($property_id);
	//echolog("$data_type_id is the data type ID for $property_id");
	//then, stuff the data into the right class and return it.
	
	$obj = null;
	switch( $data_type_id ){
		case 'commonsMedia':
		case 'external-id':
		case 'url':
		case 'string':
			$obj = new StringValue( $data );
			break;
		case 'monolingualtext':
			$obj = new MonolingualTextValue( $language, $data );
			break;
		case 'wikibase-item':
			$obj = new EntityIdValue( new ItemId($data) );
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

/**
 * Gets a property object from a property_id string.
 * @staticvar array $retrieved local caching
 * @param string $property_id The ID of the property object to get
 * @param boolean $refresh Force getting even if we've cached it already this run
 * @return Property object
 */
function getProperty($property_id, $refresh = false){
	//static here to prevent bandwidth wasting
	static $retrieved = array();
	if ( ($refresh === false) && (array_key_exists($property_id, $retrieved)) ){
		return $retrieved[$property_id];
	}
	
	$propertyLookup = getWikibaseFactory()->newPropertyLookup();
	$propId_Obj = new PropertyId($property_id);
	$property = $propertyLookup->getPropertyForId($propId_Obj);
	$retrieved[$property_id] = $property;
	return $property;
}

function getPropertyTypeID($property_id){
	$property = getProperty($property_id);
	return $property->getDataTypeId();
}

/**
 * Takes an item id string, and returns an object of type... that comes back 
 * when you call getItemForId on an ItemLookup object. Shrug.
 * Bothersome that this is basically copied code from the function above...
 * @staticvar array $retrieved Caching in the function to hopefully sometimes 
 * cut down on bandwidth.
 * @param string $item_id The item ID we're getting as an object.
 * @param bool $refresh Go get it, whether or not it's already been retrieved 
 * and cached  this run.
 * @return Object. Totally.
 */
function getItem($item_id, $refresh = false){
	//static here to prevent bandwidth wasting
	static $retrieved = array();
	if ( ($refresh === false) && (array_key_exists($item_id, $retrieved)) ){
		return $retrieved[$item_id];
	}
	
	//Not wasting bandwidth is great, but Items can get big.
	while(count($retrieved) > 500){
		array_shift($retrieved);
	}
	
	$itemLookup = getWikibaseFactory()->newItemLookup();
	$itemId_Obj = new ItemId($item_id);
	$item = $itemLookup->getItemForId($itemId_Obj);
	$retrieved[$item_id] = $item;
	return $item;
}

/**
 * Returns a Reference object corresponding to the reference information set in 
 * config.
 * TODO: Local caching here too.
 * @return Reference
 */
function getReferencesFromConfig(){
	static $refObject = null;
	if(is_null($refObject)){
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
	}
	return $refObject;
}

/**
 * Builds usable wikibase objects from the edit summary defined in the config 
 * file.
 * Returns an EditInfo object directly consumable by the editing monster.
 * @return EditInfo
 */
function getEditSummaryFromConfig(){
	static $editInfoObject = null;
	if(is_null($editInfoObject)){
		$summary = getConfig('edit_summary');
		//EditInfo object takes the following:
		//$summary = '', $minor = self::NOTMINOR, $bot = self::NOTBOT
		//so, I'm telling it this edit is not minor, and that I am in fact a bot.
		$editInfoObject = new EditInfo($summary, false, true);
	}
	return $editInfoObject;
}

/**
 * Get the string value of an object of class Statement.
 * @param Statement $statementObj
 * @return string
 */
function getStatementObjectValue($statementObj){
	$main = $statementObj->getMainSnak();
	$value = $main->getDataValue()->getValue(); // o_O;
	
	if( gettype($value) != 'string' ){	
		$class = get_class($value);
		switch ($class){
			case 'Wikibase\DataModel\Entity\EntityIdValue' :
				$value = $value->getEntityId()->serialize();
				break;
			default:
				echolog("Oh look, an unhandled possibility ($class) in " . __FUNCTION__);
				die();
		}	
	}
	
	if( gettype($value) != 'string' ){
		echolog("TODO: Solve for partially handled class " . get_class($value) . " in " . __FUNCTION__);
		die();
	}
	return $value;
}

/**
 * Adds references to a statement on the wikibase instance defined in the 
 * config file, under the user account also specified in the config file.
 * Edit summary and references also built from config.
 * Returns true... all the time, I guess.
 * THIS FUNCTION DOES PERFORM LIVE EDITS
 * @param string $statement The statement guid
 * @return true Truuuuuue
 */
function editAddStatementReferences( $statement ){
	static $refsetter = null;
	if(is_null($refsetter)){
		$refsetter = getWikibaseFactory()->newReferenceSetter();
	}

	//to actually set the reference with the setter, we need the following:			
	//Reference $reference, $statement, $targetReference = null, EditInfo $editInfo = null

	$refsetter->set(
			getReferencesFromConfig(), 
			$statement, 
			null, 
			getEditSummaryFromConfig()
	);
	return true;
}

