<?php
require_once 'config.php';

echo "<h2>🔍 Teste de Links dos Leads</h2>";

// Buscar alguns leads para teste
try {
    $stmt = $pdo->query("SELECT * FROM leads ORDER BY data_cadastro DESC LIMIT 5");
    $leads = $stmt->fetchAll();
    
    if (empty($leads)) {
        echo "<p>❌ Nenhum lead encontrado no banco de dados.</p>";
    } else {
        echo "<h3>Leads encontrados:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Link Teste</th><th>Arquivo Existe?</th></tr>";
        
        foreach ($leads as $lead) {
            $link = "lead_detalhes.php?id=" . $lead['id'];
            $arquivo_existe = file_exists('lead_detalhes.php') ? '✅ Sim' : '❌ Não';
            
            echo "<tr>";
            echo "<td>" . $lead['id'] . "</td>";
            echo "<td>" . htmlspecialchars($lead['nome']) . "</td>";
            echo "<td><a href='{$link}' target='_blank'>Testar Link</a></td>";
            echo "<td>{$arquivo_existe}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<br><h3>Verificações:</h3>";
echo "<ul>";
echo "<li>Arquivo lead_detalhes.php existe: " . (file_exists('lead_detalhes.php') ? '✅ Sim' : '❌ Não') . "</li>";
echo "<li>Arquivo dashboard_cliente.php existe: " . (file_exists('dashboard_cliente.php') ? '✅ Sim' : '❌ Não') . "</li>";
echo "<li>URL atual: " . $_SERVER['REQUEST_URI'] . "</li>";
echo "<li>Diretório atual: " . __DIR__ . "</li>";
echo "</ul>";

echo "<br><a href='dashboard_cliente.php'>← Voltar ao Dashboard</a>";
?>