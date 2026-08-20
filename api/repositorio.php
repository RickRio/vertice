<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$dataFile = 'data.json';
$adminKey = 'vertice2026';

// Función para leer datos
function readData($file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

// Función para escribir datos
function writeData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Obtener acción
$action = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($action) {
    case 'listar':
        // Listar todos los elementos
        echo json_encode(['success' => true, 'data' => readData($dataFile)]);
        break;

    case 'agregar':
        // Agregar nuevo elemento
        $clave = $_POST['clave'] ?? '';
        if ($clave !== $adminKey) {
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

        $data = readData($dataFile);
        $newId = count($data) > 0 ? max(array_column($data, 'id')) + 1 : 1;
        
        $data[] = [
            'id' => $newId,
            'tipo' => $tipo,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'url' => $url
        ];

        writeData($dataFile, $data);
        echo json_encode(['success' => true, 'message' => 'Elemento agregado correctamente']);
        break;

    case 'eliminar':
        // Eliminar elemento por ID
        $clave = $_POST['clave'] ?? '';
        if ($clave !== $adminKey) {
            echo json_encode(['success' => false, 'error' => 'Clave incorrecta']);
            break;
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            break;
        }

        $data = readData($dataFile);
        $filtered = array_filter($data, function($item) use ($id) {
            return $item['id'] !== $id;
        });

        if (count($filtered) === count($data)) {
            echo json_encode(['success' => false, 'error' => 'Elemento no encontrado']);
            break;
        }

        writeData($dataFile, array_values($filtered));
        echo json_encode(['success' => true, 'message' => 'Elemento eliminado correctamente']);
        break;

    case 'exportar_csv':
        // Exportar datos a CSV
        $data = readData($dataFile);
        if (empty($data)) {
            echo json_encode(['success' => false, 'error' => 'No hay datos para exportar']);
            break;
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Tipo', 'Nombre', 'Fecha', 'URL']);

        foreach ($data as $item) {
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