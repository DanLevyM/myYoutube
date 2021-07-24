<?php

require_once("../src/user_functions.php");

class UserException extends Exception {

}

class User {
    private $_id;
    private $_username;
    private $_pseudo;
    private $_email;
    private $_loginattempts;

    public function __construct($id, $username, $pseudo, $email) {
        $this->setID($id);
        $this->setUsername($username);
        $this->setPseudo($pseudo);
        $this->setEmail($email);
    }

    public function getId() {
        return $this->_id;
    }

    public function getUsername() {
        return $this->_username;
    }

    public function getPseudo() {
        return $this->_pseudo;
    }

    public function getEmail() {
        return $this->_email;
    }

    public function getLogginattempts() {
        return $this->_loginattempts;
    }

    /**
     * @throws UserException
     */
    public function setID($id) {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new UserException("User ID error");
        }
        $this->_id = $id;
    }

    public function setUsername($username) {
        if (strlen($username) <= 0 || strlen($username) > 60) throw new UserException("Username error");
        $this->_username = $username;
    }

    /**
     * @throws UserException
     */
    public function setPseudo($pseudo) {
        if (strlen($pseudo) <= 0 || strlen($pseudo) > 60) {
            throw new UserException("Pseudo error");
        }
        $this->_pseudo = $pseudo;
    }

    public function setEmail($email) {
        check_email_format($email);
        $this->_email = $email;
    }

    /**
     * @throws UserException
     */
    public function setLogginattempts($loginattempts) {
        if (($loginattempts !== null) && (!is_numeric($loginattempts) || $loginattempts < 0 || $loginattempts > 9223372036854775807 || $this->_loginattempts !== null)) {
            throw new UserException("User ID error");
        }
        $this->_loginattempts = $loginattempts;
    }

    public function returnUserArray() {
        $userArray = array();
        $userArray['id'] = $this->getId();
        $userArray['username'] = $this->getUsername();
        $userArray['pseudo'] = $this->getPseudo();
        $userArray['email'] = $this->getEmail();
        $userArray['logginattempts'] = $this->getLogginattempts();
        return $userArray;
    }

}