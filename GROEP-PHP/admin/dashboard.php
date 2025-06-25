<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../includes/database.php");

if (!isset($_SESSION['gebruiker_id']) || $_SESSION['admin'] != 1) {
    header("Location: ../index.php");
    exit;
}

$gebruiker_id = $_SESSION["gebruiker_id"];

// Fetch user details
$stmt = $conn->prepare("SELECT naam, email, Geboorte FROM users WHERE id = ?");
$stmt->bind_param("i", $gebruiker_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: (header("Location: ../index.php") && exit);
$stmt->close();

function logActie($conn, $actie, $reservering_id, $gebruiker_id) {
    $stmt = $conn->prepare("INSERT INTO logs (actie, reservering_id, gebruiker_id, tijdstip) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sii", $actie, $reservering_id, $gebruiker_id);
    $stmt->execute();
}

// Fetch data
$boeken = $conn->query("SELECT * FROM boeken")->fetch_all(MYSQLI_ASSOC);
$reserveringen = $conn->query(
    "SELECT r.id, b.titel, u.naam, b.categorie, r.status, r.datum_reservering, r.datum_terug, r.verlenging_status, r.verlenging_datum, r.boek_id, r.gebruiker_id
     FROM reserveringen r JOIN boeken b ON r.boek_id = b.id JOIN users u ON r.gebruiker_id = u.id"
)->fetch_all(MYSQLI_ASSOC);
$verlengingsverzoeken = $conn->query(
    "SELECT r.id, b.titel, u.naam, r.datum_terug, r.verlenging_datum, r.verlenging_aanvraag_datum
     FROM reserveringen r JOIN boeken b ON r.boek_id = b.id JOIN users u ON r.gebruiker_id = u.id
     WHERE r.verlenging_status = 'in afwachting'"
)->fetch_all(MYSQLI_ASSOC);
$leden = $conn->query("SELECT id, naam, email, klas, Geboorte FROM users")->fetch_all(MYSQLI_ASSOC);
$logs = $conn->query(
    "SELECT l.id, l.actie, l.reservering_id, l.tijdstip, u1.naam AS admin_naam, u2.naam AS gebruiker_naam, u2.email AS gebruiker_email, u2.Geboorte AS gebruiker_geboorte
     FROM logs l
     JOIN users u1 ON l.gebruiker_id = u1.id
     LEFT JOIN reserveringen r ON l.reservering_id = r.id
     LEFT JOIN users u2 ON r.gebruiker_id = u2.id
     ORDER BY l.tijdstip DESC"
)->fetch_all(MYSQLI_ASSOC);
$total_books = $conn->query("SELECT COUNT(*) as total FROM boeken")->fetch_assoc()['total'];
$total_members = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle image upload
    function handleImageUpload($file, $target_dir = "../Uploads/studenten/") {
        if ($file['size'] > 5000000 || !getimagesize($file['tmp_name'])) {
            echo "Ongeldige afbeelding.";
            exit;
        }
        $filename = basename($file['name']);
        $target = $target_dir . $filename;
        return move_uploaded_file($file['tmp_name'], $target) ? $filename : false;
    }

    // Add book
    if (isset($_POST['toevoegen'])) {
        $data = array_map('htmlspecialchars', $_POST);
        $afbeelding_naam = handleImageUpload($_FILES['afbeelding']);
        if ($afbeelding_naam) {
            $stmt = $conn->prepare("INSERT INTO boeken (titel, auteur, categorie, aantal_beschikbaar, beschrijving, afbeelding) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $data['titel'], $data['auteur'], $data['categorie'], $data['aantal_beschikbaar'], $data['beschrijving'], $afbeelding_naam);
            $stmt->execute();
        }
    }

    // Return book
if (isset($_POST['terugbrengen'])) {
    $stmt = $conn->prepare("UPDATE reserveringen SET status = 'teruggebracht' WHERE id = ?");
    $stmt->bind_param("i", $_POST['reservering_id']);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE boeken SET aantal_beschikbaar = aantal_beschikbaar + 1 WHERE id = ?");
    $stmt->bind_param("i", $_POST['boek_id']);
    $stmt->execute();

    logActie($conn, "Boek teruggebracht", $_POST['reservering_id'], $gebruiker_id);
}

    // Update book
    if (isset($_POST['wijzigen_boek'])) {
        $data = array_map('htmlspecialchars', $_POST);
        $afbeelding_naam = $_POST['huidige_afbeelding'];
        if (!empty($_FILES['afbeelding']['name'])) {
            $afbeelding_naam = handleImageUpload($_FILES['afbeelding']) ?: $afbeelding_naam;
        }
        $stmt = $conn->prepare("UPDATE boeken SET titel = ?, auteur = ?, categorie = ?, aantal_beschikbaar = ?, beschrijving = ?, afbeelding = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $data['titel'], $data['auteur'], $data['categorie'], $data['aantal_beschikbaar'], $data['beschrijving'], $afbeelding_naam, $data['boek_id']);
        $stmt->execute();
    }

    // Update member
    if (isset($_POST['wijzigen_lid'])) {
        $data = array_map('htmlspecialchars', $_POST);
        $stmt = $conn->prepare("UPDATE users SET naam = ?, email = ?, klas = ?, Geboorte = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $data['naam'], $data['email'], $data['klas'], $data['geboorte'], $data['user_id']);
        $stmt->execute();
    }

    // Delete member
    if (isset($_POST['verwijderen_lid'])) {
        $user_id = $_POST['user_id'];
        $conn->query("DELETE FROM logs WHERE reservering_id IN (SELECT id FROM reserveringen WHERE gebruiker_id = $user_id)");
        $conn->query("DELETE FROM reserveringen WHERE gebruiker_id = $user_id");
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND admin != 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    // Delete book
    if (isset($_POST['verwijderen'])) {
        $boek_id = $_POST['boek_id'];
        $conn->query("DELETE FROM logs WHERE reservering_id IN (SELECT id FROM reserveringen WHERE boek_id = $boek_id)");
        $conn->query("DELETE FROM reserveringen WHERE boek_id = $boek_id");
        $stmt = $conn->prepare("DELETE FROM boeken WHERE id = ?");
        $stmt->bind_param("i", $boek_id);
        $stmt->execute();
    }

    // Approve extension
    if (isset($_POST['goedkeuren'])) {
        $stmt = $conn->prepare("UPDATE reserveringen SET verlenging_status = 'goedgekeurd', datum_terug = ? WHERE id = ?");
        $stmt->bind_param("si", $_POST['datum_terug'], $_POST['reservering_id']);
        $stmt->execute();
        logActie($conn, "Verlenging goedgekeurd", $_POST['reservering_id'], $gebruiker_id);
    }

    // Reject extension
    if (isset($_POST['afwijzen'])) {
        $stmt = $conn->prepare("UPDATE reserveringen SET verlenging_status = 'afgewezen' WHERE id = ?");
        $stmt->bind_param("i", $_POST['reservering_id']);
        $stmt->execute();
        logActie($conn, "Verlenging afgewezen", $_POST['reservering_id'], $gebruiker_id);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Beheer • Bibliotheek</title>
    <link rel="stylesheet" href="dashboard.css?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="../includes/images/logo-bieb.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <img src="../includes/images/logo-bieb.png" alt="Bibliotheek Logo" class="sidebar-logo">
        <ul class="sidebar-menu">
            <li><a href="#"><i class="fas fa-home"></i></a></li>
            <li><a href="#"><i class="fas fa-book"></i></a></li>
            <li><a href="#"><i class="fas fa-clipboard-list"></i></a></li>
            <li><a href="#"><i class="fas fa-history"></i></a></li>
            <li><a href="../logout.php" class="logout-link"><i class="fas fa-sign-out"></i></a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1>Beheer Schoolbibliotheek</h1>
            <div class="user-section">
                <span class="user-name"><?= htmlspecialchars($user['naam']) ?></span>
                <div class="user-profile" onclick="showUserOverlay()">👤</div>
            </div>
        </header>

        <!-- User Overlay -->
        <div class="user-overlay" id="user-overlay">
            <div class="user-overlay-content">
                <h3>Gebruikersinformatie</h3>
                <p><strong>Naam:</strong> <?= htmlspecialchars($user['naam']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Geboortedatum:</strong> <?= htmlspecialchars($user['Geboorte']) ?></p>
                <button onclick="closeUserOverlay()">Sluiten</button>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-card">
                <h3>Totale Boeken</h3>
                <p><?= $total_books ?></p>
                <button class="add-btn" onclick="showAddBookOverlay()">Boek Toevoegen</button>
            </div>
            <div class="stat-card">
                <h3>Actieve Leden</h3>
                <p><?= $total_members ?></p>
                <button class="add-btn secondary" onclick="showMembersOverlay()">Leden Beheren</button>
            </div>
            <div class="stat-card">
                <h3>Uitgeleende Boeken</h3>
                <p><?= $conn->query("SELECT COUNT(*) as total FROM reserveringen WHERE status = 'geleend'")->fetch_assoc()['total'] ?></p>
            </div>
        </div>

        <!-- Ledenbeheer Overlay -->
        <div class="overlay" id="leden-overlay">
            <div class="overlay-content">
                <h3>Ledenbeheer</h3>
                <input type="text" id="search-members" placeholder="Zoek op naam of klas..." oninput="filterMembers()">
                <ul class="member-list" id="member-list">
                    <?php foreach ($leden as $lid): ?>
                        <li class="member-item">
                            <div class="member-summary" onclick="toggleDropdown(<?= $lid['id'] ?>)">
                                <span class="member-name"><?= htmlspecialchars($lid['naam']) ?></span> - 
                                <span class="member-klas"><?= htmlspecialchars($lid['klas'] ?? '') ?></span>
                                <span class="member-email"><?= htmlspecialchars($lid['email']) ?></span>
                            </div>
                            <div class="member-details" id="details-<?= $lid['id'] ?>">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $lid['id'] ?>">
                                    <label>Naam: <input type="text" name="naam" value="<?= htmlspecialchars($lid['naam']) ?>" required></label>
                                    <label>Email: <input type="email" name="email" value="<?= htmlspecialchars($lid['email']) ?>" required></label>
                                    <label>Klas: <input type="text" name="klas" value="<?= htmlspecialchars($lid['klas'] ?? '') ?>"></label>
                                    <label>Geboortedatum: <input type="date" name="geboorte" value="<?= htmlspecialchars($lid['Geboorte']) ?>" required></label>
                                    <button type="submit" name="wijzigen_lid">Wijzigen</button>
                                    <?php if ($lid['id'] != $gebruiker_id): ?>
                                        <button type="submit" name="verwijderen_lid" onclick="return confirm('Lid verwijderen?')">Verwijderen</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button onclick="closeMembersOverlay()">Sluiten</button>
            </div>
        </div>

        <!-- Reserveringen -->
        <div class="card">
            <h3>Reserveringen</h3>
            <ul>
                <?php foreach ($reserveringen as $res): ?>
                    <li class="<?= strtotime($res['datum_terug']) < time() && $res['status'] == 'geleend' ? 'te-laat' : '' ?>">
                        <span>
                            <strong>ID:</strong> <?= htmlspecialchars($res['id']) ?> - 
                            <strong>Title:</strong> <?= htmlspecialchars($res['titel']) ?> - 
                            <strong>Naam:</strong> <?= htmlspecialchars($res['naam']) ?> - 
                            <strong>Uitleen:</strong> <?= htmlspecialchars($res['datum_reservering']) ?> - 
                            <strong>Terugbrengen:</strong> <?= htmlspecialchars($res['datum_terug']) ?> - 
                            <strong>Status:</strong> <?= htmlspecialchars($res['status']) ?> - 
                            <strong>Verlenging Status:</strong> <?= htmlspecialchars($res['verlenging_status'] ?? 'Geen') ?> 
                            <?php if ($res['verlenging_datum']): ?>
                                - <strong>Verlenging Datum:</strong> <?= htmlspecialchars($res['verlenging_datum']) ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($res['status'] == 'geleend'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="reservering_id" value="<?= $res['id'] ?>">
                                <input type="hidden" name="boek_id" value="<?= $res['boek_id'] ?>">
                                <button type="submit" name="terugbrengen">Terugbrengen</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Verlengingsverzoeken -->
        <div class="card">
            <h3>Verlengingsverzoeken</h3>
            <ul>
                <?php foreach ($verlengingsverzoeken as $verzoek): ?>
                    <li>
                        <span>
                            <strong>ID:</strong> <?= htmlspecialchars($verzoek['id']) ?> - 
                            <strong>Title:</strong><?= htmlspecialchars($verzoek['titel']) ?> - 
                            <strong>Naam:</strong> <?= htmlspecialchars($verzoek['naam']) ?> - 
                            <strong>Huidige Terug:</strong> <?= htmlspecialchars($verzoek['datum_terug']) ?> - 
                            <strong>Verleng Tot:</strong> <?= htmlspecialchars($verzoek['verlenging_datum']) ?> - 
                            <strong>Aangevraagd:</strong> <?= htmlspecialchars($verzoek['verlenging_aanvraag_datum']) ?>
                        </span>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="reservering_id" value="<?= $verzoek['id'] ?>">
                            <input type="hidden" name="datum_terug" value="<?= htmlspecialchars($verzoek['verlenging_datum']) ?>">
                            <button type="submit" name="goedkeuren">Goedkeuren</button>
                            <button type="submit" name="afwijzen">Afwijzen</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Boekenlijst -->
        <div class="card">
            <h3>Boekenlijst</h3>
            <ul>
                <?php foreach ($boeken as $boek): ?>
                    <li>
                        <span>
                            <strong>Title:</strong> <?= htmlspecialchars($boek['titel']) ?> - 
                            <strong>Auteur:</strong> <?= htmlspecialchars($boek['auteur']) ?> - 
                            <strong>Categorie:</strong> <?= htmlspecialchars($boek['categorie']) ?> - 
                            <strong>Beschikbaar:</strong> <?= htmlspecialchars($boek['aantal_beschikbaar']) ?>
                        </span>
                        <div class="button-group">
                            <button name="Wijzigen" onclick="showEditBookOverlay(<?= $boek['id'] ?>)">Wijzigen</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="boek_id" value="<?= $boek['id'] ?>">
                                <button type="submit" name="verwijderen" onclick="return confirm('Boek verwijderen?')">Verwijderen</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Logboek -->
        <div class="card">
            <h3>Logboek</h3>
            <ul>
                <?php foreach ($logs as $log): ?>
                    <?php if (!empty($log['id']) && is_numeric($log['id'])): ?>
                        <li class="log-item" data-log-id="<?= htmlspecialchars($log['id']) ?>">
                            <?= htmlspecialchars($log['tijdstip']) ?> - 
                            <?= htmlspecialchars($log['admin_naam']) ?> - 
                            <?= htmlspecialchars($log['actie']) ?> 
                            (Reservering ID: <?= htmlspecialchars($log['reservering_id'] ?: 'N/A') ?>)
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Log Overlay -->
        <div class="log-overlay" id="log-overlay" style="display: none;">
            <div class="log-overlay-content">
                <div class="left">
                    <h3>Gebruiker</h3>
                    <p id="log-gebruiker-naam"></p>
                    <p id="log-gebruiker-email"></p>
                    <p id="log-gebruiker-geboorte"></p>
                </div>
                <div class="right">
                    <h3>Beheerder</h3>
                    <p id="log-admin-naam"></p>
                    <p id="log-actie"></p>
                    <p id="log-tijdstip"></p>
                </div>
                <button onclick="closeLogOverlay()">Sluiten</button>
            </div>
        </div>

        <!-- Boek Toevoegen Overlay -->
        <div class="overlay" id="overlay">
            <div class="overlay-content">
                <h3>Boek Toevoegen</h3>
                <form method="POST" enctype="multipart/form-data">
                    <label>Titel: <input type="text" name="titel" required></label>
                    <label>Auteur: <input type="text" name="auteur" required></label>
                    <label>Categorie: <input type="text" name="categorie" required></label>
                    <label>Aantal: <input type="number" name="aantal_beschikbaar" min="0" required></label>
                    <label>Beschrijving: <input type="text" name="beschrijving" required></label>
                    <label>Afbeelding: <input type="file" id="afbeelding" name="afbeelding" accept="image/*" required></label>
                    <p id="file-name-display">Geen bestand gekozen</p>
                    <button type="submit" name="toevoegen">Toevoegen</button>
                    <button type="button" onclick="closeAddBookOverlay()">Annuleren</button>
                </form>
            </div>
        </div>

        <!-- Boek Wijzigen Overlay -->
        <div class="overlay" id="edit-boek-overlay">
            <div class="overlay-content">
                <h3>Boek Wijzigen</h3>
                <form method="POST" enctype="multipart/form-data" id="edit-boek-form">
                    <input type="hidden" name="boek_id" id="edit-boek-id">
                    <input type="hidden" name="huidige_afbeelding" id="edit-huidige-afbeelding">
                    <label>Titel: <input type="text" id="edit-titel" name="titel" required></label>
                    <label>Auteur: <input type="text" id="edit-auteur" name="auteur" required></label>
                    <label>Categorie: <input type="text" id="edit-categorie" name="categorie" required></label>
                    <label>Aantal: <input type="number" id="edit-aantal_beschikbaar" name="aantal_beschikbaar" min="0" required></label>
                    <label>Beschrijving: <input type="text" id="edit-beschrijving" name="beschrijving" required></label>
                    <label>Afbeelding: <input type="file" id="edit-afbeelding" name="afbeelding" accept="image/*"></label>
                    <p id="edit-file-name-display">Geen bestand gekozen</p>
                    <button type="submit" name="wijzigen_boek">Opslaan</button>
                    <button type="button" onclick="closeEditBookOverlay()">Annuleren</button>
                </form>
            </div>
</div>

<script>
    window.boekenData = <?php echo json_encode($boeken); ?>;
    window.logsData = <?php echo json_encode($logs); ?>;
</script>
<script src="dashboard.js?v=1"></script>
</body>
</html>