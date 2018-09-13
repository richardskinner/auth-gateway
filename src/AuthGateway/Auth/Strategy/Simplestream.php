<?php

namespace AuthGateway\Auth\Strategy;

use AuthGateway\Auth\Strategy\Strategy as StrategyInterface;
use AuthGateway\Auth\Transformers\Simplestream as SimplestreamTransformer;
use AuthGateway\Exception\AuthGatewayException;
use AuthGateway\Auth\AuthGateway;
use AuthGateway\Auth\Helper\ArrayHelper;
use AuthGateway\Auth\Helper\DateTimeHelper;
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
        session_destroy();
    }

    /**
     * getUsers
     *
     * @param int   $companyId
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
        $sqlPieces = array();
        $bindings = array();

        // Validate page inputs
        if (
            (!is_integer($page) || $page < 1)
            || (!is_integer($perPage) || $perPage < 1)
        ) {
            throw new AuthGatewayException('Invalid pagination spec provided');
        }

        // Start select
        $sqlPieces['select'] = "SELECT SQL_CALC_FOUND_ROWS * FROM `recurly_accounts`";

        // Apply filters
        $sqlPieces['where'] = "WHERE `company_id` = ".$companyId;

        // Date created
        if (isset($filters['account_mm_created'])) {
            $sqlPieces['where_created'] = "AND `account_mm_created` = :account_mm_created";

            $creationDate = DateTimeHelper::getDateFilterFormat($filters['account_mm_created']);

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

        try {
            // Exec statement with bound values
            $stmt->execute($bindings);
        } catch (\Exception $e) {
            // TODO - map PDO errors to human readable messages
            throw new AuthGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        // Extract and transform data
        $accounts = array();

        while ($item = $stmt->fetch())
        {
            $accounts['data'][] = SimplestreamTransformer::transform((array) $item);
        }

        // Get total
        $stmt = $this->pdo->prepare("SELECT FOUND_ROWS() AS `total-users`");

        try {
            $stmt->execute();
        } catch (\Exception $e) {
            // TODO - map PDO errors to human readable messages
            throw new AuthGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        $total = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($total)) {
            $accounts['total'] = $total['total-users'];
        }

        return $accounts;
    }

    public function createUser($companyId, $email, $password, array $data)
    {
        $data = array_merge($data, [
            'company_id' => $companyId,
            'account_created' => date('Y-m-d H:i:s'),
            'account_mm_created' => date('Y-m-d H:i:s'),
            'auth_vendor' => AuthGateway::SIMPLESTREAM_AUTH,
        ]);

        // @TODO: Really need to stop this specific company logic....RIDICULOUS!
        if (in_array($companyId, [22, 25, 37, 94, 95, 114, 121, 122,])) {
            $data["account_password"] = password_hash($password, PASSWORD_BCRYPT, array('cost' => 10));
        } else {
            $data["account_password"] = md5($password);
        }

        $columnNames = array_keys($data);

        $sqlColumns = '`'.implode('`, `', $columnNames).'`';
        $sqlValues = ':'.implode(', :', $columnNames);

        $sqlQuery = 'INSERT INTO `recurly_accounts` ('.$sqlColumns.') VALUES ('.$sqlValues.')';

        // Prep statement
        $stmt = $this->pdo->prepare($sqlQuery);

        try {
            // Exec statement with bound values
            $stmt->execute($data);
        } catch (\Exception $e) {
            // TODO - map PDO errors to human readable messages
            throw new AuthGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->pdo->lastInsertId();
    }

    public function getUser()
    {
        return \Illuminate\Support\Facades\Auth::user();
    }

    public function updateUser($companyId, $userId, array $data)
    {
        unset($data['account_code']);

        // Filter data
        $data = ArrayHelper::removeEmptyElementFromMultidimensionalArray($data);

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

        try {
            // Exec statement with bound values
            $stmt->execute($data);
        } catch (\Exception $e) {
            // TODO - map PDO errors to human readable messages
            throw new AuthGatewayException($e->getMessage(), $e->getCode(), $e);
        }

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

        try {
            // Exec statement with bound values
            $stmt->execute([
                'company_id' => $companyId,
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            // TODO - map PDO errors to human readable messages
            throw new AuthGatewayException($e->getMessage(), $e->getCode(), $e);
        }

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

        try {
            // Exec statement with bound values
            $stmt->execute([
                'company_id' => $companyId,
                'user_email' => $email
            ]);
        } catch (\Exception $e) {
            // TODO - map PDO errors to human readable messages
            throw new AuthGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        // Extract and transform data
        return SimplestreamTransformer::transform((array) $stmt->fetch());
    }

    public function deleteUser($companyId, $userId)
    {
        $dateDeleted = new DateTime();

        // @TODO implement account_deleted_by

        return $this->updateUser(
            $companyId,
            $userId,
            ['account_deleted' => $dateDeleted->format('Y-m-d H:i:s')]
        );
    }
}