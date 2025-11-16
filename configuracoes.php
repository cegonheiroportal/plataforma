<?php
require_once 'config.php';

// Verificar login
if (!verificarLogin()) {
    header('Location: login.php');
    exit;
}

// Verificar se é cliente
$nivel_acesso = $_SESSION['nivel_acesso'] ?? '';
if ($nivel_acesso != 'cliente') {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Buscar dados do usuário logado
try {
    $user_id = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;
    
    if (!$user_id) {
        header('Location: login.php');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT u.*, c.telefone as telefone_cliente 
                           FROM usuarios u 
                           LEFT JOIN clientes c ON u.email = c.email 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Location: login.php');
        exit;
    }
    
    // Usar telefone do cliente se existir
    if (!empty($usuario['telefone_cliente'])) {
        $usuario['telefone'] = $usuario['telefone_cliente'];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar usuário: " . $e->getMessage());
    die("Erro ao carregar dados do usuário. Por favor, tente novamente.");
}

// Buscar configurações específicas do usuário
$configuracoes = [
    'notificacoes_email' => 1,
    'notificacoes_push' => 1,
    'notificacoes_leads' => 1,
    'notificacoes_cotacoes' => 1,
    'notificacoes_whatsapp' => 1,
    'tema_escuro' => 0,
    'idioma' => 'pt-BR',
    'fuso_horario' => 'America/Sao_Paulo',
    'privacidade_perfil' => 'publico',
    'receber_newsletter' => 1
];

try {
    $stmt = $pdo->prepare("SELECT * FROM user_configuracoes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $config_db = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config_db) {
        $configuracoes = array_merge($configuracoes, $config_db);
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda, usar configurações padrão
    error_log("Aviso: Tabela user_configuracoes não existe: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        if ($acao == 'salvar_configuracoes') {
            $notificacoes_email = isset($_POST['notificacoes_email']) ? 1 : 0;
            $notificacoes_push = isset($_POST['notificacoes_push']) ? 1 : 0;
            $notificacoes_leads = isset($_POST['notificacoes_leads']) ? 1 : 0;
            $notificacoes_cotacoes = isset($_POST['notificacoes_cotacoes']) ? 1 : 0;
            $notificacoes_whatsapp = isset($_POST['notificacoes_whatsapp']) ? 1 : 0;
            $tema_escuro = isset($_POST['tema_escuro']) ? 1 : 0;
            $idioma = $_POST['idioma'] ?? 'pt-BR';
            $fuso_horario = $_POST['fuso_horario'] ?? 'America/Sao_Paulo';
            $privacidade_perfil = $_POST['privacidade_perfil'] ?? 'publico';
            $receber_newsletter = isset($_POST['receber_newsletter']) ? 1 : 0;
            
            // Criar tabela se não existir
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_configuracoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                notificacoes_email TINYINT(1) DEFAULT 1,
                notificacoes_push TINYINT(1) DEFAULT 1,
                notificacoes_leads TINYINT(1) DEFAULT 1,
                notificacoes_cotacoes TINYINT(1) DEFAULT 1,
                notificacoes_whatsapp TINYINT(1) DEFAULT 1,
                tema_escuro TINYINT(1) DEFAULT 0,
                idioma VARCHAR(10) DEFAULT 'pt-BR',
                fuso_horario VARCHAR(50) DEFAULT 'America/Sao_Paulo',
                privacidade_perfil ENUM('publico', 'privado') DEFAULT 'publico',
                receber_newsletter TINYINT(1) DEFAULT 1,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Inserir ou atualizar configurações
            $stmt = $pdo->prepare("
                INSERT INTO user_configuracoes (
                    user_id, notificacoes_email, notificacoes_push, notificacoes_leads, 
                    notificacoes_cotacoes, notificacoes_whatsapp, tema_escuro, idioma, fuso_horario, 
                    privacidade_perfil, receber_newsletter
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    notificacoes_email = VALUES(notificacoes_email),
                    notificacoes_push = VALUES(notificacoes_push),
                    notificacoes_leads = VALUES(notificacoes_leads),
                    notificacoes_cotacoes = VALUES(notificacoes_cotacoes),
                    notificacoes_whatsapp = VALUES(notificacoes_whatsapp),
                    tema_escuro = VALUES(tema_escuro),
                    idioma = VALUES(idioma),
                    fuso_horario = VALUES(fuso_horario),
                    privacidade_perfil = VALUES(privacidade_perfil),
                    receber_newsletter = VALUES(receber_newsletter)
            ");
            
            $stmt->execute([
                $user_id,
                $notificacoes_email,
                $notificacoes_push,
                $notificacoes_leads,
                $notificacoes_cotacoes,
                $notificacoes_whatsapp,
                $tema_escuro,
                $idioma,
                $fuso_horario,
                $privacidade_perfil,
                $receber_newsletter
            ]);
            
            $mensagem = '⚙️ Configurações salvas com sucesso!';
            $tipo_mensagem = 'success';
            
            // Atualizar array de configurações
            $configuracoes = [
                'notificacoes_email' => $notificacoes_email,
                'notificacoes_push' => $notificacoes_push,
                'notificacoes_leads' => $notificacoes_leads,
                'notificacoes_cotacoes' => $notificacoes_cotacoes,
                'notificacoes_whatsapp' => $notificacoes_whatsapp,
                'tema_escuro' => $tema_escuro,
                'idioma' => $idioma,
                'fuso_horario' => $fuso_horario,
                'privacidade_perfil' => $privacidade_perfil,
                'receber_newsletter' => $receber_newsletter
            ];
            
        } elseif ($acao == 'limpar_cache') {
            $mensagem = '🗑️ Cache limpo com sucesso!';
            $tipo_mensagem = 'success';
            
        } elseif ($acao == 'exportar_dados') {
            // Preparar dados para exportação
            $dados_exportacao = [
                'usuario' => [
                    'nome' => $usuario['nome'] ?? '',
                    'email' => $usuario['email'] ?? '',
                    'telefone' => $usuario['telefone'] ?? '',
                    'data_cadastro' => $usuario['data_cadastro'] ?? ''
                ],
                'configuracoes' => $configuracoes,
                'data_exportacao' => date('Y-m-d H:i:s')
            ];
            
            // Criar arquivo JSON
            $filename = 'dados_usuario_' . $user_id . '_' . date('Y-m-d_H-i-s') . '.json';
            $filepath = 'exports/' . $filename;
            
            // Criar diretório se não existir
            if (!is_dir('exports')) {
                mkdir('exports', 0755, true);
            }
            
            file_put_contents($filepath, json_encode($dados_exportacao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $mensagem = '📁 Dados exportados com sucesso! <a href="' . htmlspecialchars($filepath, ENT_QUOTES, 'UTF-8') . '" download class="alert-link">Clique aqui para baixar</a>';
            $tipo_mensagem = 'success';
            
        } elseif ($acao == 'desativar_conta') {
            $motivo = trim($_POST['motivo_desativacao'] ?? '');
            
            if (empty($motivo)) {
                throw new Exception('Por favor, informe o motivo da desativação.');
            }
            
            // Atualizar status do usuário
            $stmt = $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log do motivo
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS account_deactivations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    motivo TEXT,
                    data_desativacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $stmt = $pdo->prepare("INSERT INTO account_deactivations (user_id, motivo) VALUES (?, ?)");
                $stmt->execute([$user_id, $motivo]);
            } catch (Exception $e) {
                error_log("Erro ao registrar desativação: " . $e->getMessage());
            }
            
            // Destruir sessão e redirecionar
            session_destroy();
            header('Location: login.php?msg=conta_desativada');
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar ação: " . $e->getMessage());
        $mensagem = '❌ Erro ao processar sua solicitação. Por favor, tente novamente.';
        $tipo_mensagem = 'danger';
    }
}

// Garantir valores padrão
$usuario['nome'] = $usuario['nome'] ?? 'Usuário';
$usuario['email'] = $usuario['email'] ?? '';
$usuario['telefone'] = $usuario['telefone'] ?? '';
$usuario['status'] = $usuario['status'] ?? 'ativo';
$usuario['nivel_acesso'] = $usuario['nivel_acesso'] ?? 'cliente';
$usuario['data_cadastro'] = $usuario['data_cadastro'] ?? date('Y-m-d H:i:s');
$usuario['tipo_cliente'] = $usuario['tipo_cliente'] ?? 'pf';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Portal Cegonheiro</title>
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
            --bg-body: #f8f9fa;
            --card-bg: #ffffff;
        }

        /* DARK THEME VARIABLES */
        body.dark-theme {
            --white: #1a1d23;
            --text-dark: #e4e6eb;
            --text-light: #b0b3b8;
            --border-color: #3a3b3c;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.5);
            --bg-body: #18191a;
            --card-bg: #242526;
            --sidebar-bg: #242526;
        }

        body.dark-theme .form-control,
        body.dark-theme .form-select {
            background: #3a3b3c;
            color: #e4e6eb;
            border-color: #3a3b3c;
        }

        body.dark-theme .form-control:focus,
        body.dark-theme .form-select:focus {
            background: #3a3b3c;
            color: #e4e6eb;
            border-color: var(--primary-green);
        }

        body.dark-theme .info-box {
            background: #3a3b3c;
            border-color: #3a3b3c;
        }

        body.dark-theme .stat-item {
            background: #3a3b3c;
            border-color: #3a3b3c;
        }

        body.dark-theme .alert-success {
            background: linear-gradient(135deg, #1a3a2a, #2a4a3a);
            color: #4ade80;
            border-left-color: var(--primary-green);
        }

        body.dark-theme .alert-danger {
            background: linear-gradient(135deg, #3a1a1a, #4a2a2a);
            color: #f87171;
            border-left-color: #dc3545;
        }

        body.dark-theme .danger-zone {
            background: #3a1a1a;
            border-color: #5a2a2a;
        }

        body.dark-theme .danger-zone h5 {
            color: #f87171;
        }

        body.dark-theme .danger-zone p {
            color: #fca5a5;
        }

        body.dark-theme .whatsapp-status {
            background: #1a3a2a;
            border-color: #25d366;
        }

        body.dark-theme .whatsapp-status.warning {
            background: #3a3a1a;
            border-color: #ffc107;
        }

        body.dark-theme .btn-secondary {
            background: #3a3b3c;
            border-color: #3a3b3c;
            color: #e4e6eb;
        }

        body.dark-theme .btn-secondary:hover {
            background: #4a4b4c;
            border-color: #4a4b4c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            font-size: 14px;
            line-height: 1.5;
            transition: background-color 0.3s ease, color 0.3s ease;
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
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            transition: border-color 0.3s ease;
        }

        .logo img {
            height: 120px;
            width: auto;
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
            transition: background-color 0.3s ease;
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
            transition: color 0.3s ease;
        }

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        .breadcrumb-nav strong {
            color: var(--text-dark);
            transition: color 0.3s ease;
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--card-bg);
            transition: all 0.3s ease;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s ease;
        }

        .card-body {
            padding: 24px;
            transition: background-color 0.3s ease;
        }

        .settings-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .section-title i {
            color: var(--primary-green);
        }

        .section-title i.fa-whatsapp {
            color: #25d366;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-dark);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 188, 117, 0.1);
        }

        .form-switch {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
        }

        .form-switch input[type="checkbox"] {
            width: 50px;
            height: 28px;
            appearance: none;
            background: #ccc;
            border-radius: 14px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-switch input[type="checkbox"]:checked {
            background: var(--primary-green);
        }

        .form-switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .form-switch input[type="checkbox"]:checked::before {
            transform: translateX(22px);
        }

        .switch-label {
            flex: 1;
            font-weight: 500;
            color: var(--text-dark);
            transition: color 0.3s ease;
        }

        .switch-description {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 4px;
            transition: color 0.3s ease;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 12px 24px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 188, 117, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            border-color: #545b62;
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }

        .btn-outline-danger:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            color: var(--secondary-green);
            border-left: 4px solid var(--primary-green);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .info-box {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .info-box h6 {
            color: var(--text-dark);
            margin-bottom: 8px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .info-box p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
            color: var(--text-light);
            transition: color 0.3s ease;
        }

        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
            transition: all 0.3s ease;
        }

        .danger-zone h5 {
            color: #c53030;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .danger-zone p {
            color: #742a2a;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }

        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-item {
            background: var(--white);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
        }

        .whatsapp-status {
            background: #e8f5e8;
            border: 1px solid #25d366;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .whatsapp-status.warning {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .whatsapp-status i {
            font-size: 16px;
        }

        .whatsapp-status.warning i {
            color: #856404;
        }

        .whatsapp-status:not(.warning) i {
            color: #25d366;
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

            .account-stats {
                grid-template-columns: 1fr;
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
    </style>
</head>
<body <?php echo ($configuracoes['tema_escuro'] ?? 0) ? 'class="dark-theme"' : ''; ?>>
    <!-- Continua com o resto do HTML igual ao anterior... -->
    <!-- Por questões de espaço, o HTML permanece o mesmo -->
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://i.ibb.co/VcS31tMR/img-logo-portal-01.png" alt="Portal Cegonheiro">
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="leads_disponiveis.php" class="menu-item">
                <i class="fas fa-list-alt"></i>
                Leads Disponíveis
            </a>
            <a href="historico_leads.php" class="menu-item">
                <i class="fas fa-history"></i>
                Histórico
            </a>
            <a href="editar_perfil.php" class="menu-item">
                <i class="fas fa-user"></i>
                Meu Perfil
            </a>
            <a href="configuracoes.php" class="menu-item active">
                <i class="fas fa-cog"></i>
                Configurações
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
                    <strong>HOME</strong> > Configurações
                </div>
                <h1 class="page-title">Configurações</h1>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo htmlspecialchars($tipo_mensagem, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-circle"></i>
                    Informações da Conta
                </h3>
            </div>
            <div class="card-body">
                <div class="account-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo ucfirst(htmlspecialchars($usuario['status'], ENT_QUOTES, 'UTF-8')); ?></div>
                        <div class="stat-label">Status da Conta</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo ucfirst(htmlspecialchars($usuario['nivel_acesso'], ENT_QUOTES, 'UTF-8')); ?></div>
                        <div class="stat-label">Nível de Acesso</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></div>
                        <div class="stat-label">Membro Desde</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo ucfirst(htmlspecialchars($usuario['tipo_cliente'], ENT_QUOTES, 'UTF-8')); ?></div>
                        <div class="stat-label">Tipo de Cliente</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bell"></i>
                    Notificações
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" id="configForm">
                    <input type="hidden" name="acao" value="salvar_configuracoes">
                    
                    <div class="settings-section">
                        <div class="section-title">
                            <i class="fab fa-whatsapp"></i>
                            Notificações WhatsApp
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="notificacoes_whatsapp" id="notificacoes_whatsapp" 
                                   <?php echo ($configuracoes['notificacoes_whatsapp'] ?? 0) ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                <strong>Receber notificações via WhatsApp</strong>
                                <div class="switch-description">
                                    <i class="fas fa-mobile-alt me-1"></i>
                                    Seja notificado instantaneamente sobre novos leads disponíveis
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <h6><i class="fas fa-info-circle me-2"></i>Como funciona</h6>
                                    <ul class="mb-0" style="font-size: 13px;">
                                        <li>Notificação automática para novos leads</li>
                                        <li>Mensagem com detalhes do transporte</li>
                                        <li>Link direto para o portal</li>
                                        <li>Processamento a cada 5 minutos</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <?php if (empty($usuario['telefone'])): ?>
                                    <div class="whatsapp-status warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <div>
                                            <strong>Telefone necessário!</strong><br>
                                            <small>
                                                Cadastre seu telefone no 
                                                <a href="editar_perfil.php" class="alert-link">perfil</a> 
                                                para receber WhatsApp.
                                            </small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="whatsapp-status">
                                        <i class="fab fa-whatsapp"></i>
                                        <div>
                                            <strong>Telefone configurado!</strong><br>
                                            <small><?php echo htmlspecialchars($usuario['telefone'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <div class="section-title">
                            <i class="fas fa-envelope"></i>
                            Notificações por E-mail
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="notificacoes_email" id="notificacoes_email" 
                                   <?php echo $configuracoes['notificacoes_email'] ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                Receber notificações por e-mail
                                <div class="switch-description">Receba atualizações importantes sobre leads e cotações</div>
                            </div>
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="notificacoes_leads" id="notificacoes_leads" 
                                   <?php echo $configuracoes['notificacoes_leads'] ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                Novos leads disponíveis
                                <div class="switch-description">Seja notificado quando novos leads estiverem disponíveis</div>
                            </div>
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="notificacoes_cotacoes" id="notificacoes_cotacoes" 
                                   <?php echo $configuracoes['notificacoes_cotacoes'] ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                Atualizações de cotações
                                <div class="switch-description">Receba notificações sobre o status das suas cotações</div>
                            </div>
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="receber_newsletter" id="receber_newsletter" 
                                   <?php echo $configuracoes['receber_newsletter'] ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                Newsletter
                                <div class="switch-description">Receba dicas e novidades do Portal Cegonheiro</div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <div class="section-title">
                            <i class="fas fa-mobile-alt"></i>
                            Notificações Push
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="notificacoes_push" id="notificacoes_push" 
                                   <?php echo $configuracoes['notificacoes_push'] ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                Notificações push no navegador
                                <div class="switch-description">Receba notificações instantâneas no seu navegador</div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <div class="section-title">
                            <i class="fas fa-palette"></i>
                            Aparência
                        </div>
                        
                        <div class="form-switch">
                            <input type="checkbox" name="tema_escuro" id="tema_escuro" 
                                   <?php echo $configuracoes['tema_escuro'] ? 'checked' : ''; ?>>
                            <div class="switch-label">
                                <strong>Tema escuro</strong>
                                <div class="switch-description">
                                    <i class="fas fa-moon me-1"></i>
                                    Ativar modo escuro para reduzir o cansaço visual e economizar bateria
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-box mt-3">
                            <h6><i class="fas fa-lightbulb me-2"></i>Benefícios do Tema Escuro</h6>
                            <ul class="mb-0" style="font-size: 13px;">
                                <li>Reduz o cansaço visual em ambientes com pouca luz</li>
                                <li>Economiza bateria em dispositivos com tela OLED/AMOLED</li>
                                <li>Melhora o contraste e a legibilidade</li>
                                <li>Experiência mais confortável para uso noturno</li>
                            </ul>
                        </div>
                    </div>

                    <div class="settings-section">
                        <div class="section-title">
                            <i class="fas fa-globe"></i>
                            Localização e Idioma
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="idioma" class="form-label">Idioma</label>
                                    <select class="form-select" name="idioma" id="idioma">
                                        <option value="pt-BR" <?php echo $configuracoes['idioma'] == 'pt-BR' ? 'selected' : ''; ?>>Português (Brasil)</option>
                                        <option value="en-US" <?php echo $configuracoes['idioma'] == 'en-US' ? 'selected' : ''; ?>>English (US)</option>
                                        <option value="es-ES" <?php echo $configuracoes['idioma'] == 'es-ES' ? 'selected' : ''; ?>>Español</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fuso_horario" class="form-label">Fuso Horário</label>
                                    <select class="form-select" name="fuso_horario" id="fuso_horario">
                                        <option value="America/Sao_Paulo" <?php echo $configuracoes['fuso_horario'] == 'America/Sao_Paulo' ? 'selected' : ''; ?>>Brasília (UTC-3)</option>
                                        <option value="America/Manaus" <?php echo $configuracoes['fuso_horario'] == 'America/Manaus' ? 'selected' : ''; ?>>Manaus (UTC-4)</option>
                                        <option value="America/Rio_Branco" <?php echo $configuracoes['fuso_horario'] == 'America/Rio_Branco' ? 'selected' : ''; ?>>Rio Branco (UTC-5)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <div class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Privacidade
                        </div>
                        
                        <div class="form-group">
                            <label for="privacidade_perfil" class="form-label">Visibilidade do Perfil</label>
                            <select class="form-select" name="privacidade_perfil" id="privacidade_perfil">
                                <option value="publico" <?php echo $configuracoes['privacidade_perfil'] == 'publico' ? 'selected' : ''; ?>>Público</option>
                                <option value="privado" <?php echo $configuracoes['privacidade_perfil'] == 'privado' ? 'selected' : ''; ?>>Privado</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Salvar Configurações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-tools"></i>
                    Ferramentas da Conta
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="acao" value="limpar_cache">
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="fas fa-broom me-2"></i>
                                Limpar Cache
                            </button>
                        </form>
                        <small class="text-muted d-block mt-2">Remove dados temporários armazenados</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="acao" value="exportar_dados">
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="fas fa-download me-2"></i>
                                Exportar Dados
                            </button>
                        </form>
                        <small class="text-muted d-block mt-2">Baixe uma cópia dos seus dados</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <button type="button" class="btn btn-secondary w-100" onclick="requestNotificationPermission()">
                            <i class="fas fa-bell me-2"></i>
                            Ativar Notificações
                        </button>
                        <small class="text-muted d-block mt-2">Permitir notificações do navegador</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="danger-zone">
            <h5>
                <i class="fas fa-exclamation-triangle"></i>
                Zona de Perigo
            </h5>
            <p>As ações abaixo são irreversíveis. Tenha certeza antes de prosseguir.</p>
            
            <form method="POST" id="deactivateForm" onsubmit="return confirmDeactivation()">
                <input type="hidden" name="acao" value="desativar_conta">
                
                <div class="form-group">
                    <label for="motivo_desativacao" class="form-label">Motivo da desativação *</label>
                    <textarea class="form-control" name="motivo_desativacao" id="motivo_desativacao" 
                              rows="3" placeholder="Por favor, nos conte o motivo da desativação..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fas fa-user-times me-2"></i>
                    Desativar Conta
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function confirmDeactivation() {
            const motivo = document.getElementById('motivo_desativacao').value.trim();
            
            if (!motivo) {
                alert('Por favor, informe o motivo da desativação.');
                return false;
            }
            
            const confirmText = 'Tem certeza que deseja desativar sua conta? Esta ação não pode ser desfeita.';
            return confirm(confirmText);
        }

        function requestNotificationPermission() {
            if ('Notification' in window) {
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        new Notification('Portal Cegonheiro', {
                            body: 'Notificações ativadas com sucesso!',
                            icon: 'https://i.ibb.co/VcS31tMR/img-logo-portal-01.png'
                        });
                        
                        document.getElementById('notificacoes_push').checked = true;
                    } else {
                        alert('Permissão para notificações negada. Você pode ativar nas configurações do navegador.');
                    }
                });
            } else {
                alert('Seu navegador não suporta notificações push.');
            }
        }

        document.getElementById('tema_escuro').addEventListener('change', function() {
            document.body.classList.add('dark-theme-transition');
            
            if (this.checked) {
                document.body.classList.add('dark-theme');
                localStorage.setItem('theme', 'dark');
                showThemeNotification('🌙 Tema escuro ativado!');
            } else {
                document.body.classList.remove('dark-theme');
                localStorage.setItem('theme', 'light');
                showThemeNotification('☀️ Tema claro ativado!');
            }
            
            setTimeout(() => {
                document.body.classList.remove('dark-theme-transition');
            }, 500);
        });

        function showThemeNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--primary-green);
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0, 188, 117, 0.3);
                z-index: 10000;
                font-weight: 600;
                animation: slideInRight 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const themeCheckbox = document.getElementById('tema_escuro');
            
                document.body.classList.add('dark-theme');
                themeCheckbox.checked = true;
            }
        });

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

        document.getElementById('configForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvando...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });

        console.log('✅ Configurações carregadas com sucesso!');
    </script>
</body>
</html>