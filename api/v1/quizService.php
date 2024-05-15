<?php

require "vendor/autoload.php";
require "dbHandler.php";

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;


class QuizHandler
{
  private $db;
  private $dbHandler;
  public function __construct($db)
  {
    $this->db = $db;
    $this->dbHandler = new dbHandler($db);
  }

  public function generateQrCode($participationId)
  {
    $randomCode = $this->dbHandler->getCode($participationId);
    $qrCodeUrl = 'https://node' . PERSONAL_CODE . '.webte.fei.stuba.sk/survey?code=' . $randomCode;

    $qrCode = QrCode::create($qrCodeUrl)
                    ->setForegroundColor(new Color(66, 135, 245));
    $writer = new PngWriter;
    $result = $writer->write($qrCode); // Write the QR code to a PNG image

    // Encode the image data to base64
    $imageData = base64_encode($result->getString());

    $responseData = [
      'image' => 'data:image/png;base64,' . $imageData, // Include the base64 encoded image data in the response
      'qr_code' => $qrCodeUrl, // Include the generated QR code URL in the response
      'code' => $randomCode['code']
    ];

    return $responseData;
  }

  function insertQuizData($quizData)
  {
    $quizTitle = isset($quizData['title']) ? $quizData['title'] : "Quiz Title";
    $quizDescription = isset($quizData['description']) ? $quizData['description'] : "Quiz Description";
    $quizUser = isset($quizData['user_id']) ? $quizData['user_id'] : "Quiz Title";
    $quizSubject = isset($quizData['subject']) ? $quizData['subject'] : "Quiz subject";

    $subjectId = $this->dbHandler->verifyExistenceAndCreateSubject($quizSubject);

    $quizId = $this->dbHandler->insertQuiz($quizUser, $quizTitle, $quizDescription, $subjectId);

    foreach ($quizData['questions'] as $questionData) {
      $questionText = $questionData['question'];
      $isOpenQuestion = $questionData['isOpenAnswer'] == "true" ? 1 : 0; 

      $questionId = $this->dbHandler->insertQuestion($quizId, $questionText, $isOpenQuestion);

      foreach ($questionData['options'] as $optionData) {
        $optionText = $optionData['value']; 
        $isCorrect = $optionData['isCorrect']  == "true" ? 1 : 0;

        $this->dbHandler->insertOption($questionId, $optionText, $isCorrect);
      }
    }

    $responseData = [
      'quiz_id' => $quizId
    ];

    return $responseData;
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

  function processVote($requestData, $dbHandler)
{
    if (!isset($requestData['participation_id'])) {
        return [
            'error' => 'Participation ID is missing in the request data'
        ];
    }

    $participationId = $requestData['participation_id'];

    foreach ($requestData['questions'] as $question) {
        $questionId = $question['question_id'];
        $answers = $question['answers'];

        foreach ($answers as $answer) {
          $answerText = $answer['answer_text'];
          $dbHandler->sendVote($questionId, $participationId, $answerText);
      }
    }
  }

}
