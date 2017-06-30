<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DataController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	//I'm not so sure if using the request here is good practice or if I should be using middleware
	//beforehand
	public function index(Request $request)
	{
		return $name = $request->name;
		//return response()->json($response,200);

	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id)
	{
		//return response()->json([

		//], 200);
		//we have to have an associative array
		//so that it can be transformed to a json object
		$response = $request->query();

		return response()->json($response,200);
	}


}
