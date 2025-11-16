<?php
// popular_dados_teste_corrigido.php - Script corrigido para popular dados de teste
require_once 'config.php';

if (isset($_GET['executar'])) {
    try {
        echo "<h2>Populando Dados de Teste (Corrigido)</h2>";
        
        // Verificar estrutura da tabela usuarios primeiro
        $stmt = $pdo->query("DESCRIBE usuarios");
        $campos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('tipo_cliente', $campos) || !in_array('nivel_acesso', $campos)) {
            echo "<p style='color: red;'>❌ Estrutura da tabela usuarios não está correta. Execute primeiro o script de correção.</p>";
            echo "<p><a href='corrigir_usuarios.php'>Corrigir estrutura da tabela usuarios</a></p>";
            exit;
        }
        
        // Criar usuário admin se não existir
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute(['admin@cegonheiro.com']);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso, empresa) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Administrador', 
                'admin@cegonheiro.com', 
                password_hash('admin123', PASSWORD_DEFAULT), 
                'pj', 
                'admin',
                'Portal Cegonheiro'
            ]);
            echo "<p>✅ Usuário admin criado (email: admin@cegonheiro.com, senha: admin123)</p>";
        } else {
            echo "<p>ℹ️ Usuário admin já existe</p>";
        }
        
        // Criar algumas transportadoras de teste
        $transportadoras = [
            ['Transportadora A', 'transportadoraa@teste.com', 'Transportes A Ltda'],
            ['Transportadora B', 'transportadorab@teste.com', 'Transportes B Ltda'],
            ['Transportadora C', 'transportadorac@teste.com', 'Transportes C Ltda'],
            ['Transportadora D', 'transportadorad@teste.com', 'Transportes D Ltda'],
            ['Transportadora E', 'transportadorae@teste.com', 'Transportes E Ltda']
        ];
        
        foreach ($transportadoras as $transp) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$transp[1]]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso, empresa) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $transp[0], 
                    $transp[1], 
                    password_hash('123456', PASSWORD_DEFAULT), 
                    'pj', 
                    'cliente',
                    $transp[2]
                ]);
                echo "<p>✅ {$transp[0]} criada (email: {$transp[1]}, senha: 123456)</p>";
            } else {
                echo "<p>ℹ️ {$transp[0]} já existe</p>";
            }
        }
        
        // Criar alguns clientes PF de teste
        $clientes_pf = [
            ['João Silva', 'joao@teste.com'],
            ['Maria Santos', 'maria@teste.com'],
            ['Pedro Costa', 'pedro@teste.com']
        ];
        
        foreach ($clientes_pf as $cliente) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$cliente[1]]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_cliente, nivel_acesso) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $cliente[0], 
                    $cliente[1], 
                    password_hash('123456', PASSWORD_DEFAULT), 
                    'pf', 
                    'cliente'
                ]);
                echo "<p>✅ Cliente PF {$cliente[0]} criado (email: {$cliente[1]}, senha: 123456)</p>";
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
            } else {
                echo "<p>ℹ️ Lead {$lead[0]} já existe</p>";
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
        echo "<h3>✅ Dados de teste populados com sucesso!</h3>";
        echo "<h4>Contas criadas:</h4>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> admin@cegonheiro.com / admin123</li>";
        echo "<li><strong>Transportadoras:</strong> transportadoraa@teste.com até transportadorae@teste.com / 123456</li>";
        echo "<li><strong>Clientes PF:</strong> joao@teste.com, maria@teste.com, pedro@teste.com / 123456</li>";
        echo "</ul>";
        
        echo "<p><a href='leads_disponiveis.php'>Ir para Leads Disponíveis</a></p>";
        echo "<p><a href='verificar_banco.php'>Verificar Estrutura do Banco</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h2>Popular Dados de Teste (Corrigido)</h2>";
    echo "<p>Este script irá criar:</p>";
    echo "<ul>";
    echo "<li>1 usuário administrador</li>";
    echo "<li>5 transportadoras de teste</li>";
    echo "<li>3 clientes PF de teste</li>";
    echo "<li>3 leads de exemplo</li>";
    echo "<li>Algumas visualizações de teste</li>";
    echo "</ul>";
    echo "<p><strong>⚠️ Atenção:</strong> Este script só deve ser executado em ambiente de desenvolvimento!</p>";
    echo "<p><a href='popular_dados_teste_corrigido.php?executar=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Executar População de Dados</a></p>";
}
?>