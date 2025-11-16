<?php
// recriar_usuarios.php - Script para recriar tabela usuarios do zero
require_once 'config.php';

echo "<h1>🔧 Recriando Tabela Usuarios</h1>";

if (isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim') {
    try {
        // 1. Fazer backup dos dados existentes
        echo "<h2>1. Fazendo Backup dos Dados</h2>";
        $stmt = $pdo->query("SELECT * FROM usuarios");
        $usuarios_backup = $stmt->fetchAll();
        echo "<p style='color: blue;'>ℹ️ Backup de " . count($usuarios_backup) . " usuários realizado</p>";
        
        // 2. Dropar tabela existente
        echo "<h2>2. Removendo Tabela Antiga</h2>";
        $pdo->exec("DROP TABLE IF EXISTS usuarios");
        echo "<p style='color: orange;'>⚠️ Tabela usuarios removida</p>";
        
        // 3. Criar nova tabela com estrutura completa
        echo "<h2>3. Criando Nova Tabela</h2>";
        $sql = "CREATE TABLE `usuarios` (
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
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Nova tabela usuarios criada</p>";
        
        // 4. Restaurar dados com estrutura correta
        echo "<h2>4. Restaurando Dados</h2>";
        foreach ($usuarios_backup as $usuario) {
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, telefone, tipo_cliente, nivel_acesso, empresa, data_cadastro, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $usuario['nome'],
                    $usuario['email'],
                    $usuario['senha'],
                    $usuario['telefone'] ?? null,
                    'pj', // Assumir PJ por padrão
                    ($usuario['email'] === 'admin@teste.com' || $usuario['email'] === 'admin@cegonheiro.com') ? 'admin' : 'cliente',
                    $usuario['empresa'] ?? 'Não informado',
                    $usuario['data_cadastro'] ?? date('Y-m-d H:i:s'),
                    1
                ]);
                echo "<p style='color: green;'>✅ Usuário restaurado: {$usuario['nome']} ({$usuario['email']})</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erro ao restaurar {$usuario['email']}: " . $e->getMessage() . "</p>";
            }
        }
        
        // 5. Criar usuários de teste se não existirem
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
            echo "<p style='color: green;'>✅ Admin de teste criado</p>";
        }
        
        // Transportadoras
        $transportadoras = [
            ['Transportadora A', 'transportadoraa@teste.com', 'Transportes A Ltda'],
            ['Transportadora B', 'transportadorab@teste.com', 'Transportes B Ltda']
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
                echo "<p style='color: green;'>✅ {$transp[0]} criada</p>";
            }
        }
        
        echo "<hr>";
        echo "<h2>🎉 Tabela Recriada com Sucesso!</h2>";
        echo "<p><a href='teste_sistema.php'>🔄 Executar Teste Completo</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h2>⚠️ Atenção!</h2>";
    echo "<p>Este script irá <strong>recriar completamente</strong> a tabela usuarios.</p>";
    echo "<p>Os dados existentes serão preservados, mas a estrutura será recriada.</p>";
    echo "<p><strong>Tem certeza que deseja continuar?</strong></p>";
    echo "<p><a href='recriar_usuarios.php?confirmar=sim' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>SIM, RECRIAR TABELA</a></p>";
    echo "<p><a href='verificar_corrigir_usuarios.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>TENTAR CORRIGIR PRIMEIRO</a></p>";
    echo "</div>";
}
?>