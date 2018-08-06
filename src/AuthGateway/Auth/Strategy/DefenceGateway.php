<?php

namespace AuthGateway\Auth\Strategy;

use AuthGateway\Auth\Strategy\Strategy as StrategyInterface;

class DefenceGateway implements StrategyInterface
{
    private $gatewayUrlLogin = "https://d2hx7rr0zgyood.cloudfront.net/login_web.php?return_to=%s";

    private $gatewayUrlLogout = "https://d2hx7rr0zgyood.cloudfront.net/logout_web.php?return_to=%s";

    private $returnTo;

    private $secret;

    public function __construct(array $settings = [])
    {
        if (!empty($settings)) {
            $this->secret = $settings['secret'];
        }
    }

    /**
     * authenticate
     *
     * @return object
     * @throws \Exception
     */
    public function authenticate()
    {
        $data = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
        $token = filter_var($_POST['token'], FILTER_SANITIZE_STRING);

        if (sha1($_POST['data'] . $this->secret) !== $token) {
            throw new \Exception('Authentication Error.');
        }

        $data = json_decode(urldecode($data));

        $identity = (object)[
            'account_email' => $data->{'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'}[0],
            'account_first_name' => $data->{'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'}[0],
            'account_last_name' => $data->{'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname'}[0],
            'subscription_state' => 'allowed',
        ];

        return $identity;
    }

    /**
     * login
     */
    public function login()
    {
        $url = sprintf($this->gatewayUrlLogin, $this->returnTo);

        header("Location: {$url}");
        exit;
    }

    /**
     * logout
     */
    public function logout()
    {
        $url = sprintf($this->gatewayUrlLogout, $this->returnTo);

        header("Location: {$url}");
        exit;
    }

    /**
     * isLoggedOut
     *
     * @return bool
     */
    public function isLoggedOut()
    {
        if (empty($_POST['data'])) {
            return true;
        }

        return false;
    }

    public function setReturnTo($url)
    {
        $this->returnTo = $url;

        return $this;
    }
}