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
 * At some point, I may find the docs and breadcrumbs that explain how to do all 
 * these things with the included wikimedia or wikibase APIs, but at the moment,
 * this is significantly easier.
 */

require_once 'vendor/autoload.php';  //grr composer. I didn't want to have to, but here we are.

/**
 * Gets an item or property ID (Q of P number) for items with a label in 
 * $language that match $text. To return exact matches only, set 
 * $exact_match to true.
 * Returns an array for multiple matches, a string for a single match, false 
 * for no matches, and null for something going amiss.
 * @param string $text The search term
 * @param string $language Langauge code to match on
 * @param string $type Type of entity to return. item|property, defaults to item
 * @param boolean $exact_match Whether or not you only want exact matches. 
 * Default false. NOTE: Searches seem to work from the start of the string, and 
 * won't necessarily match from the middle or end...
 * @return boolean|string|array|null
 */
function getWikibaseEntsByLabel( $text, $language, $type = 'item', $exact_match = false ){
	//echolog("Looking for existing $type labeled '$text' in $language");
	$params = array(
		'action' => 'wbsearchentities',
		'format' => 'json',
		'limit' => '50',
		'continue' => '0',
		'language' => $language,
		// 'uselang' => 'en', //seems to be superfluous
		'strictlanguage' => true, //without this, you'll get fallback language results.
		'search' => $text,
		'type' => $type,	//type = property works for searches too.
	);
	
	//then curl it and see what happens.
	stopwatch("getWikibaseEntsByLabel_curl");
	$json = curl_transaction(getConfig('wikibase_api_url'), $params);
	stopwatch("getWikibaseEntsByLabel_curl", $text);
	
	if(!$json){
		echolog("Something went wrong with the curl response.");
		return null;
	}
	
	$data = json_decode($json, true); //json -> array
	
	//if we're running with exact match, filter out the ones that are not exact
	//after you ucase both sides.
	if($exact_match){
		foreach ( $data['search'] as $ind => $arr ){
			if ( strtoupper($text) != strtoupper($arr['label']) ){
				unset($data['search'][$ind]);
			}
		}
	}
	
	//now, return either a single Q number, an array of Q numbers, or false.
	$result_count = count($data['search']);
	
	switch ($result_count) {
		case 0:
			return false;
		case 1:
			//just return the one id...
			///but we may have unset '0', so...
			foreach( $data['search'] as $ind => $arr ){
				//the first one is the only one.
				return $arr['id'];
			}
		default:
			$ret = array();
			foreach( $data['search'] as $ind => $arr ){
				array_push( $ret, $arr['id'] );
			}
			return $ret;
	}
	
}

/**
 * Gets an item or property label in $language for the item specified in $id 
 * Returns a string for a single match, and false for no (or multiple, wtf) matches
 * @param string $id The item to search for
 * @param string $language Langauge code to return labels for
 * @return boolean|string
 */
function getWikibaseEntsByID( $id, $language ){
	//set $type here.
	if ( !is_numeric(trim($id, 'PQ')) ){
		echolog("'$id' does not appear to be an item or proprty ID.");
		return false;
	}
	
	$type = false;
	if (strpos($id, 'Q') === 0 ){
		$type = 'item';
	}
	if (strpos($id, 'P') === 0) {
		$type = 'property';
	}
	
	if(!$type){
		echolog("Could not get item type for ID '$id'.");
		return false;
	}
	
	echolog("Looking for existing labels for item $id in $language");
	$params = array(
		'action' => 'wbsearchentities',
		'format' => 'json',
		'limit' => '50',
		'continue' => '0',
		'language' => $language,
		// 'uselang' => 'en', //seems to be superfluous
		'strictlanguage' => true, //without this, you'll get fallback language results.
		'search' => $id,
		'type' => $type,	//type = property works for searches too.
	);
	
	//then curl it and see what happens.
	
	$json = curl_transaction(getConfig('wikibase_api_url'), $params);
	
	if(!$json){
		echolog("Something went wrong with the curl response.");
		return false;
	}
	
	$data = json_decode($json, true); //json -> array
	
	//Make sure we're matching on the ids, and not some silly text match
	foreach ( $data['search'] as $ind => $arr ){
		if ( strtoupper($id) != strtoupper($arr['id']) ){
			unset($data['search'][$ind]);
		}
	}
	
	//now, return either a single Q number, an array of Q numbers, or false.
	$result_count = count($data['search']);
	
	switch ($result_count) {
		case 0:
			return false;
		case 1:
			//just return the one id...
			///but we may have unset '0', so...
			foreach( $data['search'] as $ind => $arr ){
				//the first one is the only one.
				return $arr['label'];
			}
		default:
			echolog("Something is amiss: $id returned multiple rows!");
			echolog($data['search']);
			return false;
	}
	
}

/**
 * Gets all values for property $propID assigned to object $objID in the wikibse
 * instance specified in config.php
 * @param string $objID The ID of an object in your wikibase instance. Example: "Q4"
 * @param string $propID The ID of a property in your wikibase instance. Example: "P2"
 * @return boolean|string|array An array of multiple values, a string if only one value exists, or false for no matches
 */
