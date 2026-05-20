<?php

declare(strict_types=1);



namespace App\Services;



use App\Core\Database;

use PDO;

use Exception;



class MenuPermissionService

{

    private const MENU_TYPE_SQL = "(mp.permission_type = 'menu' OR mp.permission_type IS NULL)";



    private PDO $pdo;



    public function __construct(Database $database)

    {

        $this->pdo = $database->getConnection();

    }



    /**

     * @return array<int, array{key: string, path: string, name: string, permission_id?: int}>

     */

    public function getMenusByUserId(int $userId): array

    {

        $sql = "

            SELECT DISTINCT mp.permission_id, mp.permission_key, mp.menu_path, mp.menu_name

            FROM user_roles ur

            JOIN role_menu_permissions rmp ON rmp.role_id = ur.role_id

            JOIN menu_permissions mp ON mp.permission_id = rmp.permission_id

            WHERE ur.user_id = ?

              AND " . self::MENU_TYPE_SQL . "

              AND mp.menu_path <> ''

            ORDER BY mp.permission_id ASC

        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$userId]);

        return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));

    }



    /**

     * @return string[]

     */

    public function getMenuPathsByUserId(int $userId): array

    {

        return array_column($this->getMenusByUserId($userId), 'path');

    }



    public function userHasMenuPath(int $userId, string $path): bool

    {

        $paths = $this->getMenuPathsByUserId($userId);

        return in_array($path, $paths, true);

    }



    /**

     * @return string[]

     */

    public function getOrderActionsByUserId(int $userId): array

    {

        $sql = "

            SELECT DISTINCT mp.permission_key

            FROM user_roles ur

            JOIN role_menu_permissions rmp ON rmp.role_id = ur.role_id

            JOIN menu_permissions mp ON mp.permission_id = rmp.permission_id

            WHERE ur.user_id = ?

              AND mp.permission_type = 'action'

              AND mp.permission_key LIKE 'orders.%'

            ORDER BY mp.permission_key ASC

        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$userId]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_key');

    }



    public function userHasOrderAction(int $userId, string $actionKey): bool

    {

        return in_array($actionKey, $this->getOrderActionsByUserId($userId), true);

    }



    /**

     * @return array<int, array{key: string, path: string, name: string, permission_id: int}>

     */

    public function getMenusByRoleId(int $roleId): array

    {

        $sql = "

            SELECT mp.permission_id, mp.permission_key, mp.menu_path, mp.menu_name

            FROM role_menu_permissions rmp

            JOIN menu_permissions mp ON mp.permission_id = rmp.permission_id

            WHERE rmp.role_id = ?

              AND " . self::MENU_TYPE_SQL . "

              AND mp.menu_path <> ''

            ORDER BY mp.permission_id ASC

        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$roleId]);

        return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));

    }



    /**

     * @return array<int, array{key: string, path: string, name: string, permission_id: int}>

     */

    public function getAllMenus(): array

    {

        $stmt = $this->pdo->query("

            SELECT permission_id, permission_key, menu_path, menu_name

            FROM menu_permissions

            WHERE permission_type = 'menu' OR permission_type IS NULL

            ORDER BY permission_id ASC

        ");

        return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));

    }



    /**

     * @param int[] $permissionIds

     */

    public function setRoleMenus(int $roleId, array $permissionIds): void

    {

        $this->pdo->beginTransaction();

        try {

            $del = $this->pdo->prepare("

                DELETE rmp FROM role_menu_permissions rmp

                INNER JOIN menu_permissions mp ON mp.permission_id = rmp.permission_id

                WHERE rmp.role_id = ?

                  AND " . self::MENU_TYPE_SQL . "

            ");

            $del->execute([$roleId]);



            if ($permissionIds !== []) {

                $ins = $this->pdo->prepare(

                    'INSERT IGNORE INTO role_menu_permissions (role_id, permission_id) VALUES (?, ?)'

                );

                foreach ($permissionIds as $pid) {

                    $ins->execute([$roleId, (int) $pid]);

                }

            }

            $this->pdo->commit();

        } catch (Exception $e) {

            $this->pdo->rollBack();

            throw $e;

        }

    }



  /** @return string[] */
    public const ORDER_ACTION_KEYS = ['orders.manage', 'orders.assign_shipper'];



    /**

     * @return string[]

     */

    public function getOrderActionsByRoleId(int $roleId): array

    {

        $sql = "

            SELECT mp.permission_key

            FROM role_menu_permissions rmp

            JOIN menu_permissions mp ON mp.permission_id = rmp.permission_id

            WHERE rmp.role_id = ?

              AND mp.permission_type = 'action'

              AND mp.permission_key LIKE 'orders.%'

            ORDER BY mp.permission_key ASC

        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([$roleId]);

        $keys = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_key');
        // #region agent log
        $this->writeDebugLog('H2', 'MenuPermissionService::getOrderActionsByRoleId', 'read', [
            'roleId' => $roleId,
            'keys' => $keys,
        ]);
        // #endregion

        return $keys;

    }



    /**

     * @param string[] $permissionKeys

     */

    public function setRoleOrderActions(int $roleId, array $permissionKeys): void

    {

        $allowed = array_flip(self::ORDER_ACTION_KEYS);

        $permissionKeys = array_values(array_unique(array_filter(

            $permissionKeys,

            static fn (string $k) => isset($allowed[$k])

        )));



        $this->pdo->beginTransaction();

        try {

            $del = $this->pdo->prepare("

                DELETE rmp FROM role_menu_permissions rmp

                INNER JOIN menu_permissions mp ON mp.permission_id = rmp.permission_id

                WHERE rmp.role_id = ?

                  AND mp.permission_type = 'action'

                  AND mp.permission_key LIKE 'orders.%'

            ");

            $del->execute([$roleId]);



            if ($permissionKeys !== []) {

                $ins = $this->pdo->prepare('

                    INSERT IGNORE INTO role_menu_permissions (role_id, permission_id)

                    SELECT ?, mp.permission_id

                    FROM menu_permissions mp

                    WHERE mp.permission_key = ?

                ');

                $lookup = $this->pdo->prepare(
                    'SELECT permission_id, permission_type FROM menu_permissions WHERE permission_key = ? LIMIT 1'
                );
                foreach ($permissionKeys as $key) {
                    $lookup->execute([$key]);
                    $mpRow = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
                    $ins->execute([$roleId, $key]);
                    // #region agent log
                    $this->writeDebugLog('H1', 'MenuPermissionService::setRoleOrderActions', 'insert attempt', [
                        'roleId' => $roleId,
                        'key' => $key,
                        'menuPermission' => $mpRow,
                        'insertRowCount' => $ins->rowCount(),
                    ]);
                    // #endregion
                }

            }

            $this->pdo->commit();
            // #region agent log
            $after = $this->getOrderActionsByRoleId($roleId);
            $this->writeDebugLog('H1', 'MenuPermissionService::setRoleOrderActions', 'after commit', [
                'roleId' => $roleId,
                'requestedKeys' => $permissionKeys,
                'storedKeys' => $after,
            ]);
            // #endregion

        } catch (Exception $e) {

            $this->pdo->rollBack();

            throw $e;

        }

    }



    /**

     * Tên menu ngắn cho cột preview (theo role đầu tiên của user).

     *

     * @return string[]

     */

    public function getMenuPreviewLabelsForUser(int $userId, int $max = 3): array

    {

        $menus = $this->getMenusByUserId($userId);

        $keys = array_column($menus, 'key');

        if (count($keys) <= $max) {

            return $keys;

        }

        $head = array_slice($keys, 0, $max);

        $head[] = '+' . (count($keys) - $max);

        return $head;

    }



    /**

     * @param array<int, array<string, mixed>> $rows

     * @return array<int, array{key: string, path: string, name: string, permission_id: int}>

     */

    private function mapRows(array $rows): array

    {

        $out = [];

        foreach ($rows as $row) {

            $out[] = [

                'permission_id' => (int) $row['permission_id'],

                'key' => (string) $row['permission_key'],

                'path' => (string) $row['menu_path'],

                'name' => (string) $row['menu_name'],

            ];

        }

        return $out;

    }

    /** @param array<string, mixed> $data */
    private function writeDebugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $line = json_encode([
            'sessionId' => '31c426',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'debug-31c426.log';
        file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }

}

