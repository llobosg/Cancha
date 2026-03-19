<?php
require_once __DIR__ . '/../includes/config.php';

try {
    echo "Iniciando vinculación automática de socios desde invitaciones...\n";

    // Paso 1: Buscar socios individuales cuyo email coincide con un jugador temporal en una pareja pendiente
    $stmt = $pdo->prepare("
        SELECT 
            s.id_socio,
            s.email,
            pt.id_pareja,
            pt.codigo_pareja
        FROM socios s
        JOIN parejas_torneo pt ON (
            pt.id_jugador_temp_2 IS NOT NULL
            AND pt.estado = 'esperando_pareja'
        )
        JOIN jugadores_temporales jt ON (
            jt.id_jugador = pt.id_jugador_temp_2
            AND jt.email COLLATE utf8mb4_unicode_ci = s.email COLLATE utf8mb4_unicode_ci
        )
        WHERE s.id_club IS NULL
    ");
    $stmt->execute();
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($matches)) {
        echo "✅ No se encontraron socios pendientes de vincular.\n";
        exit;
    }

    echo "Encontrados " . count($matches) . " socios para vincular:\n";

    foreach ($matches as $match) {
        echo "- Socio {$match['id_socio']} ({$match['email']}) → Pareja {$match['id_pareja']} ({$match['codigo_pareja']})\n";

        // Actualizar la pareja
        $pdo->prepare("
            UPDATE parejas_torneo 
            SET 
                id_socio_2 = ?,
                id_jugador_temp_2 = NULL,
                estado = 'completa'
            WHERE id_pareja = ?
        ")->execute([$match['id_socio'], $match['id_pareja']]);
    }

    echo "✅ Vinculación completada.\n";

} catch (Exception $e) {
    error_log("Error en vincular_socios_desde_invitaciones.php: " . $e->getMessage());
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>