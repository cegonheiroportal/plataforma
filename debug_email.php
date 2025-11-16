<?php
// Debug básico - sem dependências
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Debug Email - Portal Cegonheiro</h2>";

// Teste 1: Verificar arquivos
echo "<h3>📁 Verificação de Arquivos:</h3>";
echo "config.php: " . (file_exists('config.php') ? '✅ Existe' : '❌ Não existe') . "<br>";
echo "notificacao_email.php: " . (file_exists('notificacao_email.php') ? '✅ Existe' : '❌ Não existe') . "<br>";

// Teste 2: Função mail
echo "<h3>📧 Função Mail:</h3>";
echo "mail() disponível: " . (function_exists('mail') ? '✅ Sim' : '❌ Não') . "<br>";

// Teste 3: Teste básico de email
if (function_exists('mail')) {
    echo "<h3>🧪 Teste Básico de Email:</h3>";
    
    $para = 'autotransportes.at@hotmail.com';
    $assunto = 'Teste Debug - Portal Cegonheiro';
    $corpo = 'Este é um teste básico de email enviado em ' . date('d/m/Y H:i:s');
    $headers = 'From: cegonheiroportal@gmail.com' . "\r\n" .
               'Content-Type: text/html; charset=UTF-8' . "\r\n";
    
    $resultado = mail($para, $assunto, $corpo, $headers);
    
    echo "Resultado: " . ($resultado ? '✅ Enviado' : '❌ Erro') . "<br>";
    echo "Para: $para<br>";
    echo "Assunto: $assunto<br>";
}

// Teste 4: Verificar config
echo "<h3>⚙️ Configurações PHP:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "SMTP: " . ini_get('SMTP') . "<br>";
echo "smtp_port: " . ini_get('smtp_port') . "<br>";
echo "sendmail_path: " . ini_get('sendmail_path') . "<br>";

// Teste 5: Tentar carregar config
echo "<h3>🔗 Teste de Conexão:</h3>";
try {
    if (file_exists('config.php')) {
        require_once 'config.php';
        echo "config.php carregado: ✅<br>";
        
        if (isset($pdo)) {
            echo "PDO disponível: ✅<br>";
        } else {
            echo "PDO não disponível: ❌<br>";
        }
    }
} catch (Exception $e) {
    echo "Erro ao carregar config: " . $e->getMessage() . "<br>";
}

echo "<br><a href='configuracoes.php'>← Voltar para configurações</a>";
?>