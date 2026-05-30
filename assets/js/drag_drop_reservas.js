function onDragStartReserva(reservaEl) {
    const deporteOrigen = reservaEl.dataset.deporte; // 👈 clave

    document.querySelectorAll('.celda-cancha').forEach(celda => {
        const deporteDestino = celda.dataset.deporte;

        if (deporteDestino !== deporteOrigen) {
            celda.classList.add('drop-bloqueado');
        } else {
            celda.classList.add('drop-activo');
        }
    });
}

function limpiarDropVisual() {
    document.querySelectorAll('.celda-cancha').forEach(celda => {
        celda.classList.remove('drop-bloqueado', 'drop-activo');
    });
}

function onDropReserva(e, celda) {
    e.preventDefault();

    if (celda.classList.contains('drop-bloqueado')) {
        showToast("❌ No puedes mover a otro deporte", "error");
        return;
    }

    // continuar flujo normal
}