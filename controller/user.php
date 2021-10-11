<?php

require_once('./db.php');
require_once('./../model/Response.php');

try {
    $writeDB = DB::connectWriteDB();
}
catch (PDOException $ex) {
    error_log("Connection error - ".$ex, 0);
    $res = new Response();
    $res->setHttpStatusCode(500);
    $res->setSuccess(false);
    $res->addMessage("Database connection failed!");
    $res->send();
    exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $res = new Response();
    $res->setHttpStatusCode(405);
    $res->setSuccess(false);
    $res->addMessage("Request method not allowed!");
    $res->send();
    exit();
}

if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    $res = new Response();
    $res->setHttpStatusCode(400);
    $res->setSuccess(false);
    $res->addMessage("Content type not set to JSON!");
    $res->send();
    exit();
}

$rawPostData = file_get_contents("php://input");

if(!$jsonData = json_decode($rawPostData)) {
    $res = new Response();
    $res->setHttpStatusCode(400);
    $res->setSuccess(false);
    $res->addMessage("Request body is not valid JSON!");
    $res->send();
    exit();
}

if(!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
    $res = new Response();
    $res->setHttpStatusCode(400);
    $res->setSuccess(false);
    (!isset($jsonData->fullname) ? $res->addMessage("Full name required") : false);
    (!isset($jsonData->username) ? $res->addMessage("Username required") : false);
    (!isset($jsonData->password) ? $res->addMessage("Password required") : false);
    $res->send();
    exit();
}

if(strlen($jsonData->fullname) < 3 || strlen($jsonData->fullname) > 100 || strlen($jsonData->username) < 3 || strlen($jsonData->username) > 100 || strlen($jsonData->password) < 6 || strlen($jsonData->password) > 255) {
    $res = new Response();
    $res->setHttpStatusCode(400);
    $res->setSuccess(false);
    (strlen($jsonData->fullname) < 3 ? $res->addMessage('Full name must be atleast 3 characters') : false);
    (strlen($jsonData->fullname) > 100 ? $res->addMessage('Full name must be atmost 100 characters') : false);
    (strlen($jsonData->username) < 3 ? $res->addMessage('Username must be atleast 3 characters') : false);
    (strlen($jsonData->username) > 100 ? $res->addMessage('Username must be atmost 100 characters') : false);
    (strlen($jsonData->password) < 6 ? $res->addMessage('Password must be atleast 6 characters') : false);
    (strlen($jsonData->password) > 100 ? $res->addMessage('Password must be atmost 100 characters') : false);
    $res->send();
    exit();
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try {
    
    $query = $writeDB->prepare("SELECT id FROM tblusers WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $rowCount = $query->rowCount();
    if($rowCount !== 0) {
        $res = new Response();
        $res->setHttpStatusCode(409);
        $res->setSuccess(false);
        $res->addMessage("User already exists with this username!");
        $res->send();
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = $writeDB->prepare("INSERT INTO tblusers (fullname, username, password) VALUES (:fullname, :username, :password)");
    $query->bindParam(':fullname', $fullname);
    $query->bindParam(':username', $username);
    $query->bindParam(':password', $hashed_password);
    $query->execute();
    $rowCount = $query->rowCount();
    if($rowCount === 0) {
        $res = new Response();
        $res->setHttpStatusCode(500);
        $res->setSuccess(false);
        $res->addMessage("Internal server error. Please try again later.");
        $res->send();
        exit();
    }

    $lastUserId = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserId;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    $res = new Response();
    $res->setHttpStatusCode(201);
    $res->setSuccess(true);
    $res->addMessage("User created successfully.");
    $res->setData($returnData);
    $res->send();
    exit();



}
catch (PDOException $ex) {
    error_log("Database query error - ".$ex, 0);
    $res = new Response();
    $res->setHttpStatusCode(500);
    $res->setSuccess(false);
    $res->addMessage("Internal server error!");
    $res->send();
    exit();
}


?>