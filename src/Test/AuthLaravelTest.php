<?php

use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\TestCase;
use Faker\Factory;
use AuthGateway\Auth\AuthLaravel;

class AuthLaravelTest extends TestCase
{
    private $authLaravel = null;

    public function setUp()
    {
        @session_start();
        Dotenv::load(dirname(__DIR__, 2));

        Auth::shouldReceive('user')->andReturn($user = \Mockery::mock('StdClass'))->once();
        $user->company_id = 122;

        $this->authLaravel = new AuthLaravel([
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
                'filters' => [
                    'name_or_email' => 'richard'
                ]
            ]
        ];
    }

    public function providerUserId()
    {
        return [
            [302163]
        ];
    }

    public function providerUpdateUser()
    {
        $faker = Factory::create();

        return [
            [
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
    public function testGetUsers($filters)
    {
        $accounts = $this->authLaravel->getUsers($filters);
        $this->assertArrayHasKey('data', $accounts);
    }

    /**
     * @dataProvider providerUserId
     * @param $userId
     */
    public function testGetUserById($userId)
    {
        $account = $this->authLaravel->getUserById($userId);
        $this->assertArrayHasKey('id', $account);
    }

    /**
     * @dataProvider providerUpdateUser
     */
    public function testUpdateUser($userId, $data)
    {
        $updated = $this->authLaravel->updateUser($userId, $data);
        $this->assertGreaterThan(0, $updated);
    }

    /**
     * @dataProvider providerCreateUser
     * @param $email
     * @param $password
     * @param $data
     */
    public function testCreateUser($email, $password, $data)
    {
        $created = $this->authLaravel->createUser($email, $password, $data);
        $this->assertTrue($created);

    }
}