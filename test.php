<?php
// test.php - Arquivo para testar a conexão básica
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Teste de Diagnóstico - Portal Cegonheiro</h2>";

// Teste 1: PHP básico
echo "<h3>✅ 1. PHP está funcionando!</h3>";
echo "Versão do PHP: " . phpversion() . "<br>";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "<br><br>";

// Teste 2: Extensões necessárias
echo "<h3>2. Verificando extensões:</h3>";
$extensoes = ['pdo', 'pdo_mysql', 'mysqli', 'json'];
foreach ($extensoes as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext: OK<br>";
    } else {
        echo "❌ $ext: NÃO DISPONÍVEL<br>";
    }
}
echo "<br>";

// Teste 3: Conexão com banco
echo "<h3>3. Testando conexão com banco:</h3>";
try {
    $host = 'localhost';
    $dbname = 'joaocr74_cegonha';
    $username = 'joaocr74_lima';
    $password = 'davi@2025';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexão com banco: OK<br>";
    
    // Teste 4: Verificar tabelas
    echo "<h3>4. Verificando tabelas:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('leads', $tabelas)) {
        echo "✅ Tabela 'leads': EXISTE<br>";
        
        // Verificar estrutura da tabela leads
        $stmt = $pdo->query("DESCRIBE leads");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tem_valor_veiculo = false;
        foreach ($colunas as $coluna) {
            if ($coluna['Field'] == 'valor_veiculo') {
                $tem_valor_veiculo = true;
                break;
            }
        }
        
        if ($tem_valor_veiculo) {
            echo "✅ Coluna 'valor_veiculo': EXISTE<br>";
        } else {
            echo "❌ Coluna 'valor_veiculo': NÃO EXISTE<br>";
            echo "<strong>Execute este comando SQL:</strong><br>";
            echo "<code>ALTER TABLE leads ADD COLUMN valor_veiculo DECIMAL(10,2) AFTER ano_modelo;</code><br>";
        }
    } else {
        echo "❌ Tabela 'leads': NÃO EXISTE<br>";
        echo "<strong>A tabela 'leads' precisa ser criada!</strong><br>";
    }
    
    echo "<br><h3>5. Tabelas existentes no banco:</h3>";
    foreach ($tabelas as $tabela) {
        echo "• $tabela<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
}

echo "<br><h3>6. Informações do servidor:</h3>";
echo "Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Não disponível') . "<br>";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Não disponível') . "<br>";
echo "Script atual: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Não disponível') . "<br>";

echo "<br><h3>7. Verificando arquivos:</h3>";
$arquivos_importantes = ['index.php', 'app/config.php'];
foreach ($arquivos_importantes as $arquivo) {
    if (file_exists($arquivo)) {
        echo "✅ $arquivo: EXISTE<br>";
    } else {
        echo "❌ $arquivo: NÃO EXISTE<br>";
    }
}
?>