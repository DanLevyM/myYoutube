<?php

require_once("db.php");
require_once("../src/functions.php");
require_once("../model/Response.php");
require_once("../model/Video.php");

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

function uploadVideoRoute($readDB, $writeDB, $userid) {
    try {
        if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], "multipart/form-data; boundary=" === false))
            sendResponse(400, false, "Content type header not set to multipart/form-data with a boundary.");

        if (!isset($_POST['attributes']))
            sendResponse(400, false, "Attributes missing from body of request.");

        if (!$jsonVideoAttributes = json_decode($_POST['attributes']))
            sendResponse(400, false, "Attributes field is not valid JSON");

        if (!isset($jsonVideoAttributes->title) || !isset($jsonVideoAttributes->filename) || $jsonVideoAttributes->title == '' || $jsonVideoAttributes->filename == '')
            sendResponse(400, false, "Title and filename are mandatory.");

        if (strpos($jsonVideoAttributes->filename, ".") > 0)
            sendResponse(400, false, "Filename must not contain a file extension");

        if (!isset($_FILES['videofile']) || $_FILES['videofile']['error'] !== 0)
            sendResponse(500, false, "Video file upload unsuccessful - make sure you selected a file");

        if (isset($_FILES['videofile']['size']) && $_FILES['videofile']['size'] > 500000000)
            sendResponse(400, false, "File must be under 50MB.");

        $videoFileDetails = mime_content_type($_FILES['videofile']['tmp_name']);
//        var_dump(getimagesize($_FILES['imagefile']['tmp_name']));
//        var_dump(mime_content_type($_FILES['videofile']['tmp_name']));

        if ($videoFileDetails !== "video/quicktime" && $videoFileDetails !== "video/mp4")
            sendResponse(400, false, "File type not supported");

        // Check extension file ! to check

        $fileExtension = "";
        switch ($videoFileDetails) {
            case "video/quicktime":
                $fileExtension = ".mov";
                break;
            case "video/mp4":
                $fileExtension = ".mp4";
            default:
                break;
        }
        if ($fileExtension == "")
            sendResponse(400, false, "No valid file extension found for mimetype");

        $video = new Video(null, $jsonVideoAttributes->title, $jsonVideoAttributes->filename.$fileExtension, $videoFileDetails, $userid);
        $title = $video->getTitle();
        $newFileName = $video->getFilename();
        $mimetype = $video->getMimetype();
        $query = $writeDB->prepare("
            SELECT videos.id 
            FROM videos, users
            WHERE videos.userid = users.id AND users.id = :userid AND videos.filename = :filename");
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->bindParam(":filename", $newFileName, PDO::PARAM_STR);
        $query->execute();
        if ($rowCount = $query->rowCount() !== 0)
            sendResponse(409, false, "A file with that filename already exists");

        $writeDB->beginTransaction();
        $query= $writeDB->prepare("
            INSERT INTO videos (title, filename, mimetype, userid)
            VALUES (:title, :filename, :mimetype, :userid)");
        $query->bindParam(":title", $title, PDO::PARAM_STR);
        $query->bindParam(":filename", $newFileName, PDO::PARAM_STR);
        $query->bindParam(":mimetype", $mimetype, PDO::PARAM_STR);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();
        if ($rowCount = $query->rowCount() === 0) {
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(500, false, "Failed to upload video");
        }

        $lastVideoId = $writeDB->lastInsertId();
        $query = $writeDB->prepare("
            SELECT videos.id, videos.title, videos.filename, videos.mimetype, videos.userid
            FROM videos, users
            WHERE videos.id = :videoid AND users.id = :userid AND videos.userid = users.id");
        $query->bindParam(":videoid", $lastVideoId, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();
        $rowCount = $query->rowCount();
        if ($rowCount === 0) {
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(500, false, "Failed to retrieve video attributes after upload");
        }

        $videoArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
            $videoArray = $video->returnVideoArray();
        }

        $video->saveVideoFile($_FILES['videofile']['tmp_name']);
        $writeDB->commit();

        // GET INFOS FOR ENCODING REQUEST
        $query = $readDB->prepare("SELECT username, email FROM users WHERE id=$userid");
        $query->bindParam(":accesstoken", $accessToken, PDO::PARAM_STR);
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $username = $row['username'];
        $email = $row['email'];

        $videoWOExt = explode(".", $video->getFilename());
        $videoTitleEncoding = $videoWOExt[0];
        $task = array();
        $task['videoName'] = $videoTitleEncoding;
        $task['videoPath'] = $video->getVideoPathToEncoding();

        function httpPost($url, $dataEncoding)
        {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dataEncoding));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            curl_close($curl);
            return $response;
        }

        $obj = array("username" => $username, "email" => $email, "task" => $task);
        httpPost('http://localhost:3005/videos/encoding', $obj);


        sendResponse(201, true, "Video uploaded successfully", false, $videoArray);
    }
    catch (PDOException $ex) {
        error_log("Database query error ".$ex->getMessage(), 0);
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(500, false, "Failed to upload the video !");
    }
    catch (VideoException $ex) {
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }
        sendResponse(500, false, $ex->getMessage());
    }
}

