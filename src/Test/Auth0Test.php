<?php

use PHPUnit\Framework\TestCase;
use Faker\Factory;
use Dotenv\Dotenv;
use AuthGateway\Auth\Auth0;

class Auth0Test extends TestCase
{
    private $auth0;

    public function setUp()
    {
        @session_start();
        parent::setUp();

        $dotenv = new Dotenv(dirname(__DIR__, 2));
        $dotenv->load();

        /** @TODO: Find out how to Mock Auth0 */
        $this->auth0 = new Auth0([
            'domain' => getenv('AUTH0_DOMAIN'),
            'client_id' => getenv('AUTH0_CLIENT_ID'),
            'client_secret' => getenv('AUTH0_CLIENT_SECRET'),
        ]);
    }

    public function providerChangeEmail()
    {
        $faker = Factory::create();

        return [
            ['auth0|47547f3eef3cbf6f1327db318688db97', 'rgskinner@werdigital.co.uk']
        ];
    }

    public function providerUpdateUserData()
    {
        $faker = Factory::create();

        return [
            [
                'auth0|47547f3eef3cbf6f1327db318688db97',
                [
                    'connection' => 'Username-Password-Authentication',
                    'user_metadata' => [
                        'first_name' => $faker->firstName,
                        'last_name' => $faker->lastName,
                        'account_type' => 'regular',
                        'company_id' => 122,
                        'recurly' => [
                            'account_code' => 'auth0|47547f3eef3cbf6f1327db318688db97'
                        ]
                    ]
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
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'account_type' => 'regular',
                    'company_id' => 122
                ]
            ]
        ];
    }

    public function providerGetUser()
    {
        return [
            ['auth0|47547f3eef3cbf6f1327db318688db97']
        ];
    }

    /**
     * @dataProvider providerGetUser
     *
     * @param $userId
     *
     * @throws Exception
     */
    public function testGetUserById($userId)
    {
        $this->assertArrayHasKey('id', $this->auth0->getUserById($userId));
    }

    /**
     * @dataProvider providerUpdateUserData
     *
     * @param $userId
     */
    public function testUpdateUser($userId, $data)
    {
        $response = $this->auth0->updateUser($userId, $data);

        $this->assertArraySubset(['user_metadata' => ['company_id' => 122]], $response);
    }

    /**
     * @dataProvider providerChangeEmail
     * @param $userId
     * @param $email
     */
    public function testChangeEmail($userId, $email)
    {
        $response = $this->auth0->changeEmail($userId, $email);

        $this->assertArraySubset(['status' => 'pending'], $response);
    }

    /**
     * @dataProvider providerCreateUser
     *
     * @param $email
     * @param $password
     * @param $metadata
     */
    public function testCreateUser($email, $password, $metadata)
    {
        $userId = $this->auth0->createUser($email, $password, $metadata);
        $this->assertRegExp('^[auth0].*$^', $userId);
    }
}