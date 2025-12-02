document.addEventListener('DOMContentLoaded', () => {
    // --- 1. Obtener elementos del DOM ---
    const chatbotToggle = document.getElementById('chatbot-toggle');
    const chatContainer = document.getElementById('chat-container');
    const chatCloseBtn = document.getElementById('chat-close-btn');
    const chatMessages = document.getElementById('chat-messages');
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    const quickRepliesContainer = document.getElementById('quick-replies-permanent');

    if (!chatbotToggle || !chatContainer || !chatCloseBtn || !chatMessages || !userInput || !sendButton || !quickRepliesContainer) {
        console.error("Error: No se encontraron todos los elementos del chatbot. Revisa tu HTML.");
        return; 
    }
    
    chatContainer.style.display = 'none';

    // --- 2. Lógica para mostrar/ocultar el chat (Botón Flotante 💬) ---
    chatbotToggle.addEventListener('click', () => {
        const isHidden = chatContainer.style.display === 'none';
        
        if (isHidden) {
            chatContainer.style.display = 'flex';
            chatbotToggle.innerHTML = '&times;'; 
            chatbotToggle.style.fontSize = '2.5rem'; 

            if (chatMessages.children.length === 0) {
                showTypingIndicator();
                getBotResponse("hola"); 
                showQuickReplies(userPermisos); 
            }
        } else {
            chatContainer.style.display = 'none';
            chatbotToggle.innerHTML = '💬';
            chatbotToggle.style.fontSize = '2rem'; 
        }
    });

    // --- 3. Lógica para el botón 'X' de CERRAR DENTRO del chat ---
    chatCloseBtn.addEventListener('click', () => {
        chatContainer.style.display = 'none';
        chatbotToggle.innerHTML = '💬';
        chatbotToggle.style.fontSize = '2rem';
    });

    // --- 4. Lógica para enviar mensaje ---
    const sendMessage = () => {
        const message = userInput.value.trim();
        if (message === '') return;
        displayMessage(message, 'user');
        userInput.value = '';
        showTypingIndicator();
        getBotResponse(message);
    };

    sendButton.addEventListener('click', sendMessage);
    userInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    // --- 5. Función para mostrar mensajes de TEXTO en el chat ---
    const displayMessage = (message, sender) => {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', sender);
        
        let formattedMessage = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formattedMessage = formattedMessage.replace(/\n/g, '<br>');

        messageElement.innerHTML = formattedMessage;
        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    };

    // ==========================================================
    // --- 6. NUEVA FUNCIÓN: Mostrar tarjetas de PRODUCTOS ---
    // ==========================================================
    const displayProducts = (products) => {
        const productList = document.createElement('div');
        productList.classList.add('chat-product-list');

        products.forEach(product => {
            const productCard = document.createElement('a');
            productCard.classList.add('chat-product-card');
            productCard.href = `catalogo.php?id=${product.id}`; // Enlace al producto
            productCard.target = "_blank"; // Abrir en nueva pestaña

            // Formatear precio
            const precioFormateado = new Intl.NumberFormat('es-CL', { 
                style: 'currency', 
                currency: 'CLP' 
            }).format(product.precio);

            productCard.innerHTML = `
                <img src="uploads/${product.imagen_principal}" alt="${product.nombre}" class="chat-product-image">
                <div class="chat-product-info">
                    <strong>${product.nombre}</strong>
                    <span>${precioFormateado}</span>
                </div>
            `;
            productList.appendChild(productCard);
        });

        chatMessages.appendChild(productList);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    };

    // --- 7. Indicador de "Escribiendo..." ---
    const showTypingIndicator = () => {
        const typingElement = document.createElement('div');
        typingElement.classList.add('chat-message', 'bot', 'typing');
        typingElement.innerHTML = '<span></span><span></span><span></span>';
        chatMessages.appendChild(typingElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    };

    const removeTypingIndicator = () => {
        const typingElement = chatMessages.querySelector('.typing');
        if (typingElement) {
            chatMessages.removeChild(typingElement);
        }
    };

    // --- 8. Función para obtener la respuesta del Bot (El Fetch - CORREGIDO) ---
    const getBotResponse = (message) => {
        const data = {
            message: message,
            // Ya no es crítico enviar userId aquí porque PHP lo inyecta, pero no hace daño dejarlo
            userId: userId, 
            permisos: userPermisos,
            nombre_usuario: userName
        };

        // VOLVEMOS A USAR EL ARCHIVO INTERMEDIARIO
        fetch('chatbot_logic.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            removeTypingIndicator();
            
            // 1. Muestra la respuesta de texto
            displayMessage(data.respuesta, 'bot'); 
            
            // 2. ¡NUEVO! Muestra los productos si existen
            if (data.productos && data.productos.length > 0) {
                displayProducts(data.productos);
            }
        })
        .catch(error => {
            removeTypingIndicator();
            displayMessage('Lo siento, el cerebro de la IA parece estar desconectado.', 'bot');
            console.error('Error en chatbot fetch (chatbot_logic.php):', error);
        });
    };

    // --- 9. Lógica de Respuestas Rápidas (Corregida) ---
    const showQuickReplies = (permisos) => {
        quickRepliesContainer.innerHTML = '';
        let replies = [];

        if (permisos === 'V') { // VENDEDOR
            replies = ['Mis ventas', 'Mis productos', 'Predice stock de [mi producto]', 'Ayuda'];
        } else if (permisos === 'A') { // ADMIN
            replies = ['Reporte de ventas hoy', 'Total usuarios', 'Buscar cliente', 'Ayuda'];
        } else { // CLIENTE ('U' o invitado)
            replies = ['Buscar producto', '¿Estado de mi pedido?', 'Métodos de pago', 'Ayuda'];
        }

        replies.forEach(replyText => {
            const button = document.createElement('button');
            button.textContent = replyText;
            button.className = 'quick-reply-btn'; 
            button.onclick = () => {
                // Si el texto es genérico, solo lo pone en el input
                if (replyText.includes('[mi producto]')) {
                    userInput.value = 'Predice stock de ';
                    userInput.focus();
                } else if (replyText === 'Buscar cliente') {
                     userInput.value = 'Busca al cliente ';
                     userInput.focus();
                } else if (replyText === 'Buscar producto') {
                     userInput.value = 'Busca ';
                     userInput.focus();
                } else {
                    // Si es una acción directa, la envía
                    userInput.value = replyText;
                    sendMessage();
                }
            };
            quickRepliesContainer.appendChild(button);
        });
    };
});