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
        $sqlPieces = array();
        $bindings = array();

        // Start select
        $sqlPieces['select'] = "SELECT * FROM `recurly_accounts`";

        // Apply filters
        $sqlPieces['where'] = "WHERE `company_id` = 122";

        // Date created
        if (isset($filters['account_mm_created'])) {
            $sqlPieces['where_created'] = "AND WHERE `account_mm_created` = :account_mm_created";

            $creationDate = $this->getDateFilterFormat($filters['account_mm_created']);

            $bindings['account_mm_created'] = $creationDate;

            unset($filters['account_mm_created']);
        }

        // Name or email
        if (isset($filters['name_or_email'])) {
            $sqlPieces['where_account_email'] = "AND (`account_email` LIKE :like_email";
            $sqlPieces['where_account_first_name'] = "OR `account_first_name` LIKE :like_first_name";
            $sqlPieces['where_account_last_name'] = "OR `account_last_name` LIKE :like_last_name)";

            $likeString = '%'.$filters['name_or_email'].'%';

            $bindings['like_email'] = $likeString;
            $bindings['like_first_name'] = $likeString;
            $bindings['like_last_name'] = $likeString;

            unset($filters['name_or_email']);
        }

        // Any remaining
        if (!empty($filters)) {
            foreach ($filters as $field => $values) {
                $pieceLabel = 'where_'.$field;
                $newPiece = 'AND `'.$field.'`';

                if (count($values) == 1) {
                    $newPiece .= ' = :'.$pieceLabel;
                    $bindings[$pieceLabel] = $values;
                } elseif (count($values) > 1) {
                    $newPiece .= ' IN (';

                    $i = 0;
                    foreach ($values as $value) {
                        $valueLabel = $pieceLabel.'_'.$i;

                        $newPiece .= ':'.$valueLabel.', ';

                        $bindings[$valueLabel] = $value;

                        $i++;
                    }

                    $newPiece = substr($newPiece, 0, -2).')';
                }

                $sqlPieces[$pieceLabel] = $newPiece;
            }
        }

        // Paginate
        $sqlPieces['limit'] = "LIMIT ".(((int) $page - 1) * (int) $perPage).", ".(int) $perPage;

        // Compile query
        $sqlQuery = implode(' ', $sqlPieces);

        // Prep statement
        $stmt = $this->pdo->prepare($sqlQuery);

        // Exec statement with bound values
        $stmt->execute($bindings);

        // Extract and transform data
        $accounts = array();

        while ($item = $stmt->fetch())
        {
            $accounts[] = SimplestreamTransformer::transform((array) $item);
        }

        return $accounts;
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