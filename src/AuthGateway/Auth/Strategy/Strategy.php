<?php

namespace AuthGateway\Auth\Strategy;

interface Strategy
{
    /**
     * Method authenticate
     *
     * @param integer $companyId 
     * @param string $email
     * @param string $password
     *
     * @return boolean
     */
    public function authenticate($companyId, $email, $password);

    /**
     * Method login
     *
     * @return mixed
     */
    public function login();

    /**
     * Method logout
     * @return mixed
     */
    public function logout();

    /**
     * Method getUsers
     *
     * @param integer $companyId
     * @param array $filters
     * @param int   $page
     * @param int   $perPage
     *
     * @return mixed
     */
    public function getUsers($companyId, $filters = [], $page = 1, $perPage = 10);

    /**
     * Method getUser
     *
     * Gets the user from session
     *
     * @return mixed
     */
    public function getUser();

    /**
     * Method getUserById
     *
     * @param integer $companyId
     * @param $userId
     *
     * @return mixed
     */
    public function getUserById($companyId, $userId);

    /**
     * Method getUserByEmail
     *
     * @param $companyId
     * @param $email
     * @return mixed
     */
    public function getUserByEmail($companyId, $email);

    /**
     * Method createUser
     *
     * @param integer $companyId
     * @param string $email
     * @param string $password
     * @param array  $data
     *
     * @return mixed
     */
    public function createUser($companyId, $email, $password, array $data);

    /**
     * Method updateUser
     *
     * @param integer $companyId
     * @param string $userId
     * @param array  $data
     *
     * @return mixed
     */
    public function updateUser($companyId, $userId, array $data);

    /**
     * Method deleteUser
     *
     * @param $userId
     *
     * @return integer
     */
    public function deleteUser($companyId, $userId);
}