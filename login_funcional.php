<?php
// login_funcional.php - Login que definitivamente funciona
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuração do banco
try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    
    try {
        // Buscar usuário
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Verificar senha
            if (password_verify($senha, $usuario['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['usuario_id'] = $usuario['id']; // Compatibilidade
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
                $_SESSION['status'] = $usuario['status'];
                $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'] ?? 'pj';
                
                $mensagem = "Login realizado com sucesso! Bem-vindo, " . $usuario['nome'];
                $tipo_mensagem = 'sucesso';
                
                // Redirecionar após 2 segundos
              header("refresh:2;url=dashboard_transportadora.php");
                
            } else {
                $mensagem = "Senha incorreta.";
                $tipo_mensagem = 'erro';
            }
        } else {
            $mensagem = "Email não encontrado ou usuário inativo.";
            $tipo_mensagem = 'erro';
        }
        
    } catch (Exception $e) {
        $mensagem = "Erro no sistema: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal Cegonheiro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .mensagem.sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .mensagem.erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .credenciais-teste {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .credenciais-teste h4 {
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .credenciais-teste p {
            margin: 5px 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🚛 Portal Cegonheiro</h1>
            <p>Sistema de Gestão de Transportes</p>
        </div>
        
        <div class="credenciais-teste">
            <h4>🔑 Credenciais de Teste</h4>
            <p><strong>Email:</strong> auto@teste.com.br</p>
            <p><strong>Senha:</strong> auto@2025</p>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
                <?php if ($tipo_mensagem === 'sucesso'): ?>
                    <br><small>Redirecionando...</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">📧 Email</label>
                <input type="email" id="email" name="email" value="auto@teste.com.br" required>
            </div>
            
            <div class="form-group">
                <label for="senha">🔒 Senha</label>
                <input type="password" id="senha" name="senha" value="auto@2025" required>
            </div>
            
            <button type="submit" class="btn-login">
                �� Entrar
            </button>
        </form>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div style="margin-top: 20px; text-align: center;">
                <p style="color: #28a745;">✅ Você já está logado!</p>
                <a href="dashboard_simples.php" style="color: #007bff; text-decoration: none;">
                    🏠 Ir para Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>