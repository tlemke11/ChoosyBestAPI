<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleExeption;
use GuzzleHttp\Client as GuzzleClient;

class TitleController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	//I'm not so sure if using the request here is good practice or if I should be using middleware
	//beforehand
	public function index( Request $request ) {

		$title = str_replace( '_', ' ', $request->title );
		$this->amzRequest($title);
		return "Please enter valid ISBN";
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function show( $id ) {
		//not sure if we should do validation before it hits show
		//perhaps we should end up doing this with middleware in the controller?
		if ( strlen( (string) $id ) === 13 || strlen( (string) $id ) === 10 ) {
			$amzRequest = $this->amzRequest(null, $id);
			$goodReadsRequest = $this->goodReadsRequest($id);
			$fullBookDetails = array_merge($amzRequest,$goodReadsRequest);

			return json_encode($fullBookDetails, JSON_UNESCAPED_SLASHES);

		}

		return "Please enter valid ISBN";

	}


	//TODO -- It seems like you would just put the amazon function within this
	// title controller - since it handles business logic. Look into refactoring
	// in the future
	public function amzRequest( $title = null, $isbn = null ) {

		if ( $title === null && $isbn === null ) {
			echo "null values in amzRequest";
			return;
		}
		//The majority of this code is copied straight from
		//http://webservices.amazon.com/scratchpad inside of my account
		// Your AWS Access Key ID, as taken from the AWS Your Account page
		$aws_access_key_id = "AKIAIG3KN5WGYN6FL67A";

		// Your AWS Secret Key corresponding to the above ID, as taken from the AWS Your Account page
		$aws_secret_key = "XwFu63jS7hoTh4Ne7z9eFNd2zHyxqaBD6SXicY6l";

		// The region you are interested in
		$endpoint = "webservices.amazon.com";

		$uri = "/onca/xml";

		if($isbn !== null) {
			$params = array(
				"Service"        => "AWSECommerceService",
				"Operation"      => "ItemLookup",
				"AWSAccessKeyId" => $aws_access_key_id,
				"AssociateTag"   => "lemke-20",
				"ItemId"         => $isbn,
				"IdType"         => "ISBN",
				"ResponseGroup"  => "Images,ItemAttributes",
				"SearchIndex"    => "Books"
			);
		}
		else {
		$params = array(
			"Service"        => "AWSECommerceService",
			"Operation"      => "ItemSearch",
			"AWSAccessKeyId" =>  $aws_access_key_id,
			"AssociateTag"   => "lemke-20",
			"SearchIndex"    => "Books",
			"ResponseGroup"  => "Images,ItemAttributes",
			"Keywords"       => $title
		);
		}

		// Set current timestamp if not set
		if ( ! isset( $params["Timestamp"] ) ) {
			$params["Timestamp"] = gmdate( 'Y-m-d\TH:i:s\Z' );
		}

		// Sort the parameters by key
		ksort( $params );

		$pairs = array();

		foreach ( $params as $key => $value ) {
			array_push( $pairs, rawurlencode( $key ) . "=" . rawurlencode( $value ) );
		}

		// Generate the canonical query
		$canonical_query_string = join( "&", $pairs );

		// Generate the string to be signed
		$string_to_sign = "GET\n" . $endpoint . "\n" . $uri . "\n" . $canonical_query_string;

		// Generate the signature required by the Product Advertising API
		$signature = base64_encode( hash_hmac( "sha256", $string_to_sign, $aws_secret_key, true ) );

		// Generate the signed URL
		$request_url = 'http://' . $endpoint . $uri . '?' . $canonical_query_string . '&Signature=' . rawurlencode( $signature );

		//$simpleXml = simplexml_load_file($request_url);
		$simpleXml = new \SimpleXMLElement(file_get_contents($request_url),LIBXML_NOCDATA);

		$json = json_decode(json_encode($simpleXml,JSON_UNESCAPED_SLASHES), true);


		//Lets grab just the stuff that I need
		$itemRoot = $json['Items']['Item']['ItemAttributes'];

		$bookDetails = array(
		'title' => $itemRoot['Title'],
		'author' => $itemRoot['Author'],
		'year' => $itemRoot['PublicationDate'],
		'isbn' => $itemRoot['ISBN'],
		'ean' => $itemRoot['EAN'],
		'smImg' => $json['Items']['Item']['SmallImage']['URL'],
		'mdImg' => $json['Items']['Item']['MediumImage']['URL'],
		'lgImg' => $json['Items']['Item']['LargeImage']['URL']
		);


		return $bookDetails;

	}

	//TODO -- It seems like you would just put the amazon function within this
	// title controller - since it handles business logic. Look into refactoring
	// in the future - I think I can abstract out a lot of this to make one class that handles
	// the building of the API's similarlly
	// see here https://hackernoon.com/creating-rest-api-in-php-using-guzzle-d6a890499b02
	// in Class CloudwaysAPIClient

	public function goodReadsRequest ($isbn){

		$guzzler = new GuzzleClient();

		// URL Building HERE

		//ratings url
		$url = "https://www.goodreads.com/book/review_counts.json";
		//my app key - keep it secret, keep it safe
		$secretKey = "2ULRwZcsSWikHdtYjRLahg";
		//isbn portion here
		//$isbnUrlPortion = "&isbns=".$isbn;

		$response = $guzzler->request('GET', "$url", [
			'query' => ['key'=>$secretKey],
			'query' => ['isbns'=>$isbn]
		])->getBody()->getContents();

		//https://stackoverflow.com/questions/30549226/guzzlehttp-how-get-the-body-of-a-response-from-guzzle-6
		//var_dump($response->getBody()->getContents());

		$response = json_decode($response, true);

		$ratingDetails = array(
			'goodReadsAverageRating' => $response['books'][0]['average_rating'],
			'goodReadsRatingsCount' => $response['books'][0]['ratings_count']

		);

		return $ratingDetails;
	}



}
