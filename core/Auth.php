<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Cart.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function register($name, $email, $password)
    {
        $existing = $this->db->selectOne(
            "SELECT id FROM users WHERE email = ?",
            [$email]
        );

        if ($existing) {
            return ['success' => false, 'message' => 'An account with this email already exists.'];
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $this->db->insert(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')",
            [$name, $email, $hashed]
        );

        return ['success' => true];
    }

    public function loginCustomer($email, $password)
    {
        return $this->loginForRole($email, $password, 'customer');
    }

    public function loginAdmin($email, $password)
    {
        return $this->loginForRole($email, $password, 'admin');
    }

    private function loginForRole($email, $password, $requiredRole)
    {
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        if ((int) $user['is_active'] === 0) {
            return ['success' => false, 'message' => 'This account has been deactivated.'];
        }

        if ($user['role'] !== $requiredRole) {
            $message = $requiredRole === 'admin'
                ? 'This login is for administrators only.'
                : 'Please use the administrator sign-in page for this account.';
            return ['success' => false, 'message' => $message];
        }

        if ($requiredRole === 'admin') {
            Session::startAdmin();
        } else {
            Session::startCustomer();
        }
        session_regenerate_id(true);

        Session::set('user_id', $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);
        if ($requiredRole === 'customer') {
            $cart = new Cart($this->db, $user['id']);
            $cart->mergeGuestCart();
        }

        return ['success' => true, 'role' => $user['role']];
    }

    public static function isLoggedIn()
    {
        Session::startCustomer();
        return Session::has('user_id') && Session::get('user_role') === 'customer';
    }

    public static function getCurrentCustomer()
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'id' => (int) Session::get('user_id'),
            'name' => Session::get('user_name'),
        ];
    }

    public static function isAdmin()
    {
        Session::startAdmin();
        return Session::has('user_id') && Session::get('user_role') === 'admin';
    }

    public static function getCurrentAdmin()
    {
        if (!self::isAdmin()) {
            return null;
        }
        return [
            'id' => (int) Session::get('user_id'),
            'name' => Session::get('user_name'),
        ];
    }

    public static function requireCustomer($redirectTo = 'login.php')
    {
        Session::startCustomer();
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireAdmin($redirectTo = 'login.php')
    {
        Session::startAdmin();
        if (!self::isAdmin()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function logoutCustomer()
    {
        Session::destroyCustomer();
    }

    public static function logoutAdmin()
    {
        Session::destroyAdmin();
    }
}
