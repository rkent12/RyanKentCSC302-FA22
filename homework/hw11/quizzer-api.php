<?php
header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'quizzer.db';

// Leave this alone. It checks if you have a directory named www-data in
// you home directory (on a *nix server). If so, the database file is
// sought/created there. Otherwise, it uses the current directory.
// The former works on digdug where I've set up the www-data folder for you;
// the latter should work on your computer.
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
$homeDir = count($matches) > 1 ? $matches[1] : '';
$dataDir = "/home/$homeDir/www-data";
if(!file_exists($dataDir)){
    $dataDir = __DIR__;
}
$dbh = new PDO("sqlite:$dataDir/$dbName")   ;
// Set our PDO instance to raise exceptions when errors are encountered.
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Put your other code here.

createTables();

// Handle incoming requests.
if(array_key_exists('action', $_POST)){
    $action = $_POST['action'];
    if($action == 'addUser'){
        addUser($_POST);
    } else if($action == 'addQuiz'){
        addQuiz($_POST);
    } else if($action == 'addQuizItem'){
        addQuizItem($_POST);
    } else if($action == 'removeQuizItem'){
        removeQuizItem($_POST);
    } else if($action == 'updateQuizItem'){
        updateQuizItem($_POST);
    } else if($action == 'submitResponses'){
        submitResponses($_POST);

    } else {
        die(json_encode([
            'success' => false, 
            'error' => 'Invalid action: '. $action
        ]));
    }
}


function createTables(){
    global $dbh;

    try{
        // Create the Users table.
        $dbh->exec('create table if not exists Users('. 
            'id integer primary key autoincrement, '. 
            'username text unique, '. 
            'password text'.
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()))');

        // Create the Quizzes table.
        $dbh->exec('create table if not exists Quizzes('. 
            'id integer primary key autoincrement, '. 
            'authorId integer, '. 
            'name text, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '.
            'foreign key (authorId) references Users(id))');

        // Create the QuizItems table.
        $dbh->exec('create table if not exists QuizItems('. 
            'id integer primary key autoincrement, '. 
            'quizId integer, '. 
            'question text, '. 
            'answer text, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '. 
            'foreign key (quizId) references Quizzes(id))');

        // Create the Submissions table.
        $dbh->exec('create table if not exists Submissions('. 
            'id integer primary key autoincrement, '. 
            'quizId integer, '. 
            'submitterId integer, '. 
            'numCorrect integer, '. 
            'score real, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '. 
            'foreign key (quizId) references Quizzes(id), '.
            'foreign key (submitterId) references Users(id))');

        // Create the QuizItemResponses table.
        $dbh->exec('create table if not exists QuizItemResponses('. 
            'quizItemId integer, '. 
            'submissionId integer, '. 
            'response text, '. 
            'isCorrect bool, '. 
            'createdAt datetime default(datetime()), '. 
            'updatedAt datetime default(datetime()), '. 
            'primary key (quizItemId, submissionId), '.
            'foreign key (quizItemId) references QuizItems(id), '.
            'foreign key (submissionId) references Submissions(id))');

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error creating the tables: $e"
        ]));
    }
}

function authenticate($username, $password){
    if($username && $password != null) {
        if(password_verify($password, $saltedHash)){
            echo json_encode(['success' => true]);

        } else {
            echo json_encode(['success' => false]);
        }
    }
}

/**
 * Adds a user to the database. Requires the parameters:
 *  - username
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the user just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addUser($data){
    global $dbh;
    try {
        $statement = $dbh->prepare('insert into Users(username) '.
            'values (:username)');
        $statement->execute([
            ':username' => $data['username']]);

        $userId = $dbh->lastInsertId();
        die(json_encode([
            'success' => true,
            'id' => $userId
        ]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error adding the user: $e"
        ]));
    } 
}

/**
 * Adds a quiz to the database. Requires the parameters:
 *  - authorUsername
 *  - name (of quiz) 
 *
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the quiz just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addQuiz($data){
    global $dbh;

    // Look up userid first.
    $user = getUserByUsername($data['authorUsername']);
    if(authenticate(getUserByUsername($data['authorUsername']), getUserByUsername($data['password']))) {
        try {
            $statement = $dbh->prepare('insert into Quizzes'. 
                '(authorId, name) values (:authorId, :name)');
            $statement->execute([
                ':authorId' => $user['id'], 
                ':name' => $data['name']
            ]);

            die(json_encode([
                'success' => true,
                'id' => $dbh->lastInsertId()
            ]));

        } catch(PDOException $e){
            http_response_code(400);
            die(json_encode([
                'success' => false, 
                'error' => "There was an error adding the quiz: $e"
            ]));
        }
    }
}

/**
 * Adds a quiz item to the database. Requires the parameters:
 *  - quizId
 *  - question
 *  - answer
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - id -- the id of the quiz item just added (only if success is true)
 *               - error -- the error encountered, if any (only if success is false)
 */
function addQuizItem($data){
    global $dbh;
    if(authenticate(getUserByUsername($data['authorUsername']), getUserByUsername($data['password']))) {
        try {
            $statement = $dbh->prepare('insert into QuizItems'. 
                '(quizId, question, answer) values (:quizId, :question, :answer)');
            $statement->execute([
                ':quizId' => $data['quizId'], 
                ':question' => $data['question'],
                ':answer' => $data['answer']
            ]);

            die(json_encode([
                'success' => true,
                'id' => $dbh->lastInsertId()
            ]));

        } catch(PDOException $e){
            http_response_code(400);
            die(json_encode([
                'success' => false, 
                'error' => "There was an error adding the quiz item: $e"
            ]));
        }
    }
}



