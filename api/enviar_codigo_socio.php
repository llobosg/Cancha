
        'success' => true,
        'id_socio' => intval($id_socio),
        'verification_code' => $verification_code, // Opcional: para debug
        'message' => 'Registro exitoso'
    ];
    http_response_code(200);

} catch (Exception $e) {
    // Manejo de Errores
    error_log("💥 ERROR: " . $e->getMessage());
    $final_response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

// 9. SALIDA FINAL DIRECTA (SIN BUFFERS)
// Imprimir JSON y morir inmediatamente.
print(json_encode($final_response));
exit;