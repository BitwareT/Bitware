document.addEventListener('DOMContentLoaded', function() {

    // --- LÓGICA PARA LOS FILTROS DE LA VISTA DE CATÁLOGO ---
    if (document.getElementById('priceRange')) {
        const priceRange = document.getElementById('priceRange');
        const priceValue = document.getElementById('priceValue');
        const sortSelect = document.getElementById('sort');

        function applyFilter(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.set(key, value);
            window.location.href = url.toString();
        }

        priceRange.addEventListener('input', () => {
            priceValue.textContent = '$' + parseInt(priceRange.value).toLocaleString('es-CL');
        });
        
        priceRange.addEventListener('change', () => {
            applyFilter('max_precio', priceRange.value);
        });
        
        sortSelect.addEventListener('change', () => {
            applyFilter('orden', sortSelect.value);
        });
    }

    // --- LÓGICA PARA LA INTERACTIVIDAD EN LA PÁGINA DE DETALLE ---
    window.changeImage = function(newSrc) {
        document.getElementById('mainProductImage').src = newSrc;
        
        let thumbnails = document.querySelectorAll('.thumbnail-images img');
        thumbnails.forEach(thumb => {
            thumb.classList.remove('active');
            if (thumb.src === newSrc) {
                thumb.classList.add('active');
            }
        });
    }

    const qtyPlus = document.getElementById('qty-plus');
    const qtyMinus = document.getElementById('qty-minus');
    const quantityInput = document.getElementById('quantity');
    const stockDisponible = parseInt(document.body.dataset.stock, 10) || 99;

    if (qtyPlus && qtyMinus && quantityInput) {
        qtyPlus.addEventListener('click', () => {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue < stockDisponible) {
                quantityInput.value = currentValue + 1;
            }
        });

        qtyMinus.addEventListener('click', () => {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
    }
});