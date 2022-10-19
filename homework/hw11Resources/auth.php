<?php
header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'auth-demo.db';

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

// Routes.
if(array_key_exists('action', $_POST)){
    $action = $_POST['action'];

    if($action == 'signup'){
        signup($_POST);

    } else if($action == 'authenticate'){
        authenticate($_POST);
    }
}


/**
 * Creates a new entry for the user with the given password.
 * 
 * @param data An associative array holding parameters and their values. Should
 *             have these keys:
 *              - username
 *              - password
 */
function signup($data){
    // TODO: add code to check that username and password params are
    //       present.

    $password = $data['password'];
    $saltedHash = password_hash($password, PASSWORD_BCRYPT);
    addUser($data['username'], $saltedHash);
    echo json_encode(['success' => true]);
}

/**
 * Authenticates the user based on the stored credentials.
 * 
 * @param data An associative array holding parameters and their values. Should
 *             have these keys:
 *              - username
 *              - password
 */
function authenticate($data){
    // TODO: add code to check that username and password params are
    //       present.

    $userInfo = getUserByUsername($data['username']);

    if($userInfo != null && password_verify($data['password'], $userInfo['password'])){
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false]);

    }
}

/**
 * Creates a new user with the given username and password.
 * 
 * @param username The username (must be unique).
 * @param password The password (should be salted and hashed before hand).
 */
function addUser($username, $password){
    global $dbh;

    try{
        $statement = $dbh->prepare('insert into users(username, password) '. 
            'values (:username, :password)');
        $statement->execute([
            ':username' => $username,
            ':password' => $password]);

    } catch(PDOException $e) {
        die(json_encode([
            'success' => false, 
            'error' => "There was an error creating the tables: $e"
        ]));
    }
}

/**
 * Creates the Users table. 
 */
function createTables(){
    global $dbh;

    try{

        // Create the Users table.
        $dbh->exec('create table if not exists Users('. 
            'id integer primary key autoincrement, '. 
            'username text unique, password text)');


    } catch(PDOException $e){
        die(json_encode([
            'success' => false, 
            'error' => "There was an error creating the tables: $e"
        ]));
    }
}

/**
 * @return The user that matches the given username.
 */
function getUserByUsername($username){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from Users where username = :username");
        $statement->execute([':username' => $username]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row;

    } catch(PDOException $e){
        die(json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
        ]));
    }
}


?>
