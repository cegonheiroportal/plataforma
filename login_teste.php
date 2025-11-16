<?php
// login_teste.php - Login simplificado para testes
require_once 'config.php';

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
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'] ?? 'cliente';
                $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'] ?? 'pf';
                
                echo "<div style='color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;'>";
                echo "✅ Login realizado com sucesso!<br>";
                echo "Nome: {$usuario['nome']}<br>";
                echo "Nível: {$_SESSION['nivel_acesso']}<br>";
                echo "Tipo: {$_SESSION['tipo_cliente']}<br>";
                echo "<a href='leads_disponiveis.php'>Ir para Leads Disponíveis</a>";
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
    <title>Login de Teste - Portal Cegonheiro</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .accounts { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>🔑 Login de Teste</h1>
    
    <form method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required>
        </div>
        
        <button type="submit">Entrar</button>
    </form>
    
    <div class="accounts">
        <h3>Contas para Teste:</h3>
        <p><strong>Administrador:</strong><br>
        Email: admin@teste.com<br>
        Senha: 123456</p>
        
        <p><strong>Transportadora:</strong><br>
        Email: transportadora@teste.com<br>
        Senha: 123456</p>
    </div>
    
    <p><a href="teste_sistema.php">🔧 Executar Teste Completo do Sistema</a></p>
</body>
</html>