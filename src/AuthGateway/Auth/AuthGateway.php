<?php

namespace AuthGateway\Auth;

use Auth0\SDK\Auth0 as Auth0SDK;

class AuthGateway
{
    const ZEND_AUTH = 1;
    const DEFENCE_GATEWAY = 2;
    const AUTH0 = 3;

    /**
     * LARAVEL_AUTH
     *
     * Not constructed yet
     *
     * @ignore
     */
    const LARAVEL_AUTH = 4;

    private static $instance;

    protected function __construct()
    {
        // TODO: Implement __construct method
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    private function __wakeup()
    {
        // TODO: Implement __wakeup() method.
    }

    public static function getInstance($gateway = self::AUTH, array $settings)
    {
        if (NULL == self::$instance) {

            switch ($gateway) {
                case self::ZEND_AUTH:
                    self::$instance = new Auth();
                    break;
                case self::DEFENCE_GATEWAY:
                    self::$instance = new DefenceGateway($settings);
                    break;
                case self::AUTH0:

                    $prot = isset($_SERVER['HTTPS']) ? 'https' : 'http';

                    self::$instance = new Auth0(new Auth0SDK([
                        'domain' => $settings['domain'],
                        'client_id' => $settings['client_id'],
                        'client_secret' => $settings['client_secret'],
                        'redirect_uri' => $prot . '://' . $_SERVER['HTTP_HOST'] . '/login',
                        'audience' => 'https://' . $settings['domain'] . '/api/v2/',
                        'scope' => 'openid profile',
                        'persist_id_token' => false,
                        'persist_access_token' => true,
                        'persist_refresh_token' => false,
                    ]), $settings);

                    break;
                default:
                    self::$instance = new Auth();
            }

            return self::$instance;

        } else {
            return NULL;
        }
    }
}