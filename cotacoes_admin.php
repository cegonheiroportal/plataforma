<?php
require_once 'config.php';

if (!verificarLogin() || ($_SESSION['nivel_acesso'] != 'administrador' && $_SESSION['nivel_acesso'] != 'funcionario')) {
    header('Location: login.php');
    exit;
}

// Buscar cotações com informações do lead
$stmt = $pdo->query("
    SELECT c.*, l.nome as cliente_nome, l.email as cliente_email, l.telefone as cliente_telefone,
           l.cidade_origem, l.cidade_destino, l.tipo_veiculo, l.ano_modelo, l.data_prevista
    FROM cotacoes c
    JOIN leads l ON c.lead_id = l.id
    ORDER BY c.data_envio DESC
    LIMIT 100
");
$cotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cotacoes");
$stats['total_cotacoes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM cotacoes WHERE status_cotacao = 'enviada'");
$stats['cotacoes_pendentes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM cotacoes WHERE status_cotacao = 'aceita'");
$stats['cotacoes_aceitas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT AVG(valor_cotacao) as media FROM cotacoes WHERE valor_cotacao > 0");
$stats['valor_medio'] = $stmt->fetch(PDO::FETCH_ASSOC)['media'] ?? 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotações - Portal Cegonheiro</title>
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
            <a href="#" class="menu-item" onclick="alert('Página em desenvolvimento')">
                <i class="fas fa-users"></i>
                Gerenciar Leads
            </a>
            <a href="clientes_admin.php" class="menu-item">
                <i class="fas fa-building"></i>
                Clientes
            </a>
            <a href="cotacoes_admin.php" class="menu-item active">
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

    <div class="main-content">
        <div class="page-header">
            <div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > <a href="dashboard_admin.php">Dashboard</a> > Cotações
                </div>
                <h1 class="page-title">Gerenciar Cotações</h1>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar cotações..." id="searchInput">
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['total_cotacoes']; ?></div>
                        <div class="stat-label">Total de Cotações</div>
                    </div>
                    <div class="stat-change">+12%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['cotacoes_pendentes']; ?></div>
                        <div class="stat-label">Cotações Pendentes</div>
                    </div>
                    <div class="stat-change">+5%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['cotacoes_aceitas']; ?></div>
                        <div class="stat-label">Cotações Aceitas</div>
                    </div>
                                        <div class="stat-change">+8%</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number">R$ <?php echo number_format($stats['valor_medio'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Valor Médio</div>
                    </div>
                    <div class="stat-change">+3%</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-file-invoice-dollar"></i> Lista de Cotações
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Transportadora</th>
                                <th>Rota</th>
                                <th>Valor</th>
                                <th>Prazo</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cotacoes)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i><br>
                                        <span class="text-muted">Nenhuma cotação encontrada</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cotacoes as $cotacao): ?>
                                    <tr>
                                        <td><strong>#<?php echo $cotacao['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cotacao['cliente_nome']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($cotacao['cliente_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($cotacao['transportadora_nome']); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($cotacao['cidade_origem']); ?></span>
                                            <i class="fas fa-arrow-right mx-1"></i>
                                            <span class="badge bg-danger"><?php echo htmlspecialchars($cotacao['cidade_destino']); ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                R$ <?php echo number_format($cotacao['valor_cotacao'], 2, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $cotacao['prazo_entrega']; ?> dias</td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'enviada' => 'warning',
                                                'aceita' => 'success',
                                                'recusada' => 'danger',
                                                'expirada' => 'secondary'
                                            ];
                                            $color = $status_colors[$cotacao['status_cotacao']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($cotacao['status_cotacao']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($cotacao['data_envio'])); ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="verCotacao(<?php echo $cotacao['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
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

        function verCotacao(id) {
            alert(`Ver detalhes da cotação ID: ${id}`);
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

        console.log('Página de cotações carregada com sucesso!');
    </script>
</body>
</html>