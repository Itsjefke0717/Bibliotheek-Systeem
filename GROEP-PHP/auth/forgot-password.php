<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord vergeten • Bibliotheek</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="form-container">
        <h2>Wachtwoord vergeten?</h2>
        <p style="text-align: center; font-size: 15px; margin-bottom: 30px;">
            Geen zorgen! Deze functie is binnenkort beschikbaar. <br>
            Probeer het later opnieuw of neem contact op met de beheerder.
        </p>
        <a href="../index.php" class="btn-link">← Terug naar inloggen</a>
    </div>
</body>
</html>
