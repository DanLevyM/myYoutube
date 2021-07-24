<?php

class CommentException extends Exception { }

class Comment {
    private $_id;
    private $_body;
    private $_user_id;
    private $_video_id;

    public function __construct($id, $body, $userid, $videoid) {
        $this->setID($id);
        $this->setBody($body);
        $this->setUserID($userid);
        $this->setVideoID($videoid);
    }

    public function getID() {
        return $this->_id;
    }

    public function getBody() {
        return $this->_body;
    }

    public function getUserID() {
        return $this->_user_id;
    }

    public function getVideoID() {
        return $this->_video_id;
    }

    /**
     * @throws CommentException
     */
    public function setID($id) {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new CommentException("Comment ID error");
        }
        $this->_id = $id;
    }

    /**
     * @throws CommentException
     */
    public function setBody($body) {
        if (strlen($body) <= 0 || strlen($body) > 1000) {
            throw new CommentException("Body error");
        }
        $this->_body = $body;
    }

    /**
     * @throws CommentException
     */
    public function setUserID($userid) {
        if (($userid !== null) && (!is_numeric($userid) || $userid <= 0 || $userid > 9223372036854775807)) {
            throw new CommentException("User ID error");
        }
        $this->_user_id = $userid;
    }

    /**
     * @throws CommentException
     */
    public function setVideoID($videoid) {
        if (($videoid !== null) && (!is_numeric($videoid) || $videoid <= 0 || $videoid > 9223372036854775807)) {
            throw new CommentException("User ID error");
        }
        $this->_video_id = $videoid;
    }

    public function returnCommentArray() {
        $commentArray = array();
        $commentArray['id'] = $this->getID();
        $commentArray['body'] = $this->getBody();
        $commentArray['user_id'] = $this->getUserID();
        $commentArray['video_id'] = $this->getVideoID();
        return $commentArray;
    }

}


?>