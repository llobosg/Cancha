<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$deporte = $_GET['deporte'] ?? null;

if ($deporte) {
    $stmt = $pdo->prepare("
    SELECT id_puesto, puesto 
    FROM puestos 
    WHERE deporte = ? 
    ORDER BY 
        CASE puesto
            WHEN 'Jugador' THEN 0
            WHEN 'Primera' THEN 1
            WHEN 'Segunda' THEN 2
            WHEN 'Tercera' THEN 3
            WHEN 'Cuarta' THEN 4
            WHEN 'Quinta' THEN 5
            WHEN 'Sexta' THEN 6
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