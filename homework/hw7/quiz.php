<?php
require "questions.php";

$responses = null;
$correct = null;

if(key_exists("responses", $_POST)){
    $responses = json_decode($_POST["responses"], true);

    $correct = 0;
    for($i = 0; $i < count($questions); $i++){
        print "question: ". json_encode($questions[$i]) ."<br/>";
        print "response: ". json_encode($responses[$i]) ."<br/>";
        $questions[$i]["response"] = $responses[$i]["answer"];
        $questions[$i]["correct"] = false;
        if($questions[$i]["answer"] === $questions[$i]["response"]){
            $questions[$i]["correct"] = true;
            $correct++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> 
    <script src="quizzer.js"></script>
    <script>
        questions = JSON.parse('<?= json_encode($questions) ?>');
    </script>
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
        <span id="score">
            <?php
            if($correct !== null){
                echo "Score: ". ($correct/count($questions)) ."($correct/". count($questions) .")";
            }
            ?>
        </span>
        
        <ol id="quiz">
            <?php

            $counter = 0;
            foreach($questions as $question){
                $correctnessClass = "";
                if(key_exists("correct", $question)){
                    $correctnessClass = $question["correct"] ? "correct" : "incorrect";
                }
                print "<li data-id=\"${counter}\" class=\"$correctnessClass\">${question['question']}<br/>".
                    "<textarea rows=\"3\" class=\"response\">"; 
                if(key_exists("response", $question)){
                    // print htmlentities($question["response"]);
                    print $question["response"];
                }
                print "</textarea></li>";
                $counter += 1;
            }
            ?>
        </ol>

        <button id="check-quiz">Check</button>
        <button id="reset-quiz">Reset</button>
    </div>

    <form id="response-submission-form" class="hidden" method="post" action="quiz.php">
        <input type="text" name="responses"/>
    </form>
    
</body>
</html>