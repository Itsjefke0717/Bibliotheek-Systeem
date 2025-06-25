<?php
if (!http_response_code()) {
    http_response_code(404);
}
?>


<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Pagina Niet Gevonden</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../includes/images/logo-bieb.png">
    <link rel="stylesheet" href="404.css">
</head>
<body>
    <div class="bg-bookshelf"></div>
    <div class="book">📖</div>
    <div class="book">📚</div>
    <div class="book">📕</div>
    <div class="container">
        <h1 class="error-code">404</h1>
        <p class="message">Oeps! Deze pagina bestaat niet</p>
        <p class="sub-message">Het lijkt erop dat deze pagina zoek is geraakt tussen de boekenplanken. Geen zorgen, we helpen je graag verder! Probeer terug te keren naar de homepage.</p>
        <a href="../index.php" class="btn">Terug naar de Homepage</a>
    </div>
</body>
</html>