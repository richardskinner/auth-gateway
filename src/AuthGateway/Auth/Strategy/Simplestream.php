<?php

namespace AuthGateway\Auth\Strategy;

use AuthGateway\Auth\Strategy\Strategy as StrategyInterface;
use AuthGateway\Auth\Transformers\Simplestream as SimplestreamTransformer;
use \PDO;
use PasswordCompat;

class Simplestream implements StrategyInterface
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

    public function authenticate($companyId, $email, $password)
    {
        // Get user object
        $account = $this->getUserByEmail($companyId, $email);

        if (!$account) {
            return false;
        }

        // Check various things (inherited from SimpleStream_Auth Zend Adapter)
        if ($account['gateway'] == "netbanx" && $account['account_password'] == "reset") {
            return false;
        }

        // Verify password
        if (password_verify($this->password, $account->account_password)) {
            return true;
        }

        return false;
    }

    /**
     * This method returns $this because we think it might be redundant, at
     * least in this case
     */
    public function login()
    {
        // TODO: Implement login() method.
        return $this;
    }

    public function logout()
    {
        // TODO: Implement logout() method.
    }

    public function getUsers($companyId, $filters = [], $page = 1, $perPage = 10)
    {
        $sqlPieces = array();
        $bindings = array();

        // Start select
        $sqlPieces['select'] = "SELECT * FROM `recurly_accounts`";

        // Apply filters
        $sqlPieces['where'] = "WHERE `company_id` = ".$companyId;

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
            $accounts['data'] = SimplestreamTransformer::transform((array) $item);
        }

        return $accounts;
    }

    public function createUser($companyId, $email, $password, array $data)
    {
        $data = array_merge($data, [
            'account_created' => date('Y-m-d H:i:s'),
        ]);

        // @TODO: Really need to stop this specific company logic....RIDICULOUS!
        if (in_array($companyId, [22, 25, 37, 94, 95, 114, 121, 122,])) {
            $data["account_password"] = password_hash($password, PASSWORD_BCRYPT, array('cost' => 10));
        } else {
            $data["account_password"] = md5($password);
        }

        // foreach ($data as $column => $value) {
            $columnNames = array_keys($data);

            $sqlColumns = '`'.implode('`, `', $columnNames).'`';
            $sqlValues = ':'.implode(', :', $columnNames);
        // }

        $sqlQuery = 'INSERT INTO `recurly_accounts` ('.$sqlColumns.') VALUES ('.$sqlValues.')';

        // Prep statement
        $stmt = $this->pdo->prepare($sqlQuery);

        // Exec statement with bound values
        return $stmt->execute($data);
    }

    public function getUser()
    {
        return \Illuminate\Support\Facades\Auth::user();
    }

    public function updateUser($companyId, $userId, array $data)
    {
        unset($data['account_code']);

        // Filter data
        $data = $this->removeEmptyElementFromMultidimensionalArray($data);

        $sqlUpdates = '';

        foreach ($data as $column => $value) {
            $sqlUpdates .= '`'.$column.'` = :'.$column.', ';
        }

        // Compile query
        $sqlQuery = 'UPDATE `recurly_accounts` ';
        $sqlQuery .= 'SET '.substr($sqlUpdates, 0, -2).' ';
        $sqlQuery .= 'WHERE `company_id` = :company_id ';
        $sqlQuery .= 'AND `account_code` = :user_id ';
        $sqlQuery .= 'LIMIT 1';

        // Prep statement
        $stmt = $this->pdo->prepare($sqlQuery);

        // Add ids to data for use in query
        $data['company_id'] = $companyId;
        $data['user_id'] = $userId;

        // Exec statement with bound values
        $stmt->execute($data);

        return $stmt->rowCount();
    }

    public function getUserById($companyId, $userId)
    {
        // Compile SQL
        $sqlQuery = "SELECT * FROM `recurly_accounts` ";
        $sqlQuery .= "WHERE `company_id` = :company_id ";
        $sqlQuery .= "AND `account_code` = :user_id";

        // Prep statement
        $stmt = $this->pdo->prepare($sqlQuery);

        // Exec statement with bound values
        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId
        ]);

        // Extract and transform data
        return SimplestreamTransformer::transform((array) $stmt->fetch());
    }

    public function getUserByEmail($companyId, $email)
    {
        // Compile SQL
        $sqlQuery = "SELECT * FROM `recurly_accounts` ";
        $sqlQuery .= "WHERE `company_id` = :company_id ";
        $sqlQuery .= "AND `account_email` = :user_email";

        // Prep statement
        $stmt = $this->pdo->prepare($sqlQuery);

        // Exec statement with bound values
        $stmt->execute([
            'company_id' => $companyId,
            'user_email' => $email
        ]);

        // Extract and transform data
        return SimplestreamTransformer::transform((array) $stmt->fetch());
    }

    public function deleteUser($userId)
    {
        $dateDeleted = new DateTime();

        // @TODO implement account_deleted_by

        return $this->updateUser(
            $companyId,
            $userId,
            ['account_deleted' => $dateDeleted->format('Y-m-d H:i:s')]
        );
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