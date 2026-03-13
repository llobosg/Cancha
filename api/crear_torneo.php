<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    // Verificar que es administrador de recinto
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_recinto = $_SESSION['id_recinto'];

    // Validar datos
    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['nombre', 'deporte', 'categoria', 'nivel', 'fecha_inicio', 'fecha_fin', 'num_parejas_max'];
    foreach ($required as $field) {
        if (empty($data[$field])) throw new Exception("Campo requerido: {$field}");
    }

    // Validar fechas
    $fecha_inicio = new DateTime($data['fecha_inicio']);
    $fecha_fin = new DateTime($data['fecha_fin']);
    if ($fecha_inicio >= $fecha_fin) {
        throw new Exception('Fecha de inicio debe ser anterior a fecha de fin');
    }

    // Insertar torneo
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO torneos (
            id_recinto, nombre, descripcion, deporte, categoria, nivel,
            fecha_inicio, fecha_fin, num_parejas_max, estado, publico, premios
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'abierto', ?, ?)
    ");
    $stmt->execute([
        $id_recinto,
        $data['nombre'],
        $data['descripcion'] ?? '',
        $data['deporte'],
        $data['categoria'],
        $data['nivel'],
        $fecha_inicio->format('Y-m-d H:i:s'),
        $fecha_fin->format('Y-m-d H:i:s'),
        $data['num_parejas_max'],
        $data['publico'] ?? 0,
        $data['premios'] ?? ''
    ]);
    $id_torneo = $pdo->lastInsertId();
    $pdo->commit();

    // Generar slug único
    $slug = substr(md5($id_torneo . time()), 0, 8);
    $pdo->prepare("UPDATE torneos SET slug = ? WHERE id_torneo = ?")->execute([$slug, $id_torneo]);

    echo json_encode([
        'success' => true,
        'id_torneo' => $id_torneo,
        'slug' => $slug,
        'qr_url' => "https://canchasport.com/torneo.php?slug={$slug}"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>