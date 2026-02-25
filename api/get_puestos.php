<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$deporte = $_GET['deporte'] ?? null;

if ($deporte) {
    // Excluir "Jugador" solo para deportes individuales como Pádel
    $stmt = $pdo->prepare("
        SELECT id_puesto, puesto 
        FROM puestos 
        WHERE deporte = ? AND puesto != 'Jugador'
        ORDER BY 
            CASE puesto
                WHEN 'Sexta' THEN 6
                WHEN 'Quinta' THEN 5
                WHEN 'Cuarta' THEN 4
                WHEN 'Tercera' THEN 3
                WHEN 'Segunda' THEN 2
                WHEN 'Primera' THEN 1
                ELSE 999
            END
    ");
    $stmt->execute([$deporte]);
} else {
    $stmt = $pdo->prepare("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
    $stmt->execute();
}

$puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($puestos);
?>