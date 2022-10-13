<?php
//$questions = [
//     array(
//         "question" => "Who is the president of Endicott College?",
//         "answer" => "Dr. DiSalvo"
//     ),
//     array(
//         "question" => "What is the capital of Massachusetts?",
//         "answer" => "Boston"
//     )
// ];
if(array_key_exists('question', $_POST)){
    try {
        $statement = $dbh->prepare('insert into QuizItems(question, answer) '.
            'values (:answer)');
        $statement->execute([
            ':answer'  => $_POST['answer']]);
    } catch(PDOException $e){
        echo "There was an error adding your answer: $e";
    }
}

?>
    <h1>Add Question</h1>
    <form method="post">
        Title: <input type="text" name="question"/><br/>
        Author: <input type="text" name="answer"/><br/>

        <input type="submit" value="Add Question"/>
    </form>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> 
    <script src="quizzer.js"></script>
    <title>Quizzer</title>

    <style>
        .correct {
            background-color: greenyellow;
        }

        .incorrect {
            background-color: pink;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <h1>Quizzer</h1>

    <div id="quiz-panel">
        <h2>Quiz</h2>
        <span id="score"></span>
        
        <ol id="quiz">
            <?php

            $counter = 0;
            foreach($questions as $question){
                print "<li data-id=\"${counter}\" data-answer=\"${question['answer']}\">${question['question']}<br/>".
                    "<textarea rows=\"3\" class=\"response\"></textarea></li>";
                $counter += 1;
            }
            ?>
        </ol>

        <button id="check-quiz">Check</button>
        <button id="reset-quiz">Reset</button>
    </div>
    
</body>
</html>