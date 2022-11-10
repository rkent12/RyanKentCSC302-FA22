<?php
// File:        router.php
// Author:      Hank Feild
// Date:        2020-10-22, updated 2020-11-05, 2020-11-19
// Purpose:     Demonstrates a RESTful API with sessionless authorization.

// For all of our JWT needs.
require_once('jwt.php');

// If the file being requested exists, load it. This is for running in
// PHP dev mode.
if(file_exists(".". $_SERVER['REQUEST_URI'])){
    return false;
}

header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'jwt-example.db';

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

createTables();

// Routes.
$routes = [
    // User management.
    makeRoute("POST", "#^/users/?(\?.*)?$#", "signup"),
    makeRoute("GET", "#^/users/?(\?.*)?$#", "getUsers"),
    makeRoute("PATCH", "#^/users/(\w+)/email/?(\?.*)?$#", "updateUserEmail"),
    makeRoute("PATCH", "#^/users/(\w+)/isAdmin/?(\?.*)?$#", "updateAdminStatus"),
    
    // Generate an authorization token.
    makeRoute("GET", "#^/token/?(\?.*)?$#", "generateToken"),
];

// Initial request processing.
// If this is being served from a public_html folder, find the prefix (e.g., 
// /~jsmith/path/to/dir).
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
if(count($matches) > 0){
    $matches = [];
    preg_match("#/home/([^/]+)/public_html/(.*$)#", dirname(__FILE__), $matches);
    $prefix = "/~". $matches[1] ."/". $matches[2];
    $uri = preg_replace("#^". $prefix ."/?#", "/", $_SERVER['REQUEST_URI']);
} else {
    $prefix = "";
    $uri = $_SERVER['REQUEST_URI'];
}

// Extract Authorization header if present.
$jwtData = null;
if(array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
    $jwtData = verifyJWT(
        str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']), $SECRET);
}

// Get the request method; PHP doesn't handle non-GET or POST requests
// well, so we'll mimic them with POST requests with a "_method" param
// set to the method we want to use.
$method = $_SERVER["REQUEST_METHOD"];
$params = $_GET;
if($method == "POST"){
    $params = $_POST;
    if(array_key_exists("_method", $_POST))
        $method = strtoupper($_POST["_method"]);
} 

// Parse the request and send it to the corresponding handler.
$foundMatchingRoute = false;
$match = [];
foreach($routes as $route){
    if($method == $route["method"]){
        preg_match($route["pattern"], $uri, $match);
        if($match){
            $route["controller"]($uri, $match, $params);
        }
    }
}

// If we made it here, we didn't find a matching endpoint.
error("No route found for: $method $uri");


////////////////////////////////////////////////////////////////////////////////
// Database management.

/**
 * Creates the tabls we need (just one currently).
 */
function createTables(){
    global $dbh;

    try{

        // Create the Users table.
        $dbh->exec('create table if not exists Users('. 
            'id integer primary key autoincrement, '. 
            'username text unique, email text, '. 
            'password text, isAdmin boolean, createdAt datetime)');

    } catch(PDOException $e){
        error("There was an error creating the tables: $e");
    }
}

////////////////////////////////////////////////////////////////////////////////
// User management controllers.


/**
 * Signs a user up. Expects the following fields in `$data`:
 *   - username
 *   - password
 *   - email
 * 
 *  Reponses:
 *       success:
 *            Status: 200 (OK), 
 *            Body: {uri: "/users/:id", username: "...", isAdmin: true/false,
 *                   createdAt: "...", email: "...", jwt: "..."}
 *       error: 
 *            Status 500 (Internal server error), 
 *            Body: {error: "There was an error signing up: :error"}
 * 
 * @param uri The URI of the request.
 * @param matches An array of id matches in the URI.
 * @param data An associative array holding parameters and their values.
 */
function signup($uri, $matches, $data){
    global $dbh, $SECRET;

    try{
        $statement = $dbh->prepare("insert into Users".
            "(username, email, password, isAdmin, createdAt) values ".
            "(:username, :email, :password, 0, datetime('now'))");
        $statement->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT)
        ]);

        // Get the newly created row.
        $statement = $dbh->prepare("select id,username,isAdmin,email,createdAt ". 
            "from Users where id = :id");
        $statement->execute([':id' => $dbh->lastInsertId()]);
        $userInfo = $statement->fetch(PDO::FETCH_ASSOC);
        $userInfo["isAdmin"] = boolval($userInfo["isAdmin"]);

        // FIXED -- convert to JWT.
        // Sign the user in.
        $userInfo['jwt'] = makeJWT([
            'user-id' => $userInfo['id'],
            'is-admin' => $userInfo['isAdmin'],
            'exp' => (new DateTime('NOW'))->modify('+1 day')->format('c')
        ], $SECRET);
        

        $uri = "/users/${userInfo['id']}";
        unset($userInfo['id']);

        created($uri, $userInfo);

    } catch(PDOException $e) {
        error("There was an error signing up: $e");
    }
}


