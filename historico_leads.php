<?php
require_once 'config.php';

// CORREÇÃO: Verificar login primeiro
if (!verificarLogin()) {
    header('Location: login.php');
    exit;
}

// CORREÇÃO: Ajustar verificação de nível de acesso para os valores reais do sistema
$nivel_acesso = $_SESSION['nivel_acesso'] ?? '';
$tipo_cliente = $_SESSION['tipo_cliente'] ?? 'pf';

// Permitir acesso para:
// 1. Administradores (nivel_acesso = 'admin')
// 2. Clientes PJ/Transportadoras (nivel_acesso = 'cliente' E tipo_cliente = 'pj')
$tem_acesso = false;
$eh_admin = false;

if ($nivel_acesso === 'admin') {
    $tem_acesso = true;
    $eh_admin = true;
} elseif ($nivel_acesso === 'cliente' && $tipo_cliente === 'pj') {
    $tem_acesso = true;
    $eh_admin = false;
}

if (!$tem_acesso) {
    $_SESSION['erro'] = 'Acesso negado. Esta página é apenas para transportadoras e administradores.';
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';

// Buscar dados do usuário para foto de perfil
try {
    $stmt = $pdo->prepare("SELECT id, nome, email, nivel_acesso, foto_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        $usuario = ['nome' => $nome_usuario, 'foto_perfil' => null];
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
    $usuario = ['nome' => $nome_usuario, 'foto_perfil' => null];
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';

// Construir query para histórico (leads finalizados ou cancelados)
$where_conditions = ["l.status IN ('finalizado', 'cancelado')"];
$params = [];

// Se não for admin, mostrar apenas os leads que o usuário visualizou
if (!$eh_admin) {
    $where_conditions[] = "EXISTS (SELECT 1 FROM lead_views lv WHERE lv.lead_id = l.id AND lv.user_id = ?)";
    $params[] = $user_id;
}

if ($filtro_status && in_array($filtro_status, ['finalizado', 'cancelado'])) {
    $where_conditions[] = "l.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_data_inicio) {
    $where_conditions[] = "DATE(l.data_cadastro) >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where_conditions[] = "DATE(l.data_cadastro) <= ?";
    $params[] = $filtro_data_fim;
}

if ($filtro_cliente) {
    $where_conditions[] = "(l.nome LIKE ? OR l.email LIKE ?)";
    $params[] = "%$filtro_cliente%";
    $params[] = "%$filtro_cliente%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Buscar histórico de leads
try {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               COUNT(c.id) as total_cotacoes,
               MIN(c.valor_cotacao) as menor_cotacao,
               MAX(c.valor_cotacao) as maior_cotacao,
               AVG(c.valor_cotacao) as cotacao_media
        FROM leads l 
        LEFT JOIN cotacoes c ON l.id = c.lead_id 
        $where_clause
        GROUP BY l.id 
        ORDER BY l.data_atualizacao DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $historico_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar histórico de leads: " . $e->getMessage());
    $historico_leads = [];
}

// Estatísticas do histórico
$stats = [];

try {
    if ($eh_admin) {
        // Admin vê todas as estatísticas
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'finalizado'");
        $stats['leads_finalizados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'cancelado'");
        $stats['leads_cancelados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status IN ('finalizado', 'cancelado') AND data_atualizacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['historico_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $pdo->query("
            SELECT AVG(c.valor_cotacao) as media 
            FROM cotacoes c 
            JOIN leads l ON c.lead_id = l.id 
            WHERE l.status = 'finalizado' AND c.status_cotacao = 'aceita'
        ");
        $stats['valor_medio_finalizados'] = $stmt->fetch(PDO::FETCH_ASSOC)['media'] ?? 0;
    } else {
        // Transportadora vê apenas seus leads visualizados
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM leads l 
            WHERE l.status = 'finalizado' 
            AND EXISTS (SELECT 1 FROM lead_views lv WHERE lv.lead_id = l.id AND lv.user_id = ?)
        ");
        $stmt->execute([$user_id]);
        $stats['leads_finalizados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM leads l 
            WHERE l.status = 'cancelado' 
            AND EXISTS (SELECT 1 FROM lead_views lv WHERE lv.lead_id = l.id AND lv.user_id = ?)
        ");
        $stmt->execute([$user_id]);
        $stats['leads_cancelados'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM leads l 
            WHERE l.status IN ('finalizado', 'cancelado') 
            AND l.data_atualizacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND EXISTS (SELECT 1 FROM lead_views lv WHERE lv.lead_id = l.id AND lv.user_id = ?)
        ");
        $stmt->execute([$user_id]);
        $stats['historico_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $pdo->prepare("
            SELECT AVG(c.valor_cotacao) as media 
            FROM cotacoes c 
            JOIN leads l ON c.lead_id = l.id 
            WHERE l.status = 'finalizado' 
            AND c.status_cotacao = 'aceita'
            AND c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats['valor_medio_finalizados'] = $stmt->fetch(PDO::FETCH_ASSOC)['media'] ?? 0;
    }
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $stats = [
        'leads_finalizados' => 0,
        'leads_cancelados' => 0,
        'historico_mes' => 0,
        'valor_medio_finalizados' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Leads - Portal Cegonheiro</title>
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

        .breadcrumb-nav a {
            color: var(--text-light);
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            color: var(--primary-green);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-green);
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid var(--primary-green);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
        }

        .stat-change {
            background: rgba(0, 188, 117, 0.1);
            color: var(--primary-green);
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
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

        .filters-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 24px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
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
            <a href="<?php echo $eh_admin ? 'dashboard_admin.php' : 'dashboard.php'; ?>" class="logo">
                <img src="https://i.ibb.co/VcS31tMR/img-logo-portal-01.png" alt="Portal Cegonheiro">
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <?php if ($eh_admin): ?>
                <a href="dashboard_admin.php" class="menu-item">
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>
                <a href="gerenciar_leads.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    Gerenciar Leads
                </a>
                <a href="historico_leads.php" class="menu-item active">
                    <i class="fas fa-history"></i>
                    Histórico de Leads
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
                <a href="usuarios_admin.php" class="menu-item">
                    <i class="fas fa-users-cog"></i>
                    Usuários
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </a>
                <a href="leads_disponiveis.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    Leads Disponíveis
                </a>
                <a href="historico_leads.php" class="menu-item active">
                    <i class="fas fa-history"></i>
                    Histórico
                </a>
                <a href="editar_perfil.php" class="menu-item">
                    <i class="fas fa-user-edit"></i>
                    Meu Perfil
                </a>
            <?php endif; ?>
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
                    <strong>HOME</strong> > <a href="<?php echo $eh_admin ? 'dashboard_admin.php' : 'dashboard.php'; ?>">Dashboard</a> > Histórico de Leads
                </div>
                <h1 class="page-title">Histórico de Leads</h1>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar no histórico..." id="searchInput">
                </div>
                <button class="btn btn-primary" onclick="exportarHistorico()">
                    <i class="fas fa-download"></i> Exportar
                </button>
                <div class="notification-badge">
                    <i class="fas fa-envelope" style="font-size: 20px; color: var(--text-light);"></i>
                </div>
                <div class="notification-badge bell">
                    <i class="fas fa-bell" style="font-size: 20px; color: var(--text-light);"></i>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($usuario['foto_perfil'] && file_exists($usuario['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto_perfil'], ENT_QUOTES, 'UTF-8'); ?>" 
                             alt="Foto de Perfil" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder">
                            <?php echo strtoupper(substr($nome_usuario, 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_finalizados']; ?></div>
                        <div class="stat-label">Leads Finalizados</div>
                    </div>
                    <div class="stat-change">✓</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_cancelados']; ?></div>
                        <div class="stat-label">Leads Cancelados</div>
                    </div>
                    <div class="stat-change">✗</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['historico_mes']; ?></div>
                        <div class="stat-label">Finalizados este Mês</div>
                    </div>
                    <div class="stat-change">+<?php echo $stats['historico_mes']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number">R$ <?php echo number_format($stats['valor_medio_finalizados'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Valor Médio</div>
                    </div>
                    <div class="stat-change">Média</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status Final</label>
                    <select class="form-control" name="status">
                        <option value="">Todos</option>
                        <option value="finalizado" <?php echo $filtro_status == 'finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                        <option value="cancelado" <?php echo $filtro_status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <input type="text" class="form-control" name="cliente" value="<?php echo htmlspecialchars($filtro_cliente, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nome ou email">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="historico_leads.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-history"></i> Histórico de Leads (<?php echo count($historico_leads); ?>)
                </h5>
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
                                <th>Status Final</th>
                                <th>Cotações</th>
                                <th>Valor Médio</th>
                                <th>Data Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historico_leads)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i><br>
                                        <span class="text-muted">Nenhum registro no histórico</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historico_leads as $lead): ?>
                                    <tr>
                                        <td><strong>#<?php echo $lead['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lead['nome'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($lead['cidade_origem'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <i class="fas fa-arrow-right mx-1"></i>
                                            <span class="badge bg-danger"><?php echo htmlspecialchars($lead['cidade_destino'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </td>
                                        <td>
                                            <i class="fas fa-car text-primary"></i>
                                            <?php echo htmlspecialchars($lead['tipo_veiculo'], ENT_QUOTES, 'UTF-8'); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($lead['ano_modelo'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $lead['status'] == 'finalizado' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($lead['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $lead['total_cotacoes']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($lead['cotacao_media'] > 0): ?>
                                                <strong class="text-success">
                                                    R$ <?php echo number_format($lead['cotacao_media'], 2, ',', '.'); ?>
                                                </strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($lead['data_atualizacao'])); ?></td>
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

        function exportarHistorico() {
            alert('Funcionalidade de exportação será implementada.');
        }

        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
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

        console.log('Página de histórico de leads carregada com sucesso!');
        console.log('Tipo de usuário:', '<?php echo $eh_admin ? "Administrador" : "Transportadora"; ?>');
        console.log('Total de leads no histórico:', <?php echo count($historico_leads); ?>);
    </script>
</body>
</html>