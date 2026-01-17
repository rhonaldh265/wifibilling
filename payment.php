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
    $result = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($result);
    if(!isset($tokenData->access_token)){
        echo json_encode(['error'=>"OAuth failed","details"=>$result]); exit;
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
    $curl_response = curl_exec($curl);
    curl_close($curl);

    echo $curl_response;

}elseif($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['checkoutID'])){
    $checkoutID = $_GET['checkoutID'];
    // Simulate payment success (sandbox/demo)
    sleep(2);
    echo json_encode(['ResultCode'=>0]);
}else{
    echo json_encode(['error'=>'Invalid request']);
}
?>

