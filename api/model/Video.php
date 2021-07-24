<?php

class VideoException extends Exception {}

class Video {
    private $_id;
    private $_title;
    private $_filename;
    private $_mimetype;
    private $_userid;
    private $_uploadFolderLocation;

    public function __construct($id, $title, $filename, $mimetype, $userId) {
        $this->setId($id);
        $this->setTitle($title);
        $this->setFilename($filename);
        $this->setMimetype($mimetype);
        $this->setUserId($userId);
        $this->_uploadFolderLocation = "../../userVideos/user";
    }

    public function getId() {
        return $this->_id;
    }

    public function getTitle() {
        return $this->_title;
    }

    public function getFilename() {
        return $this->_filename;
    }

    public function getFileExtension() {
        $filenameParts = explode(".", $this->_filename);
        $lastArrayElement = count($filenameParts) - 1;
        $fileExtension = $filenameParts[$lastArrayElement];
        return $fileExtension;
    }

    public function getMimetype() {
        return $this->_mimetype;
    }

    public function getUserId() {
        return $this->_userid;
    }

    public function getUploadedFolderLocation() {
        return $this->_uploadFolderLocation;
    }

    public function getVideoURL() {
        $httpOrHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] = "on" ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];
        $url = "/api_rest/user/".$this->getUserId()."/videos/".$this->getId();
        return $httpOrHttps."://".$host.$url;
    }

    // Check how to do this for videos
    /**
     * @throws VideoException
     */
    public function returnVideoFile() {
        $filePath = $this->getUploadedFolderLocation().$this->getUserId().'/'.$this->getFilename();
        if (!file_exists($filePath))
            throw new VideoException("Video not found.");

        header('Content-Type: '.$this->getMimetype());
        header('Content-Disposition: inline; filename="'.$this->getFilename()).'"';
        if (!readfile($filePath))
            http_response_code(404);
        exit;
    }

    /**
     * @throws VideoException
     */
    public function setId($id) {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new VideoException("Video ID Error.");
        }
        $this->_id = $id;
    }

    /**
     * @throws VideoException
     */
    public function setTitle($title) {
        if (strlen($title) < 1 || strlen($title) > 255) {
            throw new VideoException("Video title error.");
        }
        $this->_title = $title;
    }

    /**
     * @throws VideoException
     */
    public function setFilename($filename) {
        if (strlen($filename) < 1 || strlen($filename) > 30 || ((preg_match("/^[a-zA-Z0-9_-]+(.mov)$/", $filename) != 1) && (preg_match("/^[a-zA-Z0-9_-]+(.mp4)$/", $filename) != 1))) {
            throw new VideoException("Filename error: .mov required");
        }
        $this->_filename = $filename;
    }

    /**
     * @throws VideoException
     */
    public function setMimetype($mimetype) {
        if (strlen($mimetype) < 1 || strlen($mimetype) > 255) {
            throw new VideoException("Video mimetype error.");
        }
        $this->_mimetype = $mimetype;
    }

    /**
     * @throws VideoException
     */
    public function setUserId($userId) {
        if (($userId !== null) && (!is_numeric($userId) || $userId <= 0 || $userId > 9223372036854775807 || $this->_userid !== null)) {
            throw new VideoException("User ID Error.");
        }
        $this->_userid = $userId;
    }

    public function deleteVideoFile() {
        $filePath = $this->getUploadedFolderLocation().$this->getUserId()."/".$this->getFilename();
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new VideoException("Failed to delete video file.");
            }
        }
    }

    public function getVideoPathToEncoding() {
//        $filePath = '/Applications/MAMP/htdocs/myYoutube/userVideos/user'.$this->getUserId();
        $filePath = 'C:\wamp64\www\myYoutube\userVideos\user'.$this->getUserId();
        return $filePath;
    }

    /**
     * @throws VideoException
     */
    public function saveVideoFile($tempFileName) {
        $uploadedFilePath = $this->getUploadedFolderLocation().$this->getUserId().'/'.$this->getFilename();
        if (!is_dir($this->getUploadedFolderLocation().$this->getUserId())) {
            if (!mkdir($this->getUploadedFolderLocation().$this->getUserId()))
                throw new VideoException("Failed to create video upload folder.");
        }

        if (!file_exists($tempFileName)) {
            throw new VideoException("Failed to upload video file");
        }

        if (!move_uploaded_file($tempFileName, $uploadedFilePath)) {
            throw new VideoException("Failed to upload video file!");
        }
    }

    /**
     * @throws VideoException
     */
    public function renameVideoFile($oldFilename, $newFilename) {
        $originalFilePath = $this->getUploadedFolderLocation().$this->getUserId().'/'.$oldFilename;
        $renamedFilePath = $this->getUploadedFolderLocation().$this->getUserId().'/'.$newFilename;

        if (!file_exists($originalFilePath))
            throw new VideoException("Cannot find video file to rename.");

        if (!rename($originalFilePath, $renamedFilePath))
            throw new VideoException("Failed to update the filename");
    }

    public function returnVideoArray() {
        $video = array();
        $video['id'] = $this->getId();
        $video['title'] = $this->getTitle();
        $video['filename'] = $this->getFilename();
        $video['mimetype'] = $this->getMimetype();
        $video['userid'] = $this->getUserId();
        $video['videourl'] = $this->getVideoUrl();
        return $video;
    }
}
