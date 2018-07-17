<?php

namespace AuthGateway\Auth;

interface AuthStrategy
{
    /**
     * Method authenticate
     *
     * @return mixed
     */
    public function authenticate();

    /**
     * Method login
     *
     * @return mixed
     */
    public function login();

    /**
     * Method logout
     *
     * @return mixed
     */
    public function logout();

    /**
     * Method getUsers
     *
     * @param array $filters
     * @param int   $page
     * @param int   $perPage
     *
     * @return mixed
     */
    public function getUsers($filters = [], $page = 1, $perPage = 10);

    /**
     * Method getUser
     *
     * @return mixed
     */
    public function getUser();

    /**
     * Method getUserById
     *
     * @param $userId
     * @return mixed
     */
    public function getUserById($userId);

    /**
     * Method createUser
     *
     * @param $email
     * @param $password
     *
     * @return mixed
     */
    public function createUser($email, $password);

    /**
     * Method updateUser
     *
     * @param string $userId
     * @param array  $data
     *
     * @return mixed
     */
    public function updateUser($userId, array $data);
}