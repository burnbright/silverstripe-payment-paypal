<?php
/**
 * PayPal Express Checkout Payment
 * @author Jeremy Shipman jeremy [at] burnbright.co.nz
 * 
 * You will need a PayPal sandbox account, along with merchant and customer test accounts,
 * which can be set up by following this guide:
 * https://developer.paypal.com/en_US/pdf/PP_Sandbox_UserGuide.pdf
 * 
 * API reference: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_api_reference
 * 
 * ..add testing info
 * ..url parameters
 * 
 */

//TODO: allow direct payments, perhaps extend this class for that

class PayPalExpressCheckoutPayment extends Payment{
	
	static $db = array(
		'Token' => 'Varchar(30)',
		'PayerID' => 'Varchar(30)',
		'TransactionID' => 'Varchar(30)'
	);
	
	protected static $logo = "payment/images/payments/paypal.jpg";
	
	//PayPal URLs
	protected static $test_API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
	protected static $test_PAYPAL_URL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";

	protected static $API_Endpoint = "https://api-3t.paypal.com/nvp";
	protected static $PAYPAL_URL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
	
	protected static $privacy_link = "https://www.paypal.com/us/cgi-bin/webscr?cmd=p/gen/ua/policy_privacy-outside";
	
	//redirect URLs
	protected static $returnURL = "PaypalExpressCheckoutaPayment_Handler/confirm";
	protected static $cancelURL = "PaypalExpressCheckoutaPayment_Handler/cancel";
	
	//config
	protected static $test_mode = true; //on by default

	protected static $API_UserName;
	protected static $API_Password;
	protected static $API_Signature;
	protected static $sBNCode = null; // BN Code 	is only applicable for partners
	
	protected static $version = '64';
	
	static function set_test_config_details($username,$password,$signature,$sbncode = null){
		self::$API_UserName = $username;
		self::$API_Password = $password;
		self::$API_Signature = $signature;
		self::$sBNCode = $sbncode;
		self::$test_mode = true;
	}
	
	static function set_config_details($username,$password,$signature,$sbncode = null){
		self::$API_UserName = $username;
		self::$API_Password = $password;
		self::$API_Signature = $signature;
		self::$sBNCode = $sbncode;
		self::$test_mode = false;
	}
	
	//main processing function
	function processPayment($data, $form) {
		
		//sanity checks for credentials
		if(!self::$API_UserName || !self::$API_Password || !self::$API_Signature){
			user_error('You are attempting to make a payment without the necessary credentials set', E_USER_ERROR);
		}
		
		$paymenturl = $this->getTokenURL($this->Amount->Amount,$this->Amount->Currency);
		
		$this->Status = "Pending";
		$this->write();
		
		if($paymenturl){
			Director::redirect($paymenturl); //redirect to payment gateway
			return new Payment_Processing();
		}
		
		$this->Message = "PayPal could not be contacted";
		$this->Status = 'Failure';
		$this->write();
		
		return new Payment_Failure($this->Message);
	}
	
	
	protected function getTokenURL($paymentAmount, $currencyCodeType, $paymentType = "Sale"){

		$data = array(
			'PAYMENTREQUEST_0_AMT' => $paymentAmount,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $currencyCodeType, //TODO: check to be sure all currency codes match the SS ones
			'PAYMENTREQUEST_0_PAYMENTACTION' => $paymentType,
			'RETURNURL' => Director::absoluteURL(self::$returnURL,true),
			'CANCELURL' => Director::absoluteURL(self::$cancelURL,true)
			
			//TODO: add shipping fields, or make it optional
			
			//'ADDROVERRIDE' => 1,
			//'PAYMENTREQUEST_0_SHIPTONAME' => $shipToName,
			//'PAYMENTREQUEST_0_SHIPTOSTREET' => $shipToStreet,
			//'PAYMENTREQUEST_0_SHIPTOSTREET2' => $shipToStreet2,
			//'PAYMENTREQUEST_0_SHIPTOCITY' => $shipToCity,
			//'PAYMENTREQUEST_0_SHIPTOSTATE' => $shipToState,
			//'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE' => $shipToCountryCode,
			//'PAYMENTREQUEST_0_SHIPTOZIP' => $shipToZip,
			//'PAYMENTREQUEST_0_SHIPTOPHONENUM' => $phoneNum
		);

		$response = $this->apiCall('SetExpressCheckout',$data);
		
		//TODO: check for success message: "SUCCESS" || "SUCCESSWITHWARNING"
		//else return null;
		if(!isset($response['ACK']) ||  !(strtoupper($response['ACK']) == "SUCCESS" || strtoupper($response['ACK']) == "SUCCESSWITHWARNING")){
			return null;
		}
		
		//get and save token for later
		$token = $response['TOKEN'];
		$this->Token = $token;
		$this->write();

		return $this->getPayPalURL($token);
	}
	
