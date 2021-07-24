<?php

require_once('../controller/db.php');
require_once('../model/Response.php');

try {
    $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) {
    // Send error message to errors history of the web service
    error_log("Connecting error : ".$ex, 0);
    sendResponse(500, false, "DB connection error.");
}

function check_email_format($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, false, "Please provide a valid email");
    }
}