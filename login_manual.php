<?php
// login_manual.php - Login manual para testes
require_once 'config.php';

$email_pre = $_GET['email'] ?? '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if ($email && $senha) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
                $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'];
                
                echo "<div style='color: green; padding: 15px; background: #d4edda; border-radius: 5px; margin: 10px 0;'>";
                echo "<h3>✅ Login Realizado com Sucesso!</h3>";
                echo "<p><strong>Nome:</strong> {$usuario['nome']}</p>";
                echo "<p><strong>Email:</strong> {$usuario['email']}</p>";
                echo "<p><strong>Nível:</strong> {$usuario['nivel_acesso']}</p>";
                echo "<p><strong>Tipo:</strong> {$usuario['tipo_cliente']}</p>";
                echo "<p><strong>É Admin:</strong> " . (ehAdmin() ? 'SIM' : 'NÃO') . "</p>";
                echo "<p><strong>É Transportadora:</strong> " . (ehTransportadora() ? 'SIM' : 'NÃO') . "</p>";
                echo "<p><strong>Pode Ver Leads:</strong> " . (podeVerLeads() ? 'SIM' : 'NÃO') . "</p>";
                echo "<hr>";
                echo "<p><a href='leads_disponiveis.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔗 Ir para Leads Disponíveis</a></p>";
                echo "<p><a href='dashboard_cliente.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Ir para Dashboard</a></p>";
                echo "</div>";
                
            } else {
                echo "<div style='color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;'>";
                echo "❌ Email ou senha incorretos!";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div style='color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ Erro: " . $e->getMessage();
            echo "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Manual - Portal Cegonheiro</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔑 Login Manual de Teste</h1>
    
    <form method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_pre); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" value="123456" required>
        </div>
        
        <button type="submit">Entrar</button>
    </form>
    
    <div class="info">
        <h3>ℹ️ Informações de Teste</h3>
        <p><strong>Senha padrão:</strong> 123456</p>
        <p><strong>Emails disponíveis:</strong></p>
        <ul>
            <li>admin@teste.com (Administrador)</li>
            <li>transportadoraa@teste.com (Transportadora)</li>
            <li>transportadorab@teste.com (Transportadora)</li>
            <li>transportadorac@teste.com (Transportadora)</li>
        </ul>
    </div>
    
    <p><a href="teste_login_completo.php">🔄 Voltar para Teste Completo</a></p>
</body>
</html>