function getVideoAttributesRoute($readDB, $userid, $videoid) {
    try {
        $query = $readDB->prepare("
            SELECT videos.id, videos.title, videos.filename, videos.mimetype, videos.userid
            FROM videos, users
            WHERE videos.id = :videoid 
              AND users.id = :userid
              AND videos.userid = users.id");
        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();

        if ($rowCount = $query->rowCount() !== 1)
            sendResponse(401, false, "Video not found.");

        $videoArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
            $videoArray[] = $video->returnVideoArray();
        }
        sendResponse(200, true, null, true, $videoArray);

    } catch (VideoException $ex) {
        sendResponse(500, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("DB query error : ".$ex, 0);
        sendResponse(500, false, "Failed to get video attributes");
    }

}

// Display video on Postman but does not work.
function getVideoRoute($readDB, $userid, $videoid) {
    var_dump("Does not work");
    die();
    try {
        $query = $readDB->prepare("
            SELECT videos.id, videos.title, videos.filename, videos.mimetype, videos.userid
            FROM videos, users
            WHERE videos.id = :videoid
            AND videos.userid = :userid
            AND videos.userid = users.id");
        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();

        if ($rowCount = $query->rowCount() == 0)
            sendResponse(404, false, "Video Not found!");

        $video = null;
        var_dump("test");

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);

        $video->returnVideoFile();

    } catch (VideoException $ex) {
        sendResponse(500, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log("Db query error: ".$ex, 0);
        sendResponse(400, false, "Error getting video.");
    }
}

function updateVideoAttributesRoute($writeDB, $userid, $videoid) {
    try {
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json')
            sendResponse(400, false, "Content type header not set to JSON");

        $rawPatchData = file_get_contents("php://input");
        if (!$jsonData = json_decode($rawPatchData))
            sendResponse(400, false, "Request body is not valid JSON");

        $title_updated = false;
        $filename_updated = false;

        $queryFields = "";
        if (isset($jsonData->title)) {
            $title_updated = true;
            $queryFields .= "videos.title = :title, ";
        }

        if (isset($jsonData->filename)) {
            if (strpos($jsonData->filename, ".") !== false)
                sendResponse(400, false, "Filename cannot contain any dots or file extension.");
            $filename_updated = true;
            $queryFields .= "videos.filename = :filename, ";
        }

        $queryFields = rtrim($queryFields, ", ");

        if ($title_updated === false && $filename_updated === false)
            sendResponse(400, false, "No video fields provided.");

        $writeDB->beginTransaction();
        $query = $writeDB->prepare("
            SELECT videos.id, videos.title, videos.filename, videos.mimetype, videos.userid
            FROM videos, users
            WHERE videos.id = :videoid
            AND videos.userid = :userid
            AND videos.userid = users.id");
        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() === 0) {
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(404, false, "No video found to update.");
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
        }

        $queryString = "UPDATE videos INNER JOIN users on videos.userid = users.id SET ".$queryFields." WHERE videos.id= :videoid AND videos.userid = :userid";
        $query = $writeDB->prepare($queryString);

        if ($title_updated == true) {
            $video->setTitle($jsonData->title);
            $up_title = $video->getTitle();
            $query->bindParam(":title", $up_title, PDO::PARAM_STR);
        }

        if ($filename_updated == true) {
            $originalFilename = $video->getFilename();
            $video->setFilename($jsonData->filename.".".$video->getFileExtension());
            $up_filename = $video->getFilename();
            $query->bindParam(":filename", $up_filename, PDO::PARAM_STR);
        }

        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() === 0) {
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(400, false, "Video attributes not updated - the given values may be the same as the stored values.");
        }

        $query = $writeDB->prepare("
            SELECT videos.id, videos.title, videos.filename, videos.mimetype, videos.userid
            FROM videos, users
            WHERE videos.id = :videoid
            AND users.id = :userid
            AND videos.userid = users.id");
        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();

        if ($query->rowCount() === 0) {
            if ($writeDB->inTransaction())
                $writeDB->rollBack();
            sendResponse(404, false, "No video found.");
        }

        $videoArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
            $videoArray[] = $video->returnVideoArray();
        }

        if ($filename_updated === true)
            $video->renameVideoFile($originalFilename, $up_filename);

        $writeDB->commit();
        sendResponse(200, true, "Video attributes updated", false, $videoArray);


    } catch (PDOException $ex) {
        error_log("Db query error: ".$ex, 0);
        if ($writeDB->inTransaction())
            $writeDB->rollBack();
        sendResponse(500, false, "Failed to update video attributes.");
    } catch (VideoException $ex) {
        if ($writeDB->inTransaction())
            $writeDB->rollBack();
        sendResponse(400, false, $ex->getMessage());
    }
}

function deleteVideoRoute($writeDB, $userid, $videoid) {
    try  {
        $writeDB->beginTransaction();
        $query = $writeDB->prepare("
            SELECT videos.id, videos.title, videos.filename, videos.mimetype, videos.userid
            FROM videos, users
            WHERE videos.id = :videoid
            AND videos.userid = :userid
            AND videos.userid = users.id");
        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() === 0) {
            $writeDB->rollBack();
            sendResponse(404, false, "Video not found.");
        }

        $video = null;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
        }
        if ($video == null) {
            $writeDB->rollBack();
            sendResponse(500, false, "Failed to get video.");
        }

        $query = $writeDB->prepare("
            DELETE videos
            FROM videos, users
            WHERE videos.id = :videoid
            AND videos.userid = :userid
            AND videos.userid = users.id");
        $query->bindParam(":videoid", $videoid, PDO::PARAM_INT);
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();

        if ($query->rowCount() === 0) {
            $writeDB->rollBack();
            sendResponse(404, false, "Video not found!");
        }

        $video->deleteVideoFile();
        $writeDB->commit();
        sendResponse(200, true, "Video deleted !");


    } catch (PDOException $ex) {
        error_log("DB query error: ".$ex, 0);
        $writeDB->rollBack();
        sendResponse(500, false, "Failed to delete image !");
    } catch (VideoException $ex) {
        $writeDB->rollBack();
        sendResponse(500, false, $ex->getMessage());
    }
}

function get_video_list_by_user($readDB, $userid) {
//    check_content_type("application/json");
//    $rawPostData = file_get_contents("php://input");
//    $jsonData = json_decode($rawPostData);
//    check_json_body($jsonData);
//    $page = $jsonData->page;
//    $perPage = $jsonData->perPage;
//    if (!isset($page) || !isset($perPage)) {
//        $message = array();
//        (!isset($page) ? $message[] = "Please provide a page number." : false);
//        (!isset($perPage) ? $message[] = "Please provide a max number per page." : false);
//        sendResponse(400, false, $message);
//    }
//    if (!is_numeric($page) || !is_numeric($perPage) || $page <= 0 || $perPage <= 0 || $userid < 1 || $userid > 9223372036854775807) {
//        $message = array();
//        (!is_numeric($page) ? $message[] = "Number of page should be integer." : false);
//        (!is_numeric($perPage) ? $message[] = "Max number of page should be integer." : false);
//        ($page <= 0 ? $message[] = "Page should be positive." : false);
//        ($perPage <= 0 ? $message[] = "PerPage should be positive." : false);
//        ($userid < 1 ? $message[] = "ID should be positive." : false);
//        ($userid > 9223372036854775807 ? $message[] = "ID out of range." : false);
//        sendResponse(400, false, $message);
//    }
//    $page = floor($page);
//    $perPage = floor($perPage);

    try {
        $query = $readDB->prepare("
            SELECT id
            FROM users
            WHERE id = :id");
        $query->bindParam(":id", $userid, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() !== 1)
            sendResponse(400, false, "Cannot find what you want.");

        $query = $readDB->prepare("
            SELECT count(videos.id) AS numberOfVideos 
            FROM videos, users 
            WHERE videos.userid = users.id
            AND videos.userid = :userid");
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() < 1)
            sendResponse(400, false, "Video not found.");

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $videosCount = intval($row['numberOfVideos']);
//        $numberOfPages = ceil($videosCount / $perPage);
//        if ($numberOfPages == 0)
//            $numberOfPages = 1;
//        if ($page > $numberOfPages || $page == 0)
//            sendResponse(404, false, "Page not found.");
//        $offset = ($page == 1 ? 0 : $perPage * ($page - 1));


        $query = $readDB->prepare("
            SELECT id, title, filename, mimetype, userid 
            FROM videos
            WHERE userid = :userid");
        $query->bindParam(":userid", $userid, PDO::PARAM_INT);
//        $query->bindParam(":pglimit", $perPage, PDO::PARAM_INT);
//        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        $videosArray = array();
        $p = 0;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
            $videosArray[$p] = $video->returnVideoArray();
            $p++;
        }

        $returnData = array();
//        $returnData['rows_returned'] = $rowCount;
//        $returnData['total_rows'] = $videosCount;
//        $returnData['total_pages'] = $numberOfPages;
//        ($page < $numberOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
//        ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
        $returnData['videos'] = $videosArray;
        sendResponse(200, true, "Page number : ".$page, null, $returnData);

    } catch (PDOException $ex) {
        error_log($ex, 0);
        sendResponse(500, false, "Failed to get videos.");
    } catch (VideoException $ex) {
        sendResponse(400, false, $ex->getMessage());
    }
}

function get_video_list($readDB) {
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
    if (!is_numeric($page) || !is_numeric($perPage) || $page <= 0 || $perPage <= 0) {
        $message = array();
        (!is_numeric($page) ? $message[] = "Number of page should be integer." : false);
        (!is_numeric($perPage) ? $message[] = "Max number of page should be integer." : false);
        ($page <= 0 ? $message[] = "Page should be positive." : false);
        ($perPage <= 0 ? $message[] = "PerPage should be positive." : false);
        sendResponse(400, false, $message);
    }
    $page = floor($page);
    $perPage = floor($perPage);

    try {
        if ($jsonData->user !== null) {
            $query = $readDB->prepare("
                SELECT id
                FROM users
                WHERE id = :id");
            $query->bindParam(":id", $jsonData->user, PDO::PARAM_INT);
            $query->execute();
            if ($query->rowCount() !== 1)
                sendResponse(400, false, "Cannot find what you want.");
        }

        $queryString = "SELECT count(videos.id) AS numberOfVideos FROM videos, users WHERE videos.userid = users.id";
        if ($jsonData->user !== null && is_numeric($jsonData->user))
            $queryString .= " AND videos.userid = :userid";

        $query = $readDB->prepare($queryString);
        if ($jsonData->user !== null)
            $query->bindParam(":userid", $jsonData->user);
        $query->execute();
        if ($query->rowCount() < 1)
            sendResponse(400, false, "Video not found.");

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $videosCount = intval($row['numberOfVideos']);
        $numberOfPages = ceil($videosCount / $perPage);
        if ($numberOfPages == 0)
            $numberOfPages = 1;
        if ($page > $numberOfPages || $page == 0)
            sendResponse(404, false, "Page not found.");
        $offset = ($page == 1 ? 0 : $perPage * ($page - 1));

        $queryString = "SELECT id, title, filename, mimetype, userid FROM videos";
        if ($jsonData->user !== null)
            $queryString .= " WHERE userid = :userid";
        $queryString .= " limit :pglimit offset :offset";
        $query = $readDB->prepare($queryString);
        if ($jsonData->user !== null)
            $query->bindParam(":userid", $jsonData->user, PDO::PARAM_INT);
        $query->bindParam(":pglimit", $perPage, PDO::PARAM_INT);
        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        $videosArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $video = new Video($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['userid']);
            $videosArray[] = $video->returnVideoArray();
        }

        $returnData = array();
        $returnData['rows_returned'] = $rowCount;
        $returnData['total_rows'] = $videosCount;
        $returnData['total_pages'] = $numberOfPages;
        ($page < $numberOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
        ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
        $returnData['videos'] = $videosArray;
        sendResponse(200, true, "Page number : ".$page, null, $returnData);

    } catch (PDOException $ex) {
        error_log($ex, 0);
        sendResponse(500, false, "Failed to get videos.");
    } catch (VideoException $ex) {
        sendResponse(400, false, $ex->getMessage());
    }
}


try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error : ".$ex->getMessage());
    sendResponse(500, false, "Db connection error !");
}


// users/1/videos/1/attributes
if (array_key_exists("userid", $_GET) && array_key_exists("videoid", $_GET) && array_key_exists("attributes", $_GET)) {
    $returned_userid = checkAuthStatusAndReturnUserId($writeDB);
    $userid = intval($_GET['userid']);
    $videoid = $_GET['videoid'];
    $attributes = $_GET['attributes'];

    if ($videoid == '' || !is_numeric($videoid) || $userid == '' || !is_numeric($userid))
        sendResponse(400, false, "Video ID or user ID error.");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getVideoAttributesRoute($readDB, $userid, $videoid);
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // check id = returned id
        if ($userid !== $returned_userid)
            sendResponse(501, false, "User not allowed.");
        updateVideoAttributesRoute($writeDB, $userid, $videoid);
    }
    else {
        sendResponse(405, false, "Request not allowed !!");
    }
}
// users/1/videos/1
elseif (array_key_exists("userid", $_GET) && array_key_exists("videoid", $_GET)) {
    $returned_userid = checkAuthStatusAndReturnUserId($writeDB);
    $userid = intval($_GET['userid']);
    $videoid = $_GET['videoid'];
    if ($videoid == '' || !is_numeric($videoid) || $userid == '' || !is_numeric($userid))
        sendResponse(400, false, "Video ID or user ID error!");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getVideoRoute($readDB, $userid, $videoid);
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        if ($userid !== $returned_userid)
            sendResponse(501, false, "User not allowed...");
        deleteVideoRoute($writeDB, $userid, $videoid);
    }
    else {
        sendResponse(405, false, "Request method not allowed.");
    }
}
// users/1/videos
elseif (array_key_exists("userid", $_GET) && !array_key_exists("videoid", $_GET)) {
    $userid = intval($_GET['userid']);

    if ($userid == '' || !is_numeric($userid))
        sendResponse(400, false, "Video ID or user ID error!");

    if ($_SERVER['REQUEST_METHOD'] === "POST") {
        $returned_userid = checkAuthStatusAndReturnUserId($writeDB);
        if ($returned_userid !== $userid)
            sendResponse(401, false, "You cannot do that!");


        uploadVideoRoute($readDB, $writeDB, $userid);
    }
    elseif ($_SERVER['REQUEST_METHOD'] === "GET") {
        get_video_list_by_user($readDB, $userid);
    }
    else {
        sendResponse(405, false, "Request method not allowed");
    }
}
elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] === "GET") {
        get_video_list($readDB);
    }
    else {
        var_dump("NOT GET");
    }
    exit;
}
else {
    sendResponse(404, false, "Endpoint not found !!");
}

