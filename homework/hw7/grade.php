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
            $responses[$i]["correct"] = true;
            $correct++;
        }
    }
}

echo $responses.json_encode();

?>