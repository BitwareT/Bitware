document.addEventListener("DOMContentLoaded", function() {
    
    // --- Función de utilidad para evitar XSS ---
    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function(m) {
            return {'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#039;'}[m];
        });
    }
    
    // --- Función para dibujar el mensaje ---
    function dibujarMensaje(msg, esMensajePropio = false) {
        var chatBox = document.getElementById("chat-box");
        if (!chatBox) return;

        var claseCSS = '';
        var remitenteNombre = '';

        // *** CAMBIO SUGERIDO: Se simplifica la determinación de la clase CSS ***
        claseCSS = msg.es_admin ? 'msg-admin' : 'msg-cliente';
        
        // La lógica para el nombre del remitente se mantiene separada según la vista
        if (esVistaAdminParaChat) {
            // VISTA ADMIN: Muestra el nombre real de ambos, o "Tú" si es propio
            remitenteNombre = msg.es_admin ? (esMensajePropio ? nombreUsuarioActual : msg.nombre_remitente) : msg.nombre_remitente;
        } else {
            // VISTA CLIENTE: Muestra "Soporte Bitware" para el admin, o "Tú" si es propio
            remitenteNombre = msg.es_admin ? 'Soporte Bitware' : (esMensajePropio ? nombreUsuarioActual : msg.nombre_remitente);
        }

        var fecha = new Date(msg.fecha_envio).toLocaleString('es-CL');
        var divMensaje = document.createElement('div');
        divMensaje.className = 'chat-message ' + claseCSS + ' fade-in';
        
        var htmlInterno = `
            <small>
                <strong>${escapeHTML(remitenteNombre)}</strong> - 
                ${fecha}
            </small>
            ${escapeHTML(msg.mensaje).replace(/\n/g, '<br>')}
        `;
        
        divMensaje.innerHTML = htmlInterno;
        chatBox.appendChild(divMensaje);

        if (esMensajePropio) {
            totalMensajesParaChat++;
        }
        
        chatBox.scrollTop = chatBox.scrollHeight;
    }


    // El script buscará estas variables globales definidas en el HTML
    if (typeof idTicketParaChat !== 'undefined') {
        
        var idTicket = idTicketParaChat;
        var totalMensajesActual = totalMensajesParaChat;
        var esVistaAdmin = esVistaAdminParaChat;
        var chatErrorMsg = document.getElementById('chat-error-msg'); // Contenedor de error
        
        // === LÓGICA DE POLLING (OBTENER MENSAJES) ===
        
        async function buscarNuevosMensajes() {
            try {
                // Al hacer polling, se podría añadir la lógica para actualizar los adjuntos también
                const response = await fetch(`check_mensajes.php?id_ticket=${idTicket}&total_mensajes=${totalMensajesActual}`);
                if (!response.ok) return;

                const nuevosMensajes = await response.json();

                if (nuevosMensajes.error) {
                    console.error(nuevosMensajes.error);
                    return;
                }

                if (nuevosMensajes.length > 0) {
                    nuevosMensajes.forEach(msg => dibujarMensaje(msg, false)); // false = no es mensaje propio
                    totalMensajesActual += nuevosMensajes.length;
                    
                    // Nota: Si se han implementado los adjuntos, el polling
                    // debería también actualizar la sección de adjuntos si
                    // es necesario (actualmente, solo muestra adjuntos iniciales).
                }
            } catch (error) {
                console.error("Error en polling:", error);
            }
        }

        // Iniciar el polling
        setTimeout(() => {
            setInterval(buscarNuevosMensajes, 5000); // Revisa cada 5 segundos
        }, 5000);


        // === LÓGICA DE ENVÍO DE MENSAJE (AJAX) ===
        
        const replyForm = document.getElementById('reply-form');
        const replyTextarea = document.getElementById('reply-textarea');
        const replyButton = document.getElementById('reply-button');
        const adjuntosInput = document.getElementById('adjuntos-input');

        if (replyForm && replyTextarea && replyButton && adjuntosInput) {
            
            replyForm.addEventListener('submit', async function(event) {
                event.preventDefault(); // ¡Evita la recarga de la página!
                
                var mensajeTexto = replyTextarea.value.trim();
                var archivos = adjuntosInput.files;

                // Validar que haya mensaje o archivos
                if (mensajeTexto === "" && archivos.length === 0) return;

                // Limpiar errores anteriores
                if (chatErrorMsg) chatErrorMsg.style.display = 'none';
                
                // Deshabilitar el formulario
                replyTextarea.disabled = true;
                replyButton.disabled = true;
                replyButton.textContent = 'Enviando...';

                // 1. Mostrar el mensaje en la pantalla INMEDIATAMENTE (Optimista)
                var mensajeOptimista = {
                    mensaje: mensajeTexto,
                    fecha_envio: new Date().toISOString(),
                    es_admin: esVistaAdmin ? 1 : 0,
                    nombre_remitente: nombreUsuarioActual 
                };
                dibujarMensaje(mensajeOptimista, true); // true = es mensaje propio
                replyTextarea.value = ''; // Limpiar textarea
                adjuntosInput.value = ''; // Limpiar input file
                document.getElementById('adjuntos-preview').innerHTML = ''; // Limpiar preview

                try {
                    // 3. Crear FormData y adjuntar archivos y texto
                    const formData = new FormData();
                    formData.append('mensaje_respuesta', mensajeTexto);
                    
                    for (let i = 0; i < archivos.length; i++) {
                         // Adjuntar cada archivo con el nombre "adjuntos[]"
                        formData.append('adjuntos[]', archivos[i]);
                    }


                    const response = await fetch(replyForm.action, { // Reutiliza la action del form
                        method: 'POST',
                        body: formData
                    });

                    // 4. Verificar el resultado del servidor
                    if (response.ok) {
                        const data = await response.json();
                        if (data.status === 'success') {
                            // Éxito. El polling se encargará de sincronizar.
                        } else {
                            throw new Error(data.error || 'Error desconocido del servidor.');
                        }
                    } else {
                         // El servidor devolvió un error (400, 403, 500)
                        const errorData = await response.json();
                        throw new Error(errorData.error || `Error ${response.status}`);
                    }

                } catch (error) {
                    console.error("Error al enviar el mensaje:", error);
                    // Mostrar error al usuario
                    if (chatErrorMsg) {
                        chatErrorMsg.textContent = `Error al enviar: ${error.message}. Inténtalo de nuevo.`;
                        chatErrorMsg.style.display = 'block';
                    }
                } finally {
                    // 5. Reactivar el formulario
                    replyTextarea.disabled = false;
                    replyButton.disabled = false;
                    replyButton.textContent = 'Enviar Respuesta';
                    replyTextarea.focus();
                }
            });
        }
        
        // === LÓGICA DE PREVISUALIZACIÓN DE IMÁGENES ===
        const adjuntosPreview = document.getElementById('adjuntos-preview');

        if (adjuntosInput && adjuntosPreview) {
            adjuntosInput.addEventListener('change', function() {
                adjuntosPreview.innerHTML = '';
                const files = this.files;
                
                if (files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = escapeHTML(file.name);
                                img.style.maxWidth = '60px';
                                img.style.maxHeight = '60px';
                                img.style.marginRight = '10px';
                                img.style.borderRadius = '4px';
                                adjuntosPreview.appendChild(img);
                            }
                            reader.readAsDataURL(file);
                        } else {
                            // Para archivos que no son imagen
                            const span = document.createElement('span');
                            span.textContent = escapeHTML(file.name);
                            span.style.display = 'block';
                            adjuntosPreview.appendChild(span);
                        }
                    }
                }
            });
        }
    }
});