<?php
// test_dashboard.php - Teste básico do dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Teste Dashboard Cliente</h2>";

// Teste 1: Verificar se o arquivo config existe
if (file_exists('config.php')) {
    echo "✅ config.php existe<br>";
    try {
        require_once 'config.php';
        echo "✅ config.php carregado com sucesso<br>";
    } catch (Exception $e) {
        echo "❌ Erro ao carregar config.php: " . $e->getMessage() . "<br>";
        exit;
    }
} else {
    echo "❌ config.php não encontrado<br>";
    exit;
}

// Teste 2: Verificar sessão
session_start();
echo "<h3>Informações da Sessão:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "verificarLogin(): " . (function_exists('verificarLogin') ? (verificarLogin() ? 'TRUE' : 'FALSE') : 'FUNÇÃO NÃO EXISTE') . "<br>";
echo "nivel_acesso: " . ($_SESSION['nivel_acesso'] ?? 'NÃO DEFINIDO') . "<br>";
echo "usuario_id: " . ($_SESSION['usuario_id'] ?? 'NÃO DEFINIDO') . "<br>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "<br>";
echo "nome: " . ($_SESSION['nome'] ?? 'NÃO DEFINIDO') . "<br>";

// Teste 3: Verificar conexão com banco
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Conexão com banco OK<br>";
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
}

// Teste 4: Verificar funções
$funcoes = ['verificarLogin', 'obterIdUsuario', 'obterDadosCliente', 'obterConfiguracoes'];
echo "<h3>Funções disponíveis:</h3>";
foreach ($funcoes as $funcao) {
    echo ($funcao . ": " . (function_exists($funcao) ? "✅ OK" : "❌ NÃO EXISTE") . "<br>");
}

echo "<br><a href='dashboard_cliente.php'>Tentar Dashboard Completo</a>";
?>