/**
 * Gets the username/uri/email/admin status of all users. Only works for admins.
 * 
 * Response: 
 *        success: 
 *            Status: 200 (OK)
 *            Body: {users: [{uri: "/users/:id", username: "...", email: "...",
 *                isAdmin: true/false, createdAt: "..."}, ...]}
 *        not signed in: 
 *            Status: 401 (Unauthorized)
 *            Body: {error: "You must be signed in to perform this action."}
 *        unauthorized: 
 *            Status: 403 (Forbidden)
 *            Body: {error: "You must be an admin in to perform this action."}
 *        other error: 
 *            Status: 500 (Internal server error)
 *            Body: {error: "There was an error retrieving user data: :error"}
 * 
 * @param uri The URI of the request.
 * @param matches An array of id matches in the URI.
 * @param data An associative array holding parameters and their values.
 */
function getUsers($uri, $matches, $data){
    global $dbh;

    stopUnlessAdmin();

    try{
        $statement = $dbh->prepare("select id,username,isAdmin,createdAt ". 
            "from Users");
        $statement->execute();
        $userInfos = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Convert the isAdmin field to a true boolean value.
        foreach($userInfos as &$userInfo){
            $userInfo['uri'] = "/users/${userInfo['id']}";
            unset($userInfo['id']);
            $userInfo['isAdmin'] = boolval($userInfo['isAdmin']);
        }

        createdNoLocation(["users" => $userInfos]);
       

    } catch(PDOException $e) {
        error("There was an error retrieving user data: $e");
    }
}


/**
 * Updates the admin status of a user; can only be invoked by admin users. 
 * Expects the following fields in `$data`:
 *   - isAdmin
 * 
 * Response: 
 *        success: 
 *            Status: 200 (OK)
 *            Body: {}
 *        user not found: 
 *            Status: 404 (Not Found) 
 *            Body: {error: "/users/:id not found."}
 *        unauthorized: 
 *            Status: 403 (Forbidden) 
 *            Body: {error: "You must be an admin to perform this action."}
 *        other error: 
 *            Status: 500 (Internal server error)
 *            Body: {error: "There was an error updating admin status: :error"}
 * 
 * @param uri The URI of the request.
 * @param matches An array of id matches in the URI. $matches[1] should be the 
 *                id of the user to update.
 * @param data An associative array holding parameters and their values.
 */
function updateAdminStatus($uri, $matches, $data){
    global $dbh;

    stopUnlessAdmin();

    $id = $matches[1];
    try{
        $statement = $dbh->prepare("update Users set isAdmin = :isAdmin ".
            "where id = :id");
        $statement->execute([
            ':id' => $id,
            ':isAdmin' => $data['isAdmin'] === 'true'
        ]);

        // If no rows were affected, then the given id doesn't exist.
        if($statement->rowCount() == 0){
            notFound($uri);
        }

        success([]);


    } catch(PDOException $e) {
        error("There was an error updating admin status: $e");
    }
}


