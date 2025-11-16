<?php
session_start();

// Habilitar exibição de erros para debug (remover em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_ilead;charset=utf8mb4", 'joaocr74_adm', 'adm@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro: Conexão com banco de dados não estabelecida.');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    
    try {
        // Buscar usuário na tabela usuarios
        $stmt = $pdo->prepare("SELECT u.*, 
                                     CASE 
                                         WHEN u.nivel_acesso = 'cliente' THEN u.tipo_cliente
                                         ELSE u.nivel_acesso
                                     END as tipo_cliente
                              FROM usuarios u 
                              WHERE u.email = ? AND u.status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['user_id'] = $usuario['id']; // Compatibilidade
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
            $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'];
            $_SESSION['status'] = $usuario['status'];
            
            // Redirecionar baseado no nível de acesso
            if ($usuario['nivel_acesso'] == 'administrador' || $usuario['nivel_acesso'] == 'funcionario') {
                header('Location: dashboard_admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $erro = 'Email ou senha inválidos.';
        }
    } catch (PDOException $e) {
        $erro = 'Erro no banco de dados: ' . $e->getMessage();
        error_log('Erro PDO no login: ' . $e->getMessage());
    } catch (Exception $e) {
        $erro = 'Erro no sistema: ' . $e->getMessage();
        error_log('Erro geral no login: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00bc75;
            --secondary-color: #07a368;
            --accent-color: #00d084;
            --dark-color: #1a1a1a;
            --light-gray: #f8f9fa;
            --border-color: #e1e5e9;
            --shadow-light: 0 2px 10px rgba(0, 188, 117, 0.1);
            --shadow-medium: 0 8px 30px rgba(0, 188, 117, 0.15);
            --shadow-heavy: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: white;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .login-container {
            display: flex;
            min-height: 100vh;
        }

        .login-image {
            flex: 1;
            background-image: url('https://i.ibb.co/LX0PcTPQ/img-top-site.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 60px 40px;
        }

        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        }

        .login-image-content {
            position: relative;
            z-index: 2;
            max-width: 500px;
        }

        .logo {
            font-size: 5rem;
            margin-bottom: 30px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .brand-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 15px rgba(0, 0, 0, 0.5);
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 1.3rem;
            font-weight: 400;
            opacity: 0.95;
            margin-bottom: 40px;
            line-height: 1.6;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.3);
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.25);
            padding: 15px 25px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 500;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .security-badge i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .login-form {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 80px 60px;
            max-width: 600px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .form-title {
            color: var(--dark-color);
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .form-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            font-weight: 400;
        }

        .form-floating {
            margin-bottom: 25px;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
            height: 65px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
            background: white;
            transform: translateY(-1px);
        }

        .form-floating > label {
            color: #6c757d;
            font-weight: 500;
            font-size: 1rem;
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background: rgba(0, 188, 117, 0.1);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 12px;
            padding: 20px 32px;
            font-weight: 600;
            font-size: 1.2rem;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 188, 117, 0.2);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 188, 117, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .divider {
            margin: 30px 0;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            background: white;
            padding: 0 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .footer-links {
            text-align: center;
        }

        .footer-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            margin: 8px 16px;
            font-size: 0.95rem;
        }

        .footer-links a:hover {
            color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .footer-links a i {
            margin-right: 6px;
        }

        .btn-outline-custom {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-outline-custom:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Loading Animation */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-image {
                min-height: 40vh;
                flex: none;
            }
            
            .login-form {
                padding: 50px 40px;
                max-width: none;
            }
            
            .brand-title {
                font-size: 2.2rem;
            }
            
            .logo {
                font-size: 3.5rem;
            }
        }

        @media (max-width: 768px) {
            .login-form {
                padding: 40px 30px;
            }
            
            .login-image {
                min-height: 35vh;
                padding: 40px 20px;
            }
            
            .form-title {
                font-size: 2rem;
            }
            
            .brand-title {
                font-size: 1.8rem;
            }
            
            .brand-subtitle {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .login-form {
                padding: 30px 20px;
            }
            
            .login-image {
                min-height: 30vh;
                padding: 30px 15px;
            }
            
            .brand-title {
                font-size: 1.5rem;
            }
            
            .brand-subtitle {
                font-size: 1rem;
            }
            
            .logo {
                font-size: 2.5rem;
            }
            
            .form-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Lado Esquerdo - Imagem -->
        <div class="login-image">
            <div class="login-image-content">
                <div class="logo">
                    <i class="fas fa-truck"></i>
                </div>
                <h1 class="brand-title">Portal Cegonheiro</h1>
                <p class="brand-subtitle">
                    Conectando você às melhores transportadoras do Brasil
                </p>
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    Acesso seguro e protegido
                </div>
            </div>
        </div>
        
        <!-- Lado Direito - Formulário -->
        <div class="login-form">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-sign-in-alt"></i> Bem-vindo de volta
                </h2>
                <p class="form-subtitle">Faça login para acessar sua conta</p>
            </div>
            
            <?php if ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($erro); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           placeholder="Digite seu e-mail" required>
                    <label for="email">
                        <i class="fas fa-envelope me-2"></i>E-mail
                    </label>
                </div>
                
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="senha" name="senha" 
                           placeholder="Digite sua senha" required>
                    <label for="senha">
                        <i class="fas fa-lock me-2"></i>Senha
                    </label>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                
                <button type="submit" class="btn btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Entrar no Sistema
                </button>
            </form>
            
            <div class="divider">
                <span>ou</span>
            </div>
            
            <div class="footer-links">
                <a href="esqueceu_senha.php" class="d-block mb-2">
                    <i class="fas fa-key"></i>
                    Esqueceu sua senha?
                </a>
                <a href="../index.php" class="d-block mb-3">
                    <i class="fas fa-user-plus"></i>
                    Solicitar cotação
                </a>
                <a href="../index.php" class="btn btn-outline-custom">
                    <i class="fas fa-home me-2"></i>
                    Voltar ao Início
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const senhaInput = document.getElementById('senha');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                senhaInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;
            
            if (!email || !senha) {
                e.preventDefault();
                showAlert('Por favor, preencha todos os campos.', 'warning');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showAlert('Por favor, digite um e-mail válido.', 'warning');
                return false;
            }
            
            // Loading state
            loginBtn.classList.add('btn-loading');
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Entrando...';
            loginBtn.disabled = true;
        });

        function showAlert(message, type = 'danger') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const form = document.getElementById('loginForm');
            form.parentNode.insertBefore(alertDiv, form);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });

        // Enter key navigation
        document.getElementById('email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('senha').focus();
            }
        });

        document.getElementById('senha').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>