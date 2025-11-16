<?php
// Webhook para receber confirmações de entrega das APIs de WhatsApp
require_once 'config.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido');
}

// Ler dados do webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    die('Dados inválidos');
}

try {
    // Log do webhook para debug
    error_log("Webhook WhatsApp recebido: " . $input);
    
    // Processar confirmação de entrega
    if (isset($data['status']) && isset($data['messageId'])) {
        $status = $data['status']; // delivered, read, failed, etc.
        $messageId = $data['messageId'];
        
        // Atualizar status na fila se necessário
        $stmt = $pdo->prepare("
            UPDATE whatsapp_queue 
            SET status = CASE 
                WHEN ? IN ('delivered', 'read') THEN 'enviado'
                WHEN ? = 'failed' THEN 'erro'
                ELSE status 
            END
            WHERE id = ? OR mensagem LIKE ?
        ");
        $stmt->execute([$status, $status, $messageId, "%$messageId%"]);
    }
    
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Erro no webhook WhatsApp: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>