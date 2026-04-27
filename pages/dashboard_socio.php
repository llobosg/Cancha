<?php
session_start();

// === CONFIG GENERAL ===
define('BASE_API', '../api/');
define('BASE_URL', '/pages/');

if (!isset($_SESSION['id_socio'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$id_socio = (int) $_SESSION['id_socio'];
$es_responsable = $_SESSION['es_responsable'] ?? false;

// === CONFIG GENERAL ===
define('BASE_API', '../api/');
define('BASE_URL', '/pages/');

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Socio</title>

<style>
body { font-family: Arial; margin:0; background:#f5f6fa; }
button { cursor:pointer; }
.hidden { display:none; }

/* Modal base */
.modal-overlay {
    position:fixed; top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.7);
    display:flex; align-items:center; justify-content:center;
    z-index:1000;
}

.modal {
    background:white;
    padding:1.5rem;
    border-radius:12px;
    max-width:500px;
    width:90%;
}

/* Toast */
#toast-container {
    position:fixed;
    bottom:20px;
    right:20px;
}
.toast {
    background:#333;
    color:white;
    padding:10px;
    border-radius:6px;
    margin-top:10px;
}
</style>
</head>

<body>

<h2>Dashboard</h2>

<!-- CONTENIDO -->
<div id="tablaContenido"></div>

<!-- CONTENEDOR MODAL -->
<div id="modal-root"></div>

<!-- TOAST -->
<div id="toast-container"></div>

<script>
/* =====================================================
   CORE APP (PRO++)
===================================================== */

const App = {

    API: {
        async request(url, options = {}) {
            try {
                const res = await fetch(url, options);
                if (!res.ok) throw new Error("Error red");
                return await res.json();
            } catch (e) {
                UI.toast("❌ Error de conexión");
                console.error(e);
                return null;
            }
        },

        post(url, data) {
            return this.request(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
        }
    },

    UI: {
        toast(msg) {
            const c = document.getElementById('toast-container');
            const el = document.createElement('div');
            el.className = 'toast';
            el.textContent = msg;
            c.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        },

        modal(contentNode) {
            const root = document.getElementById('modal-root');
            root.innerHTML = '';

            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';

            const modal = document.createElement('div');
            modal.className = 'modal';

            modal.appendChild(contentNode);
            overlay.appendChild(modal);

            overlay.onclick = e => {
                if (e.target === overlay) root.innerHTML = '';
            };

            root.appendChild(overlay);
        }
    },

    Eventos: {

        async anotarse(id) {
            const res = await App.API.post(App.BASE_API + 'gestion_eventos.php', {
                action:'anotarse',
                id_actividad:id
            });

            if (!res) return;

            App.UI.toast(res.message || 'OK');
            App.Tabla.cargar('inscritos');
        },

        async bajarse(id) {
            if (!confirm("¿Seguro?")) return;

            const res = await App.API.post(App.BASE_API + 'gestion_eventos.php', {
                action:'bajarse',
                id_reserva:id
            });

            if (!res) return;

            App.UI.toast(res.message);
            App.Tabla.cargar('inscritos');
        }
    },

    Clubes: {
        async listar() {
            const clubes = await App.API.request(App.BASE_API + 'listar_clubes_publicos.php');
            if (!clubes) return;

            const container = document.createElement('div');

            const title = document.createElement('h3');
            title.textContent = "Selecciona un club";

            const list = document.createElement('div');

            clubes.forEach(c => {
                const item = document.createElement('div');
                item.textContent = c.nombre;
                item.style.padding = '8px';
                item.onclick = () => App.Clubes.unirse(c.id_club);
                list.appendChild(item);
            });

            container.append(title, list);
            App.UI.modal(container);
        },

        async unirse(id) {
            const res = await App.API.post(App.BASE_API + 'unirse_a_club.php', {
                id_club:id
            });

            if (!res) return;

            App.UI.toast(res.message);
            location.reload();
        }
    },

    Tabla: {
        async cargar(filtro = 'inscritos') {

            const data = await App.API.request(`${App.BASE_API}get_tabla_datos.php?filtro=${filtro}`);
            const tbody = document.getElementById('tablaContenido');

            if (!data || data.length === 0) {
                tbody.textContent = "Sin datos";
                return;
            }

            tbody.innerHTML = '';

            data.forEach(row => {
                const div = document.createElement('div');

                div.textContent = `${row.nombre || ''} - $${row.monto || 0}`;

                const btn = document.createElement('button');
                btn.textContent = "Bajar";
                btn.onclick = () => App.Eventos.bajarse(row.id_evento);

                div.appendChild(btn);

                tbody.appendChild(div);
            });
        }
    }

};

/* =====================================================
   INIT
===================================================== */

document.addEventListener('DOMContentLoaded', () => {
    App.BASE_API = "<?= BASE_API ?>";
    App.Tabla.cargar();
});
</script>

</body>
</html>