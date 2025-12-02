<?php
// includes/footer.php
?>

    <button class="chatbot-btn" id="chatbot-toggle">💬</button>

    <div class="chat-container" id="chat-container" style="display:none;">
        <div class="chat-header">
            <span>Asistente BitWare 🤖</span>
            <button class="chat-close-btn" id="chat-close-btn" title="Cerrar">&times;</button>
        </div>
        <div class="chat-messages" id="chat-messages"></div>
        <div class="quick-replies-permanent" id="quick-replies-permanent"></div>
        <div class="chat-input">
            <input type="text" id="user-input" placeholder="Escribe tu mensaje...">
            <button id="send-button">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="white" width="20px"><path d="M16.1 260.2c-22.6 12.9-20.5 47.3 3.6 57.3L160 376V479.3c0 18.1 14.6 32.7 32.7 32.7c9.7 0 18.9-4.3 25.1-11.8l62-74.3 123.9 51.6c18.9 7.9 40.8-4.5 43.9-24.7l64-416c1.9-12.1-3.4-24.3-13.5-31.2s-23.3-7.5-34-1.4l-448 256zm52.1 25.5L409.7 90.6 190.1 336l1.2 1L68.2 285.7zM403.3 425.4L236.7 355.9 450.8 116.6 403.3 425.4z"/></svg>
            </button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    
    <script src="js/chatbot.js?v=1.2"></script> <script>
        const userId = <?php echo json_encode($_SESSION['id'] ?? null); ?>;
        const userName = <?php echo json_encode($_SESSION['nombre'] ?? 'Invitado'); ?>;
        const userPermisos = <?php echo json_encode($_SESSION['permisos'] ?? null); ?>;
    </script>
    
</body>
</html>