<?php
require_once 'config.php';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $mensagem = 'Por favor, informe seu email.';
        $tipo_mensagem = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Email inválido.';
        $tipo_mensagem = 'danger';
    } else {
        try {
            // Criar tabela se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    used_at TIMESTAMP NULL,
                    INDEX idx_token (token),
                    INDEX idx_user_email (user_id, email),
                    INDEX idx_expires (expires_at)
                )
            ");
            
            // Verificar se o email existe
            $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Gerar token único
                $token = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token válido por 1 hora
                
                // Salvar token no banco
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, email, token, expires_at, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    token = VALUES(token), 
                    expires_at = VALUES(expires_at), 
                    created_at = NOW()
                ");
                $stmt->execute([$usuario['id'], $email, $token, $expira]);
                
                // Enviar email de recuperação usando a classe EmailNotificacao
                $sucesso = enviarEmailRecuperacaoComClasse($usuario, $token);
                
                if ($sucesso) {
                    $mensagem = 'Email de recuperação enviado! Verifique sua caixa de entrada e spam.';
                    $tipo_mensagem = 'success';
                } else {
                    $mensagem = 'Erro ao enviar email. Tente novamente em alguns minutos.';
                    $tipo_mensagem = 'danger';
                }
            } else {
                // Por segurança, sempre mostrar sucesso mesmo se email não existir
                $mensagem = 'Se o email estiver cadastrado, você receberá as instruções de recuperação.';
                $tipo_mensagem = 'info';
            }
            
        } catch (Exception $e) {
            error_log('Erro na recuperação de senha: ' . $e->getMessage());
            $mensagem = 'Erro interno. Tente novamente mais tarde.';
            $tipo_mensagem = 'danger';
        }
    }
}

/**
 * Função para enviar email de recuperação usando EmailNotificacao
 */
function enviarEmailRecuperacaoComClasse($usuario, $token) {
    global $pdo;
    
    try {
        // Verificar se a classe existe
        if (!file_exists('notificacao_email.php')) {
            error_log('Arquivo notificacao_email.php não encontrado');
            return false;
        }
        
        require_once 'notificacao_email.php';
        
        if (!class_exists('EmailNotificacao')) {
            error_log('Classe EmailNotificacao não encontrada');
            return false;
        }
        
        $emailNotificacao = new EmailNotificacao($pdo);
        
        $link_recuperacao = "https://portalcegonheiro.com.br/app/redefinir_senha.php?token=" . $token;
        $nome = explode(' ', $usuario['nome'])[0];
        
        $assunto = "🔐 Recuperação de Senha - Portal Cegonheiro";
        
        $corpo = criarTemplateRecuperacao($nome, $link_recuperacao);
        
        // Usar o método público da classe
        return $emailNotificacao->enviarEmail($usuario['email'], $usuario['nome'], $assunto, $corpo);
        
    } catch (Exception $e) {
        error_log('Erro ao enviar email de recuperação: ' . $e->getMessage());
        return false;
    }
}

/**
 * Template do email de recuperação
 */
