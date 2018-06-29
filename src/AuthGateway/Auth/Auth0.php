<?php

namespace AuthGateway\Auth;

use Auth0\SDK\Auth0 as Auth0SDK;
use Auth0\SDK\API\Management;
use Auth0\SDK\API\Authentication;
use Auth0\SDK\Exception\ApiException;
use Auth0\SDK\Exception\CoreException;
use Auth0\SDK\Exception\InvalidTokenException;
use GuzzleHttp\Exception\ClientException;

class Auth0 implements AuthStrategy
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var bool
     */
    private $auth0Enabled = false;

    private $authenticationClient;

    /**
     * Auth0 constructor.
     *
     * @param Auth0SDK $auth0
     * @param array $settings
     */
    public function __construct(Auth0SDK $auth0, array $settings = [])
    {
        $this->authenticationClient = $auth0;
        $this->auth0Enabled = true;

        if (!empty($settings)) {
            $this->domain = $settings['domain'];
            $this->clientId = $settings['clientId'];
            $this->clientSecret = $settings['clientSecret'];
        }
    }

    /**
     * authenticate
     *
     * @return string
     *
     * @throws \Auth0\SDK\Exception\ApiException
     * @throws \Auth0\SDK\Exception\CoreException
     * @throws \Exception
     */
    public function authenticate()
    {
        $auth0 = $this->getAuthenticationClient();
        $userInfo = $auth0->getUser();

        if (null === $userInfo) {
            return false;
        }

        $socialLogin = false;

        if (!isset($userInfo['https://metadata/auth0/userId'])) {
            $socialLogin = true;
        }

        $appMetadata = $this->getAppMetadata($userInfo['sub']);

        // @TODO: Probably should consider not formatting the response before sending back, each Auth0 client will have a different returned format
        $identity = (object)[
            'account_code' => md5($userInfo['sub']),
            'account_created' => null,
            'account_type' => $appMetadata['account_type'],
            'account_state' => 'active',
            'account_email' => $userInfo['email'],
            'account_first_name' => '-',
            'account_last_name' => '-',
            'subscription_state' => $appMetadata['account_type'],
            'social_login' => $socialLogin,
            'recurly_account_code' => isset($appMetadata['recurly']['account_code']) ? $appMetadata['recurly']['account_code'] : null,
        ];

        return $identity;
    }

    /**
     * login
     */
    public function login()
    {
        $auth0 = $this->getAuthenticationClient();
        $auth0->login();
    }

    /**
     * logout
     *
     * @return string
     */
    public function logout()
    {
        if ($this->auth0Enabled) {
            $auth0 = $this->getAuthenticationClient();
            $auth0->logout();
        }
    }

    /**
     * getAuthenticationClient
     *
     * @deprecated Remove when DI completed
     *
     * @return Auth0SDK
     */
    protected function getAuthenticationClient()
    {


        return new Auth0SDK([
            'domain' => $this->domain,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $prot . '://' . $_SERVER['HTTP_HOST'] . '/login',
            'audience' => 'https://' . $this->domain . '/api/v2/',
            'scope' => 'openid profile',
            'persist_id_token' => false,
            'persist_access_token' => true,
            'persist_refresh_token' => false,
        ]);
    }

    /**
     * getManagementClient
     *
     * @return Management
     * @throws \Exception
     */
    protected function getManagementClient()
    {
        if (!$this->token) {

            $auth0Auth = new Authentication($this->domain, $this->clientId, $this->clientSecret);

            try {

                $response = $auth0Auth->client_credentials(array('audience' => 'https://' . $this->domain . '/api/v2/'));
                $this->token = $response['access_token'];

            } catch (ApiException $e) {
                throw new \Exception('Internal Error');
            }
        }

        return new Management($this->token, $this->domain);
    }

    /**
     * getAppMetadata
     *
     * @param string $userId
     * @return array
     * @throws \Exception
     */
    protected function getAppMetadata($userId)
    {
        try {
            $auth0Mgm = $this->getManagementClient();
            $user = $auth0Mgm->users->get($userId);
        } catch (\Exception $exception) {
            throw new \Exception('Internal Error');
        }

        if (isset($user['app_metadata'])) {
            return $user['app_metadata'];
        }

        return array();

    }

    /**
     * @param string $userId
     * @return string
     * @throws \Exception
     */
    protected function setRecurlyAccountCode($userId)
    {
        try {
            $auth0Mgm = $this->getManagementClient();
            $accountCode = $userId;
            $auth0Mgm->users->update($userId, array('app_metadata' => array('recurly' => array('account_code' => $accountCode), 'termOfUseTimestamp' => date('c'))));
        } catch (\Exception $exception) {
            throw new \Exception('Internal Error');
        }

        return $accountCode;
    }

    /**
     * getUser
     *
     * @return mixed|string
     * @throws \Auth0\SDK\Exception\CoreException
     * @throws \Auth0\SDK\Exception\ApiException
     */
    public function getUser()
    {
        $auth0 = $this->getAuthenticationClient();
        return $auth0->getUser();
    }

    /**
     * createUser
     *
     * @param string $email
     * @param string $password
     *
     * @return string AccountCode|bool
     *
     * @throws \Exception
     */
    public function createUser($email, $password)
    {
        try {

            $auth0Mgm = $this->getManagementClient();
            $listUsers = $auth0Mgm->usersByEmail->get(array('email' => $email));

            // multiple users
            $accountCode = null;

            if (!empty($listUsers)) {

                $auth0Mgm->users->create(
                    array(
                        'connection' => 'Username-Password-Authentication',
                        'email' => $email,
                        'password' => $password,
                        'app_metadata' => array(
                            'multi_account' => true
                        )
                    )
                );

                return false;

            } else {

                $user = $auth0Mgm->users->create(
                    array(
                        'connection' => 'Username-Password-Authentication',
                        'email' => $email,
                        'password' => $password
                    )
                );

                $userId = $user['user_id'];
            }

            $accountCode = $this->setRecurlyAccountCode($userId);

            return $accountCode;

        } catch (ClientException $e) {

            if ($e->hasResponse()) {

                $response = $e->getResponse();

                if ($response->getStatusCode() == 400) {
                    $body = json_decode($response->getBody());
                    throw new \Exception($body->message);
                }
            }

            throw new \Exception('Something went wrong, please try again.');
        }
    }

    /**
     * changePassword
     *
     * @param string $password
     * @throws \Exception
     */
    public function changePassword($password)
    {

        $user = $this->getUser();
        if (!$user) {
            throw new \Exception('Invalid user');
        }
        $userId = $user['sub'];
        $auth0Mgm = $this->getManagementClient();
        $auth0Mgm->users->update($userId, array('password' => $password, 'connection' => 'Username-Password-Authentication'));
    }

    /**
     * changeEmail
     *
     * @param string $email
     * @throws \Exception
     */
    public function changeEmail($email)
    {
        $user = $this->getUser();

        if (!$user) {
            throw new \Exception('Invalid user');
        }

        $userId = $user['sub'];
        $auth0Mgm = $this->getManagementClient();
        $auth0Mgm->users->update($userId, array('email' => $email, 'client_id' => $this->clientId, 'connection' => 'Username-Password-Authentication'));
        $auth0Mgm->jobs->sendVerificationEmail($userId);
    }

    /**
     * sendEmailVerification
     *
     * @param string $token
     * @throws \Exception
     */
    public function sendEmailVerification($token)
    {
        $userId = base64_decode(preg_replace('#^[^:]+:#', '', urldecode($token)));

        if (preg_match('#^auth0#', $userId)) {
            try {
                $auth0Mgm = $this->getManagementClient();
                $auth0Mgm->jobs->sendVerificationEmail($userId);
            } catch (\Exception $e) {
                throw new \Exception('Internal Error');
            }
        }
    }
}