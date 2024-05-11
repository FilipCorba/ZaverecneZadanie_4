<?php


require "vendor/autoload.php";

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;


class QR
{
  private $db;

  public function __construct($db)
  {
    $this->db = $db;
  }



  public function generateQrCode($quizData)
  {
    // Insert quiz data into the database
    $quizId = $this->insertQuizData($quizData);

    do {
      // Generate a random code consisting of letters and numbers with length 5
      $randomCode = $this->generateRandomCode(5);

      // Check if the random code already exists in the database
      $codeExists = $this->checkCodeExists($randomCode);
    } while ($codeExists);

    // Append the random code to the base URL
    $qrCodeUrl = 'https://node25.webte.fei.stuba.sk/survey?code=' . $randomCode;

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

    $this->insertQrCodeData($qrCodeUrl, $randomCode, $quizId);

    return $responseData;
  }


  private function checkCodeExists($randomCode)
  {
    // Prepare and execute query to check if the random code exists in the database
    $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM qr_codes WHERE unique_code = ?");
    $stmt->bind_param("s", $randomCode);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If count > 0, code exists
    return $result['count'] > 0;
  }



  public function generateRandomCode($length)
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomCode = ''; // Initialize random code
    $charactersLength = strlen($characters);

    // Append random characters to the code
    for ($i = 0; $i < $length; $i++) {
      $randomCode .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomCode;
  }

  private function insertQrCodeData($qrCodeUrl, $randomCode, $quizId)
  {
    // Prepare and execute query to insert data into qr_codes table
    $stmt = $this->db->prepare("INSERT INTO qr_codes (user_id, qr_code, unique_code, quiz_id) VALUES (?, ?, ?, ?)");
    // Assuming user_id is always 1 (modify as per your actual requirement)
    $userId = 1;
    $stmt->bind_param("isss", $userId, $qrCodeUrl, $randomCode, $quizId);
    $stmt->execute();
    $stmt->close();
  }


  private function insertQuizData($quizData)
  {
    $quizTitle = isset($quizData['title']) ? $quizData['title'] : "Quiz Title";
    $quizDescription = isset($quizData['description']) ? $quizData['description'] : "Quiz Description";

    $stmt = $this->db->prepare("INSERT INTO quizzes (title, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $quizTitle, $quizDescription);
    $stmt->execute();
    $quizId = $stmt->insert_id;
    $stmt->close();


    foreach ($quizData['questions'] as $questionData) {
      $questionText = $questionData['question'];

      // Insert question into the 'questions' table
      $stmt = $this->db->prepare("INSERT INTO questions (quiz_id, question_text, open_question) VALUES (?, ?, ?)");
      $stmt->bind_param("iss", $quizId, $questionText, $isOpenQuestion);
      $isOpenQuestion = $questionData['isOpenAnswer'] ? 1 : 0; // Convert boolean to integer
      $stmt->execute();
      $questionId = $stmt->insert_id;
      $stmt->close();

      // Insert answers for the question into the 'answers' table
      foreach ($questionData['answers'] as $answerData) {
        $answerText = $answerData['label']; // Assuming label is the answer text
        $isCorrect = $answerData['isCorrect'] ? 1 : 0; // Convert boolean to integer

        $stmt = $this->db->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $questionId, $answerText, $isCorrect);
        $stmt->execute();
        $stmt->close();
      }
    }

    return $quizId;
  }



}

?>