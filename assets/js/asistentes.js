let editId = null;
const modal = document.getElementById('modal');
const passGroup = document.getElementById('passGroup');
const passInput = document.getElementById('password');
const userInput = document.getElementById('usuario');

function openModal() {
    editId = null;
    document.getElementById('modalTitle').textContent = 'Nuevo Asistente';
    document.getElementById('formAsistente').reset();
    
    // En creación, usuario y password son obligatorios
    userInput.required = true;
    passInput.required = true;
    passGroup.style.display = 'block';
    
    modal.style.display = 'flex';
}

function closeModal() {
    modal.style.display = 'none';
}

function editar(id, email, nombre, usuario) {
    editId = id;
    document.getElementById('modalTitle').textContent = 'Editar Asistente';
    
    document.getElementById('email').value = email;
    document.getElementById('nombre').value = nombre;
    document.getElementById('usuario').value = usuario;
    document.getElementById('password').value = ''; // Limpiar password
    
    // En edición, usuario NO se cambia (o se deshabilita), password es opcional
    userInput.disabled = true;
    userInput.style.background = '#eee';
    passInput.required = false;
    passGroup.style.display = 'block'; // Mostrar por si quiere cambiarla
    
    modal.style.display = 'flex';
}

async function guardar() {
    const fd = new FormData(document.getElementById('formAsistente'));
    
    if (editId) {
        fd.append('action', 'editar');
        fd.append('id', editId);
    } else {
        fd.append('action', 'crear');
    }

    try {
        const res = await fetch('../api/gestion_asistentes.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('✅ Guardado correctamente', 'success');
            } else {
                alert('Guardado correctamente');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.error || 'Error desconocido');
        }
    } catch (err) {
        if (typeof showToast === 'function') {
            showToast('❌ ' + err.message, 'error');
        } else {
            alert('Error: ' + err.message);
        }
    }
}

async function eliminar(id) {
    if (!confirm("¿Estás seguro de eliminar este asistente?")) return;

    const fd = new FormData();
    fd.append('action', 'eliminar');
    fd.append('id', id);

    try {
        const res = await fetch('../api/gestion_asistentes.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('🗑️ Eliminado', 'success');
            } else {
                alert('Eliminado');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.error || 'Error al eliminar');
        }
    } catch (err) {
        if (typeof showToast === 'function') {
            showToast('❌ ' + err.message, 'error');
        } else {
            alert('Error: ' + err.message);
        }
    }
}

// Cerrar modal al hacer click fuera
window.onclick = function(event) {
    if (event.target == modal) {
        closeModal();
    }
}