<?php
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Habilitar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro: Conexão com banco de dados não estabelecida.');
}

// Incluir o gerenciador de notificações
require_once 'notificacoes.php';
$notificacaoManager = new NotificacaoManager($pdo);

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

// Buscar notificações não lidas
$notificacoes_nao_lidas = $notificacaoManager->buscarNaoLidas($_SESSION['usuario_id'], 5);
$total_notificacoes = $notificacaoManager->contarNaoLidas($_SESSION['usuario_id']);

// Estatísticas para o dashboard
try {
    // Total de leads disponíveis
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'novo'");
    $total_leads = $stmt->fetch()['total'];
    
    // Leads urgentes (próximos 7 dias)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'novo' AND data_prevista <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $leads_urgentes = $stmt->fetch()['total'];
    
    // Leads de hoje
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE DATE(data_cadastro) = CURDATE()");
    $leads_hoje = $stmt->fetch()['total'];
    
    // Leads desta semana
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE data_cadastro >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $leads_semana = $stmt->fetch()['total'];
    
    // Leads deste mês
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE MONTH(data_cadastro) = MONTH(CURDATE()) AND YEAR(data_cadastro) = YEAR(CURDATE())");
    $leads_mes = $stmt->fetch()['total'];
    
    // Buscar leads recentes (aumentado para 15)
    $stmt = $pdo->query("SELECT * FROM leads WHERE status = 'novo' ORDER BY data_cadastro DESC LIMIT 15");
    $leads_recentes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $total_leads = 0;
    $leads_urgentes = 0;
    $leads_hoje = 0;
    $leads_semana = 0;
    $leads_mes = 0;
    $leads_recentes = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Cegonheiro</title>
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

        /* Sidebar */
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
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
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

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: 100vh;
        }

        /* Header */
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

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 280px;
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .breadcrumb-nav strong {
            color: var(--text-dark);
        }

        /* User Profile in Header */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(0, 188, 117, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-green);
            transition: all 0.3s ease;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            background: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            border: 2px solid var(--primary-green);
            transition: all 0.3s ease;
        }

        .user-profile:hover .user-avatar,
        .user-profile:hover .user-avatar-placeholder {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 188, 117, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            line-height: 1.2;
        }

        .user-role {
            font-size: 12px;
            color: var(--text-light);
            text-transform: capitalize;
        }

        /* Notification badges */
        .notification-badge {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .notification-badge:hover {
            background: rgba(0, 188, 117, 0.05);
        }

        .notification-badge .badge-count {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 16px;
            text-align: center;
            line-height: 1.2;
            display: none;
        }

        .notification-badge.has-notifications .badge-count {
            display: block;
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            z-index: 1000;
            display: none;
            max-height: 500px;
            overflow: hidden;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-dark);
        }

        .mark-all-read {
            color: var(--primary-green);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }

        .mark-all-read:hover {
            text-decoration: underline;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: rgba(0, 188, 117, 0.02);
            border-left: 3px solid var(--primary-green);
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 20px;
            right: 20px;
            width: 8px;
            height: 8px;
            background: var(--primary-green);
            border-radius: 50%;
        }

        .notification-content h6 {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 4px 0;
            color: var(--text-dark);
        }

        .notification-content p {
            font-size: 13px;
            color: var(--text-light);
            margin: 0 0 8px 0;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 11px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 16px;
        }

        .notification-icon.lead {
            background: rgba(0, 188, 117, 0.1);
            color: var(--primary-green);
        }

        .notification-icon.urgent {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-light);
        }

        .notification-empty i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Stats Cards - Simplificado sem porcentagens */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-bottom: 16px;
        }

        .stat-icon.today {
            background: linear-gradient(135deg, #00bc75, #07a368);
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .stat-icon.urgent {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .stat-icon.week {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-icon.month {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-description {
            color: var(--text-light);
            font-size: 12px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
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
            justify-content: space-between;
        }

        .card-subtitle {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 4px;
        }

        .card-body {
            padding: 24px;
        }

        /* Table */
        .table {
            margin: 0;
        }

        .table th {
            background: #f8f9fa;
            border: none;
            padding: 15px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 15px;
            border-color: var(--border-color);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 188, 117, 0.05);
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .bg-primary { background: var(--primary-green) !important; }
        .bg-success { background: #28a745 !important; }
        .bg-warning { background: #ffc107 !important; }
        .bg-danger { background: #dc3545 !important; }
        .bg-info { background: #17a2b8 !important; }

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 16px;
        }

        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            border-color: var(--secondary-green);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .dropdown-menu {
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            margin-top: 8px;
        }

        .dropdown-item {
            padding: 12px 16px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background: rgba(0, 188, 117, 0.05);
            color: var(--primary-green);
        }

        .dropdown-item i {
            width: 16px;
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-box input {
                width: 200px;
            }

            .user-info {
                display: none;
            }

            .notification-dropdown {
                width: 320px;
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

            .search-box input {
                width: 100%;
            }

            .user-info {
                display: none;
            }

            .notification-dropdown {
                width: 280px;
                right: -20px;
            }
        }

        /* Sidebar Toggle */
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
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <img src="https://i.ibb.co/VcS31tMR/img-logo-portal-01.png" alt="Portal Cegonheiro">
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="leads_disponiveis.php" class="menu-item">
                <i class="fas fa-list-alt"></i>
                Leads Disponíveis
            </a>
            <a href="relatorios.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                Relatórios
            </a>
            <a href="editar_perfil.php" class="menu-item">
                <i class="fas fa-user"></i>
                Meu Perfil
            </a>
            <a href="configuracoes.php" class="menu-item">
                <i class="fas fa-cog"></i>
                Configurações
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > Dashboard
                </div>
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search">
                </div>
                
                <!-- Notification Bell -->
                <div class="notification-badge <?php echo $total_notificacoes > 0 ? 'has-notifications' : ''; ?>" 
                     id="notificationBell" onclick="toggleNotifications()">
                    <i class="fas fa-bell" style="font-size: 20px; color: var(--text-light);"></i>
                    <span class="badge-count" id="notificationCount"><?php echo $total_notificacoes; ?></span>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h6>Notificações</h6>
                            <a href="#" class="mark-all-read" onclick="markAllAsRead()">Marcar todas como lidas</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <!-- Notificações serão carregadas via AJAX -->
                        </div>
                    </div>
                </div>
                
                <!-- User Profile Dropdown -->
                <div class="user-dropdown dropdown">
                    <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($usuario['foto_perfil'] && file_exists($usuario['foto_perfil'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" 
                                 alt="Foto de Perfil" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder">
                                <?php echo strtoupper(substr($usuario['nome'], 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                            <div class="user-role"><?php echo ucfirst($usuario['nivel_acesso']); ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: var(--text-light); font-size: 12px;"></i>
                    </div>
                    
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="editar_perfil.php">
                                <i class="fas fa-user"></i>
                                Meu Perfil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="configuracoes.php">
                                <i class="fas fa-cog"></i>
                                Configurações
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Cards - Simplificado sem porcentagens e sem taxa de conversão -->
        <div class="stats-grid">
            

            <!-- Total de Leads -->
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_leads; ?></div>
                <div class="stat-label">Total Disponíveis</div>
                <div class="stat-description">Leads aguardando cotação</div>
            </div>

            <!-- Leads Urgentes -->
            <div class="stat-card">
                <div class="stat-icon urgent">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?php echo $leads_urgentes; ?></div>
                <div class="stat-label">Urgentes</div>
                <div class="stat-description">Vencem em 7 dias</div>
            </div>

            <!-- Leads da Semana -->
            <div class="stat-card">
                <div class="stat-icon week">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-number"><?php echo $leads_semana; ?></div>
                <div class="stat-label">Esta Semana</div>
                <div class="stat-description">Últimos 7 dias</div>
            </div>

            <!-- Leads do Mês -->
            <div class="stat-card">
                <div class="stat-icon month">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $leads_mes; ?></div>
                <div class="stat-label">Este Mês</div>
                <div class="stat-description">Mês atual</div>
            </div>
        </div>

        <!-- Leads Table - Aumentado para 15 leads -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Leads Recentes (<?php echo count($leads_recentes); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Rota</th>
                                <th>Veículo</th>
                                <th>Valor do Veículo</th>
                                <th>Data Prevista</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads_recentes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">Nenhum lead disponível</h5>
                                        <p class="text-muted">Novos pedidos aparecerão aqui quando forem cadastrados.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leads_recentes as $lead): ?>
                                    <?php
                                    // Extrair apenas o primeiro nome
                                    $nome_completo = $lead['nome'];
                                    $primeiro_nome = explode(' ', $nome_completo)[0];
                                    
                                    // Calcular dias restantes
                                    $dias_restantes = ceil((strtotime($lead['data_prevista']) - time()) / (60 * 60 * 24));
                                    $urgente = $dias_restantes <= 7;
                                    ?>
                                    <tr <?php echo $urgente ? 'style="background-color: rgba(239, 68, 68, 0.05);"' : ''; ?>>
                                        <td>
                                            <strong class="text-primary">#<?php echo $lead['id']; ?></strong>
                                            <?php if ($urgente): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Urgente</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($primeiro_nome); ?></strong>
                                           
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($lead['cidade_origem']); ?></span><br>
                                            <span class="badge bg-danger"><?php echo htmlspecialchars($lead['cidade_destino']); ?></span>
                                        </td>
                                        <td>
                                            <i class="fas fa-car text-primary"></i>
                                            <?php echo htmlspecialchars($lead['tipo_veiculo']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($lead['ano_modelo']); ?></small>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php 
                                                if ($lead['valor_veiculo']) {
                                                    echo 'R$ ' . number_format($lead['valor_veiculo'], 2, ',', '.');
                                                } else {
                                                    echo '<span class="text-muted">Não informado</span>';
                                                }
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?>
                                            <br>
                                            <small class="<?php echo $urgente ? 'text-danger' : 'text-muted'; ?>">
                                                <?php if ($dias_restantes > 0): ?>
                                                    em <?php echo $dias_restantes; ?> dias
                                                <?php elseif ($dias_restantes == 0): ?>
                                                    hoje
                                                <?php else: ?>
                                                    <?php echo abs($dias_restantes); ?> dias atrás
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">Novo</span>
                                        </td>
                                        <td>
                                            <a href="lead_detalhes.php?id=<?php echo $lead['id']; ?>" 
                                               class="btn btn-primary btn-sm" 
                                               title="Ver Detalhes do Lead #<?php echo $lead['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside (mobile)
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

        // Notification System
        let notificationDropdownOpen = false;

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            
            if (notificationDropdownOpen) {
                dropdown.classList.add('show');
                loadNotifications();
            } else {
                dropdown.classList.remove('show');
            }
        }

        function loadNotifications() {
            fetch('api_notificacoes.php?action=buscar')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderNotifications(data.notificacoes);
                        updateNotificationCount(data.total);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar notificações:', error);
                });
        }

        function renderNotifications(notifications) {
            const notificationList = document.getElementById('notificationList');
            
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <h6>Nenhuma notificação</h6>
                        <p>Você está em dia!</p>
                    </div>
                `;
                return;
            }

            notificationList.innerHTML = notifications.map(notification => {
                const timeAgo = getTimeAgo(notification.data_criacao);
                const iconClass = getNotificationIcon(notification.tipo);
                const iconColor = getNotificationIconColor(notification.tipo);
                
                return `
                    <div class="notification-item ${!notification.lida ? 'unread' : ''}" 
                         onclick="handleNotificationClick(${notification.id}, '${notification.link || '#'}')">
                        <div style="display: flex; align-items: flex-start;">
                            <div class="notification-icon ${iconColor}">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="notification-content" style="flex: 1;">
                                <h6>${notification.titulo}</h6>
                                <p>${notification.mensagem}</p>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    ${timeAgo}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function getNotificationIcon(tipo) {
            const icons = {
                'novo_lead': 'fa-user-plus',
                'lead_urgente': 'fa-exclamation-triangle',
                'cotacao_recebida': 'fa-file-invoice-dollar',
                'sistema': 'fa-cog',
                'default': 'fa-bell'
            };
            return icons[tipo] || icons.default;
        }

        function getNotificationIconColor(tipo) {
            const colors = {
                'novo_lead': 'lead',
                'lead_urgente': 'urgent',
                'cotacao_recebida': 'lead',
                'sistema': 'lead'
            };
            return colors[tipo] || 'lead';
        }

        function getTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) {
                return 'Agora mesmo';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} min atrás`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours}h atrás`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days}d atrás`;
            } else {
                return date.toLocaleDateString('pt-BR');
            }
        }

        function handleNotificationClick(notificationId, link) {
            // Marcar como lida
            markAsRead(notificationId);
            
            // Redirecionar se houver link
            if (link && link !== '#') {
                window.location.href = link;
            }
        }

        function markAsRead(notificationId) {
            fetch('api_notificacoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=marcar_lida&id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recarregar notificações
                    loadNotifications();
                }
            })
            .catch(error => {
                console.error('Erro ao marcar notificação como lida:', error);
            });
        }

        function markAllAsRead() {
            fetch('api_notificacoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=marcar_todas_lidas'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                    updateNotificationCount(0);
                }
            })
            .catch(error => {
                console.error('Erro ao marcar todas notificações como lidas:', error);
            });
        }

        function updateNotificationCount(count) {
            const badge = document.getElementById('notificationCount');
            const bell = document.getElementById('notificationBell');
            
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                bell.classList.add('has-notifications');
            } else {
                bell.classList.remove('has-notifications');
            }
        }

        // Close notifications when clicking outside
        document.addEventListener('click', function(e) {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (!notificationBell.contains(e.target) && notificationDropdownOpen) {
                notificationDropdown.classList.remove('show');
                notificationDropdownOpen = false;
            }
        });

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            if (!notificationDropdownOpen) {
                // Apenas atualizar o contador se o dropdown não estiver aberto
                fetch('api_notificacoes.php?action=contar')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateNotificationCount(data.total);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao atualizar contador de notificações:', error);
                    });
            }
        }, 30000);

        // Load initial notification count
        document.addEventListener('DOMContentLoaded', function() {
            fetch('api_notificacoes.php?action=contar')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationCount(data.total);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar contador inicial:', error);
                });
        });

        // Highlight urgent leads
        function highlightUrgentLeads() {
            const urgentRows = document.querySelectorAll('tr[style*="background-color: rgba(239, 68, 68, 0.05)"]');
            urgentRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
                });
            });
        }

        // Initialize urgent leads highlighting
        highlightUrgentLeads();

        // Smooth animations for stat cards
        function animateStatCards() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
                        }, index * 100);
            });
        }

        // Initialize animations when page loads
        window.addEventListener('load', animateStatCards);

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const tableRows = document.querySelectorAll('tbody tr');
                
                tableRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Add tooltips to stat cards
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                const label = card.querySelector('.stat-label').textContent;
                card.setAttribute('title', `Clique para ver detalhes de ${label}`);
                
                card.addEventListener('click', function() {
                    // Add click functionality to stat cards
                    const cardLabel = this.querySelector('.stat-label').textContent.toLowerCase();
                    
                    if (cardLabel.includes('hoje') || cardLabel.includes('urgentes')) {
                        // Filter table to show relevant leads
                        filterTableByType(cardLabel);
                    }
                });
            });
        });

        function filterTableByType(type) {
            const tableRows = document.querySelectorAll('tbody tr');
            
            tableRows.forEach(row => {
                const isUrgent = row.style.backgroundColor.includes('rgba(239, 68, 68');
                const isToday = row.querySelector('small') && row.querySelector('small').textContent.includes('hoje');
                
                if (type.includes('urgentes') && isUrgent) {
                    row.style.display = '';
                } else if (type.includes('hoje') && isToday) {
                    row.style.display = '';
                } else if (type.includes('urgentes') || type.includes('hoje')) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });
            
            // Add a reset button
            if (!document.getElementById('resetFilter')) {
                const resetBtn = document.createElement('button');
                resetBtn.id = 'resetFilter';
                resetBtn.className = 'btn btn-secondary btn-sm ms-2';
                resetBtn.innerHTML = '<i class="fas fa-times"></i> Limpar Filtro';
                resetBtn.onclick = function() {
                    tableRows.forEach(row => row.style.display = '');
                    this.remove();
                };
                
                const cardHeader = document.querySelector('.card-header h5');
                cardHeader.appendChild(resetBtn);
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + N para abrir notificações
            if (e.altKey && e.key === 'n') {
                e.preventDefault();
                toggleNotifications();
            }
            
            // Alt + D para ir para dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
            
            // Alt + L para ir para leads
            if (e.altKey && e.key === 'l') {
                e.preventDefault();
                window.location.href = 'leads_disponiveis.php';
            }
            
            // Escape para fechar notificações
            if (e.key === 'Escape' && notificationDropdownOpen) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
        });

        // Auto-refresh dashboard stats every 60 seconds
        setInterval(function() {
            // Refresh page stats without full reload
            fetch('dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stat cards
                        updateStatCards(data);
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar estatísticas:', error);
                });
        }, 60000);

        function updateStatCards(data) {
            const statCards = document.querySelectorAll('.stat-card');
            
            statCards.forEach((card, index) => {
                const label = card.querySelector('.stat-label').textContent.toLowerCase();
                const numberElement = card.querySelector('.stat-number');
                
                if (label.includes('hoje') && data.leads_hoje !== undefined) {
                    numberElement.textContent = data.leads_hoje;
                } else if (label.includes('total') && data.total_leads !== undefined) {
                    numberElement.textContent = data.total_leads;
                } else if (label.includes('urgentes') && data.leads_urgentes !== undefined) {
                    numberElement.textContent = data.leads_urgentes;
                } else if (label.includes('semana') && data.leads_semana !== undefined) {
                    numberElement.textContent = data.leads_semana;
                } else if (label.includes('mês') && data.leads_mes !== undefined) {
                    numberElement.textContent = data.leads_mes;
                }
            });
        }

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log('✅ Dashboard carregado em:', Math.round(loadTime), 'ms');
            console.log('📊 Estatísticas carregadas:');
            console.log('  • Leads hoje:', <?php echo $leads_hoje; ?>);
            console.log('  • Total leads:', <?php echo $total_leads; ?>);
            console.log('  • Leads urgentes:', <?php echo $leads_urgentes; ?>);
            console.log('  • Leads semana:', <?php echo $leads_semana; ?>);
            console.log('  • Leads mês:', <?php echo $leads_mes; ?>);
            console.log('🔔 Notificações não lidas:', <?php echo $total_notificacoes; ?>);
        });

        // Error handling
        window.addEventListener('error', function(e) {
            console.error('❌ Erro JavaScript:', e.error);
        });

        // Add loading states
        function showLoading(element) {
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        function hideLoading(element, originalContent) {
            element.innerHTML = originalContent;
        }

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Tab navigation enhancement
            if (e.key === 'Tab') {
                const focusedElement = document.activeElement;
                if (focusedElement.classList.contains('stat-card')) {
                    focusedElement.style.outline = '2px solid var(--primary-green)';
                    focusedElement.style.outlineOffset = '2px';
                }
            }
        });

        // Add focus styles for accessibility
        document.addEventListener('focusout', function(e) {
            if (e.target.classList.contains('stat-card')) {
                e.target.style.outline = 'none';
            }
        });

        // Real-time clock for dashboard
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('pt-BR');
            const dateString = now.toLocaleDateString('pt-BR');
            
            // Update page title with current time
            document.title = `Dashboard - ${timeString} - Portal Cegonheiro`;
        }

        // Update clock every second
        setInterval(updateClock, 1000);

        // Initialize clock
        updateClock();

        // Add hover effects to table rows
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.transition = 'transform 0.2s ease';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Add click to copy lead ID functionality
        document.querySelectorAll('td strong.text-primary').forEach(element => {
            element.style.cursor = 'pointer';
            element.title = 'Clique para copiar o ID';
            
            element.addEventListener('click', function() {
                const leadId = this.textContent.replace('#', '');
                navigator.clipboard.writeText(leadId).then(() => {
                    // Show temporary feedback
                    const originalText = this.textContent;
                    this.textContent = '✓ Copiado!';
                    this.style.color = 'var(--primary-green)';
                    
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.color = '';
                    }, 2000);
                });
            });
        });

        // Add notification sound preference
        let notificationSoundEnabled = localStorage.getItem('notificationSound') !== 'false';

        function toggleNotificationSound() {
            notificationSoundEnabled = !notificationSoundEnabled;
            localStorage.setItem('notificationSound', notificationSoundEnabled);
            
            console.log('Som de notificação:', notificationSoundEnabled ? 'Ativado' : 'Desativado');
        }

        // Add keyboard shortcut to toggle sound
        document.addEventListener('keydown', function(e) {
            // Alt + S para toggle do som
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                toggleNotificationSound();
            }
        });

        // Add quick stats refresh button
        function addRefreshButton() {
            const pageTitle = document.querySelector('.page-title');
            const refreshBtn = document.createElement('button');
            refreshBtn.className = 'btn btn-outline-primary btn-sm ms-3';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            refreshBtn.title = 'Atualizar estatísticas';
            refreshBtn.onclick = function() {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                location.reload();
            };
            
            pageTitle.parentNode.appendChild(refreshBtn);
        }

        // Initialize refresh button
        addRefreshButton();

        // Add data export functionality
        function exportTableData() {
            const table = document.querySelector('.table');
            const rows = table.querySelectorAll('tr');
            let csvContent = '';
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = Array.from(cells).map(cell => 
                    cell.textContent.trim().replace(/,/g, ';')
                ).join(',');
                csvContent += rowData + '\n';
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `leads_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Add export button to table header
        const cardHeader = document.querySelector('.card-header h5');
        const exportBtn = document.createElement('button');
        exportBtn.className = 'btn btn-outline-success btn-sm ms-2';
        exportBtn.innerHTML = '<i class="fas fa-download"></i> Exportar';
        exportBtn.onclick = exportTableData;
        cardHeader.appendChild(exportBtn);

        // Initialize dashboard
        console.log('🚀 Dashboard Portal Cegonheiro inicializado');
        console.log('📱 Versão: 2.1 - Simplificado');
        console.log('👤 Usuário:', '<?php echo htmlspecialchars($usuario['nome']); ?>');
        console.log('🎯 Funcionalidades ativas: Notificações, Stats em tempo real, Filtros, Export');
        console.log('⌨️ Atalhos disponíveis:');
        console.log('  • Alt + N: Abrir notificações');
        console.log('  • Alt + D: Dashboard');
        console.log('  • Alt + L: Leads');
        console.log('  • Alt + S: Toggle som notificações');
        console.log('  • Escape: Fechar dropdowns');

        // Add welcome message for new users
        if (localStorage.getItem('dashboardVisited') !== 'true') {
            setTimeout(() => {
                console.log('🎉 Bem-vindo ao Dashboard Portal Cegonheiro!');
                console.log('💡 Dica: Clique nos cards de estatísticas para filtrar a tabela');
                localStorage.setItem('dashboardVisited', 'true');
            }, 2000);
        }

        // Monitor connection status
        window.addEventListener('online', function() {
            console.log('🌐 Conexão restaurada');
        });

        window.addEventListener('offline', function() {
            console.log('📡 Sem conexão - algumas funcionalidades podem estar limitadas');
        });

        // Add page visibility API for performance
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('📱 Página em segundo plano');
            } else {
                console.log('📱 Página ativa - atualizando dados...');
                // Refresh notifications when page becomes visible
                if (notificationDropdownOpen) {
                    loadNotifications();
                }
            }
        });
    </script>
</body>
</html>