function criarTemplateRecuperacao($nome, $link_recuperacao) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Recuperação de Senha</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px;'>
            <h1 style='margin: 0; font-size: 28px;'>🔐 Portal Cegonheiro</h1>
            <p style='margin: 10px 0 0 0; font-size: 16px;'>Recuperação de Senha</p>
        </div>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h2 style='color: #dc3545; margin-top: 0;'>Olá, {$nome}! 👋</h2>
            <p>Recebemos uma solicitação para <strong>redefinir sua senha</strong> no Portal Cegonheiro.</p>
        </div>
        
        <div style='background: white; border: 2px solid #dc3545; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
            <h3 style='color: #dc3545; margin-top: 0;'>🔑 Redefinir Senha</h3>
            <p>Clique no botão abaixo para criar uma nova senha:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$link_recuperacao}' 
                   style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block;'>
                    🔐 Redefinir Minha Senha
                </a>
            </div>
            
            <p style='font-size: 14px; color: #6c757d; margin-top: 20px;'>
                <strong>⏰ Este link expira em 1 hora</strong><br>
                Se você não solicitou esta recuperação, ignore este email.
            </p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;'>
                <p style='margin: 0; font-size: 12px; color: #6c757d;'>
                    <strong>Link alternativo:</strong><br>
                    Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                    <span style='word-break: break-all;'>{$link_recuperacao}</span>
                </p>
            </div>
        </div>
        
        <div style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;'>
            <p style='margin: 0; color: #856404;'>
                <strong>🛡️ Dica de Segurança:</strong> Nunca compartilhe sua senha com outras pessoas. 
                O Portal Cegonheiro nunca solicitará sua senha por email.
            </p>
        </div>
        
        <div style='border-top: 2px solid #e9ecef; padding-top: 20px; margin-top: 30px; text-align: center; color: #6c757d; font-size: 14px;'>
            <p><strong>Portal Cegonheiro</strong><br>
            Sistema de Transporte de Veículos</p>
            
            <p style='margin-top: 15px;'>
                <a href='https://portalcegonheiro.com.br/app/login.php' style='color: #6c757d; text-decoration: none;'>
                    🏠 Voltar ao Login
                </a> | 
                <a href='https://portalcegonheiro.com.br/app/contato.php' style='color: #6c757d; text-decoration: none;'>
                    📞 Suporte
                </a>
            </p>
            
            <p style='margin-top: 15px; font-size: 12px;'>
                Este email foi enviado automaticamente em " . date('d/m/Y H:i:s') . "
            </p>
        </div>
    </body>
    </html>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueceu sua Senha? - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }
        
        .back-link {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #dc3545;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-key fa-3x mb-3"></i>
                <h2 class="mb-0">Esqueceu sua Senha?</h2>
                <p class="mb-0 mt-2 opacity-75">Não se preocupe, vamos ajudar você!</p>
            </div>
            
            <div class="login-body">
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?> mb-4">
                        <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : ($tipo_mensagem == 'info' ? 'info-circle' : 'exclamation-triangle'); ?> me-2"></i>
                        <?php echo $mensagem; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($tipo_mensagem != 'success'): ?>
                <form method="POST">
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email Cadastrado
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="Digite seu email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                                <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Digite o email usado no seu cadastro
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-4">
                        <i class="fas fa-paper-plane me-2"></i>Enviar Link de Recuperação
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="text-center">
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i>Voltar ao Login
                    </a>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <h6 class="text-muted mb-3">Como funciona?</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <i class="fas fa-envelope fa-2x text-primary mb-2"></i>
                            <small class="d-block text-muted">1. Digite seu email</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-link fa-2x text-warning mb-2"></i>
                            <small class="d-block text-muted">2. Receba o link</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-key fa-2x text-success mb-2"></i>
                            <small class="d-block text-muted">3. Nova senha</small>
                        </div>
                    </div>
                </div>
                
                <!-- Debug Info (remover em produção) -->
                <?php if (isset($_POST['email'])): ?>
                <div class="debug-info">
                    <strong>🔧 Debug Info:</strong><br>
                    <small>
                        Email informado: <?php echo htmlspecialchars($_POST['email']); ?><br>
                        Classe EmailNotificacao: <?php echo file_exists('notificacao_email.php') ? '✅ Existe' : '❌ Não existe'; ?><br>
                        Função mail(): <?php echo function_exists('mail') ? '✅ Disponível' : '❌ Não disponível'; ?><br>
                        Timestamp: <?php echo date('d/m/Y H:i:s'); ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus no campo email
        document.getElementById('email')?.focus();
        
        // Remover mensagens após 15 segundos
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 15000);
        
        // Log para debug
        console.log('🔐 Página de recuperação de senha carregada');
        console.log('📧 SMTP configurado: cegonheiroportal@gmail.com');
        console.log('🕐 Timestamp:', new Date().toLocaleString('pt-BR'));
    </script>
</body>
</html>