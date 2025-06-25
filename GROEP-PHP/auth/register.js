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

function checkStrength(password) {
    const strengthIndicator = document.getElementById('password-strength');
    const strengthBar = document.createElement('div');

    // Verwijder eventuele vorige elementen
    strengthIndicator.innerHTML = '';
    strengthIndicator.appendChild(strengthBar);

    // Reguliere expressies voor sterktecontroles
    const lengthCheck = /^(?=.{8,})/;  // Minimaal 8 tekens
    const uppercaseCheck = /[A-Z]/;    // Hoofdletters
    const numberCheck = /\d/;          // Cijfers
    const specialCheck = /[!@#$%^&*(),.?":{}|<>]/;  // Speciale tekens

    let strength = 0;

    // Controleer de sterkte van het wachtwoord
    if (lengthCheck.test(password)) strength += 25;
    if (uppercaseCheck.test(password)) strength += 25;
    if (numberCheck.test(password)) strength += 25;
    if (specialCheck.test(password)) strength += 25;

    // Stel de sterktebalk in
    strengthBar.style.width = `${strength}%`;
    strengthBar.style.height = '10px';
    strengthBar.style.borderRadius = '5px';
    strengthBar.style.transition = 'width 0.3s ease';

    // Pas de kleur van de sterktebalk aan op basis van de sterkte
    if (strength < 50) {
        strengthBar.style.backgroundColor = 'red';  // Zwak
    } else if (strength < 75) {
        strengthBar.style.backgroundColor = 'orange';  // Gemiddeld
    } else {
        strengthBar.style.backgroundColor = 'green';  // Sterk
    }

    // Toon sterkte tekst
    const strengthText = document.createElement('span');
    if (strength < 50) {
        strengthText.innerText = 'Zwak wachtwoord';
        strengthText.style.color = 'red';
    } else if (strength < 75) {
        strengthText.innerText = 'Gemiddeld wachtwoord';
        strengthText.style.color = 'orange';
    } else if (strength < 100) {
        strengthText.innerText = 'Sterk wachtwoord';
        strengthText.style.color = 'green';
    }else {
        strengthText.innerText = 'Uitstekend';
        strengthText.style.color = 'green';
    }
    strengthIndicator.appendChild(strengthText);
}
