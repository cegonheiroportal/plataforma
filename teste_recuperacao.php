<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>🧪 Teste de Recuperação de Senha</h2>";

// Teste 1: Verificar arquivos
echo "<h3>📁 Verificação de Arquivos:</h3>";
echo "config.php: " . (file_exists('config.php') ? '✅ Existe' : '❌ Não existe') . "<br>";
echo "notificacao_email.php: " . (file_exists('notificacao_email.php') ? '✅ Existe' : '❌ Não existe') . "<br>";

// Teste 2: Verificar classe
if (file_exists('notificacao_email.php')) {
    require_once 'notificacao_email.php';
    echo "Classe EmailNotificacao: " . (class_exists('EmailNotificacao') ? '✅ Carregada' : '❌ Não encontrada') . "<br>";
}

// Teste 3: Teste direto de envio
if (isset($_GET['testar']) && $_GET['testar'] == '1') {
    echo "<h3>📧 Teste de Envio:</h3>";
    
    try {
        if (!class_exists('EmailNotificacao')) {
            throw new Exception('Classe EmailNotificacao não encontrada');
        }
        
        $emailNotificacao = new EmailNotificacao($pdo);
        
        $usuario_teste = [
            'nome' => 'AUTO TRANSPORTE',
            'email' => 'autotransportes.at@hotmail.com'
        ];
        
        $token_teste = 'teste_' . bin2hex(random_bytes(16));
        $link_teste = "https://portalcegonheiro.com.br/app/redefinir_senha.php?token=" . $token_teste;
        
        $assunto = "🧪 Teste Recuperação - Portal Cegonheiro";
        
        $corpo = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #dc3545; color: white; padding: 20px; border-radius: 10px; text-align: center;'>
                <h1>🧪 Teste de Recuperação</h1>
                <p>Portal Cegonheiro</p>
            </div>
            
            <div style='padding: 20px; margin: 20px 0; border: 2px solid #dc3545; border-radius: 10px;'>
                <h3>Teste de Email de Recuperação</h3>
                <p>Este é um teste do sistema de recuperação de senha.</p>
                
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='{$link_teste}' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                        🔐 Link de Teste
                    </a>
                </div>
                
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Token:</strong> {$token_teste}</p>
            </div>
        </body>
        </html>";
        
        $resultado = $emailNotificacao->enviarEmail(
            $usuario_teste['email'], 
            $usuario_teste['nome'], 
            $assunto, 
            $corpo
        );
        
        if ($resultado) {
            echo "✅ Email de teste enviado com sucesso!<br>";
            echo "📧 Destinatário: " . $usuario_teste['email'] . "<br>";
            echo "🕐 Horário: " . date('d/m/Y H:i:s') . "<br>";
        } else {
            echo "❌ Erro ao enviar email de teste<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "<br>";
    }
}

// Teste 4: Verificar configurações
echo "<h3>⚙️ Configurações:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "mail() function: " . (function_exists('mail') ? '✅ Disponível' : '❌ Não disponível') . "<br>";

if (isset($pdo)) {
    echo "PDO: ✅ Conectado<br>";
    
    // Verificar usuários
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE email IS NOT NULL");
        $total = $stmt->fetch()['total'];
        echo "Usuários com email: {$total}<br>";
    } catch (Exception $e) {
        echo "Erro ao contar usuários: " . $e->getMessage() . "<br>";
    }
} else {
    echo "PDO: ❌ Não conectado<br>";
}

echo "<hr>";
echo "<h3>🧪 Ações de Teste:</h3>";
echo "<a href='?testar=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📧 Testar Envio de Email</a><br><br>";
echo "<a href='esqueceu_senha.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔐 Ir para Recuperação</a><br><br>";
echo "<a href='teste_email_config.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>⚙️ Testar Configuração Email</a>";
?>