<?php

use PHPUnit\Framework\TestCase;
use Faker\Factory;
use Dotenv\Dotenv;
use AuthGateway\Auth\Auth0;

class Auth0Test extends TestCase
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        $dotenv = new Dotenv(dirname(__DIR__, 2));
        $dotenv->load();

        parent::__construct($name, $data, $dataName);
    }

    public function providerUserData()
    {
        return [
            [
                'account_code' => null,
                'account_created' => null,
                'account_type' => null,
                'account_state' => null,
                'account_email' => null,
                'account_first_name' => null,
                'account_last_name' => null,
                'subscription_state' => null,
                'social_login' => null,
                'recurly_account_code' => null,
            ]
        ];
    }

    public function providerCreateUserAuth0()
    {
        $faker = Factory::create();

        return [
            [$faker->email, $faker->password, isset($_SERVER['HTTPS']) ? 'https' : 'http']
        ];
    }

    public function providerCreateUser()
    {
        return [
            []
        ];
    }

    public function providerProtocol()
    {
        return [
            []
        ];
    }

//    public function testFailedAuthenticationOfUser()
//    {
//        $dotenv = new Dotenv(dirname(__DIR__, 2));
//        $dotenv->load();
//
//        $auth0 = \AuthGateway\Auth\AuthGateway::getInstance(\AuthGateway\Auth\AuthGateway::AUTH0, [
//            'domain' => getenv('DOMAIN'),
//            'clientId' => getenv('CLIENT_ID'),
//            'clientSecret' => getenv('CLIENT_SECRET')
//        ]);
//
//        $authenticate = $auth0->authenticate();
//
//        $this->assertFalse($authenticate);
//    }

    /**
     * @dataProvider providerCreateUserAuth0
     */
    public function testCreateUserAccount($username, $password, $prot)
    {
        try {
            $auth0SDK = $this->getMockClass('\Auth0\SDK\Auth0', [], [
                'domain' => getenv('AUTH0_DOMAIN'),
                'client_id' => getenv('AUTH0_CLIENT_ID'),
                'client_secret' => getenv('AUTH0_CLIENT_SECRET'),
                'redirect_uri' => $prot . '://' . $_SERVER['HTTP_HOST'] . '/login',
                'audience' => 'https://' . getenv('AUTH0_DOMAIN') . '/api/v2/',
                'scope' => 'openid profile',
                'persist_id_token' => false,
                'persist_access_token' => true,
                'persist_refresh_token' => false,
            ]);

            var_dump($auth0SDK);

//            $auth0 = new Auth0($auth0SDK, [
//                'domain' => getenv('HH_DOMAIN'),
//                'clientId' => getenv('HH_CLIENT_ID'),
//                'clientSecret' => getenv('HH_CLIENT_SECRET')
//            ]);
//
//            $userID = $auth0->createUser($username, $password);
//
//            $this->assertRegExp('^auth0\|[a-zA-Z0-9]*^', $userID);

        } catch (Exception $exception) {
            // ...
        } catch (ReflectionException $exception) {
            // ...
        }
    }
}