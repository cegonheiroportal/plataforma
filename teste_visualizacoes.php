<?php
// teste_visualizacoes.php - Script para testar o sistema de visualizações
require_once 'config.php';

// Verificar se a tabela lead_views tem dados
try {
    echo "<h2>Testando Sistema de Visualizações</h2>";
    
    // Verificar estrutura da tabela
    $stmt = $pdo->query("DESCRIBE lead_views");
    $estrutura = $stmt->fetchAll();
    
    echo "<h3>Estrutura da tabela lead_views:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($estrutura as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar dados existentes
    $stmt = $pdo->query("SELECT * FROM lead_views ORDER BY view_timestamp DESC LIMIT 10");
    $views = $stmt->fetchAll();
    
    echo "<h3>Últimas 10 visualizações:</h3>";
    if (empty($views)) {
        echo "<p>Nenhuma visualização encontrada.</p>";
    } else {
        echo "<table border='1'>";
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
    
    // Contar visualizações por lead
    $stmt = $pdo->query("
        SELECT l.id, l.nome, l.cidade_origem, l.cidade_destino, 
               COUNT(DISTINCT lv.user_id) as total_visualizacoes
        FROM leads l 
        LEFT JOIN lead_views lv ON l.id = lv.lead_id 
        WHERE l.status IN ('novo', 'em_andamento')
        GROUP BY l.id 
        ORDER BY total_visualizacoes DESC
        LIMIT 10
    ");
    $leads_stats = $stmt->fetchAll();
    
    echo "<h3>Estatísticas de visualizações por lead:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Lead ID</th><th>Cliente</th><th>Rota</th><th>Visualizações</th></tr>";
    foreach ($leads_stats as $stat) {
        echo "<tr>";
        echo "<td>{$stat['id']}</td>";
        echo "<td>{$stat['nome']}</td>";
        echo "<td>{$stat['cidade_origem']} → {$stat['cidade_destino']}</td>";
        echo "<td>{$stat['total_visualizacoes']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Adicionar algumas visualizações de teste (opcional)
    if (isset($_GET['popular'])) {
        echo "<h3>Populando dados de teste...</h3>";
        
        // Buscar alguns leads e usuários
        $stmt = $pdo->query("SELECT id FROM leads WHERE status IN ('novo', 'em_andamento') LIMIT 3");
        $leads = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->query("SELECT id FROM usuarios WHERE tipo_cliente = 'pj' LIMIT 5");
        $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($leads) && !empty($usuarios)) {
            foreach ($leads as $lead_id) {
                // Adicionar 2-3 visualizações aleatórias para cada lead
                $num_views = rand(2, min(7, count($usuarios)));
                $usuarios_selecionados = array_slice($usuarios, 0, $num_views);
                
                foreach ($usuarios_selecionados as $user_id) {
                    try {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO lead_views (lead_id, user_id, view_timestamp) VALUES (?, ?, NOW() - INTERVAL ? HOUR)");
                        $stmt->execute([$lead_id, $user_id, rand(1, 24)]);
                        echo "<p>Visualização adicionada: Lead $lead_id por User $user_id</p>";
                    } catch (Exception $e) {
                        echo "<p>Erro ao adicionar visualização: " . $e->getMessage() . "</p>";
                    }
                }
            }
            echo "<p><strong>Dados de teste adicionados! <a href='teste_visualizacoes.php'>Recarregar página</a></strong></p>";
        } else {
            echo "<p>Não foi possível encontrar leads ou usuários para popular.</p>";
        }
    } else {
        echo "<p><a href='teste_visualizacoes.php?popular=1'>Clique aqui para popular com dados de teste</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p>Erro: " . $e->getMessage() . "</p>";
}
?>