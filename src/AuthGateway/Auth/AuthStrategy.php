<?php

namespace AuthGateway\Auth;

interface AuthStrategy
{
    /**
     * authenticate
     *
     * @return mixed
     */
    public function authenticate();

    /**
     * login
     *
     * @return mixed
     */
    public function login();

    /**
     * logout
     *
     * @return mixed
     */
    public function logout();
}