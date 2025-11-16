<?php
// teste_sistema.php - Script completo de verificação
require_once 'config.php';

echo "<h1>🔧 Teste Completo do Sistema Portal Cegonheiro</h1>";

// 1. Verificar conexão com banco
echo "<h2>1. Conexão com Banco de Dados</h2>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ Conexão com banco OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro na conexão: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Verificar estrutura das tabelas
echo "<h2>2. Estrutura das Tabelas</h2>";

$tabelas_necessarias = ['usuarios', 'leads', 'lead_views', 'cotacoes'];
foreach ($tabelas_necessarias as $tabela) {
    if (tabelaExiste($tabela)) {
        echo "<p style='color: green;'>✅ Tabela '$tabela' existe</p>";
        
        // Mostrar estrutura da tabela usuarios
        if ($tabela === 'usuarios') {
            $stmt = $pdo->query("DESCRIBE usuarios");
            $campos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<ul>";
            foreach ($campos as $campo) {
                echo "<li>$campo</li>";
            }
            echo "</ul>";
            
            // Verificar colunas críticas
            $colunas_criticas = ['tipo_cliente', 'nivel_acesso'];
            foreach ($colunas_criticas as $coluna) {
                if (in_array($coluna, $campos)) {
                    echo "<p style='color: green;'>✅ Coluna '$coluna' existe</p>";
                } else {
                    echo "<p style='color: red;'>❌ Coluna '$coluna' NÃO existe</p>";
                }
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Tabela '$tabela' NÃO existe</p>";
    }
}

// 3. Criar usuário de teste se não existir
echo "<h2>3. Usuários de Teste</h2>";

// Admin
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute(['admin@teste.com']);
$admin = $stmt->fetch();

if (!$admin) {
    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso, empresa) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Admin Teste',
            'admin@teste.com',
            password_hash('123456', PASSWORD_DEFAULT),
            'pj',
            'admin',
            'Sistema'
        ]);
        echo "<p style='color: green;'>✅ Admin criado: admin@teste.com / 123456</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao criar admin: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ Admin já existe: admin@teste.com</p>";
}

// Transportadora
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute(['transportadora@teste.com']);
$transportadora = $stmt->fetch();

if (!$transportadora) {
    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso, empresa) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Transportadora Teste',
            'transportadora@teste.com',
            password_hash('123456', PASSWORD_DEFAULT),
            'pj',
            'cliente',
            'Transportes Teste Ltda'
        ]);
        echo "<p style='color: green;'>✅ Transportadora criada: transportadora@teste.com / 123456</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao criar transportadora: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ Transportadora já existe: transportadora@teste.com</p>";
}

// 4. Criar leads de teste
echo "<h2>4. Leads de Teste</h2>";

$leads_teste = [
    ['João Silva', 'joao@cliente.com', '11999999999', 'São Paulo, SP', 'Rio de Janeiro, RJ', 'Carro', 'Honda Civic 2020', 80000],
    ['Maria Santos', 'maria@cliente.com', '11888888888', 'Fortaleza, CE', 'Brasília, DF', 'Carro', 'Toyota Corolla 2019', 75000]
];

foreach ($leads_teste as $lead) {
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ?");
    $stmt->execute([$lead[1]]);
    if (!$stmt->fetch()) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO leads (nome, email, telefone, cidade_origem, cidade_destino, tipo_veiculo, ano_modelo, valor_veiculo, data_prevista, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 DAY), 'novo')
            ");
            $stmt->execute($lead);
            echo "<p style='color: green;'>✅ Lead criado: {$lead[0]} ({$lead[3]} → {$lead[4]})</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erro ao criar lead: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Lead já existe: {$lead[0]}</p>";
    }
}

// 5. Testar funções do sistema
echo "<h2>5. Teste das Funções</h2>";

// Simular login de admin
$_SESSION['user_id'] = 1;
$_SESSION['nome'] = 'Admin Teste';
$_SESSION['nivel_acesso'] = 'admin';
$_SESSION['tipo_cliente'] = 'pj';

echo "<p>Testando com sessão de admin:</p>";
echo "<ul>";
echo "<li>ehAdmin(): " . (ehAdmin() ? "✅ SIM" : "❌ NÃO") . "</li>";
echo "<li>ehTransportadora(): " . (ehTransportadora() ? "✅ SIM" : "❌ NÃO") . "</li>";
echo "<li>podeVerLeads(): " . (podeVerLeads() ? "✅ SIM" : "❌ NÃO") . "</li>";
echo "</ul>";

// Simular login de transportadora
$_SESSION['nivel_acesso'] = 'cliente';
$_SESSION['tipo_cliente'] = 'pj';

echo "<p>Testando com sessão de transportadora:</p>";
echo "<ul>";
echo "<li>ehAdmin(): " . (ehAdmin() ? "✅ SIM" : "❌ NÃO") . "</li>";
echo "<li>ehTransportadora(): " . (ehTransportadora() ? "✅ SIM" : "❌ NÃO") . "</li>";
echo "<li>podeVerLeads(): " . (podeVerLeads() ? "✅ SIM" : "❌ NÃO") . "</li>";
echo "</ul>";

// 6. Testar sistema de visualizações
echo "<h2>6. Sistema de Visualizações</h2>";

$stmt = $pdo->query("SELECT id FROM leads LIMIT 1");
$lead_teste = $stmt->fetch();

if ($lead_teste) {
    $lead_id = $lead_teste['id'];
    echo "<p>Testando com Lead ID: $lead_id</p>";
    
    $total_views = contarVisualizacoesLead($lead_id);
    echo "<p>Total de visualizações: $total_views</p>";
    
    $ja_viu = jaVisualizouLead($lead_id, 1);
    echo "<p>Usuário 1 já visualizou: " . ($ja_viu ? "SIM" : "NÃO") . "</p>";
}

// 7. Links para testes manuais
echo "<h2>7. Testes Manuais</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
echo "<h3>Contas para Teste:</h3>";
echo "<p><strong>Administrador:</strong><br>";
echo "Email: admin@teste.com<br>";
echo "Senha: 123456</p>";

echo "<p><strong>Transportadora:</strong><br>";
echo "Email: transportadora@teste.com<br>";
echo "Senha: 123456</p>";

echo "<h3>Links para Testar:</h3>";
echo "<p><a href='login.php' target='_blank'>🔑 Página de Login</a></p>";
echo "<p><a href='leads_disponiveis.php' target='_blank'>📋 Leads Disponíveis</a></p>";
echo "<p><a href='dashboard_cliente.php' target='_blank'>🏠 Dashboard</a></p>";
echo "</div>";

// 8. Logs recentes
echo "<h2>8. Logs Recentes</h2>";
echo "<p>Verifique os logs do servidor para mensagens de debug.</p>";
echo "<p>Caminho típico: /var/log/apache2/error.log ou similar</p>";

echo "<hr>";
echo "<p><strong>Teste concluído!</strong> Use as contas acima para fazer login e testar o sistema.</p>";
?>