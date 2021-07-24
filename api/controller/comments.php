<?php

require_once("db.php");
require_once("../model/Comment.php");
require_once("../model/Response.php");
require_once("../src/functions.php");

function add_comment($writeDB, $videoId) {
    $returned_userid = checkAuthStatusAndReturnUserId($writeDB);
    check_content_type("application/json");
    $rawPostData = file_get_contents("php://input");
    $jsonData = json_decode($rawPostData);
    check_json_body($jsonData);

    if (!isset($jsonData->body))
        sendResponse(400, false, "Body not supplied");

    if (strlen($jsonData->body) < 1 || strlen($jsonData->body) > 1000) {
        $message = array();
        (strlen($jsonData->body) < 1 ? $message[] = "Username cannot be blank." : false);
        (strlen($jsonData->body) > 1000 ? $message[] = "Username cannot be greater than 60 characters." : false);
        sendResponse(400, false, $message);
    }

    try {
        $writeDB->beginTransaction();
        $query = $writeDB->prepare("
            SELECT id
            FROM videos
            WHERE id = :id");
        $query->bindParam(":id", $videoId, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() !== 1)
            sendResponse(404, false, "Not found.");

        $query = $writeDB->prepare("
            INSERT INTO comments (body, user_id, video_id)
            VALUES (?, ?, ?)");
        $query->bindParam(1, $jsonData->body, PDO::PARAM_STR);
        $query->bindParam(2, $returned_userid, PDO::PARAM_INT);
        $query->bindParam(3, $videoId, PDO::PARAM_INT);
        $query->execute();
        var_dump($query->rowCount());

        if ($query->rowCount() !== 1) {
            $writeDB->rollBack();
            sendResponse(500, false,"Failed creating the comment.");
        }

        $lastInsertID = $writeDB->lastInsertId();
        $commentArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $comment = new Comment($lastInsertID, $row['body'], $row['user_id'], $row['video_id']);
            $commentArray[] = $comment->returnCommentArray();
        }
        $writeDB->commit();
        sendResponse(201, true, "Comment added.", false, $commentArray);

    } catch (PDOException $ex) {
        error_log($ex, 0);
        if ($writeDB->inTransaction())
            $writeDB->rollBack();
        sendResponse(500, false, "There was an issue creating a comment");
    } catch (CommentException $ex) {
        if ($writeDB->inTransaction())
            $writeDB->rollBack();
        sendResponse(500, false, $ex->getMessage());
    }
}

function comment_list($readDB, $videoId) {
    $returned_userid = checkAuthStatusAndReturnUserId($readDB);
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
    if (!is_numeric($page) || !is_numeric($perPage) || $page <= 0 || $perPage <= 0 || $videoId < 1 || $videoId > 9223372036854775807) {
        $message = array();
        (!is_numeric($page) ? $message[] = "Number of page should be integer." : false);
        (!is_numeric($perPage) ? $message[] = "Max number of page should be integer." : false);
        ($page <= 0 ? $message[] = "Page should be positive." : false);
        ($perPage <= 0 ? $message[] = "PerPage should be positive." : false);
        ($videoId < 1 ? $message[] = "ID should be positive." : false);
        ($videoId > 9223372036854775807 ? $message[] = "ID out of range." : false);
        sendResponse(400, false, $message);
    }
    $page = floor($page);
    $perPage = floor($perPage);

    try {
        $query = $readDB->prepare("
            SELECT id
            FROM videos
            WHERE id = :id");
        $query->bindParam(":id", $videoId, PDO::PARAM_INT);
        $query->execute();
        if ($query->rowCount() !== 1)
            sendResponse(400, false, "Cannot find video.");

        $query = $readDB->prepare("
            SELECT count(comments.id) AS numberOfComments
            FROM videos, comments
            WHERE videos.id = comments.video_id
            AND comments.video_id = :videoId");
        $query->bindParam(":videoId", $videoId, PDO::PARAM_INT);
        $query->execute();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $commentsCount = intval($row['numberOfComments']);
        }

        if ($query->rowCount() < 1 || $commentsCount <= 0)
            sendResponse(400, false, "Comment not found");

        $numberOfPages = ceil($commentsCount / $perPage);
        if ($numberOfPages == 0)
            $numberOfPages = 1;
        if ($page > $numberOfPages || $page == 0)
            sendResponse(404, false, "Page not found.");
        $offset = ($page == 1 ? 0 : $perPage * ($page - 1));
        var_dump($videoId, $perPage, $offset);

        $query = $readDB->prepare("
            SELECT id, body, user_id, video_id
            FROM comments
            WHERE comments.video_id = :videoId limit :pglimit offset :offset");
        $query->bindParam(":videoId", $videoId, PDO::PARAM_INT);
        $query->bindParam(":pglimit", $perPage, PDO::PARAM_INT);
        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        $commentArray = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $comment = new Comment($row['id'], $row['body'], $row['user_id'], $row['video_id']);
            $commentArray[] = $comment->returnCommentArray();
        }

        $returnData = array();
        $returnData['rows_returned'] = $rowCount;
        $returnData['total_rows'] = $commentsCount;
        $returnData['total_pages'] = $numberOfPages;
        ($page < $numberOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
        ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
        $returnData['comments'] = $commentArray;
        sendResponse(200, true, "Page number : ".$page, null, $returnData);

    } catch (CommentException $ex) {
        sendResponse(400, false, $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex, 0);
        sendResponse(500, false, "Failed to get comments.");
    }
}

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error : ".$ex->getMessage());
    sendResponse(500, false, "Db connection error !");
}

if (array_key_exists("id", $_GET)) {
    $videoId = intval($_GET['id']);
    if ($_SERVER['REQUEST_METHOD'] === "POST") {
        add_comment($writeDB, $videoId);
    }
    elseif ($_SERVER['REQUEST_METHOD'] === "GET") {
        comment_list($readDB, $videoId);
    }
}
else {
    sendResponse(404, false, "Endpoint not found !!");
}
exit;