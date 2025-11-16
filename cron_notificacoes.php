<?php
// cron_notificacoes.php - Script para executar via cron job
require_once 'config.php';
require_once 'notificacoes.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $notificacaoManager = new NotificacaoManager($pdo);
    
    // Verificar leads urgentes
    $leads_urgentes = $notificacaoManager->notificarLeadsUrgentes();
    
    echo "Script executado com sucesso. {$leads_urgentes} notificações de leads urgentes criadas.\n";
    
} catch (Exception $e) {
    error_log("Erro no cron de notificações: " . $e->getMessage());
    echo "Erro: " . $e->getMessage() . "\n";
}
?>