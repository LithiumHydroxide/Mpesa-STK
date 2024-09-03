<?php
// Initialize the variables
$consumer_key = 'mWBnOJ1VqAvJbmtsoRARVvjYwB3VwZdv';
$consumer_secret = 'vKJWepYy0bLeHEDn';
$Business_Code = '174379';
$Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$Type_of_Transaction = 'CustomerPayBillOnline';
$Token_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$phone_number = $_POST['phone_number'];
$OnlinePayment = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$total_amount = $_POST['amount'];
$CallBackURL = 'https://4375-197-136-183-22.ngrok-free.app';
$Time_Stamp = date("Ymdhis");
$password = base64_encode($Business_Code . $Passkey . $Time_Stamp);

// Generate authentication token
$curl_Tranfer = curl_init();
curl_setopt($curl_Tranfer, CURLOPT_URL, $Token_URL);
$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
curl_setopt($curl_Tranfer, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($curl_Tranfer, CURLOPT_HEADER, false);
curl_setopt($curl_Tranfer, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl_Tranfer, CURLOPT_SSL_VERIFYPEER, false);
$curl_Tranfer_response = curl_exec($curl_Tranfer);

// Check for errors in token request
if ($curl_Tranfer_response === false) {
    die('Error getting access token: ' . curl_error($curl_Tranfer));
}

$token_data = json_decode($curl_Tranfer_response);

// Check for API errors
if (isset($token_data->error)) {
    die('Token request error: ' . $token_data->error_description);
}

// Check if the access token is present in the response
if (!isset($token_data->access_token)) {
    die('Error: Access token not found in the response');
}

$token = $token_data->access_token;

$curl_Tranfer2 = curl_init();
curl_setopt($curl_Tranfer2, CURLOPT_URL, $OnlinePayment);
curl_setopt($curl_Tranfer2, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $token));

$curl_Tranfer2_post_data = [
    'BusinessShortCode' => $Business_Code,
    'Password' => $password,
    'Timestamp' =>$Time_Stamp,
    'TransactionType' =>$Type_of_Transaction,
    'Amount' => $total_amount,
    'PartyA' => $phone_number,
    'PartyB' => $Business_Code,
    'PhoneNumber' => $phone_number,
    'CallBackURL' => $CallBackURL,
    'AccountReference' => 'NTMS',
    'TransactionDesc' => 'Test',
];

$data2_string = json_encode($curl_Tranfer2_post_data);

curl_setopt($curl_Tranfer2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_Tranfer2, CURLOPT_POST, true);
curl_setopt($curl_Tranfer2, CURLOPT_POSTFIELDS, $data2_string);
curl_setopt($curl_Tranfer2, CURLOPT_HEADER, false);
curl_setopt($curl_Tranfer2, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($curl_Tranfer2, CURLOPT_SSL_VERIFYHOST, 0);
$curl_Tranfer2_response = json_decode(curl_exec($curl_Tranfer2));

// Check if payment was successful before storing the information
if (isset($curl_Tranfer2_response->MerchantRequestID) && isset($curl_Tranfer2_response->CheckoutRequestID)) {
    // Payment was successful, store the payment information in the database
    $payment_status = $curl_Tranfer2_response->ResponseDescription;

    // Connect to your database
    $conn = mysqli_connect('localhost', 'root', '', 'tms');

    // Check connection
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    // Prepare and bind the SQL statement
    $stmt = $conn->prepare("INSERT INTO payments (phone_number, amount, status) VALUES (?, ?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sds", $phone_number, $total_amount, $payment_status);

    // Execute the statement
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    // Close statement and connection
    $stmt->close();
    $conn->close();

    // Output response
    echo json_encode($curl_Tranfer2_response, JSON_PRETTY_PRINT);
} else {
    // Payment failed, output response
    echo json_encode($curl_Tranfer2_response, JSON_PRETTY_PRINT);
}
?>