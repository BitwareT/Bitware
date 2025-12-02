document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const popup = document.getElementById('password-requirements-popup');
    
    // Objeto que mapea los IDs del HTML
    const requirements = {
        length: document.getElementById('req-length'),
        lowercase: document.getElementById('req-lowercase'), // AÑADIDO: Elemento para minúsculas
        uppercase: document.getElementById('req-uppercase'),
        number: document.getElementById('req-number'),
        special: document.getElementById('req-special')
    };

    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    passwordInput.addEventListener('input', updatePasswordCheck);

    // Funciones para mostrar/ocultar el pop-up (deben estar en el ámbito global si se llaman desde onfocus/onblur en el HTML)
    window.showPasswordRequirements = function() {
        if (popup) {
            popup.style.display = 'block';
            updatePasswordCheck(); // Ejecutar al mostrar para el estado inicial
        }
    }

    window.hidePasswordRequirements = function() {
        if (popup) {
            popup.style.display = 'none';
        }
    }

    function updatePasswordCheck() {
        const password = passwordInput.value;
        
        // --- 1. LÓGICA DE VERIFICACIÓN DE REQUISITOS (Pop-up) ---
        const checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password), // AÑADIDO: Comprobación de minúsculas
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^a-zA-Z0-9\s]/.test(password)
        };

        // Actualizar el estado de cada ítem en el pop-up
        for (const key in checks) {
            const li = requirements[key];
            // Verificación de seguridad: solo actualizar si el elemento existe (para evitar el error 'Cannot read properties of null')
            if (li) { 
                li.classList.toggle('passed', checks[key]);
                li.classList.toggle('failed', !checks[key]);
            }
        }

        // --- 2. LÓGICA DE FORTALEZA (Barra) ---
        let score = 0;
        let strength = "";
        let color = "";
        let width = "0%";
        
        if (password.length > 0) {
            // Cálculo de puntuación
            score += Math.min(40, password.length * 5);
            
            // Sumar puntos por la presencia de cada tipo de carácter
            if (checks.lowercase) score += 15; // NUEVO
            if (checks.uppercase) score += 15;
            if (checks.number) score += 15;
            if (checks.special) score += 15;
            
            score = Math.min(100, score);
            
            // Asignación de Nivel y Color
            // Requisitos mínimos del servidor (longitud de 8 y carácter especial)
            const allRequiredPassed = checks.length && checks.special; 

            if (!allRequiredPassed) {
                strength = "¡Débil! Faltan requisitos.";
                color = "red";
                width = (checks.length ? 40 : 10) + "%"; 
            } 
            else if (score > 90) {
                strength = "¡Excelente!";
                color = "green";
                width = "100%";
            } else if (score > 75) {
                strength = "Muy Segura";
                color = "#28a745"; 
                width = "85%";
            } else if (score > 60) {
                strength = "Segura";
                color = "orange"; 
                width = "65%";
            } else {
                strength = "Aceptable";
                color = "gold"; 
                width = "50%";
            }
        }

        // Actualizar la barra de fortaleza
        if (strengthBar && strengthText) {
            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = strength;
            strengthText.style.color = (color === "red" || color === "gold" || color === "orange") ? "#333" : "white";
        }
    }
});