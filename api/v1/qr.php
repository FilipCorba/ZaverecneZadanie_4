<?php

require "config.php";
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
    do {
      $randomCode = $this->generateRandomCode(5);
      $codeExists = $this->checkCodeExists($randomCode);
    } while ($codeExists);

    $qrCodeUrl = 'https://node' . PERSONAL_CODE . '.webte.fei.stuba.sk/survey?code=' . $randomCode;

    $qrCode = QrCode::create($qrCodeUrl); // Create the QR code with the generated URL
    $writer = new PngWriter;
    $result = $writer->write($qrCode); // Write the QR code to a PNG image

    // Encode the image data to base64
    $imageData = base64_encode($result->getString());


    $responseData = [
      'image' => 'data:image/png;base64,' . $imageData, // Include the base64 encoded image data in the response
      'qr_code' => $qrCodeUrl, // Include the generated QR code URL in the response
    ];

    $this->insertQuizData($quizData, $randomCode);

    return $responseData;
  }

  private function insertQuizData($quizData, $randomCode)
  {
    $quizTitle = isset($quizData['title']) ? $quizData['title'] : "Quiz Title";
    $quizDescription = isset($quizData['description']) ? $quizData['description'] : "Quiz Description";
    $quizUser = isset($quizData['user']) ? $quizData['user'] : "Quiz Title";
    $quizSubject = isset($quizData['subject']) ? $quizData['subject'] : "Quiz subject";

    $subjectId = $this->verifyExistenceAndCreateSubject($quizSubject);

    $quizId = $this->insertQuiz($quizUser, $quizTitle, $quizDescription, $randomCode, $subjectId);

    foreach ($quizData['questions'] as $questionData) {
      $questionText = $questionData['question'];
      $isOpenQuestion = $questionData['isOpenAnswer'] ? 1 : 0; // Convert boolean to integer

      $questionId = $this->insertQuestion($quizId, $questionText, $isOpenQuestion);

      // Insert options for the question into the 'options' table
      foreach ($questionData['options'] as $optionData) {
        $optionText = $optionData['label']; // Assuming label is the option text
        $isCorrect = $optionData['isCorrect'] ? 1 : 0; // Convert boolean to integer

        $this->insertOption($questionId, $optionText, $isCorrect);
      }
    }

    return $quizId;
  }

  private function insertQuiz($quizUser, $quizTitle, $quizDescription, $randomCode, $subjectId)
  {
    $stmt = $this->db->prepare("INSERT INTO quizzes (user_id, title, description, code, subject_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $quizUser, $quizTitle, $quizDescription, $randomCode, $subjectId);
    $stmt->execute();
    $quizId = $stmt->insert_id;
    $stmt->close();
    return $quizId;
  }

  private function insertQuestion($quizId, $questionText, $isOpenQuestion)
  {
    $stmt = $this->db->prepare("INSERT INTO questions (quiz_id, question_text, open_question) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $quizId, $questionText, $isOpenQuestion);
    $stmt->execute();
    $questionId = $stmt->insert_id;
    $stmt->close();
    return $questionId;
  }

  private function insertOption($questionId, $optionText, $isCorrect)
  {
    $stmt = $this->db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $questionId, $optionText, $isCorrect);
    $stmt->execute();
    $stmt->close();
  }

  private function verifyExistenceAndCreateSubject($subjectName)
  {
    // Check if the subject already exists in the database
    $stmt = $this->db->prepare("SELECT subject_id FROM subjects WHERE name = ?");
    $stmt->bind_param("s", strtolower($subjectName));
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result !== null) {
      return $result['subject_id'];
    }

    // If the subject does not exist, insert it into the database
    $stmt = $this->db->prepare("INSERT INTO subjects (name) VALUES (?)");
    $stmt->bind_param("s", strtolower($subjectName));
    $stmt->execute();
    $subjectId = $stmt->insert_id;
    $stmt->close();

    return $subjectId;
  }

  private function checkCodeExists($randomCode)
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM quizzes WHERE code = ?");
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
}
