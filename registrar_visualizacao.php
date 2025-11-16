<?php
// registrar_visualizacao.php
require_once 'config.php';

header('Content-Type: application/json');

if (!verificarLogin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$user_id = obterIdUsuario();
$nivel_acesso = $_SESSION['nivel_acesso'] ?? '';
$tipo_cliente = $_SESSION['tipo_cliente'] ?? 'pf';

// Debug
error_log("Debug - User ID: $user_id, Nível: $nivel_acesso, Tipo: $tipo_cliente");

// Verificar se é transportadora
if (!($nivel_acesso === 'cliente' && $tipo_cliente === 'pj')) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas transportadoras podem registrar visualizações']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$lead_id = (int)($input['lead_id'] ?? 0);

error_log("Debug - Tentando registrar visualização do lead: $lead_id pelo user: $user_id");

if ($lead_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do lead inválido']);
    exit;
}

try {
    // Verificar se o lead existe
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Lead não encontrado']);
        exit;
    }
    
    // Verificar quantas visualizações já existem
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as total FROM lead_views WHERE lead_id = ?");
    $stmt->execute([$lead_id]);
    $total_views = $stmt->fetch()['total'];
    
    error_log("Debug - Total de visualizações existentes: $total_views");
    
    // Verificar se o usuário já visualizou
    $stmt = $pdo->prepare("SELECT id FROM lead_views WHERE lead_id = ? AND user_id = ?");
    $stmt->execute([$lead_id, $user_id]);
    $ja_visualizou = $stmt->fetch();
    
    error_log("Debug - Usuário já visualizou: " . ($ja_visualizou ? 'SIM' : 'NÃO'));
    
    if (!$ja_visualizou && $total_views >= 7) {
        http_response_code(403);
        echo json_encode(['error' => 'Limite de visualizações atingido']);
        exit;
    }
    
    // Registrar visualização (INSERT IGNORE para evitar duplicatas)
    $stmt = $pdo->prepare("INSERT IGNORE INTO lead_views (lead_id, user_id, view_timestamp) VALUES (?, ?, NOW())");
    $result = $stmt->execute([$lead_id, $user_id]);
    
    error_log("Debug - Inserção realizada: " . ($result ? 'SUCESSO' : 'FALHA'));
    error_log("Debug - Linhas afetadas: " . $stmt->rowCount());
    
    // Verificar se a inserção foi bem-sucedida
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Visualização registrada com sucesso']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Visualização já existia']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao registrar visualização: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>