/**
 * Changes a user's email. This can only be accessed by the user whose email 
 * is being modified. Expects the following fields in `$data`:
 *   - isAdmin
 * 
 * Response: 
 *        success: 
 *            Status: 200 (OK)
 *            Body: {}
 *        not signed in: 
 *            Status: 401 (Unauthorized)
 *            Body: {error: "You must be signed in to perform this action."}
 *        unauthorized: 
 *            Status: 403 (Forbidden)
 *            Body: {error: "You must be the account owner to perform this action."}
 *        other error: 
 *            Status: 500 (Internal server error)
 *            Body: {error: "There was an error updating the email: :error"}
 * 
 * @param uri The URI of the request.
 * @param matches An array of id matches in the URI. $matches[1] should be the
 *                user id.
 * @param data An associative array holding parameters and their values.
 */
function updateUserEmail($uri, $matches, $data){
    global $dbh;

    $id = $matches[1];
    stopUnlessOwner($id);

    try{
        $statement = $dbh->prepare("update Users set email = :email ".
            "where id = :id");
        $statement->execute([
            ':id' => $id,
            ':email' => $data['email']
        ]);

        // If no rows were affected, then the given id doesn't exist.
        if($statement->rowCount() == 0){
            notFound($uri);
        }

        success([]);


    } catch(PDOException $e) {
        error("There was an error updating the email: $e");
    }
}

////////////////////////////////////////////////////////////////////////////////
// Token generation controllers.

/**
 * Generates an authentication token for a user. Expects the following fields 
 * in `$data`:
 *   - username
 *   - password
 * 
 * Response: 
 *    success: 
 *        Status: 200 (OK) 
 *        Body: {uri: "/users/:id", username: "...", isAdmin: true/false,
 *                createdAt: "...", email: "...", jwt: "..."}
 *    invalid credentials: 
 *        Status: 404 (Not found)
 *        Body: {error: "A user with that username and password was not found"}
 *    other error: 
 *        Status: 500 (Internal server error) 
 *        Body: {error: "There was an error signing in: :error"}
 * 
 * @param uri The URI of the request.
 * @param matches An array of id matches in the URI.
 * @param data An associative array holding parameters and their values.
 */
function generateToken($uri, $matches, $data){
    global $dbh, $SECRET;

    try{
        $statement = $dbh->prepare("select * from Users where username = :username");
        $statement->execute([':username' => $data['username']]);
        $userInfo = $statement->fetch(PDO::FETCH_ASSOC);

        if($userInfo != null && password_verify($data['password'], 
                $userInfo['password'])){

            // FIXED -- generate JWT token.

            success([ 
                'username' => $data['username'],
                'uri' => "/users/${userInfo['id']}",
                'isAdmin' => boolval($userInfo['isAdmin']),
                'createdAt' => $userInfo['createdAt'],
                'jwt' => makeJWT([
                    'user-id' => $userInfo['id'],
                    'is-admin' => $userInfo['isAdmin'],
                    'exp' => (new DateTime('NOW'))->modify('+1 day')->format('c')
                ], $SECRET)
            ]);
        } else {
            notFound('A user with that username and password was');
        }

    } catch(PDOException $e) {
        error("There was an error signing in: $e");
    }
}



////////////////////////////////////////////////////////////////////////////////
// HELPERS

/**
 *  Creates a map with three keys pointing the the arguments passed in:
 *      - method => $method
 *      - pattern => $pattern
 *      - controller => $function
 * 
 * @param method The http method for this route.
 * @param pattern The pattern the URI is matched against. Include groupings
 *                around ids, etc.
 * @param function The name of the function to call.
 * @return A map with the key,value pairs described above.
 */
function makeRoute($method, $pattern, $function){
    return [
        "method" => $method,
        "pattern" => $pattern,
        "controller" => $function
    ];
}

