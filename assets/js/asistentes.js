let editId = null;

function openModal() {
    editId = null;
    modal.style.display = 'flex';
}

function closeModal() {
    modal.style.display = 'none';
}

function editar(id, email, nombre) {
    editId = id;
    modal.style.display = 'flex';
    document.getElementById('email').value = email;
    document.getElementById('nombre').value = nombre;
}

async function guardar() {
    const fd = new FormData();

    if (editId) {
        fd.append('action', 'editar');
        fd.append('id', editId);
    } else {
        fd.append('action', 'crear');
        fd.append('usuario', usuario.value);
        fd.append('password', password.value);
    }

    fd.append('email', email.value);
    fd.append('nombre_completo', nombre.value);

    const res = await fetch('../api/gestion_asistentes.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();

    if (data.success) {
        showToast('✅ Guardado correctamente', 'success');
        location.reload();
    } else {
        showToast(data.error, 'error');
    }
}

async function eliminar(id) {
    if (!confirm("¿Eliminar asistente?")) return;

    const fd = new FormData();
    fd.append('action', 'eliminar');
    fd.append('id', id);

    const res = await fetch('../api/gestion_asistentes.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();

    if (data.success) {
        showToast('🗑️ Eliminado', 'success');
        location.reload();
    } else {
        showToast(data.error, 'error');
    }
}