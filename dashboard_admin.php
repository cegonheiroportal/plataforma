<?php
require_once 'config.php';

if (!verificarLogin() || ($_SESSION['nivel_acesso'] != 'administrador' && $_SESSION['nivel_acesso'] != 'funcionario')) {
    header('Location: login.php');
    exit;
}

// Buscar estatísticas gerais
$stats = [];

// Total de leads
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
$stats['total_leads'] = $stmt->fetch()['total'];

// Leads novos (últimos 7 dias)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['leads_novos'] = $stmt->fetch()['total'];

// Leads em andamento
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'em_andamento'");
$stats['leads_andamento'] = $stmt->fetch()['total'];

// Leads finalizados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'finalizado'");
$stats['leads_finalizados'] = $stmt->fetch()['total'];

// Total de clientes
$stmt = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM clientes WHERE status = 'ativo') + 
    (SELECT COUNT(*) FROM empresas WHERE status = 'ativo') as total_clientes");
$stats['total_clientes'] = $stmt->fetch()['total_clientes'];

// Buscar leads recentes
$stmt = $pdo->prepare("
    SELECT l.*, 
           COUNT(c.id) as total_cotacoes,
           MAX(c.data_envio) as ultima_cotacao
    FROM leads l 
    LEFT JOIN cotacoes c ON l.id = c.lead_id 
    GROUP BY l.id 
    ORDER BY l.data_cadastro DESC 
    LIMIT 10
");
$stmt->execute();
$leads_recentes = $stmt->fetchAll();

// Dados para gráficos
$stmt = $pdo->query("
    SELECT 
        DATE(data_cadastro) as data,
        COUNT(*) as total_leads
    FROM leads 
    WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(data_cadastro)
    ORDER BY data ASC
");
$chart_data = $stmt->fetchAll();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Stats Cards */
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

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-bottom: 32px;
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

        /* Chart */
        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-tabs {
            display: flex;
            gap: 8px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
        }

        .chart-tab {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.2s;
        }

        .chart-tab.active {
            background: var(--white);
            color: var(--text-dark);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 0.9rem;
        }

        .activity-content h6 {
            margin: 0 0 5px 0;
            font-weight: 600;
            color: var(--text-dark);
        }

        .activity-content p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .activity-time {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-left: auto;
        }

        /* Responsive */
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

        /* Notification badges */
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-truck"></i>
                <span>Portal Cegonheiro</span>
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item active">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="#" class="menu-item" onclick="alert('Página em desenvolvimento')">
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['total_leads']; ?></div>
                        <div class="stat-label">Total de Leads</div>
                    </div>
                    <div class="stat-change">+6%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_novos']; ?></div>
                        <div class="stat-label">Novos (7 dias)</div>
                    </div>
                    <div class="stat-change" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">-2.02%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_andamento']; ?></div>
                        <div class="stat-label">Em Andamento</div>
                    </div>
                    <div class="stat-change">+13%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['leads_finalizados']; ?></div>
                        <div class="stat-label">Finalizados</div>
                    </div>
                    <div class="stat-change">+1.03%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['total_clientes']; ?></div>
                        <div class="stat-label">Clientes Ativos</div>
                    </div>
                    <div class="stat-change">+8%</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Statistics Chart -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Estatísticas de Leads
                            <div class="chart-tabs">
                                <button class="chart-tab active">Semana</button>
                                <button class="chart-tab">Mês</button>
                                <button class="chart-tab">Ano</button>
                            </div>
                        </h3>
                        <div class="card-subtitle">
                            <strong><?php echo $stats['total_leads']; ?></strong> Total de Leads &nbsp;&nbsp; <strong><?php echo $stats['leads_novos']; ?></strong> Novos esta semana
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statisticsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Leads Recentes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Rota</th>
                                <th>Veículo</th>
                                <th>Valor do Veículo</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads_recentes as $lead): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($lead['nome']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($lead['telefone']); ?></small>
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
                                    <td><?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?></td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'novo' => 'primary',
                                            'em_andamento' => 'warning',
                                            'cotado' => 'info',
                                            'finalizado' => 'success',
                                            'cancelado' => 'danger'
                                        ];
                                        $color = $status_colors[$lead['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $lead['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="lead_detalhes.php?id=<?php echo $lead['id']; ?>" 
                                           class="btn btn-primary btn-sm" 
                                           title="Ver Detalhes do Lead #<?php echo $lead['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="lead_editar.php?id=<?php echo $lead['id']; ?>" class="btn btn-warning btn-sm" title="Editar Lead">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Activity Feed -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-clock"></i> Atividade Recente</h5>
            </div>
            <div class="card-body">
                <?php foreach (array_slice($leads_recentes, 0, 5) as $lead): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="activity-content">
                            <h6>Novo Lead</h6>
                            <p><?php echo htmlspecialchars($lead['nome']); ?> - <?php echo htmlspecialchars($lead['cidade_origem']); ?> → <?php echo htmlspecialchars($lead['cidade_destino']); ?></p>
                        </div>
                        <div class="activity-time">
                            <?php echo date('d/m H:i', strtotime($lead['data_cadastro'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Analytics Chart com dados reais
        const ctx = document.getElementById('statisticsChart').getContext('2d');
        const statisticsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("', '", array_column($chart_data, 'data')) . "'"; ?>],
                datasets: [{
                    label: 'Leads por Dia',
                    data: [<?php echo implode(', ', array_column($chart_data, 'total_leads')); ?>],
                    borderColor: '#00bc75',
                    backgroundColor: 'rgba(0, 188, 117, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });

        // Chart tabs functionality
        document.querySelectorAll('.chart-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function logout() {
            if (confirm('Tem certeza que deseja sair?')) {
                window.location.href = 'logout.php';
            }
        }

        // Auto-refresh
        setInterval(function() {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 300000);

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
    </script>
</body>
</html>