/**
 * Stops the script unelss the requester is logged in and listed as an admin.
 */
function stopUnlessAdmin(){
    global $dbh, $jwtData;

    // Stop if the requester isn't signed in.
    stopUnlessSignedIn();

    // FIXED -- needs to use the token, not $_SESSION.
    // Stop if the requester has admin privileges.
    if(!$jwtData['payload']['is-admin']){
        forbidden("You must be the account owner to perform this action.");
    }
}

/**
 * Stops the script unelss the requester is logged in and their id matches the
 * given user id.
 */
function stopUnlessOwner($userId){
    global $dbh;

    // Stop if the requester isn't signed in.
    stopUnlessSignedIn();

    // TODO 1 -- needs to use the token, not $_SESSION.
    // Stop if the requester has admin privileges.
    if($_SESSION['user-id'] !== intval($userId)){
        forbidden("You must be the account owner to perform this action.");
    }
}

/**
 * Stops the script unelss the requester is logged in.
 */
function stopUnlessSignedIn(){
    global $jwtData;

    // FIXED -- needs to be updated to use the token, not $_SESSION.
    // Stop if the requester isn't signed in.
    if($jwtData == null || !$jwtData['verified'] || isExpired($jwtData)){
        // die(json_encode($jwtData));
        // die(json_encode([
        //     'null' => $jwtData == null,
        //     '!verified' =>  !$jwtData['verified'],
        //     'isExpired' => isExpired($jwtData)
        // ]
        // ));
        notSignedIn();
    }
}




////////////////////////////////////////////////////////////////////////////////
// Response functions

/**
 * Emits a 200 OK response along with the given date, serialized as
 * json. 
 * 
 * @param $data The value to assign to the `data` field of the output.
 */
function success($data){
    http_response_code(200);
    if(count($data) == 0){
        die(json_encode($data, JSON_FORCE_OBJECT));
    } else {
        die(json_encode($data));
    }
}

/**
 * Emits a 201 Created response along with the given date, serialized as
 * json.
 * 
 * Sets the "Location" field of the header to the given URI.
 * 
 * @param $uri The URI of the created resource.
 * @param $data The value to assign to the `data` field of the output.
 */
function created($uri, $data){
    http_response_code(201);
    header("Location: $uri");
    if(count($data) == 0){
        die(json_encode($data, JSON_FORCE_OBJECT));
    } else {
        die(json_encode($data));
    }
}

/**
 * Emits a 201 Created response along with the given date, serialized as
 * json. Unlike `created()`, this does not include a Location header.
 * 
 * @param $data The value to assign to the `data` field of the output.
 */
function createdNoLocation($data){
    http_response_code(201);
    if(count($data) == 0){
        die(json_encode($data, JSON_FORCE_OBJECT));
    } else {
        die(json_encode($data));
    }
}

/**
 * Emits a 500 response along with a JSON object with one field:
 *   {"error": "...an error message"}
 * 
 * @param $error The value to assign to the `error` field of the output.
 */
function error($error){
    http_response_code(500);
    die(json_encode([
        'error' => $error
    ]));
}

/**
 * Emits a 403 Forbidden response along with an error message embedded in a
 * JSON object:
 *   {"error": "$error"}
 */
function forbidden($error){
    http_response_code(403);
    die(json_encode([
        'error' => $error
    ]));
}

/**
 * Emits a 401 Unauthorized response along with an error message embedded in a
 * JSON object:
 *   {"error": "You must be signed in to perform this action."}
 */
function notSignedIn(){
    http_response_code(401);
    die(json_encode([
        'error' => 'You must be signed in to perform this action.'
    ]));
}

/**
 * Emits a 404 response along with a JSON object with one field:
 *   {"error": "$uri not found."}
 * 
 * @param $error The value to assign to the `error` field of the output.
 */
function notFound($uri){
    // Question 6 solution.
    http_response_code(404);
    die(json_encode([
        'error' => "$uri not found."
    ]));
}




?>
