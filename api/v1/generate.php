<?php

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Generate a random code consisting of letters and numbers with length 5
$randomCode = generateRandomCode(5);

// Append the random code to the base URL
$qrCodeUrl = 'https://node25.webte.fei.stuba.sk/survey?code=' . $randomCode;

require "vendor/autoload.php";

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$qrCode = QrCode::create($qrCodeUrl); // Create the QR code with the generated URL

$writer = new PngWriter;
$result = $writer->write($qrCode); // Write the QR code to a PNG image

// Encode the image data to base64
$imageData = base64_encode($result->getString());

// Prepare the response data
$responseData = [
  'image' => 'data:image/png;base64,' . $imageData, // Include the base64 encoded image data in the response
  'qr_code' => $qrCodeUrl, // Include the generated QR code URL in the response
];

// Set the Content-Type header to JSON
header('Content-Type: application/json');

// Output the response JSON
echo json_encode($responseData);

// Function to generate a random code consisting of letters and numbers
function generateRandomCode($length)
{
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $randomCode = '';
  for ($i = 0; $i < $length; $i++) {
    $randomCode .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $randomCode;
}
