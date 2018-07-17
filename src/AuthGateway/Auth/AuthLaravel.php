<?php

namespace AuthGateway\Auth;

use App\Factories\ModelFactory;
use App\Repositories\AccountRepository;

class AuthLaravel implements AuthStrategy
{
    private $repository;

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
        $accounts = $this->repository->findByFilters($filters, $perPage)->toArray();

        $transformed = array_map(function ($item) {
            return SimplestreamTransformer::transform($item);
        }, $accounts['data']);

        $accounts['data'] = $transformed;

        return $accounts;
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

    public function deleteUser($userId)
    {
        // TODO: Implement deleteUser() method.
    }
}