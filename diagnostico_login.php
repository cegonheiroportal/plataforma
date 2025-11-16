<?php
// diagnostico_login.php - Diagnosticar problema de login
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔍 Diagnóstico de Login - Auto Transporte</h1>";
    
    // 1. Verificar se o usuário existe
    echo "<h2>1. Verificando se o usuário existe</h2>";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['auto@teste.com.br']);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h4 style='color: #155724;'>✅ Usuário encontrado</h4>";
        echo "<p><strong>ID:</strong> {$usuario['id']}</p>";
        echo "<p><strong>Nome:</strong> {$usuario['nome']}</p>";
        echo "<p><strong>Email:</strong> {$usuario['email']}</p>";
        echo "<p><strong>Status:</strong> {$usuario['status']}</p>";
        echo "<p><strong>Nível:</strong> {$usuario['nivel_acesso']}</p>";
        echo "<p><strong>Hash da senha:</strong> " . substr($usuario['senha'], 0, 60) . "...</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h4 style='color: #721c24;'>❌ Usuário NÃO encontrado</h4>";
        echo "<p>O email 'auto@teste.com.br' não existe na tabela usuarios.</p>";
        echo "</div>";
        
        // Criar o usuário se não existir
        echo "<h3>Criando o usuário...</h3>";
        $senha_hash = password_hash('auto@2025', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel_acesso, status, tipo_cliente, telefone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Auto Transporte',
            'auto@teste.com.br',
            $senha_hash,
            'cliente',
            'ativo',
            'pj',
            '(85) 99999-1000'
        ]);
        
        $usuario_id = $pdo->lastInsertId();
        echo "<p>✅ Usuário criado com ID: $usuario_id</p>";
        
        // Buscar novamente
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute(['auto@teste.com.br']);
        $usuario = $stmt->fetch();
    }
    
    // 2. Testar verificação de senha
    echo "<h2>2. Testando verificação de senha</h2>";
    
    $senhas_teste = ['auto@2025', 'Auto@2025', 'AUTO@2025', '123456'];
    
    foreach ($senhas_teste as $senha_teste) {
        $resultado = password_verify($senha_teste, $usuario['senha']);
        $status = $resultado ? '✅ VÁLIDA' : '❌ INVÁLIDA';
        $cor = $resultado ? 'green' : 'red';
        
        echo "<p style='color: $cor;'><strong>Senha '$senha_teste':</strong> $status</p>";
        
        if ($resultado) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 6px; margin: 5px 0;'>";
            echo "<strong>🎉 SENHA CORRETA ENCONTRADA: $senha_teste</strong>";
            echo "</div>";
            break;
        }
    }
    
    // 3. Gerar nova senha se necessário
    echo "<h2>3. Redefinir senha para 'auto@2025'</h2>";
    
    $nova_senha = 'auto@2025';
    $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
    $stmt->execute([$novo_hash, 'auto@teste.com.br']);
    
    echo "<p>✅ Senha redefinida para: <strong>$nova_senha</strong></p>";
    echo "<p>Novo hash: " . substr($novo_hash, 0, 60) . "...</p>";
    
    // 4. Testar nova senha
    echo "<h2>4. Testando nova senha</h2>";
    
    $verificacao = password_verify($nova_senha, $novo_hash);
    if ($verificacao) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h4 style='color: #155724;'>✅ Nova senha funcionando!</h4>";
        echo "<p><strong>Email:</strong> auto@teste.com.br</p>";
        echo "<p><strong>Senha:</strong> auto@2025</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h4 style='color: #721c24;'>❌ Problema com a nova senha</h4>";
        echo "</div>";
    }
    
    // 5. Listar todos os usuários
    echo "<h2>5. Todos os usuários na tabela</h2>";
    
    $stmt = $pdo->query("SELECT id, nome, email, nivel_acesso, status FROM usuarios ORDER BY id");
    $todos_usuarios = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Nome</th><th>Email</th><th>Nível</th><th>Status</th></tr>";
    foreach ($todos_usuarios as $u) {
        $cor_linha = ($u['email'] === 'auto@teste.com.br') ? 'background: #fff3cd;' : '';
        echo "<tr style='$cor_linha'>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['nome']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['nivel_acesso']}</td>";
        echo "<td>{$u['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Login</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        table { width: 100%; }
        th, td { padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <h2>🔑 Teste de Login Direto</h2>
    
    <form method="POST" action="teste_login_corrigido.php">
        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <h4>Credenciais Corretas:</h4>
            <p><strong>Email:</strong> auto@teste.com.br</p>
            <p><strong>Senha:</strong> auto@2025</p>
        </div>
        
        <div style="margin: 10px 0;">
            <label>Email:</label><br>
            <input type="email" name="email" value="auto@teste.com.br" style="width: 300px; padding: 8px;" required>
        </div>
        
        <div style="margin: 10px 0;">
            <label>Senha:</label><br>
            <input type="password" name="senha" value="auto@2025" style="width: 300px; padding: 8px;" required>
        </div>
        
        <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
            🔑 Testar Login
        </button>
    </form>
</body>
</html>