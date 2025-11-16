<?php
// corrigir_admin_simples.php - Versão simplificada para corrigir admin
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Corrigindo Administrador - Versão Simples</h1>";

try {
    // Conexão direta com o banco
    $host = 'localhost';
    $dbname = 'joaocr74_cegonha';
    $username = 'joaocr74_lima';
    $password = 'davi@2025';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Conexão com banco estabelecida</p>";
    
    // 1. Verificar se admin existe
    echo "<h2>1. Verificando Admin</h2>";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p>Admin encontrado:</p>";
        echo "<ul>";
        echo "<li>ID: {$admin['id']}</li>";
        echo "<li>Nome: {$admin['nome']}</li>";
        echo "<li>Email: {$admin['email']}</li>";
        echo "<li>Nível atual: {$admin['nivel_acesso']}</li>";
        echo "<li>Tipo atual: {$admin['tipo_cliente']}</li>";
        echo "</ul>";
        
        // 2. Atualizar senha
        echo "<h2>2. Atualizando Senha</h2>";
        $nova_senha = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $stmt->execute([$nova_senha, 'admin@teste.com']);
        echo "<p style='color: green;'>✅ Senha atualizada para '123456'</p>";
        
        // 3. Atualizar nível de acesso
        echo "<h2>3. Atualizando Nível de Acesso</h2>";
        $stmt = $pdo->prepare("UPDATE usuarios SET nivel_acesso = 'administrador' WHERE email = ?");
        $stmt->execute(['admin@teste.com']);
        echo "<p style='color: green;'>✅ Nível atualizado para 'administrador'</p>";
        
        // 4. Atualizar tipo de cliente
        echo "<h2>4. Atualizando Tipo de Cliente</h2>";
        $stmt = $pdo->prepare("UPDATE usuarios SET tipo_cliente = 'pj' WHERE email = ?");
        $stmt->execute(['admin@teste.com']);
        echo "<p style='color: green;'>✅ Tipo atualizado para 'pj'</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Admin não encontrado. Criando...</p>";
        
        // Criar admin
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
    
    // 5. Verificar dados finais
    echo "<h2>5. Dados Finais</h2>";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['admin@teste.com']);
    $admin_final = $stmt->fetch();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    foreach ($admin_final as $campo => $valor) {
        echo "<tr><td>$campo</td><td>$valor</td></tr>";
    }
    echo "</table>";
    
    // 6. Testar senha
    echo "<h2>6. Testando Senha</h2>";
    if (password_verify('123456', $admin_final['senha'])) {
        echo "<p style='color: green;'>✅ Senha '123456' funciona corretamente</p>";
    } else {
        echo "<p style='color: red;'>❌ Problema com a senha</p>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 Admin Corrigido!</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Dados para Login:</h3>";
    echo "<p><strong>Email:</strong> admin@teste.com</p>";
    echo "<p><strong>Senha:</strong> 123456</p>";
    echo "<p><strong>Nível:</strong> administrador</p>";
    echo "<p><strong>Tipo:</strong> pj</p>";
    echo "</div>";
    
    echo "<p><a href='teste_login_simples.php'>🧪 Testar Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p>Linha: " . $e->getLine() . "</p>";
    echo "<p>Arquivo: " . $e->getFile() . "</p>";
}
?>