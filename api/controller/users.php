<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/User.php');
require_once('../src/functions.php');
require_once('../src/user_functions.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    // Send error message to errors history of the web service
    error_log("Connecting error : ".$ex, 0);
    sendResponse(500, false, "DB connection error.");
}



// POUR POUVOIR RECUPERER LES DATA EN FRONT 
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
// POUR POUVOIR RECUPERER LES DATA EN FRONT 



if (array_key_exists("id", $_GET)) {

    // GET USER
    if ($_SERVER['REQUEST_METHOD'] === "GET") {
        $id = $_GET['id'];
        if (!is_numeric($id) || $id == '')
            sendResponse(400, false, "Id not found.");

        if ($id < 1 || $id > 9223372036854775807)
            sendResponse(400, false, "Wrong Id.");

        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
            sendResponse(401, false, "Access token is missing from the header.");

        $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

        $query = $readDB->prepare("SELECT id FROM sessions WHERE accesstoken = :accesstoken");
        $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
        $query->execute();
        $rowCount = $query->rowCount();
        if ($rowCount != 1)
            sendResponse(401, false, "Access token not valid");

        $query = $readDB->prepare("SELECT username, pseudo, email FROM users WHERE id = :id");
        $query->bindParam(":id", $id, PDO::PARAM_INT);
        $query->execute();
        $rowCount = $query->rowCount();
        if ($rowCount != 1) {
            sendResponse(404, false, "User not found.");
        }
        else {
            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returnData = array();
            $returnData['username'] = $row['username'];
            $returnData['pseudo'] = $row['pseudo'];
            $returnData['email'] = $row['email'];
            sendResponse(200, true, "User found.", null, $returnData);
        }
        exit;
    }
    // DELETE USER
    else if ($_SERVER['REQUEST_METHOD'] === "DELETE") {
        $id = $_GET['id'];
        if (!is_numeric($id) || $id == '')
            sendResponse(400, false, "Id not found.");
        $id = intval($_GET['id']);
        if ($id < 1 || $id > 9223372036854775807)
            sendResponse(400, false, "Wrong Id.");

        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
            sendResponse(401, false, "Access token is missing from the header.");

        $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

        try {
            $writeDB->beginTransaction();

            $query = $writeDB->prepare("
            SELECT id, userid 
            FROM sessions 
            WHERE accesstoken = :accesstoken");
            $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();
            if ($rowCount != 1)
                sendResponse(401, false, "Access token not valid");

            // A user can delete ONLY his own account
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if ($id !== $row['userid'])
                sendResponse(401, false, "You are not allowed to do that!");

            $query = $writeDB->prepare("
            DELETE users
            FROM users, sessions
            WHERE users.id = :userid
            AND sessions.accesstoken = :accesstoken");
            $query->bindParam(":userid", $row['userid'], PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount !== 1) {
                $writeDB->rollBack();
                sendResponse(404, false, "There was an issue deleting user.");
            }
            else {
                $writeDB->commit();
                sendResponse(200, true, "User deleted. Hope to see you back soon !");
            }
            exit;
        } catch (PDOException $ex) {
            error_log("Db query error: ".$ex, 0);
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(500, false, "Failed to delete user.");
        }
    }
    // UPDATE USER
    else if ($_SERVER['REQUEST_METHOD'] === "PUT") {
        $id = $_GET['id'];
        if (!is_numeric($id) || $id == '')
            sendResponse(400, false, "Id not found.");
        $id = intval($_GET['id']);

        if ($id < 1 || $id > 9223372036854775807)
            sendResponse(400, false, "Wrong Id.");

        if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
            sendResponse(401, false, "Access token is missing from the header.");

        $accessToken = $_SERVER['HTTP_AUTHORIZATION'];
        try {
            $writeDB->beginTransaction();

            $query = $writeDB->prepare("
            SELECT id, userid 
            FROM sessions 
            WHERE accesstoken = :accesstoken");
            $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();
            if ($rowCount != 1)
                sendResponse(401, false, "Access token not valid");

            // A user can patch ONLY his own account
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if ($id !== $row['userid'])
                sendResponse(401, false, "You are not allowed to do that!");

            $rawPatchData = file_get_contents("php://input");
            if (!$jsonData = json_decode($rawPatchData))
                sendResponse(400, false, "Request body is not valid JSON");
            if (!isset($jsonData->username) && !isset($jsonData->pseudo) && !isset($jsonData->password) && !isset($jsonData->email))
                sendResponse(400, false, "Nothing to update.");

            $username = trim($jsonData->username);
            $pseudo = trim($jsonData->pseudo);
            $email = trim($jsonData->email);
            $password = $jsonData->password;

            $username_updated = false;
            $pseudo_updated = false;
            $password_updated = false;
            $email_updated = false;

            $queryFields = "";
            if (isset($jsonData->username)) {
                if (strlen($username) < 1 || strlen($username) > 60 || is_numeric($username)) {
                    $message = array();
                    (strlen($username) < 1 ? $message[] = "Username cannot be blank." : false);
                    (strlen($username) > 60 ? $message[] = "Username cannot be greater than 60 characters." : false);
                    (is_numeric($username) ? $message[] = "Username cannot be numeric." : false);
                    sendResponse(400, false, $message);
                }
                if (!preg_match('/^[a-zA-Z0-9]{1,}$/', $username))
                    sendResponse(400, false, "Invalid input for username!");
                $username_updated = true;
                $queryFields .= "username = :username, ";
            }

            if (isset($jsonData->pseudo)) {
                if (strlen($pseudo) < 1 || strlen($pseudo) > 60 || is_numeric($pseudo)) {
                    $message = array();
                    (strlen($pseudo) < 1 ? $message[] = "Pseudo cannot be blank." : false);
                    (strlen($pseudo) > 60 ? $message[] = "Pseudo cannot be greater than 60 characters." : false);
                    (is_numeric($pseudo) ? $message[] = "Pseudo cannot be numeric." : false);
                    sendResponse(400, false, $message);
                }
                if (!preg_match('/^[a-zA-Z0-9]{1,}$/', $pseudo))
                    sendResponse(400, false, "Invalid input for pseudo!");
                $pseudo_updated = true;
                $queryFields .= "pseudo = :pseudo, ";
            }

            if (isset($jsonData->password)) {
                if (strlen($password) < 1 || strlen($password) > 60) {
                    $message = array();
                    (strlen($password) < 1 ? $message[] = "Password cannot be blank." : false);
                    (strlen($password) > 60 ? $message[] = "Password cannot be greater than 60 characters." : false);
                    sendResponse(400, false, $message);
                }
                $password_updated = true;
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $queryFields .= "password = :password, ";
            }

            if (isset($jsonData->email)) {
                check_email_format($email);
                $email_updated = true;
                $queryFields .= "email = :email, ";
            }


            $queryFields = rtrim($queryFields, ", ");

            $query = $writeDB->prepare("
                SELECT id 
                FROM users 
                WHERE pseudo = :pseudo");
            $query->bindParam(":pseudo", $pseudo, PDO::PARAM_STR);
            $query->execute();
            if ($query->rowCount() !== 0)
                sendResponse(409, false, "Pseudo already exists. Please try again.");


            $query = $writeDB->prepare("
                SELECT id 
                FROM users 
                WHERE email = :email");
            $query->bindParam(":email", $email, PDO::PARAM_STR);
            $query->execute();
            if ($query->rowCount() !== 0)
                sendResponse(409, false, "Email already exists. Please try again.");


            $query = $writeDB->prepare("
                SELECT users.id, users.username, users.pseudo, users.email
                FROM users, sessions
                WHERE users.id = :id 
                AND sessions.accesstoken = :accesstoken
                AND users.id = sessions.userid");
            $query->bindParam(":id", $id, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_INT);
            $query->execute();
            $rowCount = $query->rowCount();
            if ($rowCount != 1)
                sendResponse(401, false, "You are not allowed to do that.");

            while ($row = $query->fetch(PDO::FETCH_ASSOC))
                $user = new User($row['id'], $row['username'], $row['pseudo'], $row['email']);

            $queryString = "UPDATE users SET ".$queryFields." WHERE id = :id";


            $query = $writeDB->prepare($queryString);
            if ($username_updated === true) {
                $user->setUsername($username);
                $up_username = $user->getUsername();
                $query->bindParam(":username", $up_username, PDO::PARAM_STR);
            }
            if ($pseudo_updated === true) {
                $user->setPseudo($pseudo);
                $up_pseudo = $user->getPseudo();
                $query->bindParam(":pseudo", $up_pseudo, PDO::PARAM_STR);
            }
            if ($password_updated === true) {
                $query->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            }
            if ($email_updated === true) {
                $user->setEmail($email);
                $up_email = $user->getEmail();
                $query->bindParam(":email", $up_email, PDO::PARAM_STR);
            }
            $query->bindParam(":id", $id, PDO::PARAM_INT);
            $query->execute();
            if ($query->rowCount() !== 1) {
                $writeDB->rollBack();
                sendResponse(400, false, "User not updated ! Nothing to modify.");
            }

            $query = $writeDB->prepare("
                SELECT id, username, pseudo, email
                FROM users
                WHERE id = :id");
            $query->bindParam(":id", $id, PDO::PARAM_INT);
            $query->execute();
            if ($query->rowCount() !== 1)
                sendResponse(400, false, "There was an error after updating user.");

            $userArray = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $updated_user = new User($row['id'], $row['username'], $row['pseudo'], $row['email']);
                $updated_user->setLogginattempts(0);
                $userArray[] = $updated_user->returnUserArray();
            }

            $writeDB->commit();
            $returnData = array();
            $returnData['users'] = $userArray;
            sendResponse(200, true, "User updated.", false, $returnData);

        } catch (PDOException $ex) {
            error_log($ex, 0);
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(500, false, "Failed to modify user");
        } catch (UserException $ex) {
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(500, false, $ex->getMessage());
        }
        exit;
    }
    else {
      sendResponse(500, false, "Request method not allowed..");
  }
}
else if (empty($_GET)) {
    // Creation user
    if ($_SERVER['REQUEST_METHOD'] === "POST") {
        check_content_type("application/json");
        $rawPostData = file_get_contents("php://input");
        $jsonData = json_decode($rawPostData);
        check_json_body($jsonData);

        if (!isset($jsonData->username) || !isset($jsonData->pseudo) || !isset($jsonData->password) || !isset($jsonData->email)) {
            $message = array();
            (!isset($jsonData->username) ? $message[] .= "Username not supplied." : false);
            (!isset($jsonData->pseudo) ? $message[] .= "Pseudo not supplied." : false);
            (!isset($jsonData->password) ? $message[] .= "Password not supplied." : false);
            (!isset($jsonData->email) ? $message[] .= "Email not supplied." : false);
            sendResponse(400, false, $message);
        }

        if (is_numeric($jsonData->username) || is_numeric($jsonData->pseudo) || is_numeric($jsonData->password)) {
            $message = array();
            (is_numeric($jsonData->username) ? $message[] .= "Username can not be numeric." : false);
            (is_numeric($jsonData->pseudo) ? $message[] .= "Pseudo can not be numeric." : false);
            (is_numeric($jsonData->password) ? $message[] .= "Password can not be numeric." : false);
            sendResponse(400, false, $message);
        }

        $username = trim($jsonData->username);
        $pseudo = trim($jsonData->pseudo);
        $email = trim($jsonData->email);
        $password = $jsonData->password;

        if (strlen($username) < 1 || strlen($username) > 60 || strlen($pseudo) < 1 || strlen($pseudo) > 60 || strlen($password) < 1 || strlen($password) > 60) {
            $message = array();
            (strlen($username) < 1 ? $message[] = "Username cannot be blank." : false);
            (strlen($username) > 60 ? $message[] = "Username cannot be greater than 60 characters." : false);
            (strlen($pseudo) < 1 ? $message[] = "Pseudo cannot be blank." : false);
            (strlen($pseudo) > 60 ? $message[] = "Pseudo cannot be greater than 60 characters." : false);
            (strlen($password) < 1 ? $message[] = "Password cannot be blank." : false);
            (strlen($password) > 60 ? $message[] = "Password cannot be greater than 60 characters." : false);
            sendResponse(400, false, $message);
        }

        if ((!preg_match('/^[a-zA-Z0-9]{1,}$/', $username)) || (!preg_match('/^[a-zA-Z0-9]{1,}$/', $pseudo))) {
            $message = array();
            ((!preg_match('/^[a-zA-Z0-9]{1,}$/', $username)) ? $message[] = "Invalid input for username ([a-zA-Z0-9])." : false);
            ((!preg_match('/^[a-zA-Z0-9]{1,}$/', $pseudo)) ? $message[] = "Invalid input for pseudo ([a-zA-Z0-9])." : false);
            sendResponse(400, false, $message);
        }

        check_email_format($email);

        try {
            $query = $writeDB->prepare("
                SELECT id 
                FROM users 
                WHERE username = :username");
            $query->bindParam(":username", $username, PDO::PARAM_STR);
            $query->execute();
            if ($rowCount = $query->rowCount() !== 0)
                sendResponse(409, false, "Username already exists. Please try again.");

            $query = $writeDB->prepare("
                SELECT id
                FROM users 
                WHERE pseudo = :pseudo");
            $query->bindParam(":pseudo", $pseudo, PDO::PARAM_STR);
            $query->execute();
            if ($rowCount = $query->rowCount() !== 0)
                sendResponse(409, false, "Pseudo already exists. Please try again.");

            $query = $writeDB->prepare("
                SELECT id 
                FROM users 
                WHERE email = :email");
            $query->bindParam(":email", $email, PDO::PARAM_STR);
            $query->execute();
            if ($rowCount = $query->rowCount() !== 0)
                sendResponse(409, false, "Email already exists. Please try again.");

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = $writeDB->prepare("INSERT INTO users (username, pseudo, password, email) VALUES (:username, :pseudo, :password, :email)");
            $query->bindParam(":username", $username, PDO::PARAM_STR);
            $query->bindParam(":pseudo", $pseudo, PDO::PARAM_STR);
            $query->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            $query->bindParam(":email", $email, PDO::PARAM_STR);
            $query->execute();
            if ($rowCount = $query->rowCount() === 0)
                sendResponse(500, false, "There is an issue creating a user account - please try again.");

            $lastInsertId = $writeDB->lastInsertId();
            $returnData = array();
            $returnData['user_id'] = $lastInsertId;
            $returnData['username'] = $username;
            $returnData['pseudo'] = $pseudo;
            $returnData['email'] = $email;




            sendResponse(201, true, "User created.", null, $returnData);
        } catch (PDOException $ex) {
            error_log("DB query error : ".$ex, 0);
            sendResponse(500, false, "There was an issue creating a user account - please try again.");
        }
    }
    // GET USERS PER PAGE
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
//        if ($_SERVER['REDIRECT_URL'] !== '/api_rest/users/page') {
//            sendResponse(405, false, "Request method not allowed.");
//        }

        check_content_type("application/json");
        $rawPostData = file_get_contents("php://input");
        $jsonData = json_decode($rawPostData);
        check_json_body($jsonData);
        $page = $jsonData->page;
        $perPage = $jsonData->perPage;
        if (!isset($page) || !isset($perPage)) {
            $message = array();
            (!isset($page) ? $message[] = "Please provide a page number." : false);
            (!isset($perPage) ? $message[] = "Please provide a max number per page." : false);
            sendResponse(400, false, $message);
        }

        if (!is_numeric($page) || !is_numeric($perPage)) {
            $message = array();
            (!is_numeric($page) ? $message[] = "Number of page should be integer." : false);
            (!is_numeric($perPage) ? $message[] = "Max number per page should be integer." : false);
            sendResponse(400, false, $message);
        }
        if ($page < 1 || $perPage < 1) {
            $message = array();
            ($page < 1 ? $message[] = "Number of page should be positive." : false);
            ($perPage < 1 ? $message[] = "Max number per page should be positive." : false);
            sendResponse(400, false, $message);
        }
        $page = floor($page);
        $perPage = floor($perPage);

        try {
            $query = $readDB->prepare("
                SELECT count(id) as numberOfUsers 
                FROM users");
            $query->execute();
            $row = $query->fetch(PDO::FETCH_ASSOC);

            $usersCount = intval($row['numberOfUsers']);

            $numberOfPages = ceil($usersCount / $perPage);

            if ($numberOfPages == 0) {
                $numberOfPages = 1;
            }

            if ($page > $numberOfPages || $page == 0)
                sendResponse(404, false, "Page not found !");

            $offset = ($page == 1 ? 0 : $perPage * ($page - 1));

            $query = $readDB->prepare("
                SELECT id, username, pseudo, email 
                FROM users limit :pglimit offset :offset");
            $query->bindParam(":pglimit", $perPage, PDO::PARAM_INT);
            $query->bindParam(":offset", $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            $userArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $user = new User($row['id'], $row['username'], $row['pseudo'], $row['email']);
                $userArray[] = $user->returnUserArray();
            }

            $returnData = array();
            $returnData['row_returned'] = $rowCount;
            $returnData['totalRows'] = $usersCount;
            $returnData['total_pages'] = $numberOfPages;
            ($page < $numberOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
            $returnData['users'] = $userArray;
            sendResponse(200, true, "Page number : ".$page, null, $returnData);
        }
        catch (PDOException $ex) {
            sendResponse(500, false, $ex->getMessage());
        }
    }
    else {
        sendResponse(500, false, "There was an issue - Try again!");
    }
} else {
    sendResponse(500, false, "There was an issue - please try again.");
}
