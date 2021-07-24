<?php

require_once("../model/Response.php");

function check_request_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method)
        sendResponse(405, false, "Request method not allowed.");
    return true;
}

function check_content_type($content_type) {
    if ($_SERVER['CONTENT_TYPE'] !== $content_type)
        sendResponse(400, false, "Content Type header not set to JSON");
}

function check_json_body($jsonData) {
    if ($jsonData === null)
        sendResponse(400, false, "Request body is not valid.");
}

function sendResponse($statusCode, $success, $message = null, $toCache = null, $data = null) {
    $response = new Response();
    $response->setHttpStatusCode($statusCode);
    $response->setSuccess($success);

    if ($message !== null)
        $response->addMessage($message);

    $response->setToCache($toCache);

    if ($data !== null)
        $response->setData($data);

    $response->send();
    exit;
}

function checkAuthStatusAndReturnUserId ($writeDB) {
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
        sendResponse(401, false, "Authorization issue");

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    try {
        $query = $writeDB->prepare("
            SELECT userid, accesstokenexpiry, loginattempts 
            FROM sessions, users 
            WHERE sessions.userid = users.id AND accesstoken = :accesstoken");
        $query->bindParam(":accesstoken", $accesstoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount !== 1)
            sendResponse(401, false, "Authentification error.");
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_loginattempts >= 3)
            sendResponse(401, false, "User account is currently locked out");

        if (strtotime($returned_accesstokenexpiry) < time())
            sendResponse(401, false, "Access token expired.");

    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        sendResponse(500, false, "There was an issue Authenticating.");
    }
    return $returned_userid;
}