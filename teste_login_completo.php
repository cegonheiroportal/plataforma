<?php
// teste_login_completo.php - Teste completo de login e funcionalidades
require_once 'config.php';

echo "<h1>🧪 Teste Completo de Login e Funcionalidades</h1>";

// Função para testar login
function testarLogin($email, $senha, $nome_teste) {
    global $pdo;
    
    echo "<h3>Testando: $nome_teste</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            echo "<p style='color: green;'>✅ Login bem-sucedido</p>";
            
            // Simular sessão
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
            $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'];
            
            echo "<p>Dados da sessão:</p>";
            echo "<ul>";
            echo "<li>ID: {$usuario['id']}</li>";
            echo "<li>Nome: {$usuario['nome']}</li>";
            echo "<li>Email: {$usuario['email']}</li>";
            echo "<li>Nível: {$usuario['nivel_acesso']}</li>";
            echo "<li>Tipo: {$usuario['tipo_cliente']}</li>";
            echo "</ul>";
            
            // Testar funções
            echo "<p>Testes de função:</p>";
            echo "<ul>";
            echo "<li>ehAdmin(): " . (ehAdmin() ? "✅ SIM" : "❌ NÃO") . "</li>";
            echo "<li>ehTransportadora(): " . (ehTransportadora() ? "✅ SIM" : "❌ NÃO") . "</li>";
            echo "<li>podeVerLeads(): " . (podeVerLeads() ? "✅ SIM" : "❌ NÃO") . "</li>";
            echo "</ul>";
            
            return true;
        } else {
            echo "<p style='color: red;'>❌ Login falhou</p>";
            return false;
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Testar diferentes tipos de usuário
echo "<h2>1. Testes de Login</h2>";

$testes = [
    ['admin@teste.com', '123456', 'Administrador'],
    ['transportadoraa@teste.com', '123456', 'Transportadora A'],
    ['transportadorab@teste.com', '123456', 'Transportadora B']
];

foreach ($testes as $teste) {
    testarLogin($teste[0], $teste[1], $teste[2]);
    echo "<hr style='margin: 10px 0;'>";
}

// Testar sistema de visualizações
echo "<h2>2. Teste do Sistema de Visualizações</h2>";

try {
    $stmt = $pdo->query("SELECT id FROM leads LIMIT 1");
    $lead = $stmt->fetch();
    
    if ($lead) {
        $lead_id = $lead['id'];
        echo "<p>Testando com Lead ID: $lead_id</p>";
        
        $total_views = contarVisualizacoesLead($lead_id);
        echo "<p>Total de visualizações: $total_views</p>";
        
        // Testar com usuário 1
        $ja_viu = jaVisualizouLead($lead_id, 1);
        echo "<p>Usuário 1 já visualizou: " . ($ja_viu ? "SIM" : "NÃO") . "</p>";
        
    } else {
        echo "<p style='color: orange;'>⚠️ Nenhum lead encontrado para teste</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro no teste de visualizações: " . $e->getMessage() . "</p>";
}

// Links para testes manuais
echo "<h2>3. Testes Manuais</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h3>Contas para Teste Manual:</h3>";

$stmt = $pdo->query("SELECT nome, email, tipo_cliente, nivel_acesso FROM usuarios ORDER BY nivel_acesso DESC, tipo_cliente");
$usuarios = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Nome</th><th>Email</th><th>Senha</th><th>Tipo</th><th>Nível</th><th>Ação</th></tr>";
foreach ($usuarios as $user) {
    $tipo_badge = $user['tipo_cliente'] === 'pj' ? '🏢' : '👤';
    $nivel_badge = $user['nivel_acesso'] === 'administrador' ? '👑' : '🚛';
    
    echo "<tr>";
    echo "<td>$tipo_badge {$user['nome']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>123456</td>";
    echo "<td>{$user['tipo_cliente']}</td>";
    echo "<td>$nivel_badge {$user['nivel_acesso']}</td>";
    echo "<td><a href='login_manual.php?email={$user['email']}' target='_blank'>Testar Login</a></td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Links para Testar:</h3>";
echo "<p><a href='leads_disponiveis.php' target='_blank'>📋 Leads Disponíveis</a></p>";
echo "<p><a href='dashboard_cliente.php' target='_blank'>🏠 Dashboard Cliente</a></p>";
echo "</div>";

echo "<hr>";
echo "<h2>✅ Teste Completo Finalizado!</h2>";
echo "<p>Use as contas acima para fazer login manual e testar todas as funcionalidades.</p>";
?>