<?php
// corrigir_problemas.php - Script para corrigir os problemas identificados
require_once 'config.php';

echo "<h1>🔧 Corrigindo Problemas Identificados</h1>";

try {
    // 1. Corrigir campo telefone para permitir NULL
    echo "<h2>1. Corrigindo Campo Telefone</h2>";
    try {
        $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN telefone VARCHAR(20) NULL");
        echo "<p style='color: green;'>✅ Campo telefone agora permite valores NULL</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao modificar campo telefone: " . $e->getMessage() . "</p>";
    }

    // 2. Corrigir nível de acesso do admin
    echo "<h2>2. Corrigindo Nível de Acesso do Admin</h2>";
    
    // Primeiro, verificar quais valores são aceitos no ENUM
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'nivel_acesso'");
    $coluna = $stmt->fetch();
    echo "<p>Valores aceitos para nivel_acesso: " . $coluna['Type'] . "</p>";
    
    // Atualizar admin para administrador
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET nivel_acesso = 'administrador' WHERE email = ?");
        $stmt->execute(['admin@teste.com']);
        echo "<p style='color: green;'>✅ Admin atualizado para nível 'administrador'</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao atualizar admin: " . $e->getMessage() . "</p>";
    }

    // 3. Criar transportadoras com telefone
    echo "<h2>3. Criando Transportadoras com Telefone</h2>";
    
    $transportadoras = [
        ['Transportadora A', '(11) 99999-0001', 'transportadoraa@teste.com', 'Transportes A Ltda'],
        ['Transportadora B', '(11) 99999-0002', 'transportadorab@teste.com', 'Transportes B Ltda'],
        ['Transportadora C', '(11) 99999-0003', 'transportadorac@teste.com', 'Transportes C Ltda']
    ];
    
    foreach ($transportadoras as $transp) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$transp[2]]);
        if (!$stmt->fetch()) {
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, telefone, email, senha, tipo_cliente, nivel_acesso, empresa) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $transp[0],
                    $transp[1],
                    $transp[2],
                    password_hash('123456', PASSWORD_DEFAULT),
                    'pj',
                    'cliente',
                    $transp[3]
                ]);
                echo "<p style='color: green;'>✅ {$transp[0]} criada: {$transp[2]} / 123456</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erro ao criar {$transp[0]}: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ {$transp[0]} já existe</p>";
        }
    }

    // 4. Verificar dados finais
    echo "<h2>4. Verificando Dados Finais</h2>";
    
    $stmt = $pdo->query("SELECT id, nome, email, telefone, tipo_cliente, nivel_acesso, empresa FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Telefone</th><th>Tipo</th><th>Nível</th><th>Empresa</th></tr>";
    foreach ($usuarios as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['nome']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['telefone']}</td>";
        echo "<td>{$user['tipo_cliente']}</td>";
        echo "<td>{$user['nivel_acesso']}</td>";
        echo "<td>{$user['empresa']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<h2>🎉 Problemas Corrigidos!</h2>";
    echo "<p><a href='teste_login_completo.php'>🧪 Executar Teste de Login Completo</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . $e->getMessage() . "</p>";
}
?>