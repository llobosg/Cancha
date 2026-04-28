<?php
$stmt = $pdo->prepare("
  SELECT t.nombre as torneo, DATE_FORMAT(t.fecha_fin, '%b %Y') as fecha,
         p1.nombre_pareja as ganadores, p2.nombre_pareja as subcampeones,
         t.num_parejas_max as participantes
  FROM torneos t
  LEFT JOIN parejas_torneo p1 ON t.id_pareja_ganadora = p1.id_pareja
  LEFT JOIN parejas_torneo p2 ON t.id_pareja_subcampeona = p2.id_pareja
  WHERE t.id_deporte = 'padel' AND t.estado = 'cerrado'
  ORDER BY t.fecha_fin DESC LIMIT 5
");
$stmt->execute();
$rankings_padel = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>