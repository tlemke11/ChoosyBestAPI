<?php
// There is a lot of talk about proper API implementation.
// with that in consideration, and considering how most real-world APIs are made
// I am going to implement my API according to this standard -
// http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api
// although I may decide to include on the HATEOAS links
//Group allows us to add a prefix to each route path
Route::group( [ 'prefix' => 'v1/book' ], function () {

	//This routes for the book title (so pulling the Amazon API data)
	Route::resource( 'title', 'TitleController', [
		'only' => [ 'index', 'show' ]
	] );

	//This routes for getting all of the extra data -
	//this controller will have to work a bunch of magic
	Route::resource( 'data', 'DataController', [
		'only' => [ 'index', 'show' ]
	] );

} );


