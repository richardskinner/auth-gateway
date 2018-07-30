<?php

namespace AuthGateway\Auth\Strategy;

use AuthGateway\Auth\Strategy\Strategy as StrategyInterface;
use AuthGateway\Auth\Transformers\Auth0 as Auth0Transformer;
use Auth0\SDK\Auth0 as Auth0SDK;
use Auth0\SDK\API\Management;
use Auth0\SDK\API\Authentication;
use GuzzleHttp\Exception\ClientException;

class Auth0 implements StrategyInterface
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

    /**
     * @var Auth0SDK
     */
    private $authenticationClient;

    /**
     * @var Management
     */
    private $managementClient;

    /**
     * Auth0 constructor.
     *
     * @param array $settings
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        if (!empty($settings)) {
            $this->domain = $settings['domain'];
            $this->clientId = $settings['client_id'];
            $this->clientSecret = $settings['client_secret'];
        }

        $this->authenticationClient = $this->getAuth0SDK();
        $this->managementClient = $this->getManagementClient();
        $this->auth0Enabled = true;
    }

    /**
     * authenticate
     *
     * @return array|bool
     *
     * @throws \Auth0\SDK\Exception\ApiException
     * @throws \Auth0\SDK\Exception\CoreException
     * @throws \Exception
     */
    public function authenticate($companyId, $email, $password)
    {
        $userInfo = $this->authenticationClient->getUser();

        if (null === $userInfo) {
            return false;
        }

        $socialLogin = false;

        if (!isset($userInfo['https://metadata/auth0/userId'])) {
            $socialLogin = true;
        }

        $userInfo['user_id'] = $userInfo['sub'];
        $userMetadata = $this->getUserMetadata($userInfo['sub']);

        return Auth0Transformer::transform(array_merge($userInfo, $userMetadata));
    }

    /**
     * login
     */
    public function login()
    {
        $this->authenticationClient->login();
    }

    /**
     * logout
     *
     * @return string
     */
    public function logout()
    {
        if ($this->auth0Enabled) {
            $this->authenticationClient->logout();
        }
    }

    /**
     * getUser
     *
     * Gets logged in user
     *
     * @return array|mixed|null
     * @throws \Auth0\SDK\Exception\ApiException
     * @throws \Auth0\SDK\Exception\CoreException
     */
    public function getUser()
    {
        return $this->authenticationClient->getUser();
    }

    /**
     * getUsers
     *
     * @param array $filters
     * @param int   $page
     * @param int   $perPage
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function getUsers($companyId, $filters = [], $page = 0, $perPage = 10)
    {
        $accounts = $this->managementClient->users->getAll(['include_totals' => true], null, true, $page, $perPage);

        $transformed = array_map(function ($item) {
            return Auth0Transformer::transform($item);
        }, $accounts['users']);

        $accounts['data'] = $transformed;
        unset($accounts['users']);

        return $accounts;
    }

    /**
     * getUserById
     *
     * @param string $userId
     *
     * @throws \Exception
     *
     * @return array|mixed|null
     */
    public function getUserById($companyId, $userId)
    {
        return Auth0Transformer::transform(
            $this->managementClient->users->get($userId)
        );
    }

    /**
     * getUserByEmail
     *
     * @param string $companyId
     * @param string $userEmail
     *
     * @throws \Exception
     *
     * @return array|mixed|null
     */
    public function getUserByEmail($companyId, $userEmail)
    {
        return Auth0Transformer::transform(
            $this->managementClient->usersByEmail->get($userEmail)
        );
    }

    /**
     * createUser
     *
     * @param string $email
     * @param string $password
     * @param array  $metadata
     *
     * @return string AccountCode|bool
     *
     * @throws \Exception
     */
    public function createUser($companyId, $email, $password, array $data)
    {
        try {
            $listUsers = $this->managementClient->usersByEmail->get(array('email' => $email));

            // multiple users
            $accountCode = null;

            if (!empty($listUsers)) {
                // @TODO: Needs some sort of transformer
                $this->managementClient->users->create(
                    array(
                        'connection' => 'Username-Password-Authentication',
                        'email' => $email,
                        'password' => $password,
                        'user_metadata' => array(
                            'multi_account' => true
                        )
                    )
                );

                return false;

            } else {
                // @TODO: Needs some sort of transformer
                $user = $this->managementClient->users->create(
                    array(
                        'connection' => 'Username-Password-Authentication',
                        'email' => $email,
                        'password' => $password,
                        'name' => (isset($data['first_name']) ? $data['first_name'] : null) . ' ' . (isset($data['last_name']) ? $data['last_name'] : null),
                        'user_metadata' => [
                            'first_name' => isset($data['account_first_name']) ? $data['account_first_name'] : null,
                            'last_name' => isset($data['account_last_name']) ? $data['account_last_name'] : null,
                            'company_id' => isset($data['company_id']) ? $data['company_id'] : null,
                            'recurly' => [
                                'account_code' => null
                            ]
                        ]
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
     * updateUser
     *
     * @param string $userId
     * @param array $data
     *
     * @return mixed|string
     *
     * @throws \Exception
     */
    public function updateUser($companyId, $userId, array $data)
    {
        // @TODO: Needs some sort of transformer
        $data = [
            'connection' => 'Username-Password-Authentication',
            'email' => isset($data['email']) ? $data['email'] : null,
            'password' => isset($data['password']) ? $data['password'] : null,
            'user_metadata' => [
                'first_name' => isset($data['account_first_name']) ? $data['account_first_name'] : null,
                'last_name' => isset($data['account_last_name']) ? $data['account_last_name'] : null,
                'company_id' => isset($companyId) ? (integer) $companyId : null,
                'recurly' => [
                    'account_code' => isset($userId) ? (integer) $userId : null,
                ]
            ]
        ];

        $data = $this->removeEmptyElementFromMultidimensionalArray($data);

        return $this->managementClient->users->update($userId, $data);
    }

    /**
     * deleteUser
     *
     * @deprecated needs testing and there are scope issues
     *
     * @param $userId
     *
     * @return mixed|string
     *
     * @throws \Exception
     */
    public function deleteUser($userId)
    {
        return $this->managementClient->users->delete($userId);
    }

    /**
     * getProtocol
     *
     * @return string
     */
    public function getProtocol()
    {
        return isset($_SERVER['HTTPS']) ? 'https' : 'http';
    }

    /**
     * @return Auth0SDK
     */
    public function getAuth0SDK()
    {
        return new Auth0SDK([
            'domain' => $this->domain,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->getProtocol() . '://' . $_SERVER['HTTP_HOST'] . '/authenticated.php',
            'audience' => 'https://' . getenv('AUTH0_DOMAIN') . '/api/v2/',
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
            $response = $auth0Auth->client_credentials(array('audience' => 'https://' . $this->domain . '/api/v2/'));
            $this->token = $response['access_token'];
        }

        return new Management($this->token, $this->domain);
    }

    /**
     * getUserMetadata
     *
     * @param string $userId
     * @return array
     * @throws \Exception
     */
    protected function getUserMetadata($userId)
    {
        try {
            $user = $this->managementClient->users->get($userId);
        } catch (\Exception $exception) {
            throw new \Exception('Internal Error');
        }

        if (isset($user['user_metadata'])) {
            return $user['user_metadata'];
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
            $accountCode = $userId;
            $this->managementClient->users->update(
                $userId,
                array(
                    'user_metadata' => array(
                        'recurly' => array(
                            'account_code' => $accountCode
                        ),
                        'termOfUseTimestamp' => date('c')
                    )
                )
            );
        } catch (\Exception $exception) {
            throw new \Exception('Internal Error');
        }

        return $accountCode;
    }

    /**
     * changePassword
     *
     * @param string $userId
     * @param string $password
     *
     * @throws \Exception
     *
     * @return array
     */
    public function changePassword($companyId, $userId, $password)
    {
        $user = $this->getUserById($companyId, $userId);

        if (!$user) {
            throw new \Exception('Invalid user');
        }

        return $this->managementClient->users->update($userId, array('password' => $password, 'connection' => 'Username-Password-Authentication'));
    }

    /**
     * changeEmail
     *
     * @param string $userId
     * @param string $email
     *
     * @throws \Exception
     *
     * @return array
     */
    public function changeEmail($companyId, $userId, $email)
    {
        $user = $this->getUserById($companyId, $userId);

        if (!$user) {
            throw new \Exception('Invalid user');
        }

        $this->managementClient->users->update(
            $userId,
            array(
                'email' => $email,
                'client_id' => $this->clientId,
                'connection' => 'Username-Password-Authentication'
            )
        );

        return $this->managementClient->jobs->sendVerificationEmail($userId);
    }

    /**
     * sendEmailVerification
     *
     * @param string $token
     *
     * @throws \Exception
     */
    public function sendEmailVerification($token)
    {
        $userId = base64_decode(preg_replace('#^[^:]+:#', '', urldecode($token)));

        if (preg_match('#^auth0#', $userId)) {
            try {
                $this->managementClient->jobs->sendVerificationEmail($userId);
            } catch (\Exception $e) {
                throw new \Exception('Internal Error');
            }
        }
    }

    /**
     * @param $arr
     * @return array
     */
    protected function removeEmptyElementFromMultidimensionalArray($arr) {

        $return = array();

        foreach($arr as $k => $v) {

            if(is_array($v)) {
                $return[$k] = $this->removeEmptyElementFromMultidimensionalArray($v); //recursion
                continue;
            }

            if(empty($v)) {
                unset($arr[$v]);
            } else {
                $return[$k] = $v;
            };
        }

        return $return;
    }
}