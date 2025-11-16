<?php
// criar_estrutura.php - Script para criar toda a estrutura do banco
require_once 'config.php';

echo "<h1>🔧 Criando Estrutura Completa do Banco</h1>";

try {
    // 1. Criar tabela usuarios
    echo "<h2>1. Criando Tabela Usuarios</h2>";
    $sql_usuarios = "CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nome` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `senha` varchar(255) NOT NULL,
        `telefone` varchar(20) DEFAULT NULL,
        `tipo_cliente` enum('pf','pj') NOT NULL DEFAULT 'pf',
        `nivel_acesso` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
        `empresa` varchar(255) DEFAULT NULL,
        `cnpj` varchar(18) DEFAULT NULL,
        `endereco` text DEFAULT NULL,
        `cidade` varchar(255) DEFAULT NULL,
        `estado` varchar(2) DEFAULT NULL,
        `cep` varchar(10) DEFAULT NULL,
        `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ativo` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        KEY `idx_tipo_cliente` (`tipo_cliente`),
        KEY `idx_nivel_acesso` (`nivel_acesso`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_usuarios);
    echo "<p style='color: green;'>✅ Tabela 'usuarios' criada com sucesso</p>";

    // 2. Criar tabela leads
    echo "<h2>2. Criando Tabela Leads</h2>";
    $sql_leads = "CREATE TABLE IF NOT EXISTS `leads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nome` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `telefone` varchar(20) NOT NULL,
        `cidade_origem` varchar(255) NOT NULL,
        `cidade_destino` varchar(255) NOT NULL,
        `tipo_veiculo` varchar(50) NOT NULL,
        `ano_modelo` varchar(100) NOT NULL,
        `valor_veiculo` decimal(10,2) DEFAULT NULL,
        `data_prevista` date NOT NULL,
        `observacoes` text DEFAULT NULL,
        `aceite_lgpd` tinyint(1) NOT NULL DEFAULT 0,
        `ip_origem` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `utm_source` varchar(255) DEFAULT NULL,
        `utm_medium` varchar(255) DEFAULT NULL,
        `utm_campaign` varchar(255) DEFAULT NULL,
        `status` enum('novo','em_andamento','concluido','cancelado') DEFAULT 'novo',
        `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`),
        KEY `idx_data_cadastro` (`data_cadastro`),
        KEY `idx_origem_destino` (`cidade_origem`, `cidade_destino`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_leads);
    echo "<p style='color: green;'>✅ Tabela 'leads' criada com sucesso</p>";

    // 3. Verificar se lead_views já existe, se não, criar
    echo "<h2>3. Verificando Tabela Lead_Views</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'lead_views'");
    if ($stmt->rowCount() == 0) {
        $sql_lead_views = "CREATE TABLE `lead_views` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `lead_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `view_timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `lead_id` (`lead_id`,`user_id`),
            KEY `idx_lead_id` (`lead_id`),
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
        
        $pdo->exec($sql_lead_views);
        echo "<p style='color: green;'>✅ Tabela 'lead_views' criada com sucesso</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Tabela 'lead_views' já existe</p>";
    }

    // 4. Criar tabela cotacoes
    echo "<h2>4. Criando Tabela Cotacoes</h2>";
    $sql_cotacoes = "CREATE TABLE IF NOT EXISTS `cotacoes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `lead_id` int(11) NOT NULL,
        `transportadora_nome` varchar(255) NOT NULL,
        `transportadora_email` varchar(255) NOT NULL,
        `transportadora_telefone` varchar(20) DEFAULT NULL,
        `valor_cotacao` decimal(10,2) NOT NULL,
        `prazo_entrega` int(11) NOT NULL,
        `observacoes` text DEFAULT NULL,
        `data_envio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `status` enum('enviada','aceita','recusada') DEFAULT 'enviada',
        PRIMARY KEY (`id`),
        KEY `idx_lead_id` (`lead_id`),
        KEY `idx_data_envio` (`data_envio`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_cotacoes);
    echo "<p style='color: green;'>✅ Tabela 'cotacoes' criada com sucesso</p>";

    // 5. Criar usuários de teste
    echo "<h2>5. Criando Usuários de Teste</h2>";
    
    // Admin
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    if (!$stmt->fetch()) {
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
    } else {
        echo "<p style='color: blue;'>ℹ️ Admin já existe</p>";
    }

    // Transportadoras
    $transportadoras = [
        ['Transportadora A', 'transportadoraa@teste.com', 'Transportes A Ltda'],
        ['Transportadora B', 'transportadorab@teste.com', 'Transportes B Ltda'],
        ['Transportadora C', 'transportadorac@teste.com', 'Transportes C Ltda']
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
            echo "<p style='color: green;'>✅ {$transp[0]} criada: {$transp[1]} / 123456</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ {$transp[0]} já existe</p>";
        }
    }

    // 6. Criar leads de teste
    echo "<h2>6. Criando Leads de Teste</h2>";
    
    $leads_teste = [
        ['João Silva', 'joao@cliente.com', '11999999999', 'São Paulo, SP', 'Rio de Janeiro, RJ', 'Carro', 'Honda Civic 2020', 80000],
        ['Maria Santos', 'maria@cliente.com', '11888888888', 'Fortaleza, CE', 'Brasília, DF', 'Carro', 'Toyota Corolla 2019', 75000],
        ['Pedro Costa', 'pedro@cliente.com', '11777777777', 'Belo Horizonte, MG', 'São Paulo, SP', 'Moto', 'Honda CB 600F 2021', 35000],
        ['Ana Oliveira', 'ana@cliente.com', '11666666666', 'Rio de Janeiro, RJ', 'Salvador, BA', 'Carro', 'Volkswagen Jetta 2021', 90000],
        ['Carlos Ferreira', 'carlos@cliente.com', '11555555555', 'Brasília, DF', 'Recife, PE', 'Carro', 'Nissan Sentra 2020', 70000]
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
            echo "<p style='color: green;'>✅ Lead criado: {$lead[0]} ({$lead[3]} → {$lead[4]})</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Lead {$lead[0]} já existe</p>";
        }
    }

    // 7. Adicionar algumas visualizações de teste
    echo "<h2>7. Adicionando Visualizações de Teste</h2>";
    
    $stmt = $pdo->query("SELECT id FROM leads WHERE status = 'novo' LIMIT 3");
    $leads_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE tipo_cliente = 'pj' AND nivel_acesso = 'cliente' LIMIT 3");
    $users_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($leads_ids) && !empty($users_ids)) {
        foreach ($leads_ids as $lead_id) {
            $num_views = rand(1, min(3, count($users_ids)));
            $selected_users = array_slice($users_ids, 0, $num_views);
            
            foreach ($selected_users as $user_id) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO lead_views (lead_id, user_id, view_timestamp) VALUES (?, ?, NOW() - INTERVAL ? HOUR)");
                $stmt->execute([$lead_id, $user_id, rand(1, 48)]);
            }
            echo "<p style='color: green;'>✅ Visualizações adicionadas para Lead #$lead_id ($num_views visualizações)</p>";
        }
    }

    echo "<hr>";
    echo "<h2>🎉 Estrutura Criada com Sucesso!</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Contas Criadas:</h3>";
    echo "<p><strong>Administrador:</strong><br>";
    echo "Email: admin@teste.com<br>";
    echo "Senha: 123456</p>";
    
    echo "<p><strong>Transportadoras:</strong><br>";
    echo "transportadoraa@teste.com / 123456<br>";
    echo "transportadorab@teste.com / 123456<br>";
    echo "transportadorac@teste.com / 123456</p>";
    echo "</div>";
    
    echo "<p><a href='teste_sistema.php'>🔄 Executar Teste Novamente</a></p>";
    echo "<p><a href='login_teste.php'>🔑 Ir para Login de Teste</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p>Detalhes do erro:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>