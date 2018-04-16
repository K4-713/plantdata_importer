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
$test_item = 'Q4';
$test_property = 'P2';
$test_item_search = 'Uncle Ghost';
$test_property_search = 'species';
$test_language = 'en';

