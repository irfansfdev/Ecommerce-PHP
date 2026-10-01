<?php

class Session
{
    private const CUSTOMER_SESSION = 'customer_session';
    private const ADMIN_SESSION = 'admin_session';

    public static function startCustomer()
    {
        self::startNamed(self::CUSTOMER_SESSION);
    }

    public static function startAdmin()
    {
        self::startNamed(self::ADMIN_SESSION);
    }

    private static function startNamed($name)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() !== $name) {
                throw new LogicException('A different authentication session is already active for this request.');
            }
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Unable to start the authentication session.');
        }
    }

    private static function isSecureRequest()
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }

    private static function requireActive()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new LogicException('Start the customer or admin session before accessing session data.');
        }
    }

    public static function set($key, $value)
    {
        self::requireActive();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        self::requireActive();
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key)
    {
        self::requireActive();
        return isset($_SESSION[$key]);
    }

    public static function remove($key)
    {
        self::requireActive();
        unset($_SESSION[$key]);
    }

    private static function destroyNamed($name, $startSession)
    {
        if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $name) {
            throw new LogicException('Cannot destroy a different authentication session.');
        }
        if (session_status() !== PHP_SESSION_ACTIVE && $startSession) {
            self::startNamed($name);
        }

        $cookieParams = session_get_cookie_params();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        setcookie($name, '', [
            'expires' => time() - 42000,
            'path' => $cookieParams['path'] ?: '/',
            'domain' => $cookieParams['domain'],
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function destroyCustomer()
    {
        self::destroyNamed(self::CUSTOMER_SESSION, true);
    }

    public static function destroyAdmin()
    {
        self::destroyNamed(self::ADMIN_SESSION, true);
    }

    // One-time messages, e.g. "Product added to cart" after a redirect
    public static function flash($key, $message = null)
    {
        self::requireActive();
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return;
        }

        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }

        return null;
    }
}
