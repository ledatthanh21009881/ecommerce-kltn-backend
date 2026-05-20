<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response};
use App\Domain\Auth\{Account, User, RefreshToken};
use App\Core\{Validator, Container};
use App\Support\{ResponseHelper, JWT, PanelRole};
use App\Services\MenuPermissionService;
use Exception;
use PDO;

class AuthController extends Controller 
{
    private Account $accountModel;
    private User $userModel;
    private RefreshToken $refreshTokenModel;
    private JWT $jwt;
    
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->accountModel = new Account($container->database());
        $this->userModel = new User($container->database());
        $this->refreshTokenModel = new RefreshToken($container->database());
        $this->jwt = $container->jwt();
    }
    
    public function login(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'account_name' => 'required',
            'password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            // Find account
            $account = $this->accountModel->findByAccountName($data['account_name']);
            if (!$account) {
                return $res->json(ResponseHelper::unauthorized('Invalid credentials'));
            }
            
            // Check if account is locked
            // Temporarily comment out to debug
            // if ($this->accountModel->isLocked($account)) {
            //     return $res->json(ResponseHelper::unauthorized('Account is locked due to multiple failed attempts. Please try again later.'));
            // }
            
            // Check failed attempts for brute force protection
            // Temporarily comment out to debug
            // if ($account['failed_attempts'] >= 5) {
            //     // Lock account for 15 minutes after 5 failed attempts
            //     $this->accountModel->lockAccount($account['account_id'], 15);
            //     return $res->json(ResponseHelper::unauthorized('Account locked due to multiple failed attempts. Please try again in 15 minutes.'));
            // }
            
            // Check if account is active
            // Temporarily comment out to debug
            // if (!$this->accountModel->isActive($account)) {
            //     return $res->json(ResponseHelper::unauthorized('Account is inactive'));
            // }
            
            // Verify password
            if (!password_verify($data['password'], $account['password'])) {
                // Temporarily comment out to debug
                // $this->accountModel->incrementFailedAttempts($account['account_id']);
                return $res->json(ResponseHelper::unauthorized('Invalid credentials'));
            }
            
            // Try to get user info with roles using direct SQL
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT u.*, r.role_name FROM users u LEFT JOIN user_roles ur ON u.user_id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.role_id WHERE u.account_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$account['account_id']]);
            $userData = $stmt->fetchAll();
            
            // If no user found in users table, create a basic user from account info
            if (!$userData) {
                // Create basic user info from account
                $user = [
                    'user_id' => $account['account_id'], // Use account_id as user_id
                    'account_name' => $account['account_name'],
                    'last_login_at' => $account['last_login_at'],
                    'roles' => [$account['account_type'] ?? 'user'] // Use account_type as role
                ];
            } else {
                // Get user basic info from first row
                $user = $userData[0];
                
                // Collect all roles for this user
                $roles = [];
                foreach ($userData as $row) {
                    if ($row['role_name']) {
                        $roles[] = $row['role_name'];
                    }
                }
                
                // Add account info and roles
                $user['account_name'] = $account['account_name'];
                $user['last_login_at'] = $account['last_login_at'];
                $user['roles'] = $roles;
            }
            
            // Reset failed attempts and update last login
            // Temporarily comment out to debug
            // $this->accountModel->resetFailedAttempts($account['account_id']);
            // $this->accountModel->updateLastLogin($account['account_id']);
            
            // Generate JWT token (keep original format)
            $token = $this->jwt->encode([
                'user_id' => $user['user_id'],
                'account_id' => $account['account_id'],
                'account_name' => $user['account_name'],
                'roles' => $user['roles']
            ]);
            
            // Generate refresh token
            $refreshToken = $this->refreshTokenModel->generateRefreshToken();
            $this->refreshTokenModel->createToken($user['user_id'], $refreshToken);
            
            // Remove sensitive data
            unset($user['account_id']);
            
            return $res->json(ResponseHelper::success([
                'token' => $token,
                'refresh_token' => $refreshToken,
                'user' => $user
            ], 'Login successful'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Login failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Admin login endpoint - specific for admin users
     */
    public function adminLogin(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'account_name' => 'required',
            'password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            // Find account - same as regular login
            $account = $this->accountModel->findByAccountName($data['account_name']);
            if (!$account) {
                return $res->json(ResponseHelper::unauthorized('Invalid admin credentials'));
            }
            
            // Check if account is locked - same as regular login
            // Temporarily comment out to debug
            // if ($this->accountModel->isLocked($account)) {
            //     return $res->json(ResponseHelper::unauthorized('Account is locked. Please try again later.'));
            // }
            
            // Check if account is active - same as regular login
            // Temporarily comment out to debug
            // if (!$this->accountModel->isActive($account)) {
            //     return $res->json(ResponseHelper::unauthorized('Account is inactive'));
            // }
            
            // Verify password - same as regular login
            if (!password_verify($data['password'], $account['password'])) {
                // Temporarily comment out to debug
                // $this->accountModel->incrementFailedAttempts($account['account_id']);
                return $res->json(ResponseHelper::unauthorized('Invalid admin credentials'));
            }
            
            $user = $this->userModel->findByAccountId($account['account_id']);
            if (!$user) {
                return $res->json(ResponseHelper::forbidden('Access denied. No user profile for this account.'));
            }

            $userId = (int) $user['user_id'];
            $roles = $this->fetchRolesForUserId($userId);

            if ($roles === [] || !PanelRole::hasAnyPanelRole($roles)) {
                return $res->json(ResponseHelper::forbidden('Access denied. Admin panel privileges required.'));
            }

            $menuService = new MenuPermissionService($this->container->database());
            $allowedMenus = $menuService->getMenusByUserId($userId);
            $allowedMenusResponse = array_map(static function (array $m) {
                return [
                    'key' => $m['key'],
                    'path' => $m['path'],
                    'name' => $m['name'],
                ];
            }, $allowedMenus);

            if ($allowedMenusResponse === []) {
                return $res->json(ResponseHelper::forbidden('Access denied. No menu permissions assigned.'));
            }

            $pdo = $this->container->database()->getConnection();

            $resetSql = "UPDATE accounts SET failed_attempts = 0, last_failed_login_at = NULL, locked_until = NULL WHERE account_id = ?";
            $resetStmt = $pdo->prepare($resetSql);
            $resetStmt->execute([$account['account_id']]);

            $loginSql = "UPDATE accounts SET last_login_at = NOW() WHERE account_id = ?";
            $loginStmt = $pdo->prepare($loginSql);
            $loginStmt->execute([$account['account_id']]);

            $isAdmin = in_array('admin', $roles, true);
            $orderActions = $this->resolveOrderActionsForUser($userId, $roles, $menuService);
            $allowedPaths = array_column($allowedMenusResponse, 'path');
            $primaryRole = $roles[0];

            $token = $this->jwt->encode([
                'user_id' => $userId,
                'account_id' => $account['account_id'],
                'account_name' => $account['account_name'],
                'account_type' => $account['account_type'],
                'roles' => $roles,
                'is_admin' => $isAdmin,
                'allowed_menu_paths' => $allowedPaths,
            ]);

            $refreshToken = $this->refreshTokenModel->generateRefreshToken();
            $this->refreshTokenModel->createToken($userId, $refreshToken);

            $locale = $account['preferred_locale'] ?? 'vi';
            if (!in_array($locale, ['vi', 'en'], true)) {
                $locale = 'vi';
            }

            return $res->json(ResponseHelper::success([
                'token' => $token,
                'refresh_token' => $refreshToken,
                'account' => [
                    'account_id' => $account['account_id'],
                    'account_name' => $account['account_name'],
                    'account_type' => $account['account_type'],
                    'role' => $primaryRole,
                    'preferred_locale' => $locale,
                ],
                'roles' => $roles,
                'allowed_menus' => $allowedMenusResponse,
                'order_actions' => $orderActions,
                'redirect' => $allowedPaths[0] ?? '/admin/dashboard',
            ], 'Admin login successful'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Admin login failed: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/auth/admin/me — refresh roles + allowed menus từ DB.
     */
    public function adminMe(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        $payload = $req->getAttribute('token_payload') ?? [];

        if (!is_array($user)) {
            return $res->json(ResponseHelper::unauthorized('Invalid session'));
        }

        $userId = (int) ($user['user_id'] ?? $payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return $res->json(ResponseHelper::unauthorized('User not found'));
        }

        try {
            $roles = $this->fetchRolesForUserId($userId);
            $menuService = new MenuPermissionService($this->container->database());
            $allowedMenus = $menuService->getMenusByUserId($userId);
            $allowedMenusResponse = array_map(static function (array $m) {
                return [
                    'key' => $m['key'],
                    'path' => $m['path'],
                    'name' => $m['name'],
                ];
            }, $allowedMenus);

            $accountId = (int) ($user['account_id'] ?? $payload['account_id'] ?? 0);
            $accountRow = null;
            if ($accountId > 0) {
                $pdo = $this->container->database()->getConnection();
                $stmt = $pdo->prepare('SELECT account_id, account_name, account_type, preferred_locale FROM accounts WHERE account_id = ?');
                $stmt->execute([$accountId]);
                $accountRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $orderActions = $this->resolveOrderActionsForUser($userId, $roles, $menuService);

            return $res->json(ResponseHelper::success([
                'account' => [
                    'account_id' => $accountId,
                    'account_name' => $accountRow['account_name'] ?? ($user['account_name'] ?? ''),
                    'account_type' => $accountRow['account_type'] ?? ($user['account_type'] ?? 'local'),
                    'role' => $roles[0] ?? 'staff',
                    'preferred_locale' => $accountRow['preferred_locale'] ?? 'vi',
                ],
                'roles' => $roles,
                'allowed_menus' => $allowedMenusResponse,
                'order_actions' => $orderActions,
            ]));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to load admin session: ' . $e->getMessage()));
        }
    }

    /**
     * @param string[] $roles
     * @return string[]
     */
    private function resolveOrderActionsForUser(int $userId, array $roles, MenuPermissionService $menuService): array
    {
        if (in_array('admin', $roles, true)) {
            return ['orders.manage', 'orders.assign_shipper'];
        }

        return $menuService->getOrderActionsByUserId($userId);
    }

    /**
     * @return string[]
     */
    private function fetchRolesForUserId(int $userId): array
    {
        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare("
            SELECT r.role_name
            FROM user_roles ur
            JOIN roles r ON r.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'role_name');
    }

    /**
     * PUT /api/v1/auth/admin/locale — lưu preferred_locale (vi|en) cho tài khoản admin.
     */
    public function updateAdminLocale(Request $req, Response $res): void
    {
        $payload = $req->getAttribute('user');
        if (!is_array($payload) || !isset($payload['account_id'])) {
            $res->json(ResponseHelper::unauthorized('Invalid token payload'));
            return;
        }

        $data = $req->json();
        $raw = $data['preferred_locale'] ?? '';
        $loc = is_string($raw) ? $raw : (string) $raw;
        if (!in_array($loc, ['vi', 'en'], true)) {
            $res->json(ResponseHelper::badRequest('preferred_locale must be vi or en'));
            return;
        }

        $accountId = (int) $payload['account_id'];
        try {
            $ok = $this->accountModel->update($accountId, ['preferred_locale' => $loc]);
            if (!$ok) {
                $res->json(ResponseHelper::serverError('Failed to update language preference'));
                return;
            }
            $res->json(ResponseHelper::success(['preferred_locale' => $loc], 'Language saved'));
        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Update failed: ' . $e->getMessage()));
        }
    }

    /**
     * Check if current user is admin
     */
    public function checkAdminRole(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized('Invalid token'));
        }
        
        $isAdmin = isset($user['roles']) && in_array('admin', $user['roles']);
        
        return $res->json(ResponseHelper::success([
            'is_admin' => $isAdmin,
            'roles' => $user['roles'] ?? []
        ]));
    }
    
    public function register(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'account_name' => 'required|min:3',
            'password' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required|phone_vn'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            $phoneNormalized = preg_replace('/\D/', '', $data['phone']);
            
            // Check if account name exists
            if ($this->accountModel->findByAccountName($data['account_name'])) {
                return $res->json(ResponseHelper::error('Account name already exists', 409));
            }
            
            // Check if email exists
            if ($this->userModel->findByEmail($data['email'])) {
                return $res->json(ResponseHelper::error('Email already exists', 409));
            }
            
            $pdo->beginTransaction();
            
            // Create account
            $accountId = $this->accountModel->createAccount([
                'account_name' => $data['account_name'],
                'password' => $data['password'],
                'account_type' => 'local',
                'is_active' => true
            ]);
            
            // Create user
            $userId = $this->userModel->create([
                'account_id' => $accountId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $phoneNormalized,
                'gender' => $data['gender'] ?? 'other',
                'birthdate' => $data['birthdate'] ?? null
            ]);
            
            // Assign customer role by default (role_id = 2)
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 2)");
            $stmt->execute([$userId]);
            
            // Create customer record
            $stmt = $pdo->prepare("INSERT INTO customers (user_id, loyalty_points, total_orders) VALUES (?, 0, 0)");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            
            return $res->json(ResponseHelper::success([
                'user_id' => $userId,
                'account_id' => $accountId,
                'message' => 'Registration successful. You can now login with your credentials.'
            ], 'Registration successful'));
            
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollback();
            }
            return $res->json(ResponseHelper::serverError('Registration failed: ' . $e->getMessage()));
        }
    }
    
    public function logout(Request $req, Response $res)
    {
        try {
            // Get current user from JWT middleware
            $user = $req->getAttribute('user');
            
            if (!$user) {
                return $res->json(ResponseHelper::unauthorized('Invalid token'));
            }
            
            // Update last logout time in database
            $pdo = $this->container->database()->getConnection();
            $sql = "UPDATE accounts SET last_logout_at = NOW() WHERE account_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['account_id']]);
            
            // In a production app, you might want to:
            // 1. Blacklist the current token
            // 2. Store token in a blacklist table
            // 3. Set token expiry immediately
            
            return $res->json(ResponseHelper::success(null, 'Logout successful'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Logout failed: ' . $e->getMessage()));
        }
    }
    
    public function refresh(Request $req, Response $res)
    {
        // Get current user from JWT
        $user = $req->getAttribute('user');
        
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized('Invalid token'));
        }
        
        // Generate new token
        $token = $this->jwt->encode([
            'user_id' => $user['user_id'],
            'account_id' => $user['account_id'],
            'roles' => $user['roles']
        ]);
        
        return $res->json(ResponseHelper::success(['token' => $token], 'Token refreshed'));
    }
    
    public function profile(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        
        $userInfo = $this->userModel->getUserWithRoles($user['user_id']);
        
        return $res->json(ResponseHelper::success($this->userModel->toArray($userInfo)));
    }
    
    public function updateProfile(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        $data = $req->json();
        
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        
        // Validate input
        $validator = Validator::make($data, [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required|phone_vn'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            $phoneNormalized = preg_replace('/\D/', '', $data['phone']);
            $this->userModel->update($user['user_id'], [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $phoneNormalized,
                'gender' => $data['gender'] ?? null,
                'birthdate' => $data['birthdate'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null
            ]);
            
            return $res->json(ResponseHelper::success(null, 'Profile updated successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Update failed: ' . $e->getMessage()));
        }
    }
    
    public function changePassword(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        $data = $req->json();
        
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        
        // Validate input
        $validator = Validator::make($data, [
            'current_password' => 'required',
            'new_password' => 'required',
            'confirm_password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        if ($data['new_password'] !== $data['confirm_password']) {
            return $res->json(ResponseHelper::error('Password confirmation does not match'));
        }
        
        // Get account
        $account = $this->accountModel->find($user['account_id']);
        if (!$account) {
            return $res->json(ResponseHelper::unauthorized('Account not found'));
        }
        
        // Verify current password
        if (!$this->accountModel->verifyPassword($data['current_password'], $account['password'])) {
            return $res->json(ResponseHelper::unauthorized('Current password is incorrect'));
        }
        
        try {
            $this->accountModel->updatePassword($user['account_id'], $data['new_password']);
            return $res->json(ResponseHelper::success(null, 'Password changed successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Password change failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * True if the account/role is allowed to use the admin area (for admin-only reset link).
     */
    private function isAdminAccountForReset(array $user, array $account): bool
    {
        if (isset($account['account_type']) && $account['account_type'] === 'admin') {
            return true;
        }
        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM user_roles ur
             JOIN roles r ON ur.role_id = r.role_id
             WHERE ur.user_id = ? AND r.role_name = ? LIMIT 1'
        );
        $stmt->execute([(int) $user['user_id'], 'admin']);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Forgot Password - Generate new password and send via email
     */
    public function forgotPassword(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'email' => 'required|email'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            $wantsAdminFlow = !empty($data['for_admin']);

            // Find user by email
            $user = $this->userModel->findByEmail($data['email']);
            
            if (!$user) {
                return $res->json([
                    'success' => false,
                    'message' => 'This email is not registered.',
                    'error_code' => 'EMAIL_NOT_REGISTERED',
                    'status_code' => 404,
                ], 404);
            }
            
            // Get account info
            $account = $this->accountModel->find($user['account_id']);
            
            if (!$account) {
                return $res->json([
                    'success' => false,
                    'message' => 'This email is not registered.',
                    'error_code' => 'EMAIL_NOT_REGISTERED',
                    'status_code' => 404,
                ], 404);
            }

            if ($wantsAdminFlow && !$this->isAdminAccountForReset($user, $account)) {
                return $res->json([
                    'success' => false,
                    'message' => 'This email is not linked to an admin account.',
                    'error_code' => 'NOT_ADMIN_FOR_RESET',
                    'status_code' => 404,
                ], 404);
            }
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            
            // Save reset token to database
            $pdo = $this->container->database()->getConnection();
            $stmt = $pdo->prepare("UPDATE accounts SET password_reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE account_id = ?");
            $stmt->execute([$resetToken, $account['account_id']]);
            
            // Create reset link (admin flow uses /admin-reset-password, storefront uses /reset-password)
            $frontendBaseUrl = rtrim($_ENV['PAYOS_BASE_URL'] ?? 'http://localhost:3000', '/');
            $isAdmin = $this->isAdminAccountForReset($user, $account);
            $resetPath = ($wantsAdminFlow && $isAdmin) ? '/admin-reset-password' : '/reset-password';
            $resetLink = $frontendBaseUrl . $resetPath . '?' . http_build_query([
                'token' => trim($resetToken),
            ]);
            
            // Send email with reset link
            $emailService = new \App\Support\EmailService();
            $emailSent = $emailService->sendPasswordResetLinkEmail($data['email'], $user['account_name'] ?? $account['account_name'], $resetLink);
            
            if ($emailSent) {
                return $res->json(ResponseHelper::success([
                    'message' => 'Password reset link has been sent to your email.',
                ], 'If the email exists, a password reset link has been sent.'));
            } else {
                // If email fails, still return success for security
                return $res->json(ResponseHelper::success(null, 'If the email exists, a password reset link has been sent.'));
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to process forgot password request: ' . $e->getMessage()));
        }
    }
    
    /**
     * Generate random password
     */
    private function generateRandomPassword(int $length = 10): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $maxIndex = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $maxIndex)];
        }
        
        return $password;
    }

    /**
     * Validate reset password token.
     */
    public function validateResetToken(Request $req, Response $res)
    {
        $data = $req->json();

        $validator = Validator::make($data, [
            'token' => 'required',
        ]);

        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }

        try {
            $token = (string)$data['token'];

            $account = $this->accountModel->findByResetToken($token);
            if (!$account) {
                return $res->json(ResponseHelper::error('Invalid or expired reset token'));
            }

            return $res->json(ResponseHelper::success(null, 'Reset token is valid'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Token validation failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Reset Password - Reset password using token
     */
    public function resetPassword(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'token' => 'required',
            'new_password' => 'required',
            'confirm_password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        if ($data['new_password'] !== $data['confirm_password']) {
            return $res->json(ResponseHelper::error('Password confirmation does not match'));
        }
        
        try {
            // Find account by reset token
            $account = $this->accountModel->findByResetToken($data['token']);
            
            if (!$account) {
                return $res->json(ResponseHelper::error('Invalid or expired reset token'));
            }
            
            // Update password
            $this->accountModel->updatePassword($account['account_id'], $data['new_password']);
            
            // Clear reset token
            $this->accountModel->clearPasswordResetToken($account['account_id']);
            
            // Reset failed attempts
            $this->accountModel->resetFailedAttempts($account['account_id']);
            
            return $res->json(ResponseHelper::success(null, 'Password has been reset successfully. You can now login with your new password.'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Password reset failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Advanced Refresh Token - NEW METHOD, doesn't affect old refresh
     */
    public function refreshTokenAdvanced(Request $req, Response $res)
    {
        $data = $req->json();
        $refreshToken = $data['refresh_token'] ?? null;
        
        if (!$refreshToken) {
            return $res->json(ResponseHelper::error('Refresh token is required'));
        }
        
        try {
            // Find valid refresh token
            $tokenData = $this->refreshTokenModel->findByToken($refreshToken);
            
            if (!$tokenData) {
                return $res->json(ResponseHelper::unauthorized('Invalid or expired refresh token'));
            }
            
            // Get user info with roles
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT u.*, a.account_name, r.role_name FROM users u 
                    JOIN accounts a ON u.account_id = a.account_id 
                    LEFT JOIN user_roles ur ON u.user_id = ur.user_id 
                    LEFT JOIN roles r ON ur.role_id = r.role_id 
                    WHERE u.user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tokenData['user_id']]);
            $userData = $stmt->fetchAll();
            
            if (!$userData) {
                return $res->json(ResponseHelper::unauthorized('User not found'));
            }
            
            // Get user basic info from first row
            $user = $userData[0];
            
            // Collect all roles for this user
            $roles = [];
            foreach ($userData as $row) {
                if ($row['role_name']) {
                    $roles[] = $row['role_name'];
                }
            }
            
            // Generate new access token
            $newAccessToken = $this->jwt->encode([
                'user_id' => $user['user_id'],
                'account_id' => $user['account_id'],
                'account_name' => $user['account_name'],
                'roles' => $roles,
                'is_admin' => in_array('admin', $roles)
            ]);
            
            // Rotate refresh token for better security
            $newRefreshToken = $this->refreshTokenModel->generateRefreshToken();
            $this->refreshTokenModel->revokeToken($refreshToken); // Revoke old token
            $this->refreshTokenModel->createToken($user['user_id'], $newRefreshToken); // Create new token
            
            return $res->json(ResponseHelper::success([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ], 'Token refreshed successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Token refresh failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Enhanced Logout with Refresh Token - NEW METHOD
     */
    public function logoutAdvanced(Request $req, Response $res)
    {
        try {
            // Get current user from JWT middleware
            $user = $req->getAttribute('user');
            
            if (!$user) {
                return $res->json(ResponseHelper::unauthorized('Invalid token'));
            }
            
            // Get refresh token from request
            $data = $req->json();
            $refreshToken = $data['refresh_token'] ?? null;
            
            if ($refreshToken) {
                // Revoke the specific refresh token
                $this->refreshTokenModel->revokeToken($refreshToken);
            } else {
                // Revoke all user's refresh tokens if no specific token provided
                $this->refreshTokenModel->revokeAllUserTokens($user['user_id']);
            }
            
            // Update last logout time in database
            $pdo = $this->container->database()->getConnection();
            $sql = "UPDATE accounts SET last_logout_at = NOW() WHERE account_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['account_id']]);
            
            return $res->json(ResponseHelper::success(null, 'Logout successful'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Logout failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Shipper Login - Mobile App Endpoint
     * Login with phone number + password for shippers only
     */
    /**
     * Normalize phone number to handle both +84 and 0 prefix
     * Converts +84xxxxxxxxx to 0xxxxxxxxx and vice versa for comparison
     */
    private function normalizePhone(string $phone): array
    {
        $phone = trim($phone);
        $variations = [$phone]; // Include original
        
        // If starts with +84, also try with 0
        if (strpos($phone, '+84') === 0) {
            $variations[] = '0' . substr($phone, 3);
        }
        // If starts with 0, also try with +84
        elseif (strpos($phone, '0') === 0 && strlen($phone) > 1) {
            $variations[] = '+84' . substr($phone, 1);
        }
        // If starts with 84 (without +), try both
        elseif (strpos($phone, '84') === 0 && strlen($phone) > 2) {
            $variations[] = '0' . substr($phone, 2);
            $variations[] = '+84' . substr($phone, 2);
        }
        
        return array_unique($variations);
    }
    
    public function shipperLogin(Request $req, Response $res)
    {
        // echo "shipperLogin";
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'phone' => 'required',
            'password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Normalize phone number to handle both +84 and 0 prefix
            $phoneVariations = $this->normalizePhone($data['phone']);
            
            // Build query to check all phone variations
            $placeholders = str_repeat('?,', count($phoneVariations) - 1) . '?';
            $sql = "SELECT u.*, a.account_id, a.account_name, a.password, a.is_active, a.last_login_at 
                    FROM users u 
                    JOIN accounts a ON u.account_id = a.account_id 
                    WHERE u.phone IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($phoneVariations);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return $res->json(ResponseHelper::unauthorized('Invalid phone number or password'));
            }
            
            // Verify password
            if (!password_verify($data['password'], $user['password'])) {
                return $res->json(ResponseHelper::unauthorized('Invalid phone number or password'));
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                return $res->json(ResponseHelper::unauthorized('Account is inactive'));
            }
            
            // Check if user is a shipper
            if (!$this->isShipper($user['user_id'])) {
                return $res->json(ResponseHelper::forbidden('Access denied. Shipper account required.'));
            }
            
            // Get shipper info
            $shipperSql = "SELECT s.* FROM shippers s WHERE s.user_id = ?";
            $shipperStmt = $pdo->prepare($shipperSql);
            $shipperStmt->execute([$user['user_id']]);
            $shipper = $shipperStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shipper) {
                return $res->json(ResponseHelper::unauthorized('Shipper record not found'));
            }
            
            // Check shipper status
            if ($shipper['status'] !== 'active') {
                return $res->json(ResponseHelper::forbidden('Shipper account is ' . $shipper['status']));
            }
            
            // Check if shipper is available (optional check, can be removed if not needed)
            // if (!$shipper['is_available']) {
            //     return $res->json(ResponseHelper::forbidden('Shipper is not available'));
            // }
            
            // Update last login
            $updateSql = "UPDATE accounts SET last_login_at = NOW() WHERE account_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$user['account_id']]);
            
            // Generate JWT token
            $token = $this->jwt->encode([
                'user_id' => $user['user_id'],
                'account_id' => $user['account_id'],
                'account_name' => $user['account_name'],
                'phone' => $user['phone'],
                'roles' => ['shipper'],
                'is_shipper' => true
            ]);
            
            // Generate refresh token
            $refreshToken = $this->refreshTokenModel->generateRefreshToken();
            $this->refreshTokenModel->createToken($user['user_id'], $refreshToken);
            
            // Prepare shipper response data
            $shipperData = [
                'user_id' => $user['user_id'],
                'account_id' => $user['account_id'],
                'phone' => $user['phone'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'avatar_url' => $user['avatar_url'],
                'vehicle_info' => $shipper['vehicle_info'],
                'rating' => (float)$shipper['rating'],
                'on_time_delivery_pct' => (float)$shipper['on_time_delivery_pct'],
                'total_delivered' => (int)$shipper['total_delivered'],
                'is_available' => (bool)$shipper['is_available'],
                'status' => $shipper['status']
            ];
            
            return $res->json(ResponseHelper::success([
                'token' => $token,
                'refresh_token' => $refreshToken,
                'shipper' => $shipperData
            ], 'Login successful'));
            
        } catch (Exception $e) {
            error_log('[Shipper Login] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Login failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Shipper Refresh Token - Mobile App Endpoint
     * Refresh token for shippers only
     */
    public function shipperRefreshToken(Request $req, Response $res)
    {
        $data = $req->json();
        $refreshToken = $data['refresh_token'] ?? null;
        
        if (!$refreshToken) {
            return $res->json(ResponseHelper::error('Refresh token is required'));
        }
        
        try {
            // Find valid refresh token
            $tokenData = $this->refreshTokenModel->findByToken($refreshToken);
            
            if (!$tokenData) {
                return $res->json(ResponseHelper::unauthorized('Invalid or expired refresh token'));
            }
            
            // Get user info
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT u.*, a.account_name, a.account_id 
                    FROM users u 
                    JOIN accounts a ON u.account_id = a.account_id 
                    WHERE u.user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tokenData['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return $res->json(ResponseHelper::unauthorized('User not found'));
            }
            
            // Validate that user is a shipper
            if (!$this->isShipper($user['user_id'])) {
                return $res->json(ResponseHelper::forbidden('Access denied. Shipper account required.'));
            }
            
            // Generate new access token
            $newAccessToken = $this->jwt->encode([
                'user_id' => $user['user_id'],
                'account_id' => $user['account_id'],
                'account_name' => $user['account_name'],
                'phone' => $user['phone'],
                'roles' => ['shipper'],
                'is_shipper' => true
            ]);
            
            // Rotate refresh token for better security
            $newRefreshToken = $this->refreshTokenModel->generateRefreshToken();
            $this->refreshTokenModel->revokeToken($refreshToken); // Revoke old token
            $this->refreshTokenModel->createToken($user['user_id'], $newRefreshToken); // Create new token
            
            return $res->json(ResponseHelper::success([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ], 'Token refreshed successfully'));
            
        } catch (Exception $e) {
            error_log('[Shipper Refresh Token] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Token refresh failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Helper method to check if user is a shipper
     */
    private function isShipper(int $userId): bool
    {
        try {
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT COUNT(*) FROM shippers WHERE user_id = ? AND status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();
            return $count > 0;
        } catch (Exception $e) {
            error_log('[isShipper] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate 6-digit OTP code
     */
    private function generateOTP(): string
    {
        return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Shipper Forgot Password - Mobile App Endpoint
     * Send OTP code to email for password reset
     */
    public function shipperForgotPassword(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'email' => 'required|email'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Find user by email
            $user = $this->userModel->findByEmail($data['email']);
            
            if (!$user) {
                // Don't reveal if email exists for security
                return $res->json(ResponseHelper::success(null, 'If the email exists, an OTP code has been sent.'));
            }
            
            // Verify user is a shipper
            if (!$this->isShipper($user['user_id'])) {
                // Don't reveal if email exists for security
                return $res->json(ResponseHelper::success(null, 'If the email exists, an OTP code has been sent.'));
            }
            
            // Get account info
            $account = $this->accountModel->find($user['account_id']);
            
            if (!$account) {
                return $res->json(ResponseHelper::success(null, 'If the email exists, an OTP code has been sent.'));
            }
            
            // Generate 6-digit OTP
            $otpCode = $this->generateOTP();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Save OTP to database (reuse password_reset_token column)
            $stmt = $pdo->prepare("UPDATE accounts SET password_reset_token = ?, reset_token_expires_at = ? WHERE account_id = ?");
            $stmt->execute([$otpCode, $expiresAt, $account['account_id']]);
            
            // Send OTP via email
            $emailService = new \App\Support\EmailService();
            $emailSent = $emailService->sendOTPEmail($data['email'], $user['account_name'] ?? $account['account_name'], $otpCode);
            
            if ($emailSent) {
                return $res->json(ResponseHelper::success([
                    'message' => 'OTP code has been sent to your email.',
                ], 'If the email exists, an OTP code has been sent.'));
            } else {
                // If email fails, still return success for security
                return $res->json(ResponseHelper::success(null, 'If the email exists, an OTP code has been sent.'));
            }
            
        } catch (Exception $e) {
            error_log('[Shipper Forgot Password] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Failed to process forgot password request: ' . $e->getMessage()));
        }
    }

    /**
     * Shipper Verify OTP - Mobile App Endpoint
     * Verify OTP code for password reset
     */
    public function shipperVerifyOTP(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Find user by email
            $user = $this->userModel->findByEmail($data['email']);
            
            if (!$user) {
                return $res->json(ResponseHelper::error('Invalid email or OTP code'));
            }
            
            // Verify user is a shipper
            if (!$this->isShipper($user['user_id'])) {
                return $res->json(ResponseHelper::error('Invalid email or OTP code'));
            }
            
            // Get account info
            $account = $this->accountModel->find($user['account_id']);
            
            if (!$account) {
                return $res->json(ResponseHelper::error('Invalid email or OTP code'));
            }
            
            // Check OTP code matches and not expired
            if ($account['password_reset_token'] !== $data['otp']) {
                return $res->json(ResponseHelper::error('Invalid OTP code'));
            }
            
            if (strtotime($account['reset_token_expires_at']) < time()) {
                return $res->json(ResponseHelper::error('OTP code has expired. Please request a new one.'));
            }
            
            // OTP is valid
            return $res->json(ResponseHelper::success([
                'verified' => true,
                'message' => 'OTP verified successfully. You can now reset your password.'
            ], 'OTP verified successfully'));
            
        } catch (Exception $e) {
            error_log('[Shipper Verify OTP] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Failed to verify OTP: ' . $e->getMessage()));
        }
    }

    /**
     * Shipper Reset Password - Mobile App Endpoint
     * Reset password after OTP verification
     */
    public function shipperResetPassword(Request $req, Response $res)
    {
        $data = $req->json();
        
        // Validate input
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'new_password' => 'required',
            'confirm_password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        
        if ($data['new_password'] !== $data['confirm_password']) {
            return $res->json(ResponseHelper::error('Password confirmation does not match'));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Find user by email
            $user = $this->userModel->findByEmail($data['email']);
            
            if (!$user) {
                return $res->json(ResponseHelper::error('Invalid email or OTP code'));
            }
            
            // Verify user is a shipper
            if (!$this->isShipper($user['user_id'])) {
                return $res->json(ResponseHelper::error('Invalid email or OTP code'));
            }
            
            // Get account info
            $account = $this->accountModel->find($user['account_id']);
            
            if (!$account) {
                return $res->json(ResponseHelper::error('Invalid email or OTP code'));
            }
            
            // Verify OTP again
            if ($account['password_reset_token'] !== $data['otp']) {
                return $res->json(ResponseHelper::error('Invalid OTP code'));
            }
            
            if (strtotime($account['reset_token_expires_at']) < time()) {
                return $res->json(ResponseHelper::error('OTP code has expired. Please request a new one.'));
            }
            
            // Update password
            $this->accountModel->updatePassword($account['account_id'], $data['new_password']);
            
            // Clear OTP token
            $this->accountModel->clearPasswordResetToken($account['account_id']);
            
            // Reset failed attempts
            $this->accountModel->resetFailedAttempts($account['account_id']);
            
            return $res->json(ResponseHelper::success(null, 'Password has been reset successfully. You can now login with your new password.'));
            
        } catch (Exception $e) {
            error_log('[Shipper Reset Password] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Password reset failed: ' . $e->getMessage()));
        }
    }
}
