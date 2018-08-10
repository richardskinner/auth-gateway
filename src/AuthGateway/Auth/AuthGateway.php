<?php

namespace AuthGateway\Auth;

use Auth0\SDK\Auth0 as Auth0SDK;

class AuthGateway
{
    const SIMPLESTREAM_AUTH = 1;
    const DEFENCE_GATEWAY = 2;
    const AUTH0 = 3;

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
                case self::SIMPLESTREAM_AUTH:
                    self::$instance = new Simplestream();
                    break;
                case self::DEFENCE_GATEWAY:
                    self::$instance = new DefenceGateway($settings);
                    break;
                case self::AUTH0:
                    self::$instance = new Auth0($settings);
                    break;
            }

            return self::$instance;

        } else {
            return NULL;
        }
    }
}