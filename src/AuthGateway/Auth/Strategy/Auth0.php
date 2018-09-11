<?php

namespace AuthGateway\Auth\Strategy;

use AuthGateway\Auth\Strategy\Strategy as StrategyInterface;
use AuthGateway\Auth\Transformers\Auth0 as Auth0Transformer;
use AuthGateway\Exception\AuthGatewayException;
use Auth0\SDK\Auth0 as Auth0SDK;
use Auth0\SDK\API\Management;
use Auth0\SDK\API\Authentication;
use GuzzleHttp\Exception\ClientException;
use AuthGateway\Auth\Helper\ArrayHelper;

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
     * @throws AuthGatewayException
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
     * @param null $companyId
     * @param null $email
     * @param null $password
     *
     * @return array|bool
     *
     * @throws AuthGatewayException
     */
    public function authenticate($companyId = null, $email = null, $password = null)
    {
        try {
            $userInfo = $this->authenticationClient->getUser();
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }

        if (null === $userInfo) {
            throw new AuthGatewayException('Internal Error');
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
        try {
            $this->authenticationClient->login();
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }
    }

    /**
     * logout
     *
     * @return string
     */
    public function logout()
    {
        if ($this->auth0Enabled) {
            try {
                $this->authenticationClient->logout();
            } catch (\Exception $exception) {
                throw new AuthGatewayException('Internal Error');
            }
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
        try {
            return $this->authenticationClient->getUser();
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }
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
     * @throws AuthGatewayException
     */
    public function getUsers($companyId, $filters = [], $page = 1, $perPage = 10)
    {
        // Convert 1-indexed page number to 0-indexed
        $page = max(0, $page - 1);

        try {
            $accounts = $this->managementClient->users->getAll(['include_totals' => true], null, true, $page, $perPage);
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }

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
     * @throws AuthGatewayException
     *
     * @return array|mixed|null
     */
    public function getUserById($companyId, $userId)
    {
        try {
            $userInfo = $this->managementClient->users->get($userId);
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }

        return Auth0Transformer::transform($userInfo);
    }

    /**
     * getUserByEmail
     *
     * @param string $companyId
     * @param string $userEmail
     *
     * @throws AuthGatewayException
     *
     * @return array|mixed|null
     */
    public function getUserByEmail($companyId, $userEmail)
    {
        try {
            $userInfo = $this->managementClient->usersByEmail->get($userEmail);
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }

        return Auth0Transformer::transform($userInfo);
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
     * @throws AuthGatewayException
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
                $body = json_decode($response->getBody());

                $message = $body->message;
            } else {
                $message = $e->getMessage();
            }

            $code = $e->getCode();

            throw new AuthGatewayException($message, $code, $e);
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
     * @throws AuthGatewayException
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

        $data = ArrayHelper::removeEmptyElementFromMultidimensionalArray($data);

        try {
            $result = $this->managementClient->users->update($userId, $data);
        } catch (Exception $e) {
            throw new AuthGatewayException('Internal Error');
        }

        return $result;
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
     * @throws AuthGatewayException
     */
    public function deleteUser($companyId, $userId)
    {
        try {
            return $this->managementClient->users->delete($userId);
        } catch (Exception $e) {
            throw new AuthGatewayException('Internal Error');
        }
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
    private function getAuth0SDK()
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
     * @throws AuthGatewayException
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
     * @throws AuthGatewayException
     */
    protected function getUserMetadata($userId)
    {
        try {
            $user = $this->managementClient->users->get($userId);
        } catch (\Exception $exception) {
            throw new AuthGatewayException('Internal Error');
        }

        if (isset($user['user_metadata'])) {
            return $user['user_metadata'];
        }

        return array();
    }

    /**
     * @param string $userId
     * @return string
     * @throws AuthGatewayException
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
            throw new AuthGatewayException('Internal Error');
        }

        return $accountCode;
    }

    /**
     * changePassword
     *
     * @param string $userId
     * @param string $password
     *
     * @throws AuthGatewayException
     *
     * @return array
     */
    public function changePassword($companyId, $userId, $password)
    {
        $user = $this->getUserById($companyId, $userId);

        if (!$user) {
            throw new AuthGatewayException('Invalid user');
        }

        return $this->managementClient->users->update($userId, array('password' => $password, 'connection' => 'Username-Password-Authentication'));
    }

    /**
     * changeEmail
     *
     * @param string $userId
     * @param string $email
     *
     * @throws AuthGatewayException
     *
     * @return array
     */
    public function changeEmail($companyId, $userId, $email)
    {
        $user = $this->getUserById($companyId, $userId);

        if (!$user) {
            throw new AuthGatewayException('Invalid user');
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
     * @throws AuthGatewayException
     */
    public function sendEmailVerification($token)
    {
        $userId = base64_decode(preg_replace('#^[^:]+:#', '', urldecode($token)));

        if (preg_match('#^auth0#', $userId)) {
            try {
                $this->managementClient->jobs->sendVerificationEmail($userId);
            } catch (\Exception $e) {
                throw new AuthGatewayException('Internal Error');
            }
        }
    }
}