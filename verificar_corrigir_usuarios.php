<?php
// verificar_corrigir_usuarios.php - Script para verificar e corrigir tabela usuarios
require_once 'config.php';

echo "<h1>🔧 Verificando e Corrigindo Tabela Usuarios</h1>";

try {
    // 1. Verificar estrutura atual da tabela usuarios
    echo "<h2>1. Estrutura Atual da Tabela Usuarios</h2>";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $campos_existentes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($campos_existentes as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Verificar quais colunas estão faltando
    echo "<h2>2. Verificando Colunas Necessárias</h2>";
    
    $colunas_nomes = array_column($campos_existentes, 'Field');
    
    $colunas_necessarias = [
        'tipo_cliente' => "ALTER TABLE usuarios ADD COLUMN tipo_cliente ENUM('pf','pj') NOT NULL DEFAULT 'pf' AFTER senha",
        'nivel_acesso' => "ALTER TABLE usuarios ADD COLUMN nivel_acesso ENUM('cliente','admin') NOT NULL DEFAULT 'cliente' AFTER tipo_cliente",
        'empresa' => "ALTER TABLE usuarios ADD COLUMN empresa VARCHAR(255) DEFAULT NULL AFTER nivel_acesso",
        'cnpj' => "ALTER TABLE usuarios ADD COLUMN cnpj VARCHAR(18) DEFAULT NULL AFTER empresa",
        'endereco' => "ALTER TABLE usuarios ADD COLUMN endereco TEXT DEFAULT NULL AFTER cnpj",
        'cidade' => "ALTER TABLE usuarios ADD COLUMN cidade VARCHAR(255) DEFAULT NULL AFTER endereco",
        'estado' => "ALTER TABLE usuarios ADD COLUMN estado VARCHAR(2) DEFAULT NULL AFTER cidade",
        'cep' => "ALTER TABLE usuarios ADD COLUMN cep VARCHAR(10) DEFAULT NULL AFTER estado",
        'ativo' => "ALTER TABLE usuarios ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER cep"
    ];
    
    foreach ($colunas_necessarias as $coluna => $sql) {
        if (!in_array($coluna, $colunas_nomes)) {
            echo "<p style='color: orange;'>⚠️ Coluna '$coluna' não existe. Adicionando...</p>";
            try {
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ Coluna '$coluna' adicionada com sucesso</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erro ao adicionar coluna '$coluna': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Coluna '$coluna' já existe</p>";
        }
    }
    
    // 3. Verificar estrutura final
    echo "<h2>3. Estrutura Final da Tabela Usuarios</h2>";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $campos_finais = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($campos_finais as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Testar criação de usuário
    echo "<h2>4. Testando Criação de Usuário</h2>";
    
    // Verificar se admin de teste já existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    $admin_existente = $stmt->fetch();
    
    if ($admin_existente) {
        echo "<p style='color: blue;'>ℹ️ Admin de teste já existe</p>";
        echo "<p>Dados do admin:</p>";
        echo "<ul>";
        foreach ($admin_existente as $campo => $valor) {
            echo "<li><strong>$campo:</strong> $valor</li>";
        }
        echo "</ul>";
        
        // Atualizar admin se necessário
        if (!isset($admin_existente['tipo_cliente']) || !isset($admin_existente['nivel_acesso'])) {
            echo "<p style='color: orange;'>⚠️ Atualizando dados do admin...</p>";
            $stmt = $pdo->prepare("UPDATE usuarios SET tipo_cliente = 'pj', nivel_acesso = 'admin', empresa = 'Sistema' WHERE email = ?");
            $stmt->execute(['admin@teste.com']);
            echo "<p style='color: green;'>✅ Admin atualizado</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Criando admin de teste...</p>";
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
    }
    
    // 5. Criar transportadoras de teste
    echo "<h2>5. Criando Transportadoras de Teste</h2>";
    
    $transportadoras = [
        ['Transportadora A', 'transportadoraa@teste.com', 'Transportes A Ltda'],
        ['Transportadora B', 'transportadorab@teste.com', 'Transportes B Ltda'],
        ['Transportadora C', 'transportadorac@teste.com', 'Transportes C Ltda']
    ];
    
    foreach ($transportadoras as $transp) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$transp[1]]);
        if (!$stmt->fetch()) {
            try {
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
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erro ao criar {$transp[0]}: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ {$transp[0]} já existe</p>";
        }
    }
    
    echo "<hr>";
    echo "<h2>🎉 Correção Concluída!</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Contas Disponíveis:</h3>";
    echo "<p><strong>Administrador:</strong><br>";
    echo "Email: admin@teste.com<br>";
    echo "Senha: 123456</p>";
    
    echo "<p><strong>Transportadoras:</strong><br>";
    echo "transportadoraa@teste.com / 123456<br>";
    echo "transportadorab@teste.com / 123456<br>";
    echo "transportadorac@teste.com / 123456</p>";
    echo "</div>";
    
    echo "<p><a href='teste_sistema.php'>🔄 Executar Teste Completo</a></p>";
    echo "<p><a href='login_teste.php'>🔑 Ir para Login de Teste</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>