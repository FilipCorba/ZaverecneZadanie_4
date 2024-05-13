<?php

require "config.php";
require "vendor/autoload.php";

class dbHandler
{
  private $db;

  public function __construct($db)
  {
    $this->db = $db;
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

  function getQuizById($quizId)
{
  global $db;
  $stmt = $db->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
  $stmt->bind_param("i", $quizId);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc();
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

}