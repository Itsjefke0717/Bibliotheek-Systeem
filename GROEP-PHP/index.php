<?php
// Start sessie & CSRF-token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include("includes/database.php");

// Automatisch inloggen met remember_token
if (!isset($_SESSION['gebruiker_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $token = $_COOKIE['remember_token'];
        $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("SELECT id, email, admin FROM users WHERE remember_token = :token");
        $stmt->bindParam(":token", $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION["gebruiker_id"] = $user["id"];
            $_SESSION["admin"] = $user["admin"];
            $_SESSION["user_email"] = $user["email"];

            $redirect = $user["admin"] == 1 ? 'admin/dashboard.php' : 'students/students.php';
            header("Location: $redirect");
            exit();
        } else {
            setcookie("remember_token", "", time() - 3600, "/");
        }
    } catch (PDOException $e) {
        setcookie("remember_token", "", time() - 3600, "/");
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen • Bibliotheek</title>
    <link rel="icon" href="includes/images/logo-bieb.png" sizes="32x32" type="image/png">
    <link rel="stylesheet" href="style.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<div class="form-container">
    <h2>INLOGGEN</h2>
    <form method="POST" action="" id="login-form">
    <div class="form-group">
        <input type="email" name="email" required placeholder=" " id="email">
        <label for="email">E-mailadres</label>
        <small class="error-message" id="email-error"></small>
    </div>

    <div class="form-group">
        <input type="password" name="wachtwoord" required placeholder=" " id="wachtwoord">
        <label for="wachtwoord">Wachtwoord</label>
        <small class="error-message" id="wachtwoord-error"></small>
    </div>

    <div class="form-group checkbox-container">
        <input type="checkbox" name="remember" id="remember">
        <label for="remember">Inloggen behouden</label>
    </div>

    <!-- CSRF-token -->
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

    <!-- CAPTCHA -->
    <div class="g-recaptcha" data-sitekey="6Lek6BsrAAAAAFV2HruPB97gmU_STLkrEB71Ee0n"></div>

    <!-- Wachtwoord vergeten link -->
    <a href="auth/forgot-password.php" class="btn-link">Wachtwoord vergeten?</a>

    <button type="submit" name="submit" id="submit-btn">
        <span class="btn-text">INLOGGEN</span>
        <span class="btn-loader"></span>
    </button>

    <a href="auth/register.php" class="btn-link">Nog geen account? Aanmelden</a>
</form>
</div>
<script src="script.js"></script>
</body>
</html>

<?php
// Verwerk het inlogformulier
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Controleer CSRF-token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF-token ongeldig!");
    }

    // Sanitize en valideer input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $wachtwoord = trim($_POST['wachtwoord']);
    $remember = isset($_POST['remember']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($wachtwoord)) {
        echo "<div class='php-result error'>Vul alle velden correct in!</div>";
        exit;
    }

    try {
        //voor de website online $conn = new PDO("mysql:host=sql210.infinityfree.com;dbname=if0_38663356_Bibliotheek", "if0_38663356", "Moortje2017");
        //voor developers $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
        $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("SELECT id, wachtwoord, admin FROM users WHERE email = :email");
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($wachtwoord, $row["wachtwoord"])) {
                session_regenerate_id(true);
                $_SESSION["gebruiker_id"] = $row["id"];
                $_SESSION["admin"] = $row["admin"];
                $_SESSION["user_email"] = $email;

                // Remember me
                if ($remember && $row["admin"] == 0) {
                    $token = bin2hex(random_bytes(32));
                    $update = $conn->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
                    $update->bindParam(":token", $token);
                    $update->bindParam(":id", $row["id"]);
                    $update->execute();

                    setcookie("remember_token", $token, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }

                $redirect = $row["admin"] == 1 ? 'admin/dashboard.php' : 'students/students.php';
                header("Location: $redirect");
                exit();
            } else {
                echo "<div class='php-result error'>Onjuist wachtwoord!</div>";
            }
        } else {
            echo "<div class='php-result error'>Gebruiker niet gevonden!</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='php-result error'>Fout: " . $e->getMessage() . "</div>";
    }
}
?>