/**
 * Removes a quiz item from the database. Requires the parameters:
 *  - quizItemId
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function removeQuizItem($data){
    global $dbh;

    if(authenticate(getUserByUsername($data['authorUsername']), getUserByUsername($data['password']))) {
        try {
            $statement = $dbh->prepare('delete from QuizItems '. 
                'where id = :id');
            $statement->execute([
                ':id' => $data['quiItemId']]);

            die(json_encode(['success' => true]));

        } catch(PDOException $e){
            http_response_code(400);
            die(json_encode([
                'success' => false, 
                'error' => "There was an error removing the quiz item: $e"
            ]));
        }
    }
}

/**
 * Updates a quiz item in the database. Requires the parameters:
 *  - quizItemId
 *  - question
 *  - answer
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function updateQuizItem($data){
    global $dbh;

    if(authenticate(getUserByUsername($data['authorUsername']), getUserByUsername($data['password']))) {
        try {
            $statement = $dbh->prepare('update QuizItems set '. 
                'question = :question, '.
                'answer = :answer, '.
                'updatedAt = datetime() '.
                'where id = :id');
            $statement->execute([
                ':question' => $data['question'],
                ':answer' => $data['answer'],
                ':id' => $data['quizItemId']
            ]);

            die(json_encode(['success' => true]));

        } catch(PDOException $e){
            http_response_code(400);
            die(json_encode([
                'success' => false, 
                'error' => "There was an error updating the quiz item: $e"
            ]));
        }
    }
}

/**
 * Updates a quiz item in the database. Requires the parameters:
 *  - submitterUsername
 *  - quizId
 *  - responses:
 *    * quizItemId
 *    * response
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function submitResponses($data){
    global $dbh;

    $user = getUserByUsername($data['submitterUsername']);

    if(authenticate(getUserByUsername($data['authorUsername']), getUserByUsername($data['password']))) {
        try {
            // Strategy: 
            // 1. grab all of the item that go with this quiz
            // 2. grade the responses
            // 3. create a new submission entry
            // 4. create a new entry for each response


            // 1. Grab all of the item that go with this quiz
            $statement = $dbh->prepare('select id, answer from QuizItems '. 
                'where quizId = :quizId');
            $statement->execute([
                ':quizId' => $data['quizId']
            ]);
            $quizItems = $statement->fetchAll(PDO::FETCH_ASSOC);

            // Put them into a nicer lookup.
            $quizItemAnswerLookup = [];
            foreach($quizItems as $quizItem){
                $quizItemAnswerLookup[$quizItem['id']] = $quizItem['answer'];
            }

            // 2. Grade the responses. 
            $responses = [];
            $numCorrect = 0;
            foreach($data['responses'] as $response){
                $isCorrect = false;
                if($quizItemAnswerLookup[$response['quizItemId']] == $response['response']){
                    $isCorrect = true;
                    $numCorrect += 1;
                }

                array_push($responses, [
                    'quizItemId' => $response['quizItemId'],
                    'response' => $response['response'],
                    'isCorrect' => $isCorrect
                ]);
            }

            // 3. Create a new submission entry.
            $statement = $dbh->prepare('insert into Submissions('. 
                'quizId, submitterId, numCorrect, score) values ('. 
                ':quizId, :submitterId, :numCorrect, :score)');
            $statement->execute([
                ':quizId' => $data['quizId'],
                ':submitterId' => $user['id'],
                ':numCorrect' => $numCorrect,
                ':score' => ($numCorrect/count($responses))
            ]);

            $submissionId = $dbh->lastInsertId();
            
            // 4. Create a new entry for each response.
            $statementText = 'insert into QuizItemResponses('. 
                'quizItemId, submissionId, response, isCorrect) values ';
            $statementData = [];

            for($i = 0; $i < count($responses); $i++){
                $statementText .= "(?, ?, ?, ?)";
                if($i < count($responses)-1){
                    $statementText .= ', ';
                }
                array_push($statementData, 
                    $responses[$i]['quizItemId'],
                    $submissionId,
                    $responses[$i]['response'],
                    $responses[$i]['isCorrect']
                
                );
            }

            $statement = $dbh->prepare($statementText);
            $statement->execute($statementData);
            

            die(json_encode([
                'success' => true,
                'id' => $submissionId
            ]));

        } catch(PDOException $e){
            http_response_code(400);
            die(json_encode([
                'success' => false, 
                'error' => "There was an error submitting the responses: $e"
            ]));
        }
    }
}

/**
 * Outputs the row of the given table that matches the given id.
 */
function getTableRow($table, $data){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table where id = :id");
        $statement->execute([':id' => $data['id']]);
        // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
        // most.
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        die(json_encode(['success' => true, 'data' => $row]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
        ]));
    }
}

/**
 * Looks up a user by their username. 
 * 
 * @param $username The username of the user to look up.
 * @return The user's row in the Users table or null if no user is found.
 */
function getUserByUsername($username){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from Users where username = :username");
        $statement->execute([':username' => $username]);
        // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
        // most.
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row;

    } catch(PDOException $e){
        return null;
    }
}

/**
 * Outputs all the values of a database table. 
 * 
 * @param table The name of the table to display.
 */
function getTable($table){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table");
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        die(json_encode(['success' => true, 'data' => $rows]));

    } catch(PDOException $e){
        http_response_code(400);
        die(json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
        ]));
    }
}
?>