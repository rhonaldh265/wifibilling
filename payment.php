<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['transactions'])){
    $_SESSION['transactions'] = [];
}

// Sandbox credentials
$BusinessShortCode = "174379";
$Passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$ConsumerKey = "XeVkJwRzSl9fUfxhdgCz7aBND1TIHokCRPqSIGqIKz0q4pxe";
$ConsumerSecret = "hLNzHL8CqKQarLLf1JL14XAVAc7CjDcHncIlpZADcXuFyKTjUP0QwZdrQfO9XGMZ";

if($_SERVER['REQUEST_METHOD']==='POST'){
    // Check if it's a free access request
    if(isset($_POST['type']) && $_POST['type'] == 'free'){
        echo json_encode(['success'=>true, 'message'=>'Free access granted for 2 minutes']);
        exit;
    }
    
    $Amount = $_POST['amount'];
    $PhoneNumber = $_POST['phone'];
    $CustomerName = $_POST['name'];
    $AccountReference = "Home network";
    $TransactionDesc = "WiFi Package Purchase";
    $Timestamp = date('YmdHis');

    $Password = base64_encode($BusinessShortCode.$Passkey.$Timestamp);

    // OAuth token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic ".base64_encode($ConsumerKey.":".$ConsumerSecret)]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($result);
    
    if(!isset($tokenData->access_token)){
        echo json_encode(['ResponseCode'=>1, 'ResponseDescription'=>'OAuth failed', 'error'=>$result]); 
        exit;
    }
    $token = $tokenData->access_token;

    // STK Push
    $stkHeader = ['Content-Type:application/json','Authorization:Bearer '.$token];
    $curl_post_data = [
        'BusinessShortCode'=>$BusinessShortCode,
        'Password'=>$Password,
        'Timestamp'=>$Timestamp,
        'TransactionType'=>'CustomerPayBillOnline',
        'Amount'=>$Amount,
        'PartyA'=>$PhoneNumber,
        'PartyB'=>$BusinessShortCode,
        'PhoneNumber'=>$PhoneNumber,
        'CallBackURL'=>'https://yourdomain.com/callback.php',
        'AccountReference'=>$AccountReference,
        'TransactionDesc'=>$TransactionDesc
    ];

    $data_string = json_encode($curl_post_data);
    $curl = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
    curl_setopt($curl, CURLOPT_HTTPHEADER, $stkHeader);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $curl_response = curl_exec($curl);
    curl_close($curl);

    // Store checkout ID in session for polling
    $response = json_decode($curl_response, true);
    
    // Store the checkout request ID in session for later verification
    if(isset($response['CheckoutRequestID'])){
        $_SESSION['checkout_'.$response['CheckoutRequestID']] = [
            'amount' => $Amount,
            'phone' => $PhoneNumber,
            'name' => $CustomerName,
            'timestamp' => $Timestamp,
            'status' => 'pending'
        ];
    }
    
    echo $curl_response;

}elseif($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['checkoutID'])){
    // Handle payment confirmation
    $checkoutID = $_GET['checkoutID'];
    
    // In sandbox mode, simulate successful payment after 5 seconds
    // For production, you would query the actual M-Pesa API
    
    // Simulate checking with M-Pesa API
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode.$Passkey.$Timestamp);
    
    // Get access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic ".base64_encode($ConsumerKey.":".$ConsumerSecret)]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($result);
    
    if(isset($tokenData->access_token)){
        $token = $tokenData->access_token;
        
        // Query transaction status
        $query_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
        $query_data = [
            'BusinessShortCode' => $BusinessShortCode,
            'Password' => $Password,
            'Timestamp' => $Timestamp,
            'CheckoutRequestID' => $checkoutID
        ];
        
        $data_string = json_encode($query_data);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $query_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $curl_response = curl_exec($curl);
        curl_close($curl);
        
        $response = json_decode($curl_response, true);
        
        // Check if payment was successful
        if(isset($response['ResultCode']) && $response['ResultCode'] == 0){
            // Payment successful
            if(isset($_SESSION['checkout_'.$checkoutID])){
                // Add to transactions
                $transaction = $_SESSION['checkout_'.$checkoutID];
                $_SESSION['transactions'][] = [
                    'name' => $transaction['name'],
                    'phone' => $transaction['phone'],
                    'amount' => $transaction['amount'],
                    'time' => date('Y-m-d H:i:s')
                ];
                
                // Mark as completed
                $_SESSION['checkout_'.$checkoutID]['status'] = 'completed';
            }
            
            echo json_encode([
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.'
            ]);
        } else {
            // Payment failed or still pending
            $resultCode = isset($response['ResultCode']) ? $response['ResultCode'] : '1032';
            $resultDesc = isset($response['ResultDesc']) ? $response['ResultDesc'] : 'Request cancelled by user';
            
            echo json_encode([
                'ResultCode' => $resultCode,
                'ResultDesc' => $resultDesc
            ]);
        }
    } else {
        // If token fails, simulate success for testing
        // FOR TESTING ONLY - Remove this in production
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Payment confirmed (test mode)'
        ]);
    }
    
}else{
    echo json_encode(['error'=>'Invalid request']);
}
?>
