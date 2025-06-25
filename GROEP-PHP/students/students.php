<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../includes/database.php");

//if (!isset($_SESSION['gebruiker_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
//    header("Location: ../index.php");
///    exit;
//}

// Automatisch inloggen via remember_token cookie
if (!isset($_SESSION['gebruiker_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    try {
        $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
        //voor de website online $conn = new PDO("mysql:host=sql210.infinityfree.com;dbname=if0_38663356_Bibliotheek", "if0_38663356", "Moortje2017");
        //voor developers $conn = new PDO("mysql:host=localhost;dbname=bibliotheek", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("SELECT id, email, admin FROM users WHERE remember_token = :token");
        $stmt->bindParam(":token", $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Log gebruiker in
            $_SESSION["gebruiker_id"] = $user["id"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["admin"] = $user["admin"];
        } else {
            // Token is ongeldig — cookie verwijderen
            setcookie("remember_token", "", time() - 3600, "/");
        }
    } catch (PDOException $e) {
        setcookie("remember_token", "", time() - 3600, "/");
    }
}

$gebruiker_id = $_SESSION["gebruiker_id"];

// Algemene functie voor meldingen
function setMelding($bericht, $type = 'success') {
    $_SESSION['melding'] = $bericht;
    $_SESSION['melding_type'] = $type;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Reserveren of lenen
if (isset($_POST['reserveer'])) {
    $boek_id = (int)$_POST['boek_id'];

    // Controleer aantal geleende boeken
    $stmt = $conn->prepare("SELECT COUNT(*) AS aantal FROM reserveringen WHERE gebruiker_id = ? AND status = 'geleend'");
    $stmt->bind_param("i", $gebruiker_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['aantal'] >= 2) {
        setMelding('Maximaal 2 boeken tegelijk lenen!', 'error');
    }

    // Controleer bestaande reserveringen
    $stmt = $conn->prepare("SELECT id FROM reserveringen WHERE gebruiker_id = ? AND status = 'gereserveerd'");
    $stmt->bind_param("i", $gebruiker_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        setMelding('Je hebt al een reservering!', 'error');
    }

    // Controleer boekbeschikbaarheid
    $stmt = $conn->prepare("SELECT aantal_beschikbaar FROM boeken WHERE id = ?");
    $stmt->bind_param("i", $boek_id);
    $stmt->execute();
    $boek = $stmt->get_result()->fetch_assoc();

    if ($boek['aantal_beschikbaar'] > 0) {
        // Boek lenen
        $stmt = $conn->prepare("UPDATE boeken SET aantal_beschikbaar = aantal_beschikbaar - 1 WHERE id = ?");
        $stmt->bind_param("i", $boek_id);
        $stmt->execute();

        $datum_terug = date('Y-m-d', strtotime('+15 days'));
        $stmt = $conn->prepare("INSERT INTO reserveringen (gebruiker_id, boek_id, status, datum_terug) VALUES (?, ?, 'geleend', ?)");
        $stmt->bind_param("iis", $gebruiker_id, $boek_id, $datum_terug);
        $stmt->execute();
        setMelding('Boek succesvol geleend!');
    } else {
        // Boek reserveren
        $stmt = $conn->prepare("INSERT INTO reserveringen (gebruiker_id, boek_id, status) VALUES (?, ?, 'gereserveerd')");
        $stmt->bind_param("ii", $gebruiker_id, $boek_id);
        $stmt->execute();
        setMelding('Boek gereserveerd!');
    }
}

// Verlengen
if (isset($_POST['verleng'])) {
    $reservering_id = (int)$_POST['reservering_id'];
    $nieuwe_datum = $_POST['nieuwe_datum'];

    $stmt = $conn->prepare("SELECT datum_terug FROM reserveringen WHERE id = ?");
    $stmt->bind_param("i", $reservering_id);
    $stmt->execute();
    $reservering = $stmt->get_result()->fetch_assoc();

    if ($reservering && strtotime($nieuwe_datum) > strtotime($reservering['datum_terug'])) {
        $stmt = $conn->prepare("UPDATE reserveringen SET verlenging_datum = ?, verlenging_status = 'in afwachting' WHERE id = ?");
        $stmt->bind_param("si", $nieuwe_datum, $reservering_id);
        $stmt->execute();
        setMelding('Verlenging aangevraagd!');
    } else {
        setMelding('Kies een latere datum!', 'error');
    }
}

// Terugbrengen
if (isset($_POST['terugbrengen'])) {
    $reservering_id = (int)$_POST['reservering_id'];

    $stmt = $conn->prepare("SELECT boek_id FROM reserveringen WHERE id = ?");
    $stmt->bind_param("i", $reservering_id);
    $stmt->execute();
    $reservering = $stmt->get_result()->fetch_assoc();

    if ($reservering) {
        $boek_id = $reservering['boek_id'];

        // Controleer wachtrij
        $stmt = $conn->prepare("SELECT id, gebruiker_id FROM reserveringen WHERE boek_id = ? AND status = 'gereserveerd' ORDER BY reserveringen_tijd ASC LIMIT 1");
        $stmt->bind_param("i", $boek_id);
        $stmt->execute();
        $wachtrij = $stmt->get_result()->fetch_assoc();

        if ($wachtrij) {
            $datum_terug = date('Y-m-d', strtotime('+15 days'));
            $stmt = $conn->prepare("UPDATE reserveringen SET status = 'geleend', datum_terug = ? WHERE id = ?");
            $stmt->bind_param("si", $datum_terug, $wachtrij['id']);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE boeken SET aantal_beschikbaar = aantal_beschikbaar + 1 WHERE id = ?");
            $stmt->bind_param("i", $boek_id);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE reserveringen SET status = 'teruggebracht', datum_terug = NOW() WHERE id = ?");
        $stmt->bind_param("i", $reservering_id);
        $stmt->execute();
        setMelding('Boek teruggebracht!');
    } else {
        setMelding('Fout bij terugbrengen.', 'error');
    }
}

// Annuleren
if (isset($_POST['annuleer'])) {
    $reservering_id = (int)$_POST['reservering_id'];
    $stmt = $conn->prepare("DELETE FROM reserveringen WHERE id = ? AND gebruiker_id = ?");
    $stmt->bind_param("ii", $reservering_id, $gebruiker_id);
    $stmt->execute();
    setMelding('Reservering geannuleerd!');
}

// Data ophalen
$boeken = $conn->query("SELECT * FROM boeken")->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT r.id, b.titel, r.datum_terug, r.verlenging_datum, r.verlenging_status 
                        FROM reserveringen r JOIN boeken b ON r.boek_id = b.id 
                        WHERE r.gebruiker_id = ? AND r.status = 'geleend'");
$stmt->bind_param("i", $gebruiker_id);
$stmt->execute();
$geleende_boeken = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT r.id, b.titel FROM reserveringen r JOIN boeken b ON r.boek_id = b.id 
                        WHERE r.gebruiker_id = ? AND r.status = 'gereserveerd'");
$stmt->bind_param("i", $gebruiker_id);
$stmt->execute();
$reserveringen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Leerlingen • Bibliotheek</title>
    <link rel="stylesheet" href="students.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../includes/images/logo-bieb.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<?php
if (isset($_SESSION['melding'])) {
    $type = isset($_SESSION['melding_type']) ? $_SESSION['melding_type'] : 'success';
    echo '<div id="melding" class="melding ' . $type . '">' . $_SESSION['melding'] . '</div>';
    unset($_SESSION['melding'], $_SESSION['melding_type']);
}
?>
    <h2>Schoolbibliotheek Systeem</h2>

    <h3>Jouw Geleende Boeken</h3>
    <ul>
        <?php foreach ($geleende_boeken as $boek): ?>
            <li>
                <?= htmlspecialchars($boek['titel']) ?> - 
                Terugbrengen op: <?= htmlspecialchars($boek['datum_terug']) ?>
                <?php if (!empty($boek['verlenging_status']) && $boek['verlenging_status'] != 'in afwachting'): ?>
                <?php if ($boek['verlenging_status'] == 'goedgekeurd'): ?>
                    (Verlenging goedgekeurd tot: <?= htmlspecialchars($boek['verlenging_datum']) ?>)
                <?php elseif ($boek['verlenging_status'] == 'afgewezen'): ?>
                    (Verlenging afgewezen)
                <?php endif; ?>
            <?php elseif ($boek['verlenging_status'] == 'in afwachting' && !empty($boek['verlenging_datum'])): ?>
                (Verlenging in afwachting)
        <?php endif; ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="reservering_id" value="<?= $boek['id'] ?>">
                <button type="button" class="verleng-button" onclick="showOverlay(<?= $boek['id'] ?>)">Verleng</button>
                <button type="submit" name="terugbrengen">Terugbrengen</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Overlay voor het verlengen van een boek -->
    <div id="overlay" class="overlay">
        <div class="overlay-content">
            <h3>Verleng Boek</h3>
            <form id="verleng-form" method="POST">
                <input type="hidden" name="reservering_id" id="reservering_id">
                <label for="nieuwe_datum">Kies nieuwe datum:</label>
                <input type="date" name="nieuwe_datum" required>
                <button type="submit" name="verleng">Verstuur verlengingsverzoek</button>
            </form>
            <button onclick="closeOverlay()">Sluit</button>
        </div>
    </div>

    <h3>Beschikbare boeken</h3>
    <!-- Zoekbalk en reserveringen-knop inline -->
    <div class="search-container">
        <input type="text" id="zoekbalk" placeholder="Zoek een boek op titel, auteur of categorie..." onkeyup="zoekBoeken()">
        <button id="reserveringen-knop" onclick="toonReserveringenPopup()">📚 Bekijk Reserveringen</button>
    </div>

    <!-- Klein vlak voor reserveringen -->
    <div id="reserveringenPopup" class="reserveringen-popup">
        <ul>
            <?php if (!empty($reserveringen)): ?>
                <?php foreach ($reserveringen as $reservering): ?>
                    <li>
                        <?= htmlspecialchars($reservering['titel']) ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="reservering_id" value="<?= $reservering['id'] ?>">
                            <button type="submit" name="annuleer">❌</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>Geen reserveringen</li>
            <?php endif; ?>
        </ul>
    </div>

    <ul id="boekenlijst">
        <?php foreach ($boeken as $boek): ?>
            <li class="<?= $boek['aantal_beschikbaar'] == 0 ? 'niet-beschikbaar' : '' ?>" 
                onclick="toonPopup('<?= htmlspecialchars($boek['titel']) ?>', '<?= htmlspecialchars($boek['afbeelding']) ?>', '<?= htmlspecialchars($boek['beschrijving']) ?>')">
                <?= htmlspecialchars($boek['titel']) ?> - <?= htmlspecialchars($boek['auteur']) ?> 
                - Categorie: <?= htmlspecialchars($boek['categorie']) ?>
                - Beschikbare exemplaren: <?= $boek['aantal_beschikbaar'] ?>
                <?php if ($boek['aantal_beschikbaar'] > 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="boek_id" value="<?= $boek['id'] ?>">
                        <button type="submit" name="reserveer">Leen</button>
                    </form>
                <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="boek_id" value="<?= $boek['id'] ?>">
                        <button type="submit" name="reserveer">Reserveer</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Popup Overlay voor het tonen van details -->
    <div id="popup-overlay"></div>
    <div id="popup" style="display: none;">
        <h2 id="popup-titel"></h2>
        <img id="popup-afbeelding" src="" alt="">
        <p id="popup-beschrijving"></p>
        <button onclick="sluitPopup()">Sluiten</button>
    </div>

    <a href="../logout.php" id="uitloggen">Uitloggen</a>

    <script src="students.js"></script>
</body>
</html>