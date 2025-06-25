document.addEventListener('DOMContentLoaded', function() {
    const notification = document.querySelector('.php-result');
    if (notification) {
        setTimeout(() => {
            notification.classList.add('hide');
        }, 5000); 
    }
});


const colors = [
    "#3a2c1b", // accent-dark
    "#d4a373", // secondary-bg
    "#f5e8d3", // primary-bg
    "#6b4e31"  // border-brown
];

let step = 0;
let colorIndices = [0, 1, 2, 3];

function updateGradient() {
    const c0_0 = hexToRgb(colors[colorIndices[0]]);
    const c0_1 = hexToRgb(colors[colorIndices[1]]);
    const c1_0 = hexToRgb(colors[colorIndices[2]]);
    const c1_1 = hexToRgb(colors[colorIndices[3]]);

    const istep = 1 - step;
    const r1 = Math.round(istep * c0_0.r + step * c0_1.r);
    const g1 = Math.round(istep * c0_0.g + step * c0_1.g);
    const b1 = Math.round(istep * c0_0.b + step * c0_1.b);
    const color1 = `rgb(${r1},${g1},${b1})`;

    const r2 = Math.round(istep * c1_0.r + step * c1_1.r);
    const g2 = Math.round(istep * c1_0.g + step * c1_1.g);
    const b2 = Math.round(istep * c1_0.b + step * c1_1.b);
    const color2 = `rgb(${r2},${g2},${b2})`;

    document.body.style.background = `linear-gradient(135deg, ${color1}, ${color2})`;

    step += 0.005;
    if (step >= 1) {
        step = 0;
        colorIndices[0] = colorIndices[1];
        colorIndices[2] = colorIndices[3];

        // Kies een nieuwe kleur voor overgang
        colorIndices[1] = (colorIndices[1] + 1) % colors.length;
        colorIndices[3] = (colorIndices[3] + 2) % colors.length;
    }
}

function hexToRgb(hex) {
    hex = hex.replace("#", "");
    const bigint = parseInt(hex, 16);
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255
    };
}

setInterval(updateGradient, 50);

document.getElementById("login-form").addEventListener("submit", function(e) {
    const email = document.getElementById("email");
    const wachtwoord = document.getElementById("wachtwoord");
    let valid = true;

    // Reset
    email.classList.remove("invalid");
    wachtwoord.classList.remove("invalid");
    document.getElementById("email-error").style.display = "none";
    document.getElementById("wachtwoord-error").style.display = "none";

    // Validatie
    if (!email.value.includes("@")) {
        email.classList.add("invalid");
        document.getElementById("email-error").textContent = "Voer een geldig e-mailadres in.";
        document.getElementById("email-error").style.display = "block";
        valid = false;
    }
    if (wachtwoord.value.length < 4) {
        wachtwoord.classList.add("invalid");
        document.getElementById("wachtwoord-error").textContent = "Wachtwoord is te kort.";
        document.getElementById("wachtwoord-error").style.display = "block";
        valid = false;
    }

    // CAPTCHA check (optioneel uitbreiden server-side)
    if (grecaptcha.getResponse() === "") {
        alert("Bevestig de CAPTCHA alstublieft.");
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        return;
    }

    // Laadeffect
    const submitBtn = document.getElementById("submit-btn");
    submitBtn.classList.add("loading");
});
