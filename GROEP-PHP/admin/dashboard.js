console.log("dashboard.js geladen");

// DOM-elementen
const overlays = {
    addBook: document.getElementById("overlay"),
    user: document.getElementById("user-overlay"),
    members: document.getElementById("leden-overlay"),
    editBook: document.getElementById("edit-boek-overlay"),
    log: document.getElementById("log-overlay")
};
const fileInput = document.getElementById("afbeelding");
const editFileInput = document.getElementById("edit-afbeelding");

// Data van PHP
const boekenData = window.boekenData || [];
const logsData = window.logsData || [];

// Algemene functie om een overlay te tonen
function showOverlay(overlayId) {
    const overlay = overlays[overlayId];
    if (overlay) {
        overlay.style.display = "flex";
        overlay.focus();
    } else {
        console.error(`Overlay met ID ${overlayId} niet gevonden.`);
    }
}

// Algemene functie om een overlay te sluiten
function closeOverlay(overlayId) {
    const overlay = overlays[overlayId];
    if (overlay) {
        overlay.style.display = "none";
    }
}

// Specifieke overlay-handlers
function showAddBookOverlay() {
    showOverlay("addBook");
}

function closeAddBookOverlay() {
    closeOverlay("addBook");
}

function showUserOverlay() {
    showOverlay("user");
}

function closeUserOverlay() {
    closeOverlay("user");
}

function showMembersOverlay() {
    showOverlay("members");
    const searchInput = document.getElementById("search-members");
    if (searchInput) {
        searchInput.value = "";
        filterMembers();
    }
}

function closeMembersOverlay() {
    closeOverlay("members");
    document.querySelectorAll(".member-details").forEach(detail => {
        detail.style.display = "none";
    });
}

function showEditBookOverlay(boekId) {
    const boek = boekenData.find(l => parseInt(l.id, 10) === boekId);
    if (boek) {
        document.getElementById("edit-boek-id").value = boek.id;
        document.getElementById("edit-titel").value = boek.titel;
        document.getElementById("edit-auteur").value = boek.auteur;
        document.getElementById("edit-categorie").value = boek.categorie;
        document.getElementById("edit-aantal_beschikbaar").value = boek.aantal_beschikbaar;
        document.getElementById("edit-beschrijving").value = boek.beschrijving;
        document.getElementById("edit-huidige-afbeelding").value = boek.afbeelding;
        showOverlay("editBook");
    } else {
        console.error(`Boek met ID ${boekId} niet gevonden.`);
    }
}

function closeEditBookOverlay() {
    closeOverlay("editBook");
}

function showLogOverlay(logId) {
    if (!Number.isInteger(logId) || logId <= 0) {
        console.error(`Ongeldige log-ID: ${logId}`);
        return;
    }
    const log = logsData.find(l => parseInt(l.id, 10) === logId);
    if (log) {
        document.getElementById("log-gebruiker-naam").innerText = `Naam: ${log.gebruiker_naam || "N/A"}`;
        document.getElementById("log-gebruiker-email").innerText = `Email: ${log.gebruiker_email || "N/A"}`;
        document.getElementById("log-gebruiker-geboorte").innerText = `Geboortedatum: ${log.gebruiker_geboorte || "N/A"}`;
        document.getElementById("log-admin-naam").innerText = `Naam: ${log.admin_naam}`;
        document.getElementById("log-actie").innerText = `Actie: ${log.actie}`;
        document.getElementById("log-tijdstip").innerText = `Tijdstip: ${log.tijdstip}`;
        showOverlay("log");
    } else {
        console.error(`Log met ID ${logId} niet gevonden.`);
    }
}

function closeLogOverlay() {
    closeOverlay("log");
}

// Leden filteren op zoekopdracht
function filterMembers() {
    const search = document.getElementById("search-members")?.value.toLowerCase() || "";
    const members = document.querySelectorAll(".member-item");
    members.forEach(member => {
        const name = member.querySelector(".member-name")?.textContent.toLowerCase() || "";
        const klas = member.querySelector(".member-klas")?.textContent.toLowerCase() || "";
        member.style.display = name.includes(search) || klas.includes(search) ? "" : "none";
    });
}

// Toggle dropdown voor ledendetails
function toggleDropdown(id) {
    const details = document.getElementById(`details-${id}`);
    if (details) {
        details.classList.toggle('active');
    } else {
        console.error(`Details element with ID details-${id} not found.`);
    }
}

// Bestandsinvoer verwerken
function handleFileInput(input, displayElementId) {
    if (input) {
        input.addEventListener("change", () => {
            const display = document.getElementById(displayElementId);
            if (display) {
                display.textContent = input.files.length > 0 ? input.files[0].name : "Geen bestand gekozen";
            }
        });
    }
}

// Bestandsinvoer listeners initialiseren
if (fileInput) {
    handleFileInput(fileInput, "file-name-display");
}
if (editFileInput) {
    handleFileInput(editFileInput, "edit-file-name-display");
}

// Overlays sluiten met Escape-toets
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        Object.keys(overlays).forEach(overlayId => {
            if (overlays[overlayId].style.display === "flex") {
                closeOverlay(overlayId);
            }
        });
    }
});

// Overlays sluiten bij klikken buiten de overlay
Object.values(overlays).forEach(overlay => {
    overlay?.addEventListener("click", (e) => {
        if (e.target === overlay) {
            Object.keys(overlays).forEach(overlayId => {
                if (overlays[overlayId] === overlay) {
                    closeOverlay(overlayId);
                }
            });
        }
    });
});

// Event listeners toevoegen aan logitems
document.querySelectorAll(".log-item").forEach(item => {
    item.addEventListener("click", () => {
        const logIdAttr = item.getAttribute("data-log-id");
        const logId = logIdAttr ? parseInt(logIdAttr, 10) : null;
        if (logId && !isNaN(logId)) {
            showLogOverlay(logId);
        } else {
            console.error(`Ongeldig data-log-id attribuut: ${logIdAttr}`);
        }
    });
});