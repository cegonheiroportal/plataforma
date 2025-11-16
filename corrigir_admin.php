<?php
// corrigir_admin.php - Script para corrigir o problema do admin
require_once 'config.php';

echo "<h1>🔧 Corrigindo Problema do Administrador</h1>";

try {
    // 1. Verificar o admin atual
    echo "<h2>1. Verificando Admin Atual</h2>";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: blue;'>ℹ️ Admin encontrado:</p>";
        echo "<ul>";
        echo "<li>ID: {$admin['id']}</li>";
        echo "<li>Nome: {$admin['nome']}</li>";
        echo "<li>Email: {$admin['email']}</li>";
        echo "<li>Nível: {$admin['nivel_acesso']}</li>";
        echo "<li>Tipo: {$admin['tipo_cliente']}</li>";
        echo "</ul>";
        
        // Verificar a senha
        echo "<h2>2. Verificando Senha</h2>";
        if (password_verify('123456', $admin['senha'])) {
            echo "<p style='color: green;'>✅ Senha '123456' está correta</p>";
        } else {
            echo "<p style='color: red;'>❌ Senha '123456' está incorreta</p>";
            echo "<p>Atualizando senha...</p>";
            
            $nova_senha = password_hash('123456', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
            $stmt->execute([$nova_senha, 'admin@teste.com']);
            echo "<p style='color: green;'>✅ Senha atualizada para '123456'</p>";
        }
        
        // Verificar e corrigir nível de acesso
        echo "<h2>3. Corrigindo Nível de Acesso</h2>";
        if ($admin['nivel_acesso'] !== 'administrador') {
            echo "<p style='color: orange;'>⚠️ Nível atual: {$admin['nivel_acesso']}</p>";
            echo "<p>Atualizando para 'administrador'...</p>";
            
            $stmt = $pdo->prepare("UPDATE usuarios SET nivel_acesso = 'administrador' WHERE email = ?");
            $stmt->execute(['admin@teste.com']);
            echo "<p style='color: green;'>✅ Nível atualizado para 'administrador'</p>";
        } else {
            echo "<p style='color: green;'>✅ Nível já está correto: administrador</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Admin não encontrado. Criando...</p>";
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, telefone, tipo_cliente, nivel_acesso, empresa) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Admin Sistema',
            'admin@teste.com',
            password_hash('123456', PASSWORD_DEFAULT),
            '(85) 99999-9999',
            'pj',
            'administrador',
            'Portal Cegonheiro'
        ]);
        echo "<p style='color: green;'>✅ Admin criado com sucesso</p>";
    }
    
    // 4. Testar login do admin
    echo "<h2>4. Testando Login do Admin</h2>";
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    $admin_teste = $stmt->fetch();
    
    if ($admin_teste && password_verify('123456', $admin_teste['senha'])) {
        echo "<p style='color: green;'>✅ Login do admin funcionando</p>";
        
        // Simular sessão
        $_SESSION['user_id'] = $admin_teste['id'];
        $_SESSION['usuario_id'] = $admin_teste['id'];
        $_SESSION['nome'] = $admin_teste['nome'];
        $_SESSION['email'] = $admin_teste['email'];
        $_SESSION['nivel_acesso'] = $admin_teste['nivel_acesso'];
        $_SESSION['tipo_cliente'] = $admin_teste['tipo_cliente'];
        
        echo "<p>Testando funções:</p>";
        echo "<ul>";
        echo "<li>ehAdmin(): " . (ehAdmin() ? "✅ SIM" : "❌ NÃO") . "</li>";
        echo "<li>ehTransportadora(): " . (ehTransportadora() ? "✅ SIM" : "❌ NÃO") . "</li>";
        echo "<li>podeVerLeads(): " . (podeVerLeads() ? "✅ SIM" : "❌ NÃO") . "</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ Login do admin ainda não funciona</p>";
    }
    
    // 5. Mostrar dados finais
    echo "<h2>5. Dados Finais do Admin</h2>";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    $admin_final = $stmt->fetch();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    foreach ($admin_final as $campo => $valor) {
        echo "<tr><td>$campo</td><td>$valor</td></tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h2>🎉 Admin Corrigido!</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Dados para Login:</h3>";
    echo "<p><strong>Email:</strong> admin@teste.com</p>";
    echo "<p><strong>Senha:</strong> 123456</p>";
    echo "<p><strong>Nível:</strong> administrador</p>";
    echo "</div>";
    
    echo "<p><a href='teste_login_admin.php'>🧪 Testar Login do Admin</a></p>";
    echo "<p><a href='leads_disponiveis.php'>📋 Ir para Leads Disponíveis</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>