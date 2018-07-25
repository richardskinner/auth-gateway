<?php

use PHPUnit\Framework\TestCase;
use Faker\Factory;
use AuthGateway\Auth\Strategy\Auth0;

class Auth0Test extends TestCase
{
    private $auth0;

    public function setUp()
    {
        @session_start();
        parent::setUp();

        Dotenv::load(dirname(__DIR__, 2));

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
            [
                122,
                'auth0|47547f3eef3cbf6f1327db318688db97',
                'rgskinner@werdigital.co.uk'
            ]
        ];
    }

    public function providerUpdateUser()
    {
        $faker = Factory::create();

        return [
            [
                122,
                'auth0|47547f3eef3cbf6f1327db318688db97',
                [
                    'connection' => 'Username-Password-Authentication',
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'account_code' => 'auth0|47547f3eef3cbf6f1327db318688db97'
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
            [
                122,
                'auth0|5a8308d99bf9bc6ee2544892'
            ]
        ];
    }

    public function providerDeleteUser()
    {
        return [
            ['auth0|5a8308d99bf9bc6ee2544892']
        ];
    }

    /**
     * @dataProvider providerGetUser
     *
     * @param $userId
     *
     * @throws Exception
     */
    public function testGetUserById($companyId, $userId)
    {
        $this->assertArrayHasKey('id', $this->auth0->getUserById($companyId, $userId));
    }

    /**
     * @dataProvider providerUpdateUser
     *
     * @param $userId
     */
    public function testUpdateUser($companyId, $userId, $data)
    {
        $response = $this->auth0->updateUser($companyId, $userId, $data);

        $this->assertArraySubset(['user_metadata' => ['company_id' => 122]], $response);
    }

    /**
     * @dataProvider providerChangeEmail
     * @param $userId
     * @param $email
     */
    public function testChangeEmail($companyId, $userId, $email)
    {
        $response = $this->auth0->changeEmail($companyId, $userId, $email);

        $this->assertArraySubset(['status' => 'pending'], $response);
    }

    /**
     * @dataProvider providerCreateUser
     *
     * @param $email
     * @param $password
     * @param $metadata
     */
    public function testCreateUser($companyId, $email, $password, $metadata)
    {
        $userId = $this->auth0->createUser($companyId, $email, $password, $metadata);
        $this->assertRegExp('^[auth0].*$^', $userId);
    }

    /**
     * @dataProvider providerDeleteUser
     *
     * @param $userId
     */
    public function testDeleteUser($userId)
    {
        $this->assertTrue(true);
    }
}