function getWikibaseObjectPropertyValues( $objID, $propID ){
	$rdf = fetchObject( $objID );
	
	if (!$rdf){
		echolog("Failed to fetch object $objID.");
		return false;
	}
	
	//we are looking for wtd:P2 in this file. 
	//
	//Curiously: The Q4 .ttl file on plantdata.io has both rdfs:label and wdt:P2 
	//entries under http://wikibase.plantdata.io/entity/Q4. 
	//See: http://wikibase.plantdata.io/wiki/Special:EntityData/Q4.ttl
	//Yet, for some reason, this works... 
//	$gotten = $rdf->all('http://wikibase.plantdata.io/entity/Q4', 'rdfs:label');
	//...and this does not.
//	$gotten = $rdf->all('http://wikibase.plantdata.io/entity/Q4', 'wdt:P2');

	//check out the text dump for some clues.
	//$gotten = $rdf->dump('text');
	//echolog("I got the following:");
	//echolog($gotten);
	
	//Until I can figure out what it wants, I'm going cheap.
	//At least I am thematically consistent.
	$arrgh =  $rdf->toRdfPhp(); //say, this works. But it's totally cheap and ugly and I hate it.

	//this should maybe be another config var, as the URIs are somewhat arbitrary.
	$entityKey = 'http://wikibase.plantdata.io/entity/' . $objID;
	$propertyKey = 'http://wikibase.plantdata.io/prop/direct/' . $propID;
	
	if (!is_array($arrgh) || !array_key_exists($entityKey, $arrgh)){
		echolog("Could not find $entityKey in the entity graph for $objID.");
		return false;
	}
	
	if (!is_array($arrgh[$entityKey]) || !array_key_exists($propertyKey, $arrgh[$entityKey])) {
		echolog("No $propertyKey in the entity graph for $objID.");
		return false;
	}
	
	$arrgh = $arrgh[$entityKey][$propertyKey];
	
	$ret = array();
	foreach($arrgh as $statements => $valueArrgh){
		array_push($ret, $valueArrgh['value']);
	}
	
	if( !empty($ret)){
		if ( count($ret) == 1 ){
			return $ret[0]; //string
		} else {
			return $ret;
		}
		
	} else { //it's empty
		return false;
	}
	
}

/**
 * Fetch and try to parse the turtle file for the entity specified in $id
 * Uses EasyRDF parser library
 * @param string $id The ID of an object in your wikibase instance. Example: "Q4"
 * @return boolean|\EasyRdf_Graph A loaded EasyRdf_Graph if the file was available and parseable, otherwise false
 */
function fetchObject($id){
	//like this:
	//http://wikibase.plantdata.io/wiki/Special:EntityData/Q4.ttl
	$url = getConfig('wikibase_entitydata_url'). $id . ".ttl";
	
	echolog("Fetching $id");

	$rdf = new EasyRdf_Graph($url);
	$rdf->load();
	$triples = $rdf->countTriples();
	
	if ($triples > 0){
		//echolog($rdf->countTriples() . " triples loaded");
		return $rdf;
	} else {
		return false;
	}
}

/**
 * uses cURL to contact your wikibase instance. If present, turns the 
 * associative array in $data into the querystring and gets from the URL you 
 * supply. Returns a response or false if it failed.
 * 
 * Largely copied from myself via DonationInterface, and simplified. 
 * 
 * @param string $url The URL to contact
 * @param array|false $data Associative array of the elements to send in the
 *  querystring, or false if you don't need it.
 * @return string|false
 */
function curl_transaction( $url, $data = false ) {
	// Initialize cURL
	$ch = curl_init();

	// assign header data necessary for the curl_setopt() function
	$content_type = 'application/x-www-form-urlencoded';
	$headers = array(
		'Content-Type: ' . $content_type . '; charset=utf-8',
		'X-VPS-Client-Timeout: 45',
	);

	//We're not posting, so turn that $data array into a querystring...
	if($data){
		$querystring = http_build_query( $data );
		$url .= '?' . $querystring;
	}
	
	
	$curl_opts = array(
		CURLOPT_URL => $url,
		CURLOPT_USERAGENT => 'plantdata-importer 0.2', //i guess
		//CURLOPT_HEADER => 0,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_FOLLOWLOCATION => 0,
		//maybe later on the next two...
		//CURLOPT_SSL_VERIFYPEER => 1,
		//CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_VERBOSE => false
	);
	
	$curl_opts[CURLOPT_HTTPHEADER] = $headers;
	curl_setopt_array( $ch, $curl_opts );

	// Execute the cURL operation
	$curl_response = curl_exec( $ch );

	if ( $curl_response !== false ) {

		$headers = curl_getinfo( $ch );
		$httpCode = $headers['http_code'];

		//Nice to have: More log messaging here.
		switch ( $httpCode ) {
			case 200:   // Everything is AWESOME
				break;

			case 400:   // Oh noes! Bad request.. BAD CODE, BAD BAD CODE!
			default:    // No clue what happened... break out and log it
				echolog("Something strange happened with your cURL request. HTTP code $httpCode");
				
				break;
		}
	} else {
		// Well the cURL transaction failed for some reason or another.
		$errno = $this->curl_errno( $ch );
		$err = curl_error( $ch );
		echolog("cURL erorred out thusly: $errno - $err");
	}

	// Clean up and return
	curl_close( $ch );
	return $curl_response;
}

