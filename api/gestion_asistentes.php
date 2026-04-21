case 'crear_asistente':
    if (!esAdmin()) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $usuario = $_POST['usuario'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nombre = $_POST['nombre_completo'];
    $telefono = $_POST['telefono'] ?? '';
    $id_recinto = $_SESSION['id_recinto'];

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_recintos 
            (id_recinto, usuario, contraseña, email, nombre_completo, telefono, rol) 
            VALUES (?, ?, ?, ?, ?, ?, 'asistente')
        ");
        
        $stmt->execute([$id_recinto, $usuario, $password, $email, $nombre, $telefono]);
        
        echo json_encode(['success' => true, 'message' => 'Asistente registrado correctamente']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    break;