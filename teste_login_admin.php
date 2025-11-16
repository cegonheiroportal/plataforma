<?php
// teste_login_admin.php - Teste específico do login do admin
require_once 'config.php';

echo "<h1>🧪 Teste Específico do Login do Admin</h1>";

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    echo "<h2>Tentando Login...</h2>";
    echo "<p>Email: $email</p>";
    echo "<p>Senha: [oculta]</p>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            echo "<p style='color: blue;'>ℹ️ Usuário encontrado no banco</p>";
            echo "<p>Dados do usuário:</p>";
            echo "<ul>";
            echo "<li>ID: {$usuario['id']}</li>";
            echo "<li>Nome: {$usuario['nome']}</li>";
            echo "<li>Email: {$usuario['email']}</li>";
            echo "<li>Nível: {$usuario['nivel_acesso']}</li>";
            echo "<li>Tipo: {$usuario['tipo_cliente']}</li>";
            echo "</ul>";
            
            if (password_verify($senha, $usuario['senha'])) {
                echo "<div style='color: green; padding: 15px; background: #d4edda; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>✅ LOGIN BEM-SUCEDIDO!</h3>";
                
                // Configurar sessão
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
                $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'];
                
                echo "<p><strong>Sessão configurada:</strong></p>";
                echo "<ul>";
                echo "<li>user_id: {$_SESSION['user_id']}</li>";
                echo "<li>nome: {$_SESSION['nome']}</li>";
                echo "<li>nivel_acesso: {$_SESSION['nivel_acesso']}</li>";
                echo "<li>tipo_cliente: {$_SESSION['tipo_cliente']}</li>";
                echo "</ul>";
                
                echo "<p><strong>Testes de função:</strong></p>";
                echo "<ul>";
                echo "<li>verificarLogin(): " . (verificarLogin() ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "<li>ehAdmin(): " . (ehAdmin() ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "<li>ehTransportadora(): " . (ehTransportadora() ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "<li>podeVerLeads(): " . (podeVerLeads() ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "</ul>";
                
                echo "<hr>";
                echo "<p><a href='leads_disponiveis.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 Testar Leads Disponíveis</a></p>";
                echo "</div>";
                
            } else {
                echo "<p style='color: red;'>❌ Senha incorreta</p>";
                echo "<p>Verificando hash da senha...</p>";
                echo "<p>Hash no banco: " . substr($usuario['senha'], 0, 20) . "...</p>";
                echo "<p>Teste com '123456': " . (password_verify('123456', $usuario['senha']) ? "✅" : "❌") . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Usuário não encontrado</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Login Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h2>🔑 Teste de Login do Administrador</h2>
    
    <form method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="admin@teste.com" required>
        </div>
        
        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" value="123456" required>
        </div>
        
        <button type="submit">Testar Login</button>
    </form>
    
    <p><a href="corrigir_admin.php">🔧 Corrigir Admin Novamente</a></p>
</body>
</html>