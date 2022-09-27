<?php
$questions = [];
if($_SERVER["REQUEST_METHOD"] == "POST"){
    foreach($_POST as $key=>$val){
        $q = str_replace('_', ' ', $key) . "?";
        $a = str_replace('_', ' ', $val);
        array_push($questions, array("question" => $q, "answer" => $a));
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