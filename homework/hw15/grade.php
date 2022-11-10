<?php
require "questions.php";

$responses = null;
// $correct = null;

if(key_exists("responses", $_POST)){
    $responses = json_decode($_POST["responses"], true);

    $correct = 0;
    for($i = 0; $i < count($questions); $i++){
        // $questions[$i]["response"] = $responses[$i]["answer"];
        $reponses[$i]["correct"] = false;
        if($questions[$i]["answer"] === $responses[$i]["answer"]){
            $responses[$i]["correct"] = true;
            // $correct++;
        }

    }
    // header('Content-Type: application/json');
    echo json_encode($responses);
} else {
    http_response_code(400);
    die("No requests parameter detected.");
}


?>