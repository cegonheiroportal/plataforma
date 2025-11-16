<?php
require_once 'config.php';

try {
    // Verificar se a coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM user_configuracoes LIKE 'notificacoes_whatsapp'");
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Adicionar coluna WhatsApp
        $pdo->exec("
            ALTER TABLE user_configuracoes 
            ADD COLUMN notificacoes_whatsapp TINYINT(1) DEFAULT 1 
            AFTER notificacoes_cotacoes
        ");
        echo "✅ Coluna 'notificacoes_whatsapp' adicionada com sucesso!<br>";
    } else {
        echo "✅ Coluna 'notificacoes_whatsapp' já existe!<br>";
    }
    
    // Atualizar registros existentes para ter WhatsApp ativado por padrão
    $stmt = $pdo->exec("
        UPDATE user_configuracoes 
        SET notificacoes_whatsapp = 1 
        WHERE notificacoes_whatsapp IS NULL
    ");
    echo "✅ Registros existentes atualizados: $stmt registros<br>";
    
    echo "<br>🎉 Atualização concluída com sucesso!";
    echo "<br><a href='configuracoes.php'>← Voltar para Configurações</a>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>