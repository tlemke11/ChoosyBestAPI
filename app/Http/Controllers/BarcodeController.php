<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleExeption;
use GuzzleHttp\Client as GuzzleClient;

class BarcodeController extends Controller {
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
			$amzRequest = $this->amzRequest(null, $id, "isbn");
			return json_encode($amzRequest, JSON_UNESCAPED_SLASHES);
		} else if (strlen( (string) $id ) === 12) {
			//Do AMZ upc lookup
			$amzRequest = $this->amzRequest(null, $id, "upc");
			return json_encode($amzRequest, JSON_UNESCAPED_SLASHES);
		}
		//TODO - return error statement here
		return "Please enter valid ISBN";
	}


	//TODO -- It seems like you would just put the amazon function within this
	// title controller - since it handles business logic. Look into refactoring
	// in the future - I think I can abstract out a lot of this to make one class that handles
	// the building of the API's similarlly
	// see here https://hackernoon.com/creating-rest-api-in-php-using-guzzle-d6a890499b02
	// in Class CloudwaysAPIClient
	public function amzRequest( $title = null, $barcode = null, $barcodeType = null) {

		//TODO - catch errors here
		if ( $title === null && $barcode === null ) {
			echo "null values in amzRequest";
			return;
		}

		//The majority of this code is copied straight from
		//http://webservices.amazon.com/scratchpad inside of my account
		// Your AWS Access Key ID, as taken from the AWS Your Account page
		$aws_access_key_id = "access_key_here";

		// Your AWS Secret Key corresponding to the above ID, as taken from the AWS Your Account page
		$aws_secret_key = "secret_here";

		// The region you are interested in
		$endpoint = "webservices.amazon.com";

		$uri = "/onca/xml";


		//TODO - Break this out into separate funcition
		if($barcode !== null && $barcodeType == "isbn") {
			$params = array(
				"Service"        => "AWSECommerceService",
				"Operation"      => "ItemLookup",
				"AWSAccessKeyId" => $aws_access_key_id,
				"AssociateTag"   => "lemke-20",
				"ItemId"         => $barcode,
				"IdType"         => "ISBN",
				"ResponseGroup"  => "AlternateVersions,EditorialReview,Images,ItemAttributes,Reviews",
				"SearchIndex"    => "Books"
			);
		} else if($barcode !== null && $barcodeType == "upc") {
			$params = array(
				"Service"        => "AWSECommerceService",
				"Operation"      => "ItemLookup",
				"AWSAccessKeyId" => $aws_access_key_id,
				"AssociateTag"   => "lemke-20",
				"ItemId"         => $barcode,
				"IdType"         => "UPC",
				"ResponseGroup"  => "AlternateVersions,EditorialReview,Images,ItemAttributes,Reviews",
				"SearchIndex"    => "Movies"
			);
		}
		else {
			$params = array(
				"Service"        => "AWSECommerceService",
				"Operation"      => "ItemSearch",
				"AWSAccessKeyId" =>  $aws_access_key_id,
				"AssociateTag"   => "lemke-20",
				"SearchIndex"    => "Books",
				"ResponseGroup"  => "AlternateVersions,EditorialReview,Images,ItemAttributes,Reviews",
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

		$json = json_decode(json_encode($simpleXml,JSON_UNESCAPED_SLASHES));


		//TODO - check for error code within response and then return not found to client

		//print_r($json);

		if (isset($json->Items->Item->ItemAttributes)){
		    $itemRoot = $json->Items->Item;
		} else if(isset($json->Items->Item[0]->ItemAttributes)){
			$itemRoot = $json->Items->Item[0];
		} else {
			//TODO return to client - no data found
		}


		if ($barcodeType == "upc"){
			$itemDetails = $this->getMovieDetails($itemRoot);
		} else if($barcodeType == "isbn"){
			$itemDetails = $this->getBookDetails($itemRoot);
		}

		return $itemDetails;
	}


	public function getBookDetails($itemRoot){
		return array(
			'title' => $itemRoot->ItemAttributes->Title,
			'author' => $itemRoot->ItemAttributes->Author,
			'year' => $itemRoot->ItemAttributes->PublicationDate,
			'isbn' => $itemRoot->ItemAttributes->ISBN,
			'ean' => $itemRoot->ItemAttributes->EAN,
			'smImg' => $itemRoot->SmallImage->URL,
			'mdImg' => $itemRoot->MediumImage->URL,
			'lgImg' => $itemRoot->LargeImage->URL,
			'reviewsIframe' => $itemRoot->CustomerReviews->IFrameURL
		);
	}

	public function getMovieDetails($itemRoot){
		return array(
			'title'         => $itemRoot->ItemAttributes->Title,
			'smImg'         => $itemRoot->SmallImage->URL,
			'mdImg'         => $itemRoot->MediumImage->URL,
			'lgImg'         => $itemRoot->LargeImage->URL,
			'actors'        => $itemRoot->ItemAttributes->Actor,
			'rating'        => $itemRoot->ItemAttributes->AudienceRating,
			'releaseDate'   => $itemRoot->ItemAttributes->ReleaseDate,
			'upc'           => $itemRoot->ItemAttributes->UPC,
			'reviewsIframe' => $itemRoot->CustomerReviews->IFrameURL


		);
	}






}
