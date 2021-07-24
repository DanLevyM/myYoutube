<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../src/functions.php');

try {
    $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) {
    error_log("Db connection error - ".$ex, 0);
    sendResponse(500, false, "DB connection error.");
}

// Allow from any origin
if(isset($_SERVER["HTTP_ORIGIN"]))
{
    // You can decide if the origin in $_SERVER['HTTP_ORIGIN'] is something you want to allow, or as we do here, just allow all
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
}
else
{
    //No HTTP_ORIGIN set, so we allow any. You can disallow if needed here
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 600");    // cache for 10 minutes

if($_SERVER["REQUEST_METHOD"] == "OPTIONS")
{
    if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"]))
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT"); //Make sure you remove those you do not want to support

    if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    //Just exit with 200 OK with the above headers for OPTIONS method
    exit(0);
}
//From here, handle the request as it is ok

if (array_key_exists("sessionid", $_GET)) {
    $sessionId = $_GET['sessionid'];
    if ($sessionId === '' || !is_numeric($sessionId))
        sendResponse(400, false, "Wrong session id.");

    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
        sendResponse(401, false, "Authorization error!");

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    if ($_SERVER['REQUEST_METHOD'] === "DELETE") {
        try {
            $query = $writeDB->prepare("DELETE FROM sessions WHERE id = :sessionid and accesstoken = :accesstoken");
            $query->bindParam(":sessionid", $sessionId, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            if ($rowCount === 0)
                sendResponse(400, false, "Failed to log out of this session.");

            $returnData = array();
            $returnData['session_id'] = intval($sessionId);

            sendResponse(200, true, "Logged out.", null, $returnData);
        } catch (PDOException $ex) {
            sendResponse(500, false, "There was an issue logging out.");

        }
    }
    else if ($_SERVER['REQUEST_METHOD'] === "PATCH") {
        if ($_SERVER['CONTENT_TYPE'] !== "application/json")
            sendResponse(400, false, "Content type header not set to Json.");

        $rawPatchData = file_get_contents("php://input");
        if (!$jsonData = json_decode($rawPatchData))
            sendResponse(400, false, "Request body is Not valid JSON");

        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1)
            sendResponse(400, false, "Refresh token not supplied");

        try {
            $refreshToken = $jsonData->refresh_token;
            $query = $writeDB->prepare("
            SELECT sessions.id as sessionId, sessions.userid as userId, accesstoken, refreshtoken, loginattempts, accesstokenexpiry, refreshtokenexpiry
            FROM sessions, users
            WHERE users.id = sessions.userid
            AND sessions.id = :sessionid
            AND sessions.accesstoken = :accesstoken
            AND sessions.refreshtoken = :refreshtoken");
            $query->bindParam(":sessionid", $sessionId, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
            $query->bindParam(":refreshtoken", $refreshToken, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();
            if ($rowCount === 0)
                sendResponse(401, false, "Access token or refresh token is incorrect for session id.");

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid = $row['sessionId'];
            $returned_userid = $row['userId'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_logginattempts = $row['logginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if ($returned_logginattempts >= 3)
                sendResponse(401, false, "User account is currently locked out");

            if (strtotime($returned_refreshtokenexpiry) < time())
                sendResponse(401, false, "Refresh token has expired - please log again");

            $access_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)));
            $refresh_token = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)));

            $access_token_expiry_seconds = 1200;
            $refresh_token_expiry_seconds = 1209600;

            $query = $writeDB->prepare("
            UPDATE sessions
            SET accesstoken = :accesstoken, accesstokenexpiry = DATE_ADD(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = DATE_ADD(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND)
            WHERE id = :sessionid
            AND userid = :userid
            AND accesstoken = :returnedaccesstoken
            AND refreshtoken = :returnedrefreshtoken");
            $query->bindParam(":userid", $returned_userid, PDO::PARAM_INT);
            $query->bindParam(":sessionid", $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $access_token, PDO::PARAM_STR);

            $query->bindParam(":accesstokenexpiryseconds", $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(":refreshtoken", $refresh_token, PDO::PARAM_STR);
            $query->bindParam(":refreshtokenexpiryseconds", $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(":returnedaccesstoken", $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(":returnedrefreshtoken", $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();
            if ($rowCount === 0)
                sendResponse(401, false, "Access token could not be refreshed, please log in again..");

            $returnData = array();
            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $access_token;
            $returnData['access_token_expiry_in'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refresh_token;
            $returnData['refrest_token_expiry_in'] = $refresh_token_expiry_seconds;

            sendResponse(200, true, "Token refreshed.", null, $returnData);

        } catch (PDOException $ex) {
            error_log($ex, 0);
            sendResponse(500, false, "There was an issue refreshing access token - please log again!");
        }
    }
    else {
        sendResponse(405, false, "Request Method not allowed.");
    }

} elseif (empty($_GET)) {
    check_request_method("POST");
    sleep(1);
    check_content_type("application/json");

    $rawPostData = file_get_contents("php://input");
    if (!$jsonData = json_decode($rawPostData))
        sendResponse(400, false, "Request body not valid JSON.");

    if (!isset($jsonData->pseudo) || !isset($jsonData->password)) {
        $message = array();
        (!isset($jsonData->pseudo) ? $message[] = "Pseudo not supplied." : false);
        (!isset($jsonData->password) ? $message[] = "Password not supplied." : false);
        sendResponse(401, false, $message);
    }

    if (strlen($jsonData->pseudo) < 1 || strlen($jsonData->pseudo) > 60 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 60) {
        $message = array();
        (strlen($jsonData->pseudo) < 1 ? $message[] = "Pseudo cannot be blank." : false);
        (strlen($jsonData->pseudo) > 60 ? $message[] = "Pseudo must be less than 60 characters." : false);
        (strlen($jsonData->password) < 1 ? $message[] = "Password cannot be blank." : false);
        (strlen($jsonData->password) > 60 ? $message[] = "Password must be less than 60 characters." : false);
        sendResponse(404, false, $message);
    }

    try {
        $pseudo = $jsonData->pseudo;
        $password = $jsonData->password;

        $query = $writeDB->prepare("SELECT id, username, pseudo, password, email, loginattempts FROM users WHERE pseudo = :pseudo");
        $query->bindParam(":pseudo", $pseudo, PDO::PARAM_STR);
        $query->execute();
        $rowCount = $query->rowCount();
        if ($rowCount !== 1)
            sendResponse(401, false, "Pseudo or password is incorrect.");

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $returned_id = $row['id'];
        $returned_username = $row['username'];
        $returned_pseudo = $row['pseudo'];
        $returned_password = $row['password'];
        $returned_email = $row['email'];
        $returned_logginattempts = $row['logginattempts'];

        if ($returned_logginattempts >= 3)
            sendResponse(401, false, "User account is currently locked out.");

        if (!password_verify($password, $returned_password)) {
            $query = $writeDB->prepare("UPDATE users SET loginattempts = loginattempts+1 WHERE id = :id");
            $query->bindParam(":id", $returned_id, PDO::PARAM_INT);
            $query->execute();

            sendResponse(500, false, "Pseudo or Password is incorrect.");
        }
        $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
        $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

        $accessTokenExpirySeconds = 1200;
        $refreshTokenExpirySeconds = 1209600;

        $userIdToLocalStorage = $row['id'];

    } catch (PDOException $ex) {
        sendResponse(500, false, "Issue logging in.");
    }

    try {
        $writeDB->beginTransaction();
        $query = $writeDB->prepare("UPDATE users SET loginattempts = 0 WHERE id = :id");
        $query->bindParam(":id", $returned_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare("INSERT INTO sessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) VALUES (:userid, :accesstoken, DATE_ADD(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), :refreshtoken, DATE_ADD(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND))");
        $query->bindParam(":userid", $returned_id, PDO::PARAM_INT);
        $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
        $query->bindParam(":accesstokenexpiryseconds", $accessTokenExpirySeconds, PDO::PARAM_INT);
        $query->bindParam(":refreshtoken", $refreshToken, PDO::PARAM_STR);
        $query->bindParam(":refreshtokenexpiryseconds", $refreshTokenExpirySeconds, PDO::PARAM_INT);
        $query->execute();

        $lastSessionId = $writeDB->lastInsertId();
        $writeDB->commit();

        $returnData = array();
        $returnData['session_id'] = $lastSessionId;
        $returnData['access_token'] = $accessToken;
        $returnData['access_token_expiry_in'] = $accessTokenExpirySeconds;
        $returnData['refresh_token'] = $refreshToken;
        $returnData['refrest_token_expiry_in'] = $refreshTokenExpirySeconds;
        $returnData['user_id'] = $userIdToLocalStorage;

        sendResponse(201, true, null, null, $returnData);
    } catch (PDOException $ex) {
        error_log($ex, 0);
        $writeDB->rollBack();
        sendResponse(500, false, "There was an issue logging in - please try again.");
    }
} else {
    sendResponse(500, false, "Endpoint not found.");
}




