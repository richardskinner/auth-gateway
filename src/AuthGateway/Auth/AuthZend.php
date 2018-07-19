<?php

namespace AuthGateway\Auth;

use AuthGateway\Auth\Transformers\SimplestreamTransformer;
use \PDO;
// use Illuminate\Database\Capsule\Manager;
// use Illuminate\Events\Dispatcher;
// use Illuminate\Container\Container;
use PasswordCompat;

class AuthZend implements AuthStrategy
{
    private $pdo = null;

    const ACCOUNTS_TABLE = 'recurly_accounts';

    public function __construct(array $settings)
    {

        $host = $settings['host'];
        $db   = $settings['database'];
        $user = $settings['username'];
        $pass = $settings['password'];
        $charset = 'utf8';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $opt);
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

    public function getUsers($filters = [], $page = 1, $perPage = 10)
    {
        // $query = Manager::table(self::ACCOUNTS_TABLE)->where('company_id', 122);

        // if (isset($filters['account_mm_created'])) {

        //     $accountCreation = $filters['account_mm_created'];
        //     unset($filters['account_mm_created']);

        //     $query->whereDate('account_mm_created', $this->getDateFilterFormat($accountCreation));
        // }

        // if (isset($filters['name_or_email'])) {

        //     $nameOrEmail = $filters['name_or_email'];
        //     unset($filters['name_or_email']);

        //     $query->where(function ($q) use ($nameOrEmail) {

        //         $q->where('account_email', 'LIKE', "%$nameOrEmail%")
        //             ->orWhere('account_first_name', 'LIKE', "%$nameOrEmail%")
        //             ->orWhere('account_last_name', 'LIKE', "%$nameOrEmail%");
        //     });
        // }

        // if (!empty($filters)) {
        //     foreach ($filters as $field => $values) {
        //         foreach ($values as $value) {
        //             $query->orWhere($field, $value);
        //         }
        //     }
        // }

        // $accounts = $query->paginate(15)->toArray();

        // $transformed = array_map(function ($item) {
        //     return SimplestreamTransformer::transform((array) $item);
        // }, $accounts['data']);

        // $accounts['data'] = $transformed;

        // return $accounts;
        return 'placeholder';
    }

    public function createUser($email, $password, array $data)
    {
        $data = array_merge($data, [
            'account_created' => date('Y-m-d H:i:s'),
        ]);

        // @TODO: Really need to stop this specific company logic....RIDICULOUS!
        if (in_array(Auth::user()->company_id, [22, 25, 37, 94, 95, 114, 121, 122,])) {
            $data["account_password"] = password_hash($data["account_password"], PASSWORD_BCRYPT, array('cost' => 10));
        } else {
            $data["account_password"] = md5($data["account_password"]);
        }

        return Manager::table(self::ACCOUNTS_TABLE)->insert($data);
    }

    public function getUser()
    {
        return \Illuminate\Support\Facades\Auth::user();
    }

    public function updateUser($userId, array $data)
    {
        unset($data['account_code']);

        $data = $this->removeEmptyElementFromMultidimensionalArray($data);

        return Manager::table(self::ACCOUNTS_TABLE)->where('account_code', $userId)->update($data);
    }

    public function getUserById($userId)
    {
        $account = Manager::table(self::ACCOUNTS_TABLE)
            ->where('company_id', 122)
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