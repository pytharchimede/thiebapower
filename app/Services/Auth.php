<?php
namespace App\Services;

use App\Core\App;

final class Auth
{
    public const PERMISSIONS = [
        "sms.view" => "Consulter les SMS Orange et leur historique",
        "sms.manage" => "Configurer et activer les SMS Orange",
        "sms.test" => "Tester Orange et envoyer un SMS réel",
        "system.manage" => "Administrer le système et les incidents",
        "dashboard.view" => "Voir le tableau de bord",
        "pricing.view" => "Consulter la tarification",
        "pricing.manage" => "Modifier les tarifs",
        "stations.view" => "Consulter les terminaux et leurs fiches",
        "batteries.view" => "Consulter les batteries et leurs fiches",
        "labels.view" => "Consulter les étiquettes QR",
        "fleet.release" => "Éjecter manuellement une batterie",
        "rentals.cancel" => "Libérer une réservation après contrôle du paiement",
        "rentals.view" => "Consulter les locations et leurs fiches",
        "reports.export" => "Exporter les données et reçus",
        "finance.withdraw" => "Effectuer et vérifier un retrait réel",
        "fleet.manage" => "Gérer les batteries",
        "integrations.manage" => "Changer les modes des intégrations",
        "rentals.manage" => "Confirmer les locations simulées",
        "payout.view" => "Consulter les paiements et les réponses API",
        "payout.send" => "Initier et clôturer les essais financiers",
        "finance.view" => "Voir les points financiers et la caisse",
        "finance.manage" => "Enregistrer les mouvements de caisse",
        "audit.view" => "Consulter le journal et les visites",
        "users.manage" => "Gérer les comptes et les permissions",
    ];
    public const ROLES = [
        "owner" => "Propriétaire",
        "manager" => "Gestionnaire",
        "operator" => "Opérateur",
        "auditor" => "Auditeur",
    ];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!headers_sent()) {
            session_name("tbp_admin");
            session_set_cookie_params([
                "lifetime" => 0,
                "path" => "/",
                "secure" =>
                    !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
                "httponly" => true,
                "samesite" => "Lax",
            ]);
            ini_set("session.use_strict_mode", "1");
        }
        session_start();
        $_SESSION["csrf"] ??= bin2hex(random_bytes(32));
    }

    public static function user(): ?array
    {
        if (
            session_status() !== PHP_SESSION_ACTIVE ||
            empty($_SESSION["user_id"])
        ) {
            return null;
        }
        if (time() - (int) ($_SESSION["last_activity"] ?? 0) > 3600) {
            unset($_SESSION["user_id"]);
            return null;
        }
        $_SESSION["last_activity"] = time();
        static $cached = [];
        $key = (int) $_SESSION["user_id"];
        if (array_key_exists($key, $cached)) {
            return $cached[$key];
        }
        try {
            $query = App::db()->prepare(
                "SELECT id,username,display_name,role,is_active FROM users WHERE id=?",
            );
            $query->execute([$_SESSION["user_id"]]);
            $user = $query->fetch();
            return $cached[$key] =
                $user && (int) $user["is_active"] === 1 ? $user : null;
        } catch (\Throwable $e) {
            error_log("Authentication lookup failed: " . $e->getMessage());
            return null;
        }
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user["id"] : null;
    }

    public static function isTechnicalAdmin(array $user): bool
    {
        $username=App::env('TECHNICAL_ADMIN_USERNAME',App::env('ADMIN_USERNAME','admin'));
        return $username!=='' && hash_equals($username,(string)($user['username']??''));
    }

    public static function can(string $permission, ?array $user = null): bool
    {
        $user ??= self::user();
        if (!$user || !isset(self::PERMISSIONS[$permission])) {
            return false;
        }
        if (in_array($permission,['sms.manage','sms.test','integrations.manage','payout.send'],true) && !self::isTechnicalAdmin($user)) {
            return false;
        }
        if ($user["role"] === "owner") {
            return true;
        }
        if ($permission === "users.manage") {
            return false;
        }
        static $permissions = [];
        if (!isset($permissions[$user["role"]])) {
            $query = App::db()->prepare(
                "SELECT permission FROM role_permissions WHERE role=?",
            );
            $query->execute([$user["role"]]);
            $permissions[$user["role"]] = array_column(
                $query->fetchAll(),
                "permission",
            );
        }
        return in_array($permission, $permissions[$user["role"]], true);
    }

    public static function requirePermission(
        string $permission,
        bool $mutation = false,
    ): array {
        self::start();
        $user = self::user();
        if (!$user) {
            if ($mutation) {
                http_response_code(401);
                exit("Connexion requise");
            }
            App::redirect("/admin/login");
            exit();
        }
        if (!self::can($permission, $user)) {
            Audit::event("authorization.denied", "permission", $permission);
            http_response_code(403);
            exit("Permission insuffisante");
        }
        if (
            $mutation &&
            !hash_equals(
                (string) $_SESSION["csrf"],
                (string) ($_POST["csrf"] ?? ""),
            )
        ) {
            Audit::event("csrf.denied");
            http_response_code(403);
            exit("Formulaire expiré");
        }
        return $user;
    }

    public static function validUsername(string $username): bool
    {
        return preg_match('/^[^\p{C}]{1,80}$/uD', trim($username)) === 1;
    }

    public static function login(string $username, string $password): bool
    {
        self::start();
        $username = trim($username);
        if (!self::validUsername($username) || strlen($password) > 512) {
            Audit::event("auth.invalid_input");
            return false;
        }
        $db = App::db();
        self::bootstrapOwner();
        $ip = Audit::ip();
        $query = $db->prepare(
            "SELECT SUM(username=?),COUNT(*) FROM auth_attempts WHERE ip_address=? AND successful=0 AND occurred_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)",
        );
        $query->execute([$username, $ip]);
        [$byUser, $byIp] = $query->fetch(\PDO::FETCH_NUM);
        if ((int) $byUser >= 5 || (int) $byIp >= 20) {
            Audit::event("auth.rate_limited", "user", $username);
            return false;
        }
        $query = $db->prepare("SELECT * FROM users WHERE username=?");
        $query->execute([$username]);
        $user = $query->fetch();
        $valid =
            $user &&
            (int) $user["is_active"] === 1 &&
            password_verify($password, $user["password_hash"]);
        $db->prepare(
            "INSERT INTO auth_attempts(username,ip_address,successful) VALUES(?,?,?)",
        )->execute([$username, $ip, $valid ? 1 : 0]);
        if (!$valid) {
            Audit::event("auth.failed", "user", $username);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int) $user["id"];
        $_SESSION["last_activity"] = time();
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        $db->prepare(
            "UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=?",
        )->execute([$user["id"]]);
        Audit::event("auth.login", "user", (string) $user["id"]);
        return true;
    }

    public static function logout(): void
    {
        self::start();
        Audit::event("auth.logout", "user", (string) (self::id() ?? ""));
        $_SESSION = [];
        session_regenerate_id(true);
    }

    private static function bootstrapOwner(): void
    {
        $db = App::db();
        if (
            (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn() !== 0
        ) {
            return;
        }
        $hash = App::env("ADMIN_PASSWORD_HASH");
        if ($hash === "") {
            throw new \RuntimeException("Mot de passe propriétaire absent");
        }
        $db->prepare(
            "INSERT IGNORE INTO users(username,display_name,password_hash,role) VALUES(?,?,?,'owner')",
        )->execute([
            App::env("ADMIN_USERNAME", "admin"),
            "Administrateur",
            $hash,
        ]);
        Audit::event(
            "auth.owner_bootstrap",
            "user",
            App::env("ADMIN_USERNAME", "admin"),
        );
    }
}
