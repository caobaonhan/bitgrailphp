<?php

	/*

	bitgrailphp

	PHP library to make easier to interface to BitGrail exchange APIs
	
	Get full API documentations here: https://bitgrail.com/api-documentation
	
	====================

	LICENSE: Use it as you want!

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	====================

	// Include the script in your configuration file
	include_once('PATH/bitgrail.php');

	// Initialize RaiBlocks connection/object
	$bitgrail = new BitGrailAPI( $publicKey, $privateKey, $version ); // Get public and private keys from your BitGrail account, version is the API version you want to call, default is 1

	// Make calls to node as methods for your object. Responses are returned as an array.
	// Example:

	$args = array(
	
		"market" => "BTC-XRB",
		"amount" => 1000,
		"price" => "0.00000900" // I suggest to pass tiny floats as string to avoid PHP formatting 9.0E-6
	
	);

	$response = $bitgrail->buyorder( $args );
	echo $response['orderId'];

	// The full response (not usually needed) is stored in $this->response while the raw JSON is stored in $this->raw_response

	// When a call fails for any reason, it will return FALSE and put the error message in $this->error
	// Example:
	echo $bitgrail->error;

	// The HTTP status code can be found in $this->status and will either be a valid HTTP status code or will be 0 if cURL was unable to connect.
	// Example:
	echo $bitgrail->status;

	*/

	class BitGrailAPI{
		
		// Configuration options
		private $version;
		private $publicKey;
		private $privateKey;

		// Information and debugging
		public $status;
		public $error;
		public $raw_response;
		public $response;
		public $method;

		private $id = 0;

		function __construct( $publicKey = "", $privateKey = "", $version = "1" ){
			
			$this->version       = $version;
			$this->publicKey     = $publicKey;
			$this->privateKey    = $privateKey;
			
		}

		function __call( $method, $params ){
			
			$this->status       = null;
			$this->error        = null;
			$this->raw_response = null;
			$this->response     = null;
			$this->method		= $method;

			// If no parameters are passed, this will be an empty array
			//$params = array_values($params);

			// The ID should be unique for each call
			$this->id++;

			// Build the request, it's ok that params might have any empty array
			$request = array();
			
			if( isset( $params[0] ) ){
			
				foreach( $params[0] as $key=>$value ){
						
					$request[$key] = $value;
						
				}
			
			}
			
			$nonce = microtime( true )*1000000;
			$request["nonce"] = number_format( $nonce, 0, '', '' );
			
			$request = http_build_query( $request, '', '&' );
						
			$signature = hash_hmac( "sha512", $request, $this->privateKey );

			$headers = array(
			
				"KEY: ".$this->publicKey,
				"SIGNATURE: ".$signature
				
			);

			// Build the cURL session
			$curl = curl_init( "https://bitgrail.com/api/v{$this->version}/{$this->method}" );
			$options = array(
			
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_USERAGENT	   => "PHP",
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POST           => TRUE,
				CURLOPT_POSTFIELDS     => $request,
				CURLOPT_SSL_VERIFYPEER => FALSE
				
			);

			// This prevents users from getting the following warning when open_basedir is set:
			// Warning: curl_setopt() [function.curl-setopt]: CURLOPT_FOLLOWLOCATION cannot be activated when in safe_mode or an open_basedir is set
			if( ini_get( 'open_basedir' ) ){
				
				unset( $options[CURLOPT_FOLLOWLOCATION] );
				
			}

			curl_setopt_array( $curl, $options );

			// Execute the request and decode to an array
			$this->raw_response = curl_exec( $curl );
			$this->response     = json_decode( $this->raw_response, TRUE );

			// If the status is not 200, something is wrong
			$this->status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			
			// If there was no error, this will be an empty string
			$curl_error = curl_error( $curl );

			curl_close( $curl );

			if( !empty( $curl_error ) ){
				
				$this->error = $curl_error;
				
			}

			if( $this->status != 200 ){
				
				// If node didn't return a nice error message, we need to make our own
				switch( $this->status ){
					
					case 400:
						$this->error = 'HTTP_BAD_REQUEST';
						break;
					case 401:
						$this->error = 'HTTP_UNAUTHORIZED';
						break;
					case 403:
						$this->error = 'HTTP_FORBIDDEN';
						break;
					case 404:
						$this->error = 'HTTP_NOT_FOUND';
						break;
						
				}
				
			}

			if( $this->error ){
				
				return FALSE;
				
			}

			return $this->response;
			
		}
		
	}

?>