	function confirmPayment(){
		
		$paymentType = "Sale";
		
		$data = array(
			'PAYERID' => $this->PayerID,
			'TOKEN' => $this->Token,
			'PAYMENTREQUEST_0_PAYMENTACTION' => $paymentType,
			'PAYMENTREQUEST_0_AMT' => $this->Amount->Amount,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->Amount->Currency,
			'IPADDRESS' => urlencode($_SERVER['SERVER_NAME'])
		);
		
		$response = $this->apiCall('DoExpressCheckoutPayment',$data);
		
		if(!isset($response['ACK']) ||  !(strtoupper($response['ACK']) == "SUCCESS" || strtoupper($response['ACK']) == "SUCCESSWITHWARNING")){
			return null;
		}
		
		if(isset($response["PAYMENTINFO_0_TRANSACTIONID"])){
			$this->TransactionID	= $response["PAYMENTINFO_0_TRANSACTIONID"]; 	//' Unique transaction ID of the payment. Note:  If the PaymentAction of the request was Authorization or Order, this value is your AuthorizationID for use with the Authorization & Capture APIs.
		} 
		//$transactionType 		= $response["PAYMENTINFO_0_TRANSACTIONTYPE"]; //' The type of transaction Possible values: l  cart l  express-checkout 
		//$paymentType			= $response["PAYMENTTYPE"];  	//' Indicates whether the payment is instant or delayed. Possible values: l  none l  echeck l  instant 
		//$orderTime 				= $response["ORDERTIME"];  		//' Time/date stamp of payment
		
		//TODO: should these be updated like this??
		//$this->Amount->Amount	= $response["AMT"];  			//' The final amount charged, including any shipping and taxes from your Merchant Profile.
		//$this->Amount->Currency= $response["CURRENCYCODE"];  	//' A three-character currency code for one of the currencies listed in PayPay-Supported Transactional Currencies. Default: USD. 
		//$feeAmt					= $response["FEEAMT"];  		//' PayPal fee amount charged for the transaction
		//$settleAmt				= $response["SETTLEAMT"];  		//' Amount deposited in your PayPal account after a currency conversion.
		//$taxAmt					= $response["TAXAMT"];  		//' Tax charged on the transaction.
		//$exchangeRate			= $response["EXCHANGERATE"];  	//' Exchange rate if a currency conversion occurred. Relevant only if your are billing in their non-primary currency. If the customer chooses to pay with a currency other than the non-primary currency, the conversion occurs in the customer’s account.

		if(isset($response["PAYMENTINFO_0_PAYMENTSTATUS"]) && strtoupper($response["PAYMENTINFO_0_PAYMENTSTATUS"]) == "COMPLETED"){
			$this->Status = 'Success';			
		}
		
		//$pendingReason	= $response["PENDINGREASON"];
		//$reasonCode		= $response["REASONCODE"];
		
		$this->write();
		
	}
	
