<?php

use PHPUnit\Framework\TestCase;
use Faker\Factory;

class AuthSimpleStreamTest extends TestCase
{
    private $authZend = null;

    public function setUp()
    {
        @session_start();
        Dotenv::load(dirname(__DIR__, 2));

        $this->authZend = new \AuthGateway\Auth\Strategy\SimpleStream([
            'driver' => getenv('DB_CONNECTION'),
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
        ]);
    }

    public function providerFilters()
    {
        return [
            [
                122,
                'filters' => [
                    'name_or_email' => 'richard',
                ]
            ]
        ];
    }

    public function providerUserId()
    {
        return [
            [
                122,
                302163
            ]
        ];
    }

    public function providerUpdateUser()
    {
        $faker = Factory::create();

        return [
            [
                122,
                302163,
                [
                    'account_code' => 302163,
                    'account_password' => $faker->password,
                    'account_email' => null,
                    'account_first_name' => $faker->firstName,
                    'account_last_name' => $faker->lastName,
                ]
            ]
        ];
    }

    public function providerCreateUser()
    {
        $faker = Factory::create();

        return [
            [
                122,
                $faker->email,
                $faker->password,
                [
                    'account_first_name' => $faker->firstName,
                    'account_last_name' => $faker->lastName,
                ]
            ]
        ];
    }

    /**
     * @dataProvider providerFilters
     */
    public function testGetUsers($companyId, $filters)
    {
        $accounts = $this->authZend->getUsers($companyId, $filters);
        $this->assertArrayHasKey('data', $accounts);
    }

    /**
     * @dataProvider providerUserId
     * @param $userId
     */
    public function testGetUserById($companyId, $userId)
    {
        $account = $this->authZend->getUserById($companyId, $userId);
        $this->assertArrayHasKey('id', $account);
    }

    /**
     * @dataProvider providerUpdateUser
     */
    public function testUpdateUser($companyId, $userId, $data)
    {
        $updated = $this->authZend->updateUser($companyId, $userId, $data);

        $this->assertGreaterThan(0, $updated);
    }

    /**
     * @dataProvider providerCreateUser
     * @param $email
     * @param $password
     * @param $data
     */
    public function testCreateUser($companyId, $email, $password, $data)
    {
        $created = $this->authZend->createUser($companyId, $email, $password, $data);
        $this->assertTrue($created);
    }
}