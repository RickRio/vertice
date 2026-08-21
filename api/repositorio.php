<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================
// 1. LECTURA DE VARIABLES DE ENTORNO (¡AQUÍ VAN!)
// ============================================================
// Estas variables las configuras en Vercel (Settings > Environment Variables)
// o las define Vercel Storage automáticamente al conectar la base de datos.
// ============================================================

// Intentar obtener las variables de entorno (las que Vercel inyecta)
$SUPABASE_URL = getenv('SUPABASE_URL') ?: getenv('STORAGE_URL') ?: '';
$SUPABASE_KEY = getenv('SUPABASE_ANON_KEY') ?: getenv('STORAGE_ANON_KEY') ?: '';

// Si no están definidas, puedes poner valores por defecto (solo para desarrollo local)
if (empty($SUPABASE_URL)) {
    $SUPABASE_URL = 'https://tu-proyecto.supabase.co';
}
if (empty($SUPABASE_KEY)) {
    $SUPABASE_KEY = 'tu_anon_key';
}

// Clave de administrador (también puede venir de variable de entorno)
$ADMIN_KEY = getenv('ADMIN_KEY') ?: 'vertice2026';

// ============================================================
// 2. FUNCIONES PARA CONECTAR CON SUPABASE
// ============================================================

function supabaseRequest($method, $endpoint, $body = null) {
    global $SUPABASE_URL, $SUPABASE_KEY;

    $url = $SUPABASE_URL . '/rest/v1/' . $endpoint;
    $headers = [
        'apikey: ' . $SUPABASE_KEY,
        'Authorization: Bearer ' . $SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

// ============================================================
// 3. MANEJAR ACCIONES
// ============================================================

$action = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($action) {
    case 'listar':
        // Obtener todos los registros ordenados por id descendente
        $result = supabaseRequest('GET', 'repositorio?order=id.desc');
        if ($result['code'] === 200) {
            echo json_encode(['success' => true, 'data' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al obtener datos']);
        }
        break;

    case 'agregar':
        $clave = $_POST['clave'] ?? '';
        if ($clave !== $ADMIN_KEY) {
            echo json_encode(['success' => false, 'error' => 'Clave incorrecta']);
            break;
        }

        $tipo = $_POST['tipo'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $url = $_POST['url'] ?? '';

        if (empty($tipo) || empty($nombre) || empty($fecha) || empty($url)) {
            echo json_encode(['success' => false, 'error' => 'Todos los campos son obligatorios']);
            break;
        }

        $body = [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'url' => $url
        ];

        $result = supabaseRequest('POST', 'repositorio', $body);
        if ($result['code'] === 201) {
            echo json_encode(['success' => true, 'message' => 'Elemento agregado correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al agregar']);
        }
        break;

    case 'eliminar':
        $clave = $_POST['clave'] ?? '';
        if ($clave !== $ADMIN_KEY) {
            echo json_encode(['success' => false, 'error' => 'Clave incorrecta']);
            break;
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            break;
        }

        $result = supabaseRequest('DELETE', 'repositorio?id=eq.' . $id);
        if ($result['code'] === 204) {
            echo json_encode(['success' => true, 'message' => 'Elemento eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
        }
        break;

    case 'exportar_csv':
        // Obtener todos los datos
        $result = supabaseRequest('GET', 'repositorio?order=id.desc');
        if ($result['code'] !== 200 || empty($result['data'])) {
            echo json_encode(['success' => false, 'error' => 'No hay datos para exportar']);
            break;
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Tipo', 'Nombre', 'Fecha', 'URL']);

        foreach ($result['data'] as $item) {
            fputcsv($output, [
                $item['id'],
                $item['tipo'],
                $item['nombre'],
                $item['fecha'],
                $item['url']
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        echo json_encode(['success' => true, 'csv' => $csvContent]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}
?>