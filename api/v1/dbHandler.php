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


  // QUIZ
  function insertQuiz($quizUser, $quizTitle, $quizDescription, $randomCode, $subjectId)
  {
    $stmt = $this->db->prepare("INSERT INTO quizzes (user_id, title, description, code, subject_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $quizUser, $quizTitle, $quizDescription, $randomCode, $subjectId);
    $stmt->execute();
    $quizId = $stmt->insert_id;
    $stmt->close();
    return $quizId;
  }

  function updateQuizTitle($quizId, $newTitle)
  {
    $stmt = $this->db->prepare("UPDATE quizzes SET title = ? WHERE quiz_id = ?");
    $stmt->bind_param("si", $newTitle, $quizId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
  }

  function deleteQuiz($quizId)
  {
    // Check if the quiz exists
    if (!$this->quizExists($quizId)) {
      return false; // Quiz does not exist
    }

    // First, delete associated questions
    $stmt = $this->db->prepare("DELETE FROM questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $stmt->close();

    // Next, delete associated options
    $stmt = $this->db->prepare("DELETE FROM options WHERE question_id NOT IN (SELECT question_id FROM questions)");
    $stmt->execute();
    $stmt->close();

    // Then, delete the quiz itself
    $stmt = $this->db->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $stmt->close();

    return true;
  }

  function quizExists($quizId)
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) AS count FROM quizzes WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($result['count'] > 0);
  }

  function getQuizById($quizId, $userId)
  {
    global $db;

    $stmt = $db->prepare("SELECT 
                            quizzes.quiz_id,
                            quizzes.user_id,
                            quizzes.title AS quiz_title,
                            quizzes.description AS quiz_description,
                            quizzes.created_at AS quiz_created_at,
                            quizzes.code AS quiz_code, 
                            questions.question_id,
                            questions.question_text,
                            questions.open_question,
                            CASE
                                WHEN questions.open_question = 1 THEN NULL
                                ELSE JSON_ARRAYAGG(
                                    JSON_OBJECT(
                                        'option_id', o.option_id,
                                        'option_text', o.option_text,
                                        'is_correct', o.is_correct
                                    )
                                )
                            END AS options,
                            s.name AS subject_name
                        FROM 
                            quizzes 
                        LEFT JOIN 
                            questions ON questions.quiz_id = quizzes.quiz_id 
                        LEFT JOIN 
                            options o ON o.question_id = questions.question_id 
                        JOIN 
                            subjects s ON s.subject_id = quizzes.subject_id 
                        WHERE 
                            quizzes.quiz_id = ? AND quizzes.user_id = ? 
                        GROUP BY 
                            quizzes.quiz_id,
                            quizzes.user_id,
                            quizzes.title,
                            quizzes.description,
                            quizzes.created_at,
                            quizzes.code,
                            questions.question_id,
                            questions.question_text,
                            questions.open_question,
                            s.name;");
    $stmt->bind_param("ii", $quizId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $quizData = $result->fetch_all(MYSQLI_ASSOC);

    // Organize the data into the desired structure
    $formattedQuizData = [
      'quiz_id' => $quizData[0]['quiz_id'],
      'user_id' => $quizData[0]['user_id'],
      'quiz_title' => $quizData[0]['quiz_title'],
      'quiz_description' => $quizData[0]['quiz_description'],
      'quiz_created_at' => $quizData[0]['quiz_created_at'],
      'quiz_code' => $quizData[0]['quiz_code'],
      'subject' => $quizData[0]['subject_name'],
      'questions' => []
    ];

    foreach ($quizData as $row) {
      $questionKey = 'question_' . $row['question_id'];
      $options = json_decode($row['options'], true);

      if ($options['option_id'] == null) {
        $options = [];
      }

      $formattedQuizData['questions'][$questionKey] = [
        'question_text' => $row['question_text'],
        'open_question' => $row['open_question'],
        'options' => $options
      ];
    }
    return $quizData[0]['quiz_id'] != null ? $formattedQuizData : null;
  }

  function getListOfQuizzes($userId)
  {
    $stmt = $this->db->prepare("SELECT q.quiz_id, q.title, q.description, q.created_at, q.code, s.name  
                                FROM quizzes q
                                JOIN subjects s on s.subject_id = q.subject_id 
                                WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $quizzes = array();
    while ($row = $result->fetch_assoc()) {
      $quizzes[] = $row;
    }

    return ['data' => $quizzes];
  }

  function checkIfQuizCodeExists($randomCode)
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM quizzes WHERE code = ?");
    $stmt->bind_param("s", $randomCode);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If count > 0, code exists
    return $result['count'] > 0;
  }


  // VOTING
  function startVote($quizId)
  {
    $stmt = $this->db->prepare("INSERT INTO quiz_participation (quiz_id, start_time) VALUES (?, NOW())");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $quizParticipationId = $stmt->insert_id;
    $stmt->close();
    return $quizParticipationId;
  }


  // QUESTION
  function insertQuestion($quizId, $questionText, $isOpenQuestion)
  {
    $stmt = $this->db->prepare("INSERT INTO questions (quiz_id, question_text, open_question) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $quizId, $questionText, $isOpenQuestion);
    $stmt->execute();
    $questionId = $stmt->insert_id;
    $stmt->close();
    return $questionId;
  }

  function deleteQuestion($quizId, $questionId)
  {
    $stmt = $this->db->prepare("DELETE FROM questions WHERE quiz_id = ? AND question_id = ?");
    $stmt->bind_param("ii", $quizId, $questionId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    return $affectedRows > 0; // Return true if rows were affected (deletion successful), false otherwise
  }

  // QUESTION - OPTION
  function insertOption($questionId, $optionText, $isCorrect,)
  {
    $stmt = $this->db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $questionId, $optionText, $isCorrect);
    $stmt->execute();
    $stmt->close();
  }


  // SUBJECT
  function verifyExistenceAndCreateSubject($subjectName)
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
}
