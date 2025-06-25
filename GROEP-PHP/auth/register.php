<?php
// Start sessie en output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF-token genereren als deze nog niet bestaat
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include("../includes/database.php");

$errors = [];
$success = false;

// Als formulier is verzonden (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ✅ CSRF-beveiliging
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF-token komt niet overeen!");
    }

    // ✅ Invoer opschonen
    $naam = trim($_POST['naam'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $klas = trim($_POST['klas'] ?? '');
    $geboorte_datum = $_POST['geboorte_datum'] ?? '';
    $wachtwoord = trim($_POST['wachtwoord'] ?? '');
    $bevestig_wachtwoord = trim($_POST['bevestig_wachtwoord'] ?? '');
    $admin = 0;

    // ✅ Validatie
    if (empty($naam) || empty($email) || empty($klas) || empty($geboorte_datum) || empty($wachtwoord) || empty($bevestig_wachtwoord)) {
        $errors[] = "Vul alle velden in.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Ongeldig e-mailadres.";
    }

    if ($wachtwoord !== $bevestig_wachtwoord) {
        $errors[] = "Wachtwoorden komen niet overeen.";
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $wachtwoord)) {
        $errors[] = "Wachtwoord moet minstens 8 tekens bevatten, inclusief één hoofdletter en één cijfer.";
    }

    //voor de website online $conn = new PDO("mysql:host=sql210.infinityfree.com;dbname=if0_38663356_Bibliotheek", "if0_38663356", "Moortje2017");
    //voor developers $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
    // ✅ Als geen fouten, doorgaan met opslaan
    if (empty($errors)) {
        try {
            $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check of e-mailadres al bestaat
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $errors[] = "E-mailadres is al in gebruik.";
            } else {
                // Wachtwoord hashen en gebruiker opslaan
                $hashed_wachtwoord = password_hash($wachtwoord, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (naam, email, klas, geboorte, wachtwoord, admin)
                    VALUES (:naam, :email, :klas, :geboorte, :wachtwoord, :admin)
                ");
                $stmt->bindParam(":naam", $naam);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":klas", $klas);
                $stmt->bindParam(":geboorte", $geboorte_datum);
                $stmt->bindParam(":wachtwoord", $hashed_wachtwoord);
                $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
                $stmt->execute();

                // Sessiegegevens instellen
                $_SESSION['gebruiker_id'] = $conn->lastInsertId();
                $_SESSION['user_naam'] = $naam;
                $_SESSION['user_email'] = $email;
                $_SESSION['is_admin'] = $admin;

                // Doorverwijzen
                header("Location: ../students/students.php");
                exit();
            }
        } catch (PDOException $e) {
            error_log("DB fout: " . $e->getMessage());
            $errors[] = "Er ging iets mis, probeer het later opnieuw.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aanmelden • Bibliotheek</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../images/logo-bieb.png">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
<?php if (!empty($errors)): ?>
    <div class="php-result error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<div class="form-container">
    <h2>AANMELDEN</h2>
    <form method="POST" action="">
        <div class="form-group">
            <input type="text" name="naam" placeholder=" " value="<?= htmlspecialchars($naam ?? '') ?>" required>
            <label>Volledige naam</label>
        </div>
        <div class="form-group">
            <input type="email" name="email" placeholder=" " value="<?= htmlspecialchars($email ?? '') ?>" required>
            <label>E-mailadres</label>
        </div>
        <div class="form-group">
            <input type="text" name="klas" placeholder=" " value="<?= htmlspecialchars($klas ?? '') ?>" required>
            <label>Klas</label>
        </div>
        <div class="form-group">
            <input type="date" name="geboorte_datum" value="<?= htmlspecialchars($geboorte_datum ?? '') ?>" required>
            <label>Geboorte Datum</label>
        </div>
        <div class="form-group">
            <input type="password" name="wachtwoord" id="wachtwoord" placeholder=" " required autocomplete="off" oninput="checkStrength(this.value)">
            <label>Wachtwoord</label>
            
        </div>
        <div class="form-group">
        <div id="password-strength"></div>
        </div>
        <div class="form-group">
            <input type="password" name="bevestig_wachtwoord" placeholder=" " required autocomplete="off">
            <label>Bevestig wachtwoord</label>
        </div>

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

        <button type="submit" name="submit">Aanmelden</button>
        <a href="../index.php" class="btn-link">Al een account? Inloggen</a>
    </form>
</div>
<script src="register.js"></script>
</body>
</html>
