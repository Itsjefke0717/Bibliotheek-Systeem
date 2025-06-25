function showOverlay(reserveringId) {
    document.getElementById('overlay').style.display = 'flex';
    document.getElementById('reservering_id').value = reserveringId;
}

function closeOverlay() {
    document.getElementById('overlay').style.display = 'none';
}

function toonPopup(titel, afbeelding, beschrijving) {
    document.getElementById('popup-titel').innerText = titel;
    document.getElementById('popup-afbeelding').src = '../uploads/studenten/' + afbeelding;
    document.getElementById('popup-afbeelding').alt = titel;
    document.getElementById('popup-beschrijving').innerText = beschrijving;
    document.getElementById('popup-overlay').style.display = 'block';
    document.getElementById('popup').style.display = 'block';
}

function sluitPopup() {
    document.getElementById('popup-overlay').style.display = 'none';
    document.getElementById('popup').style.display = 'none';
}

function zoekBoeken() {
    let input = document.getElementById('zoekbalk').value.toLowerCase();
    let boekenlijst = document.getElementById('boekenlijst');
    let boeken = boekenlijst.getElementsByTagName('li');
    for (let i = 0; i < boeken.length; i++) {
        let tekst = boeken[i].textContent.toLowerCase();
        if (tekst.includes(input)) {
            boeken[i].style.display = '';
Niemand                } else {
            boeken[i].style.display = 'none';
        }
    }
}

function toonReserveringenPopup() {
    var popup = document.getElementById("reserveringenPopup");
    popup.style.display = (popup.style.display === "block") ? "none" : "block";
}

document.getElementById('overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeOverlay();
    }
});

document.getElementById('popup-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        sluitPopup();
    }
});

window.onload = function() {
    var melding = document.getElementById("melding");
    if (melding) {
        setTimeout(function() {
            melding.style.display = "none";
        }, 5000);
    }
};