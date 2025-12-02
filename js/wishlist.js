document.addEventListener('DOMContentLoaded', function() {
    // Selecciona todos los botones de wishlist que encuentre en la página
    const wishlistButtons = document.querySelectorAll('.wishlist-btn');

    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Evita que la página se mueva al hacer clic

            const productId = this.dataset.productId;
            const icon = this.querySelector('i');

            // Muestra un estado de "cargando" para feedback visual
            icon.classList.remove('fas', 'far');
            icon.classList.add('fa-spinner', 'fa-spin');

            fetch('wishlist_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_producto: productId })
            })
            .then(response => response.json())
            .then(data => {
                // Quita el spinner
                icon.classList.remove('fa-spinner', 'fa-spin');

                if (data.status === 'success') {
                    if (data.action === 'added') {
                        // Cambia el ícono a "lleno" (favorito)
                        icon.classList.add('fas', 'fa-heart');
                        icon.classList.remove('far');
                        this.classList.add('active');
                    } else {
                        // Cambia el ícono a "vacío" (no favorito)
                        icon.classList.add('far', 'fa-heart');
                        icon.classList.remove('fas');
                        this.classList.remove('active');
                    }
                } else {
                    // Si el usuario no ha iniciado sesión, lo redirige al login
                    if (data.message.includes('iniciar sesión')) {
                        window.location.href = 'login.php';
                    } else {
                        // Si hubo otro error, lo muestra y restaura el ícono original
                        alert(data.message);
                        if(this.classList.contains('active')){
                           icon.classList.add('fas', 'fa-heart');
                        } else {
                           icon.classList.add('far', 'fa-heart');
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hubo un problema de conexión.');
                // Restaura el ícono en caso de error de red
                icon.classList.remove('fa-spinner', 'fa-spin');
                if(this.classList.contains('active')){
                   icon.classList.add('fas', 'fa-heart');
                } else {
                   icon.classList.add('far', 'fa-heart');
                }
            });
        });
    });
});