let editId = null;
const modal = document.getElementById('modal');
const passGroup = document.getElementById('passGroup');
const passInput = document.getElementById('password');
const userInput = document.getElementById('usuario');

// === FUNCIÓN TOAST ===
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    // Eliminar después de 3 segundos
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function openModal() {
    editId = null;
    document.getElementById('modalTitle').textContent = 'Nuevo Asistente';
    document.getElementById('formAsistente').reset();
    
    // En creación, usuario y password son obligatorios
    userInput.required = true;
    userInput.disabled = false;
    userInput.style.background = '#fff';
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
    
    // En edición, usuario NO se cambia (se deshabilita), password es opcional
    userInput.disabled = true;
    userInput.required = false; // No requerir usuario al editar
    passInput.required = false;
    passGroup.style.display = 'block'; // Mostrar por si quiere cambiarla
    
    modal.style.display = 'flex';
}

async function guardar() {
    const fd = new FormData(document.getElementById('formAsistente'));
    
    // Validación manual antes de enviar
    const usuarioVal = userInput.value.trim();
    const nombreVal = document.getElementById('nombre').value.trim();
    const emailVal = document.getElementById('email').value.trim();
    const passVal = passInput.value.trim();

    if (!editId) {
        // Modo CREAR: Usuario y Password son obligatorios
        if (!usuarioVal) {
            showToast('❌ El campo Usuario es obligatorio', 'error');
            return;
        }
        if (!passVal) {
            showToast('❌ La contraseña es obligatoria para nuevos usuarios', 'error');
            return;
        }
        fd.append('action', 'crear');
        fd.append('usuario', usuarioVal);
        fd.append('password', passVal);
    } else {
        // Modo EDITAR: Solo Email y Nombre son críticos
        fd.append('action', 'editar');
        fd.append('id', editId);
        if (passVal) {
            fd.append('password', passVal); // Solo enviar si se escribió algo
        }
    }

    if (!nombreVal || !emailVal) {
        showToast('❌ Nombre y Email son obligatorios', 'error');
        return;
    }

    fd.append('email', emailVal);
    fd.append('nombre_completo', nombreVal);

    try {
        const res = await fetch('../api/gestion_asistentes.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();

        if (data.success) {
            showToast('✅ Guardado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.error || 'Error desconocido'), 'error');
        }
    } catch (err) {
        showToast('❌ Error de conexión', 'error');
        console.error(err);
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
            showToast('🗑️ Eliminado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.error || 'Error al eliminar'), 'error');
        }
    } catch (err) {
        showToast('❌ Error de conexión', 'error');
        console.error(err);
    }
}

// Cerrar modal al hacer click fuera
window.onclick = function(event) {
    if (event.target == modal) {
        closeModal();
    }
}