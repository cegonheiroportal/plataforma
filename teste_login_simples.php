<?php
// teste_login_simples.php - Teste de login simplificado
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h1>🧪 Teste de Login Simplificado</h1>";

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    try {
        // Conexão direta
        $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<h2>Tentando Login...</h2>";
        echo "<p>Email: $email</p>";
        
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            echo "<p style='color: blue;'>ℹ️ Usuário encontrado</p>";
            echo "<p>Nível: {$usuario['nivel_acesso']}</p>";
            echo "<p>Tipo: {$usuario['tipo_cliente']}</p>";
            
            if (password_verify($senha, $usuario['senha'])) {
                echo "<div style='color: green; padding: 15px; background: #d4edda; border-radius: 5px;'>";
                echo "<h3>✅ LOGIN BEM-SUCEDIDO!</h3>";
                
                // Configurar sessão
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
                $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'];
                
                echo "<p>Sessão configurada com sucesso!</p>";
                
                // Testar funções básicas
                $eh_admin = ($_SESSION['nivel_acesso'] === 'admin' || $_SESSION['nivel_acesso'] === 'administrador');
                $eh_transportadora = ($_SESSION['nivel_acesso'] === 'cliente' && $_SESSION['tipo_cliente'] === 'pj');
                $pode_ver_leads = $eh_admin || $eh_transportadora;
                
                echo "<p><strong>Testes:</strong></p>";
                echo "<ul>";
                echo "<li>É Admin: " . ($eh_admin ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "<li>É Transportadora: " . ($eh_transportadora ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "<li>Pode Ver Leads: " . ($pode_ver_leads ? "✅ SIM" : "❌ NÃO") . "</li>";
                echo "</ul>";
                
                echo "<p><a href='leads_disponiveis.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 Testar Leads Disponíveis</a></p>";
                echo "</div>";
                
            } else {
                echo "<p style='color: red;'>❌ Senha incorreta</p>";
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
<html>
<head>
    <title>Teste Login Simples</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        input { width: 100%; padding: 10px; margin: 5px 0; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h2>🔑 Login de Teste</h2>
    
    <form method="POST">
        <p>Email:</p>
        <input type="email" name="email" value="admin@teste.com" required>
        
        <p>Senha:</p>
        <input type="password" name="senha" value="123456" required>
        
        <p><button type="submit">Testar Login</button></p>
    </form>
    
    <p><a href="corrigir_admin_simples.php">🔧 Corrigir Admin Novamente</a></p>
</body>
</html>