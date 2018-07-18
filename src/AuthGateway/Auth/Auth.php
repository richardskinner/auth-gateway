<?php

namespace AuthGateway\Auth;

use PasswordCompat;

class Auth implements AuthStrategy
{
    private $model;

    public function __construct(array $settings = [])
    {
        if (!empty($settings)) {
            // ...
        }
    }

    public function setModel(Zend_Db_Table_Abstract $model)
    {
        $this->model = $model;

        return $this;
    }

    public function authenticate()
    {
        $account = $this->model->getByEmail($_POST['username']);

        if (false === password_verify($this->password, $_POST['password'])) {
            return false;
        }

        return (object)[
            'account_code' => $account->account_code,
            'account_created' => $account->account_created,
            'account_type' => $account->account_type,
            'account_state' => $account->account_state,
            'account_email' => $account->account_email,
            'account_first_name' => $account->account_first_name,
            'account_last_name' => $account->account_last_name,
            'gateway' => $account->gateway,
            'subscription_valid_until' => $account->subscription_valid_until,
            'netbanx_agreement_ref' => $account->netbanx_agreement_ref,
            'netbanx_agreement_start' => $account->netbanx_agreement_start,
            'netbanks_payment_ref' => $account->netbanks_payment_ref,
            'subscription_status' => $account->subscription_status,
        ];
    }

    public function login()
    {
        $form = new SimpleStream_Core_Modules_Auth_Forms_Authentication();
        $form->setAction($this->view->url([], 'auth_login'));
//        $form->setAttrib('fails', $this->loginSession->loginAttempts);

        return $form;
    }

    public function logout()
    {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            Zend_Auth::getInstance()->clearIdentity();
            return true;
        }

        return false;
    }

    public function getUsers($filters = [], $page = 1, $perPage = 10)
    {
        // TODO: Implement getUsers() method.
    }

    public function createUser($email, $password, array $data)
    {
        // TODO: Implement createUser() method.
    }

    public function getUserById($userId)
    {
        // TODO: Implement getUserById() method.
    }

    public function getUser()
    {
        // TODO: Implement getUser() method.
    }

    public function updateUser($userId, array $data)
    {
        // TODO: Implement updateUser() method.
    }

    public function deleteUser($userId)
    {
        // TODO: Implement deleteUser() method.
    }
}
