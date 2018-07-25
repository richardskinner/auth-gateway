<?php

namespace AuthGateway\Auth;

use AuthGateway\Auth\Transformers\SimplestreamTransformer;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use PasswordCompat;

class AuthLaravel implements AuthStrategy
{
    const ACCOUNTS_TABLE = 'recurly_accounts';

    public function __construct(array $settings)
    {
        $manager = new Manager();
        $manager->addConnection([
            'driver' => $settings['driver'],
            'host' => $settings['host'],
            'database' => $settings['database'],
            'username' => $settings['username'],
            'password' => $settings['password'],
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);

        $manager->setEventDispatcher(new Dispatcher(new Container));

        // Make this Capsule instance available globally via static methods... (optional)
        $manager->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $manager->bootEloquent();
    }

    public function authenticate()
    {
        // TODO: Implement authenticate() method.
    }

    public function login()
    {
        // TODO: Implement login() method.
    }

    public function logout()
    {
        // TODO: Implement logout() method.
    }

    public function getUsers($companyId, $filters = [], $page = 0, $perPage = 15)
    {
        $query = Manager::table(self::ACCOUNTS_TABLE)->where('company_id', $companyId);

        if (isset($filters['account_mm_created'])) {

            $accountCreation = $filters['account_mm_created'];
            unset($filters['account_mm_created']);

            $query->whereDate('account_mm_created', $this->getDateFilterFormat($accountCreation));
        }

        if (isset($filters['name_or_email'])) {

            $nameOrEmail = $filters['name_or_email'];
            unset($filters['name_or_email']);

            $query->where(function ($q) use ($nameOrEmail) {

                $q->where('account_email', 'LIKE', "%$nameOrEmail%")
                    ->orWhere('account_first_name', 'LIKE', "%$nameOrEmail%")
                    ->orWhere('account_last_name', 'LIKE', "%$nameOrEmail%");
            });
        }

        if (!empty($filters)) {
            foreach ($filters as $field => $values) {
                foreach ($values as $value) {
                    $query->orWhere($field, $value);
                }
            }
        }

        $accounts = $query->paginate(15)->toArray();

        $transformed = array_map(function ($item) {
            return SimplestreamTransformer::transform((array) $item);
        }, $accounts['data']);

        $accounts['data'] = $transformed;

        return $accounts;
    }

    public function createUser($companyId, $email, $password, array $data)
    {
        $data = array_merge($data, [
            'account_password' => $password,
            'account_created' => date('Y-m-d H:i:s'),
        ]);

        // @TODO: Really need to stop this specific company logic....RIDICULOUS!
        if (in_array($companyId, [22, 25, 37, 94, 95, 114, 121, 122,])) {
            $data["account_password"] = password_hash($password, PASSWORD_BCRYPT, array('cost' => 10));
        } else {
            $data["account_password"] = md5($password);
        }

        return Manager::table(self::ACCOUNTS_TABLE)->insert($data);
    }

    public function getUser()
    {
        return \Illuminate\Support\Facades\Auth::user();
    }

    public function updateUser($companyId, $userId, array $data)
    {
        $data = $this->removeEmptyElementFromMultidimensionalArray($data);

        return Manager::table(self::ACCOUNTS_TABLE)
            ->where('company_id', $companyId)
            ->where('account_code', $userId)->update($data);
    }

    public function getUserById($companyId, $userId)
    {
        $account = Manager::table(self::ACCOUNTS_TABLE)
            ->where('company_id', $companyId)
            ->where('account_code', $userId)
            ->first();

        return SimplestreamTransformer::transform((array) $account);
    }

    public function deleteUser($userId)
    {
        // TODO: Implement deleteUser() method.
    }

    /**
     * Date Filters
     *
     * @param $filter
     * @return string
     */
    protected function getDateFilterFormat($filter)
    {
        $date = new \DateTime();
        $date->add(\DateInterval::createFromDateString($filter));

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param $arr
     * @return array
     */
    function removeEmptyElementFromMultidimensionalArray($arr) {

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