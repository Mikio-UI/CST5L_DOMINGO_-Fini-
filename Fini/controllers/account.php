<?php
class AccountController {
    private mysqli $conn;

    public function __construct(string $server, string $user, string $pass, string $db) {
        $this->conn = new mysqli($server, $user, $pass, $db);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    // ── LOGIN ──────────────────────────────────────────────────────────────
    public function login(string $username, string $password): int|false {
        $stmt = $this->conn->prepare(
            "SELECT id, password FROM accounts WHERE username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return false;
        }

        $stmt->bind_result($id, $hashed);
        $stmt->fetch();

        return password_verify($password, $hashed) ? $id : false;
    }

    // ── REGISTER ───────────────────────────────────────────────────────────
    public function register(string $username, string $email, string $password): bool|string {
        // Check for duplicate username or email
        $check = $this->conn->prepare(
            "SELECT id FROM accounts WHERE username = ? OR email = ?"
        );
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            return "Username or email already taken.";
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "INSERT INTO accounts (username, email, password) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $username, $email, $hashed);

        return $stmt->execute() ? true : false;
    }

    // ── SEND PASSWORD RESET ────────────────────────────────────────────────
    public function sendPasswordReset(string $email): bool {
        // Check if email exists
        $check = $this->conn->prepare(
            "SELECT id FROM accounts WHERE email = ?"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            return true; // Return true anyway to avoid email enumeration
        }

        // Delete any existing reset tokens for this email
        $delete = $this->conn->prepare(
            "DELETE FROM password_resets WHERE email = ?"
        );
        $delete->bind_param("s", $email);
        $delete->execute();

        // Generate a secure token, expires in 1 hour
        $token   = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $stmt = $this->conn->prepare(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $email, $token, $expires);
        $stmt->execute();

        // Send the email
        $resetLink = "http://localhost/Fini/reset_password.php?token=" . $token;
        $subject   = "Fini — Password Reset Request";
        $body      = "Click the link below to reset your password. It expires in 1 hour.\n\n" . $resetLink;
        $headers   = "From: no-reply@fini.com";

        mail($email, $subject, $body, $headers);

        return true;
    }

    // ── RESET PASSWORD ─────────────────────────────────────────────────────
    public function resetPassword(string $token, string $newPassword): bool|string {
        $stmt = $this->conn->prepare(
            "SELECT email, expires_at FROM password_resets WHERE token = ?"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return "Invalid or expired reset link.";
        }

        $stmt->bind_result($email, $expires);
        $stmt->fetch();

        if (strtotime($expires) < time()) {
            return "This reset link has expired. Please request a new one.";
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $this->conn->prepare(
            "UPDATE accounts SET password = ? WHERE email = ?"
        );
        $update->bind_param("ss", $hashed, $email);
        $update->execute();

        // Delete the used token
        $delete = $this->conn->prepare(
            "DELETE FROM password_resets WHERE token = ?"
        );
        $delete->bind_param("s", $token);
        $delete->execute();

        return true;
    }
}