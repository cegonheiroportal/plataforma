<?php
// Script para limpar tokens expirados (executar via cron job)
require_once 'config.php';

try {
    // Remover tokens expirados (mais de 24 horas)
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    
    $removidos = $stmt->rowCount();
    echo "Tokens expirados removidos: $removidos\n";
    
} catch (Exception $e) {
    error_log('Erro na limpeza de tokens: ' . $e->getMessage());
    echo "Erro na limpeza\n";
}
?>