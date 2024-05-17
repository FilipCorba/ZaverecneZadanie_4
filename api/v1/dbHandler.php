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
  function insertQuiz($quizUser, $quizTitle, $quizDescription, $subjectId)
  {
    $stmt = $this->db->prepare("INSERT INTO quizzes (user_id, title, description, subject_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $quizUser, $quizTitle, $quizDescription, $subjectId);
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

  function deleteQuiz($quizId, $userId)
  {
    // Check if the quiz exists
    if (!$this->quizExists($quizId, $userId)) {
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

  function quizExists($quizId, $userId)
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) AS count FROM quizzes WHERE quiz_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $quizId, $userId);
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
      'subject' => $quizData[0]['subject_name'],
      'questions' => []
    ];

    foreach ($quizData as $row) {
      if ($row['question_id'] !== null && $row['question_text'] !== null && $row['open_question'] !== null) {
        $questionKey = 'question_' . $row['question_id'];
        $options = json_decode($row['options'], true);

        $formattedQuizData['questions'][$questionKey] = [
          'question_text' => $row['question_text'],
          'open_question' => $row['open_question'],
          'options' => $options
        ];
      }
    }

    return $quizData[0]['quiz_id'] != null ? $formattedQuizData : null;
  }

  function getListOfQuizzes($userId)
  {
    $stmt = $this->db->prepare("SELECT q.quiz_id, 
                                    q.title, 
                                    q.description, 
                                    q.created_at, 
                                    s.name,
                                    COUNT(questions.question_id) AS number_of_questions,
                                    CASE 
                                        WHEN COUNT(qp.participation_id) > 0 THEN true
                                        ELSE false
                                    END AS is_active 
                                FROM quizzes q
                                JOIN subjects s ON s.subject_id = q.subject_id 
                                LEFT JOIN quiz_participation qp ON qp.quiz_id = q.quiz_id 
                                LEFT JOIN questions ON questions.quiz_id = q.quiz_id 
                                WHERE user_id = ? 
                                GROUP BY q.quiz_id, q.title, q.description, q.created_at, s.name;");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $quizzes = array();
    while ($row = $result->fetch_assoc()) {
      $quizzes[] = $row;
    }

    return ['data' => $quizzes];
  }

  function getListOfSubjects($userId)
  {
    $stmt = $this->db->prepare("SELECT DISTINCT s.name  
                                FROM quizzes q
                                JOIN subjects s on s.subject_id = q.subject_id 
                                WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $subjects = array();
    while ($row = $result->fetch_assoc()) {
      $subjects[] = $row;
    }

    // Encode the array as JSON
    return json_encode($subjects);
  }


  function checkIfQuizCodeExists($randomCode)
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM quiz_participation WHERE code = ?");
    $stmt->bind_param("s", $randomCode);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If count > 0, code exists
    return $result['count'] > 0;
  }


  // VOTING
  function getCode($participationId)
  {
    $stmt = $this->db->prepare("SELECT code FROM quiz_participation WHERE participation_id = ?");
    $stmt->bind_param("i", $participationId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result;
  }

  function getQuizId($code)
  {
    $stmt = $this->db->prepare("SELECT DISTINCT quiz_id FROM quiz_participation WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        $quizId = intval($result['quiz_id']);
        return $quizId;
    } else {
        return 0; 
    }
  }

  function getQuestions($quizId)
  {
    $stmt = $this->db->prepare("SELECT question_id, question_text FROM questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $questions = [];
    if ($result) { 
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
    } 
    return $questions;
  }

  function getSurvey($questions)
  {
    $optionsJson = []; 

    foreach ($questions as $question) {
        $questionId = $question['question_id'];
        $questionText = $question['question_text'];

        $stmt = $this->db->prepare("SELECT option_text FROM options WHERE question_id = ?");
        $stmt->bind_param("i", $questionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $questionOptions = [];

        while ($row = $result->fetch_assoc()) {
            $questionOptions[] = $row['option_text'];
        }
        $quizType = "options";
        if (count($questionOptions) == 0)
        {
          $quizType = "open";
        }

        $questionData = [
            'quiz_type' => $quizType,
            'question' => $questionText,
            'options' => $questionOptions
        ];

        $optionsJson[] = $questionData;
    }

    return json_encode($optionsJson);
  }

  function startVote($quizId, $code)
  {
    $stmt = $this->db->prepare("INSERT INTO quiz_participation (quiz_id, start_time, code) VALUES (?, NOW(), ?)");
    $stmt->bind_param("is", $quizId, $code);
    $stmt->execute();
    $quizParticipationId = $stmt->insert_id;
    $stmt->close();
    return $quizParticipationId;
  }

  // TO DO - what if questions cannot be correct? like what opinion do you have on...?, what is attempted_questions
  // in what format should total_time_taken be
  function endVote($note, $participationId)
  {
    $stmt = $this->db->prepare("UPDATE quiz_participation 
              SET end_time = NOW(),
                  total_time_taken = SEC_TO_TIME(TIMESTAMPDIFF(MINUTE, start_time, NOW())),
                  note = ?
              WHERE participation_id = ? AND end_time IS NULL"); // Changed the condition to check for NULL
    $stmt->bind_param("si", $note, $participationId);
    $stmt->execute();
    $rowsUpdated = $stmt->affected_rows; // Get the number of updated rows
    $stmt->close();
    return $rowsUpdated == 1;
  }

  function sendVote($questionId, $participationId, $answerText)
  {
    $stmt = $this->db->prepare("INSERT INTO answers (question_id, participation_id, answer_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $questionId, $participationId, $answerText);
    $stmt->execute();
    $stmt->close();
  }

  function getParticipation($participationId) {
    $stmt = $this->db->prepare("SELECT * FROM quiz_participation
                                WHERE participation_id = ?;");
    $stmt->bind_param("i", $participationId);
    $stmt->execute();
    $result = $stmt->get_result();

    $participationData = [];
    while ($row = $result->fetch_assoc()) {
        $participationData[] = $row;
    }

    return $participationData;
}


  function getVoteList($quizId)
  {
    $stmt = $this->db->prepare("SELECT * FROM quiz_participation
                                WHERE quiz_id = ?;");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $result = $stmt->get_result();

    $quizzes = array();
    while ($row = $result->fetch_assoc()) {
      $quizzes[] = $row;
    }
    return ['data' => $quizzes];
  }

  function getVoteStatistics($participationId) {
    $stmt = $this->db->prepare("SELECT q.question_id, q.question_text, q.open_question, a.answer_text, COUNT(*) AS answer_count
                                  FROM questions q
                                  JOIN quizzes ON quizzes.quiz_id = q.quiz_id 
                                  JOIN answers a ON q.question_id = a.question_id
                                  WHERE a.participation_id = ?
                                  GROUP BY q.question_id, a.answer_text;");
    $stmt->bind_param("i", $participationId);
    $stmt->execute();
    $result = $stmt->get_result();

    $questions = array();
    while ($row = $result->fetch_assoc()) {
        $questionIndex = array_search($row['question_text'], array_column($questions, 'question_text'));
        if ($questionIndex === false) {
            $questions[] = [
                'question_id' => $row['question_id'], 
                'question_text' => $row['question_text'],
                'open_question' => $row['open_question'],
                'answers' => [
                    ['answer_text' => $row['answer_text'], 'answer_count' => $row['answer_count']]
                ]
            ];
        } else {
            $questions[$questionIndex]['answers'][] = [
                'answer_text' => $row['answer_text'],
                'answer_count' => $row['answer_count']
            ];
        }
    }
    return ['data' => $questions];
}


  function doesParticipationExist($participationId)
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) AS count FROM quiz_participation WHERE participation_id = ?");
    $stmt->bind_param("i", $participationId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($result['count'] > 0);
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
