<?php
// verificar_banco.php - Script para verificar e corrigir estrutura do banco
require_once 'config.php';

echo "<h2>Verificação da Estrutura do Banco de Dados</h2>";

try {
    // Verificar tabelas existentes
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tabelas existentes:</h3>";
    echo "<ul>";
    foreach ($tabelas as $tabela) {
        echo "<li>$tabela</li>";
    }
    echo "</ul>";
    
    // Verificar estrutura da tabela leads
    if (in_array('leads', $tabelas)) {
        echo "<h3>Estrutura da tabela 'leads':</h3>";
        $stmt = $pdo->query("DESCRIBE leads");
        $campos = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($campos as $campo) {
            echo "<tr>";
            echo "<td>{$campo['Field']}</td>";
            echo "<td>{$campo['Type']}</td>";
            echo "<td>{$campo['Null']}</td>";
            echo "<td>{$campo['Key']}</td>";
            echo "<td>{$campo['Default']}</td>";
            echo "<td>{$campo['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Contar registros
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
        $total = $stmt->fetch()['total'];
        echo "<p><strong>Total de leads: $total</strong></p>";
    }
    
    // Verificar estrutura da tabela lead_views
    if (in_array('lead_views', $tabelas)) {
        echo "<h3>Estrutura da tabela 'lead_views':</h3>";
        $stmt = $pdo->query("DESCRIBE lead_views");
        $campos = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($campos as $campo) {
            echo "<tr>";
            echo "<td>{$campo['Field']}</td>";
            echo "<td>{$campo['Type']}</td>";
            echo "<td>{$campo['Null']}</td>";
            echo "<td>{$campo['Key']}</td>";
            echo "<td>{$campo['Default']}</td>";
            echo "<td>{$campo['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Contar registros
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM lead_views");
        $total = $stmt->fetch()['total'];
        echo "<p><strong>Total de visualizações: $total</strong></p>";
        
        // Mostrar últimas visualizações
        $stmt = $pdo->query("SELECT * FROM lead_views ORDER BY view_timestamp DESC LIMIT 5");
        $views = $stmt->fetchAll();
        
        if (!empty($views)) {
            echo "<h4>Últimas 5 visualizações:</h4>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Lead ID</th><th>User ID</th><th>Data/Hora</th></tr>";
            foreach ($views as $view) {
                echo "<tr>";
                echo "<td>{$view['id']}</td>";
                echo "<td>{$view['lead_id']}</td>";
                echo "<td>{$view['user_id']}</td>";
                echo "<td>{$view['view_timestamp']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // Verificar estrutura da tabela usuarios
    if (in_array('usuarios', $tabelas)) {
        echo "<h3>Usuários cadastrados:</h3>";
        $stmt = $pdo->query("SELECT id, nome, email, tipo_cliente, nivel_acesso FROM usuarios ORDER BY id");
        $usuarios = $stmt->fetchAll();
        
        if (!empty($usuarios)) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Nível</th></tr>";
            foreach ($usuarios as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['nome']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['tipo_cliente']}</td>";
                echo "<td>{$user['nivel_acesso']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // Verificar relacionamento entre leads e visualizações
    echo "<h3>Estatísticas de Visualizações por Lead:</h3>";
    $stmt = $pdo->query("
        SELECT l.id, l.nome, l.cidade_origem, l.cidade_destino, 
               COUNT(DISTINCT lv.user_id) as total_visualizacoes,
               COUNT(lv.id) as total_registros
        FROM leads l 
        LEFT JOIN lead_views lv ON l.id = lv.lead_id 
        WHERE l.status IN ('novo', 'em_andamento')
        GROUP BY l.id 
        ORDER BY total_visualizacoes DESC
        LIMIT 10
    ");
    $stats = $stmt->fetchAll();
    
    if (!empty($stats)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Lead ID</th><th>Cliente</th><th>Rota</th><th>Usuários Únicos</th><th>Total Registros</th></tr>";
        foreach ($stats as $stat) {
            echo "<tr>";
            echo "<td>{$stat['id']}</td>";
            echo "<td>{$stat['nome']}</td>";
            echo "<td>{$stat['cidade_origem']} → {$stat['cidade_destino']}</td>";
            echo "<td>{$stat['total_visualizacoes']}</td>";
            echo "<td>{$stat['total_registros']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma estatística encontrada.</p>";
    }
    
    // Botão para limpar logs de erro
    if (isset($_GET['limpar_logs'])) {
        // Limpar arquivo de log (se existir)
        $log_file = ini_get('error_log');
        if ($log_file && file_exists($log_file)) {
            file_put_contents($log_file, '');
            echo "<p style='color: green;'><strong>Logs de erro limpos!</strong></p>";
        }
    }
    
    echo "<hr>";
    echo "<p><a href='verificar_banco.php?limpar_logs=1'>Limpar Logs de Erro</a></p>";
    echo "<p><a href='leads_disponiveis.php'>Voltar para Leads Disponíveis</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>