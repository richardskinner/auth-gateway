<?php

namespace AuthGateway\Auth;

use App\Factories\ModelFactory;
use App\Repositories\AccountRepository;

class AuthLaravel implements AuthStrategy
{
    private $repository;
    private $filters;

    public function __construct()
    {
        $this->repository = new AccountRepository(new ModelFactory());
    }

    public function authenticate()
    {
        // TODO: Implement authenticate() method.
    }

    public function login()
    {
        // TODO: Implement login() method.
    }

    public function logout()
    {
        // TODO: Implement logout() method.
    }

    public function getUsers($filters = [], $page = 0, $perPage = 15)
    {
        return $this->repository->findByFilters($filters, $perPage);
    }

    public function createUser($email, $password)
    {
        // TODO: Implement createUser() method.
    }

    public function getUser()
    {
        // TODO: Implement getUser() method.
    }

    public function updateUser($userId, array $data)
    {
        // TODO: Implement updateUser() method.
    }

    public function getUserById($userId)
    {
        // TODO: Implement getUserById() method.
    }
}