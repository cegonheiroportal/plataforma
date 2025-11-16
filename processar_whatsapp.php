<?php
// Arquivo para processar fila via cron job
require_once 'config.php';
require_once 'notificacao_whatsapp.php';

// Verificar se está sendo executado via CLI ou cron
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    die('Acesso negado');
}

// Chave de segurança para execução via URL
$cron_key = $_GET['cron_key'] ?? '';
if ($cron_key !== 'SUA_CHAVE_SECRETA_AQUI') {
    die('Chave inválida');
}

try {
    $whatsapp = new WhatsAppNotificacao($pdo);
    $resultado = $whatsapp->processarFilaWhatsApp(20); // Processar até 20 mensagens
    
    echo "Processamento concluído:\n";
    echo "- Mensagens processadas: {$resultado['processadas']}\n";
    echo "- Total na fila: {$resultado['total']}\n";
    echo "- Status: " . ($resultado['sucesso'] ? 'Sucesso' : 'Erro') . "\n";
    
    if (!$resultado['sucesso']) {
        echo "- Erro: {$resultado['erro']}\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    error_log("Erro no processador WhatsApp: " . $e->getMessage());
}
?>