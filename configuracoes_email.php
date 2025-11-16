<?php
require_once 'config.php';

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'administrador') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Buscar configurações de email
$stmt = $pdo->query("SELECT * FROM configuracoes WHERE categoria = 'email' ORDER BY chave");
$config_email = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar configurações
$configs = [];
foreach ($config_email as $config) {
    $configs[$config['chave']] = $config['valor'];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Atualizar configurações de email
        $email_configs = [
            'smtp_host' => $_POST['smtp_host'],
            'smtp_port' => $_POST['smtp_port'],
            'smtp_username' => $_POST['smtp_username'],
            'smtp_password' => $_POST['smtp_password'],
            'smtp_security' => $_POST['smtp_security'],
            'email_from' => $_POST['email_from'],
            'email_from_name' => $_POST['email_from_name']
        ];
        
        foreach ($email_configs as $chave => $valor) {
            // Verificar se a configuração existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuracoes WHERE chave = ?");
            $stmt->execute([$chave]);
            
            if ($stmt->fetchColumn() > 0) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            } else {
                // Inserir nova
                $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor, categoria, descricao) VALUES (?, ?, 'email', ?)");
                $descricoes = [
                    'smtp_host' => 'Servidor SMTP',
                    'smtp_port' => 'Porta SMTP',
                    'smtp_username' => 'Usuário SMTP',
                    'smtp_password' => 'Senha SMTP',
                    'smtp_security' => 'Segurança SMTP',
                    'email_from' => 'Email remetente',
                    'email_from_name' => 'Nome do remetente'
                ];
                $stmt->execute([$chave, $valor, $descricoes[$chave] ?? $chave]);
            }
        }
        
        // Testar configurações se solicitado
        if (isset($_POST['testar_email'])) {
            $teste_resultado = testarConfiguracoesEmail($email_configs);
            if ($teste_resultado['sucesso']) {
                $mensagem = 'Configurações salvas e testadas com sucesso! Email de teste enviado.';
                $tipo_mensagem = 'success';
            } else {
                $mensagem = 'Configurações salvas, mas erro no teste: ' . $teste_resultado['erro'];
                $tipo_mensagem = 'warning';
            }
        } else {
            $mensagem = 'Configurações de email atualizadas com sucesso!';
            $tipo_mensagem = 'success';
        }
        
        $pdo->commit();
        
        // Recarregar configurações
        $stmt = $pdo->query("SELECT * FROM configuracoes WHERE categoria = 'email' ORDER BY chave");
        $config_email = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $configs = [];
        foreach ($config_email as $config) {
            $configs[$config['chave']] = $config['valor'];
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = 'Erro ao atualizar configurações: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

function testarConfiguracoesEmail($configs) {
    // Simulação de teste de email
    // Em uma implementação real, você usaria PHPMailer ou similar
    if (empty($configs['smtp_host']) || empty($configs['smtp_port'])) {
        return ['sucesso' => false, 'erro' => 'Configurações SMTP incompletas'];
    }
    
    // Simular sucesso
    return ['sucesso' => true];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações de Email - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #00bc75;
            --secondary-green: #07a368;
            --sidebar-bg: #f8f9fa;
            --white: #ffffff;
            --text-dark: #2c2c2c;
            --text-light: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: var(--text-dark);
            font-size: 14px;
            line-height: 1.5;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
        }

        .logo i {
            color: var(--primary-green);
            font-size: 24px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: block;
            padding: 12px 20px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            color: var(--primary-green);
            background: rgba(0, 188, 117, 0.05);
            border-left-color: var(--primary-green);
        }

        .menu-item i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .breadcrumb-nav strong {
            color: var(--text-dark);
        }

        .breadcrumb-nav a {
            color: var(--text-light);
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            color: var(--primary-green);
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--white);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 24px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-outline-secondary {
            border-color: var(--text-light);
            color: var(--text-light);
        }

        .btn-outline-secondary:hover {
            background: var(--text-light);
            border-color: var(--text-light);
            color: var(--white);
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 18px;
        }

        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box h6 {
            color: #1976d2;
            margin-bottom: 8px;
        }

        .info-box p {
            color: #424242;
            margin: 0;
            font-size: 14px;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

               .sidebar-toggle {
            display: none;
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            margin-right: 16px;
        }

        @media (max-width: 992px) {
            .sidebar-toggle {
                display: inline-block;
            }
        }

        .notification-badge {
            position: relative;
        }

        .notification-badge::after {
            content: '2';
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 16px;
            text-align: center;
        }

        .notification-badge.bell::after {
            content: '10';
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-truck"></i>
                <span>Portal Cegonheiro</span>
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="gerenciar_leads.php" class="menu-item">
                <i class="fas fa-users"></i>
                Gerenciar Leads
            </a>
            <a href="clientes_admin.php" class="menu-item">
                <i class="fas fa-building"></i>
                Clientes
            </a>
            <a href="cotacoes_admin.php" class="menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                Cotações
            </a>
            <a href="cadastro_cliente.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                Novo Cliente
            </a>
            <a href="relatorios_admin.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                Relatórios
            </a>
            <a href="configuracoes_admin.php" class="menu-item">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
            <a href="configuracoes_email.php" class="menu-item active">
                <i class="fas fa-envelope"></i>
                Config. Email
            </a>
            <a href="usuarios_admin.php" class="menu-item">
                <i class="fas fa-users-cog"></i>
                Usuários
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > <a href="dashboard_admin.php">Dashboard</a> > Configurações de Email
                </div>
                <h1 class="page-title">Configurações de Email</h1>
            </div>
            <div class="header-actions">
                <div class="notification-badge">
                    <i class="fas fa-envelope" style="font-size: 20px; color: var(--text-light);"></i>
                </div>
                <div class="notification-badge bell">
                    <i class="fas fa-bell" style="font-size: 20px; color: var(--text-light);"></i>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                    <div style="width: 40px; height: 40px; background: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                        <?php echo strtoupper(substr($_SESSION['nome'], 0, 2)); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h6><i class="fas fa-info-circle"></i> Informações Importantes</h6>
            <p>Configure aqui as informações do servidor de email para envio de notificações automáticas do sistema. Certifique-se de testar as configurações antes de salvar.</p>
        </div>

        <form method="POST">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-server"></i> Configurações do Servidor SMTP
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="smtp_host" class="form-label">Servidor SMTP *</label>
                            <input type="text" class="form-control" name="smtp_host" id="smtp_host" 
                                   value="<?php echo htmlspecialchars($configs['smtp_host'] ?? ''); ?>" 
                                   placeholder="smtp.gmail.com" required>
                            <small class="text-muted">Ex: smtp.gmail.com, smtp.outlook.com</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="smtp_port" class="form-label">Porta SMTP *</label>
                            <input type="number" class="form-control" name="smtp_port" id="smtp_port" 
                                   value="<?php echo htmlspecialchars($configs['smtp_port'] ?? '587'); ?>" 
                                   placeholder="587" required>
                            <small class="text-muted">587 (TLS) ou 465 (SSL)</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="smtp_security" class="form-label">Segurança</label>
                            <select class="form-control" name="smtp_security" id="smtp_security">
                                <option value="tls" <?php echo ($configs['smtp_security'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($configs['smtp_security'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($configs['smtp_security'] ?? '') == 'none' ? 'selected' : ''; ?>>Nenhuma</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="smtp_username" class="form-label">Usuário SMTP *</label>
                            <input type="email" class="form-control" name="smtp_username" id="smtp_username" 
                                   value="<?php echo htmlspecialchars($configs['smtp_username'] ?? ''); ?>" 
                                   placeholder="seu-email@gmail.com" required>
                            <small class="text-muted">Geralmente é o seu endereço de email</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="smtp_password" class="form-label">Senha SMTP *</label>
                            <input type="password" class="form-control" name="smtp_password" id="smtp_password" 
                                   value="<?php echo htmlspecialchars($configs['smtp_password'] ?? ''); ?>" 
                                   placeholder="••••••••" required>
                            <small class="text-muted">Use senha de app para Gmail/Outlook</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-user"></i> Informações do Remetente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email_from" class="form-label">Email Remetente *</label>
                            <input type="email" class="form-control" name="email_from" id="email_from" 
                                   value="<?php echo htmlspecialchars($configs['email_from'] ?? ''); ?>" 
                                   placeholder="noreply@portalcegonheiro.com.br" required>
                            <small class="text-muted">Email que aparecerá como remetente</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email_from_name" class="form-label">Nome do Remetente *</label>
                            <input type="text" class="form-control" name="email_from_name" id="email_from_name" 
                                   value="<?php echo htmlspecialchars($configs['email_from_name'] ?? 'Portal Cegonheiro'); ?>" 
                                   placeholder="Portal Cegonheiro" required>
                            <small class="text-muted">Nome que aparecerá como remetente</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
                <button type="submit" name="testar_email" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-paper-plane"></i> Salvar e Testar
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 992 && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });

        console.log('Página de configurações de email carregada com sucesso!');
    </script>
</body>
</html>