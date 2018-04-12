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
  what command line parameters do I want to use?
  import file name (let's do the mapping in the header)
  run size
  dry run
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

//now do something.
//Both log and echo what we're doing
require_once('logger.php');
$log = new echologger();
$log->say("Running the importer on $filename, for $run_size entries.");

if (isset($dry_run) && $dry_run) {
	$log->say("This is a dry run.");
} else {
	$log->say("THIS IS REAL.");
}

//Summon the wikibase-api libraries.
require_once( __DIR__ . '/libs/wikibase-api/vendor/autoload.php' );

//we want to use these classes
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\ApiUser;
use Wikibase\Api\WikibaseFactory;

try {
	//pull in the config settings and make the connections
	require_once('config.php');
	$api = new MediawikiApi($wikibase_api_url);
	$api->login(new ApiUser($wikibase_username, $wikibase_password));
} catch (Exception $e) {
	$log->say('Caught ErrorException: ' . $e->getMessage());
	$log->lastError();
}


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
$wbFactory = new WikibaseFactory(
	$api, new DataValues\Deserializers\DataValueDeserializer($dataValueClasses), new DataValues\Serializers\DataValueSerializer()
);

/**
 * End copied code
 */

