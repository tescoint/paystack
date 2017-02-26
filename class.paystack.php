<?php
/*
 * PAYSTACK Payment Gateway Integration Module for HostBill
 * Author - Tes Sal
 * Email - tescointsite@gmail.com
 *  
 * http://codeignito.com
 */
class Paystack extends PaymentModule {
    
    /*
     * const NAME
     * Note: This needs to reflect class name - case sensitive.
     */
    const NAME = 'Paystack';

    /*
     * const VER
     * Insert your module version here
     */
    const VER ='1.0';
    
    /*
     * protected $modname
     * AKA. "Nice name" - you can additionally add this variable - its contents will be displayed as module name after activation
     */
    protected $modname = 'PayStack Hostbill';
    
    /*
     * protected $description
     * If you want, you can add description to module, so its potential users will know what its for.
     */
    protected $description='PayStack Payment Gateway Module.';

    /*
     * protected $filename
     * This needs to reflect actual filename of module - case sensitive.
     */
    protected $filename='class.paystack.php';
    
    /*
     * protected $supportedCurrencies
     * List of currencies supported by PayStack.
     */
    protected $supportedCurrencies = array('NGN');
    
    /*
     * protected $configuration
     * Configuration Array
     */
    protected $configuration = array(
        'test_secret_key' =>array(
            'value'=>'',
            'type'=>'input'
        ),
        'test_public_key' =>array(
            'value'=>'',
            'type'=>'input'
        ),
        'live_secret_key' =>array(
            'value'=>'',
            'type'=>'input'
        ),
        'live_public_key' =>array(
            'value'=>'',
            'type'=>'input'
        ),
        'mode'=>array(
            'value'=>'test',
            'type'=>'input'
        ),
        'success_message'=>array(
            'value'=>'Thank you! Transaction was successful! We have received your payment.',
            'type'=>'input'
        ),
        'failure_message'=>array(
            'value'=>'Transaction Failed!',
            'type'=>'input'
        )
        
    );
    
    //language array - each element key should start with module NAME
    protected $lang=array(
        'english'=>array(
            'Paystacktest_secret_key'=>'Test Secret Key',
            'Paystacktest_public_key'=>'Test Public Key',
            'Paystacklive_secret_key'=>'Live Secret Key',
            'Paystacklive_public_key'=>'Live Public Key',
            'Paystackmode'=>'Mode',
            'Paystacksuccess_message'=>'Success Message',
            'Paystackfailure_message'=>'Failure Message'
        )
    );

    //prepare  payment hidden form fields
    public function drawForm($autosubmit = true) {
        $gatewayaccountid = $this->configuration['live_public_key']['value']; // Your Merchant ID
        $gatewaytestmode = $this->configuration['mode']['value']; // Mode
        if($gatewaytestmode == 'test'){
            $gatewayaccountid = $this->configuration['test_public_key']['value'];
        }
        # Invoice Variables
        $invoiceid = $this->invoice_id;
        $description = $this->subject;
        $amount = $this->amount * 100;


        # Client Variables
        $name = $this->client['firstname'] . $this->client['lastname'];
        $email = $this->client['email'];
        $address1 = $this->client['address1'];
        $city = $this->client['city'];
        $state = $this->client['state'];
        $postcode = $this->client['postcode'];
        $country = $this->client['country'];
        $phone = $this->client['phonenumber'];

        $callBackUrl = $this->callback_url . "&DR={DR}&invoice_id=$invoiceid";

        // $hash = $secret_key . "|" . $gatewayaccountid . "|" . $amount . "|" . $invoiceid . "|" . $callBackUrl . "|" . $gatewaytestmode;

        // $secure_hash = md5($hash);
        $reference = mt_rand(100000,999999);
        $reference = $reference.date("YmdHis"); 
        # System Variables
        $companyname = 'PAYSTACK';
        $code = '
        <form action="'.$callBackUrl.'" method="POST" name="frmTransaction" id="frmTransaction" onSubmit="return validate()" >
          <script
            src="https://js.paystack.co/v1/inline.js" 
            data-key="'.$gatewayaccountid.'"
            data-email="'.$email.'"
            data-amount="'.$amount.'"
            data-ref="'.$reference.'"
          >
          </script>
        </form>
        ';
        if ($autosubmit) {
            $code .="<script language=\"javascript\">
                setTimeout ( \"autoForward()\" , 5000 );
                function autoForward() {
                    document.forms.payform.submit()
                }
                </script>
                ";
        }

        return $code;
    }

    public function callback() {

   // die(print_r($_POST));
    if(isset($_POST['paystack-trxref'])){
        //PaymentReference
    $reference = $_POST['paystack-trxref'];
    $invoiceid = $_GET['invoice_id'];

    //get the full transaction details as an json from voguepay
    if($this->configuration['mode']['value'] == 'test'){
        echo $secret_key = $this->configuration['test_secret_key']['value'];
    }else{
        $secret_key = $this->configuration['live_secret_key']['value'];
    }

    $transaction = array();
        //The parameter after verify/ is the transaction reference to be verified
        $url = "https://api.paystack.co/transaction/verify/$reference";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
          $ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $secret_key "]
        );
        $request = curl_exec($ch);
        curl_close($ch);

         //create new array to store our transaction detail
        if ($request) {
          $transaction = json_decode($request, true);
        }
        //Use the $result array
        print_r($transaction);
    // if($transaction['merchant_id'] != $merchant_id)die('Invalid merchant');
    if($transaction['data']['amount'] == 0)die('Invalid total');
    // if($transaction['status'] != 'Approved')die('Failed transaction');    
    /*You can do anything you want now with the transaction details or the merchant reference.
    You should query your database with the merchant reference and fetch the records you saved for this transaction.
    Then you should compare the $transaction['total'] with the total from your database.*/
    if($transaction['data']['status'] == 'success'){
        if($this->_transactionExists( $reference) == false ) {        
        
            $this->logActivity(array(
                'output' => $transaction,
                'result' => PaymentModule::PAYMENT_SUCCESS
            ));
  
            // $response['Fee'] = round(($response['Amount'] * $this->configuration['tdr']['value']), 2);  
            
            $this->addTransaction(array(
                'client_id' => $this->client['id'],
                'invoice_id' => $invoiceid,
                'description' => "Payment For Invoice ".$invoiceid." with reference $reference",
                'in' => $transaction['data']['amount'] / 100, 
                'fee' => $transaction['data']['amount'] / 100,
                'transaction_id' => $reference
            ));
            
            }
            
            $this->addInfo($this->configuration['success_message']['value']);
            Utilities::redirect('?cmd=clientarea');

    }else{
        $this->logActivity(array(
                'output' => $transaction,
                'result' => PaymentModule::PAYMENT_FAILURE
            ));

            $this->addInfo($this->configuration['failure_message']['value']);
            Utilities::redirect('?cmd=clientarea');
    }
}else{
         $this->logActivity(array(
                'output' => "Nothing was returned from PayStack..Error Error Error",
                'result' => PaymentModule::PAYMENT_FAILURE
            ));

            $this->addInfo($this->configuration['failure_message']['value']);
            Utilities::redirect('?cmd=clientarea');
}               


        
    }

}

?>
