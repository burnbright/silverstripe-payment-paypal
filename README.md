# Paypal Payment Type

Paypal payment type for the SilverStripe payment module.

## Testing setup:

You will need a PayPal sandbox account, along with merchant and customer test accounts,
which can be set up by following this guide: https://developer.paypal.com/en_US/pdf/PP_Sandbox_UserGuide.pdf

How to set up your paypal account:

 * Set up a paypal merchant account
 * Log in
 * Visit 'My Account' > 'Profile' > 'My selling tools'
 * Click API Access 'update' link
 * Click option 2 : 'Request API credentials'
 * Choose 'Request API signature', and click 'Agree and Submit'
 * Enter these details into your mysite/_config.php file with either the set_config_details or set_test_config_details functions

Add the following to your mysite/_config.php, and populate values with the appropriate information from your Paypal account:

```php
  if(Director::isDev() || Director::isTest()){
		PayPalExpressCheckoutPayment::set_test_config("TestPaypalAPIUsername","TestPaypalAPIPassword","TestPaypalSignature");
	}elseif(Director::isLive()){
		PayPalExpressCheckoutPayment::set_live_config("PaypalAPIUsername","PaypalAPIPassword","PaypalSignature");
	}
```

## Troubleshooting

 * If you get the message "PayPal could not be contacted" , check your debug logs. This will contain debugging
information. Note that you need to enable debug logging. You can add debug file logging by adding the following
to your _config.php file:

```php
	SS_Log::add_writer(new SS_LogFileWriter(Director::baseFolder().'/errors.log'), SS_Log::ERR);
```

 * you must be logged into sandbox to process a test payment.