	/**
	 * Handles actual communication with API server.
	 */
	protected function apiCall($method,$data = array()){
		
		$postfields = array(
			'METHOD' => $method,
			'VERSION' => self::$version,
			'USER' => self::$API_UserName,			
			'PWD'=> self::$API_Password,
			'SIGNATURE' => self::$API_Signature,
			'BUTTONSOURCE' => self::$sBNCode
		);
		
		$postfields = array_merge($postfields,$data);
		
		//Make POST request to Paystation via RESTful service
		$paystation = new RestfulService($this->getApiEndpoint(),0); //REST connection that will expire immediately
		$paystation->httpHeader('Accept: application/xml');
		$paystation->httpHeader('Content-Type: application/x-www-form-urlencoded');
		
		$response = $paystation->request('','POST',http_build_query($postfields));	
		
		return $this->deformatNVP($response->getBody());
	}
	
	protected function deformatNVP($nvpstr){
		$intial = 0;
	 	$nvpArray = array();

		while(strlen($nvpstr)){
			//postion of Key
			$keypos= strpos($nvpstr,'=');
			//position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

			/*getting the Key and Value values and storing in a Associative Array*/
			$keyval=substr($nvpstr,$intial,$keypos);
			$valval=substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
			//decoding the respose
			$nvpArray[urldecode($keyval)] =urldecode( $valval);
			$nvpstr=substr($nvpstr,$valuepos+1,strlen($nvpstr));
	     }
		return $nvpArray;
	}
	
	protected function getApiEndpoint(){
		return (self::$test_mode) ? self::$test_API_Endpoint : self::$API_Endpoint;
	}
	
	protected function getPayPalURL($token){
		$url = (self::$test_mode) ? self::$test_PAYPAL_URL : self::$PAYPAL_URL;
		return $url.$token;
	}
	
	
	function getPaymentFormFields() {
		$logo = '<img src="' . self::$logo . '" alt="Credit card payments powered by PayPal"/>';
		$privacyLink = '<a href="' . self::$privacy_link . '" target="_blank" title="Read PayPal\'s privacy policy">' . $logo . '</a><br/>';
		return new FieldSet(
			new LiteralField('PayPalInfo', $privacyLink),
			new LiteralField(
				'PayPalPaymentsList',
				
				//TODO: these methods aren't available in all countries
				'<img src="payment/images/payments/methods/visa.jpg" alt="Visa"/>' .
				'<img src="payment/images/payments/methods/mastercard.jpg" alt="MasterCard"/>' .
				'<img src="payment/images/payments/methods/american-express.gif" alt="American Express"/>' .
				'<img src="payment/images/payments/methods/discover.jpg" alt="Discover"/>' .
				'<img src="payment/images/payments/methods/paypal.jpg" alt="PayPal"/>'
			)
		);
	}

	function getPaymentFormRequirements() {return null;}
	
}

class PaypalExpressCheckoutaPayment_Handler extends Controller{
	
	protected $payment = null; //only need to get this once
	
	static $allowed_actions = array(
		'confirm',
		'cancel'
	);
	
	function payment(){
		if($this->payment){
			return $this->payment;
		}
		
		if($token = Controller::getRequest()->getVar('token')){
			$p =  DataObject::get_one('PayPalExpressCheckoutPayment',"\"Token\" = '$token' AND \"Status\" = 'Pending'");
			$this->payment = $p;
			return $p;
		}
		return null;
	}
	
	function confirm($request){
		
		//TODO: pretend the user confirmed, and skip straight to results. (check that this is allowed)
		//TODO: get updated shipping details from paypal??
		
		if($payment = $this->payment()){
			
			if($pid = Controller::getRequest()->getVar('PayerID')){
				$payment->PayerID = $pid;
				$payment->write();
				
				$payment->confirmPayment();
			}
			
		}else{
			//something went wrong?	..perhaps trying to pay for a payment that has already been processed	
		}
		
		$this->doRedirect();
		return;
	}
	
	function cancel($request){

		if($payment = $this->payment()){
			
			//TODO: do API call to gather further information
			
			$payment->Status = "Failure";
			$payment->Message = "User cancelled";
			$payment->write();
		}
		
		$this->doRedirect();
		return;
	}
	
	function doRedirect(){
		
		$payment = $this->payment();
		if($payment && $obj = $payment->PaidObject()){
			Director::redirect($obj->Link());
			return;
		}
		
		Director::redirect(Director::absoluteURL('home',true)); //TODO: make this customisable in Payment_Controllers
		return;
	}
}