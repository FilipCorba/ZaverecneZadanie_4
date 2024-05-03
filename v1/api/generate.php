<?php 

require "vendor/autoload.php";

use Endroid\QrCode\QrCode;
Use Endroid\Qrcode\Writer\PngWriter;

$text = $_POST['text'];

$qr_code = QrCode::create($text);

$writer = new PngWriter();

$result = $writer->write($qr_code);

echo $result->getString();