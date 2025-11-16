<?php
// popular_dados_teste.php - Script para popular dados de teste
require_once 'config.php';

if (isset($_GET['executar'])) {
    try {
        echo "<h2>Populando Dados de Teste</h2>";
        
        // Criar usuário admin se não existir
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute(['admin@cegonheiro.com']);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Administrador', 'admin@cegonheiro.com', password_hash('admin123', PASSWORD_DEFAULT), 'pj', 'admin']);
            echo "<p>✅ Usuário admin criado (email: admin@cegonheiro.com, senha: admin123)</p>";
        }
        
        // Criar algumas transportadoras de teste
        $transportadoras = [
            ['Transportadora A', 'transportadoraa@teste.com'],
            ['Transportadora B', 'transportadorab@teste.com'],
            ['Transportadora C', 'transportadorac@teste.com'],
            ['Transportadora D', 'transportadorad@teste.com'],
            ['Transportadora E', 'transportadorae@teste.com']
        ];
        
        foreach ($transportadoras as $transp) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$transp[1]]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$transp[0], $transp[1], password_hash('123456', PASSWORD_DEFAULT), 'pj', 'cliente']);
                echo "<p>✅ {$transp[0]} criada (email: {$transp[1]}, senha: 123456)</p>";
            }
        }
        
        // Criar alguns leads de teste
        $leads_teste = [
            ['João Silva', 'joao@teste.com', '11999999999', 'São Paulo, SP', 'Rio de Janeiro, RJ', 'Carro', 'Honda Civic 2020', 80000],
            ['Maria Santos', 'maria@teste.com', '11888888888', 'Fortaleza, CE', 'Brasília, DF', 'Carro', 'Toyota Corolla 2019', 75000],
            ['Pedro Costa', 'pedro@teste.com', '11777777777', 'Belo Horizonte, MG', 'São Paulo, SP', 'Moto', 'Honda CB 600F 2021', 35000]
        ];
        
        foreach ($leads_teste as $lead) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ?");
            $stmt->execute([$lead[1]]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO leads (nome, email, telefone, cidade_origem, cidade_destino, tipo_veiculo, ano_modelo, valor_veiculo, data_prevista, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), 'novo')
                ");
                $stmt->execute([
                    $lead[0], $lead[1], $lead[2], $lead[3], $lead[4], 
                    $lead[5], $lead[6], $lead[7], rand(5, 30)
                ]);
                echo "<p>✅ Lead criado: {$lead[0]} ({$lead[3]} → {$lead[4]})</p>";
            }
        }
        
        // Adicionar algumas visualizações de teste
        $stmt = $pdo->query("SELECT id FROM leads WHERE status = 'novo' LIMIT 3");
        $leads_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->query("SELECT id FROM usuarios WHERE tipo_cliente = 'pj' AND nivel_acesso = 'cliente' LIMIT 5");
        $users_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($leads_ids) && !empty($users_ids)) {
            foreach ($leads_ids as $lead_id) {
                $num_views = rand(1, min(7, count($users_ids)));
                $selected_users = array_slice($users_ids, 0, $num_views);
                
                foreach ($selected_users as $user_id) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO lead_views (lead_id, user_id, view_timestamp) VALUES (?, ?, NOW() - INTERVAL ? HOUR)");
                    $stmt->execute([$lead_id, $user_id, rand(1, 48)]);
                }
                echo "<p>✅ Visualizações adicionadas para Lead #$lead_id ($num_views visualizações)</p>";
            }
        }
        
        echo "<hr>";
        echo "<p><strong>✅ Dados de teste populados com sucesso!</strong></p>";
        echo "<p><a href='leads_disponiveis.php'>Ir para Leads Disponíveis</a></p>";
        echo "<p><a href='verificar_banco.php'>Verificar Estrutura do Banco</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h2>Popular Dados de Teste</h2>";
    echo "<p>Este script irá criar:</p>";
    echo "<ul>";
    echo "<li>1 usuário administrador</li>";
    echo "<li>5 transportadoras de teste</li>";
    echo "<li>3 leads de exemplo</li>";
    echo "<li>Algumas visualizações de teste</li>";
    echo "</ul>";
    echo "<p><strong>⚠️ Atenção:</strong> Este script só deve ser executado em ambiente de desenvolvimento!</p>";
    echo "<p><a href='popular_dados_teste.php?executar=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Executar População de Dados</a></p>";
}
?>