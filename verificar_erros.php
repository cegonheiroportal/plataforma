<?php
// verificar_erros.php - Script para verificar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Verificação de Erros</h1>";

// Testar conexão básica
try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    echo "<p style='color: green;'>✅ Conexão com banco OK</p>";
    
    // Testar se tabela usuarios existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Tabela usuarios existe</p>";
        
        // Mostrar estrutura
        $stmt = $pdo->query("DESCRIBE usuarios");
        $campos = $stmt->fetchAll();
        
        echo "<h3>Estrutura da tabela usuarios:</h3>";
        echo "<ul>";
        foreach ($campos as $campo) {
            echo "<li>{$campo['Field']} - {$campo['Type']}</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ Tabela usuarios não existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Verificar se config.php tem problemas
echo "<h2>Testando config.php</h2>";
try {
    // Não incluir o config.php, apenas testar sintaxe
    $config_content = file_get_contents('config.php');
    if ($config_content === false) {
        echo "<p style='color: red;'>❌ Não foi possível ler config.php</p>";
    } else {
        echo "<p style='color: green;'>✅ config.php pode ser lido</p>";
        echo "<p>Tamanho: " . strlen($config_content) . " bytes</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar config.php: " . $e->getMessage() . "</p>";
}

phpinfo();
?>