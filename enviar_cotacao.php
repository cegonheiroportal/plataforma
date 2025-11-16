<?php
require_once 'config.php';

if (!verificarLogin() || $_SESSION['nivel_acesso'] != 'cliente' || $_SESSION['tipo_cliente'] != 'pj') {
    header('Location: login.php');
    exit;
}

$cliente_id = $_SESSION['cliente_id'];
$lead_id = filter_input(INPUT_GET, 'lead_id', FILTER_VALIDATE_INT);

if (!$lead_id) {
    header('Location: dashboard_cliente.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$cliente_id]);
$transportadora = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transportadora) {
    header('Location: dashboard_cliente.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND status IN ('novo', 'em_andamento', 'cotado')");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    $_SESSION['mensagem'] = 'Lead não encontrado ou não disponível para cotação.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: dashboard_cliente.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cotacoes WHERE lead_id = ? AND transportadora_nome = ?");
$stmt->execute([$lead_id, $transportadora['nome_fantasia']]);
$ja_cotou = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

if ($ja_cotou) {
    $_SESSION['mensagem'] = 'Você já enviou uma cotação para este lead.';
    $_SESSION['tipo_mensagem'] = 'warning';
    header('Location: dashboard_cliente.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $valor_cotacao = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_cotacao']);
        $valor_cotacao = floatval($valor_cotacao);
        
        $prazo_entrega = intval($_POST['prazo_entrega']);
        $observacoes = trim($_POST['observacoes']);
        
        if ($valor_cotacao <= 0) {
            throw new Exception('Valor da cotação deve ser maior que zero.');
        }
        
        if ($prazo_entrega <= 0) {
            throw new Exception('Prazo de entrega deve ser maior que zero.');
        }
        
        // Inserir cotação apenas com campos que existem
        $stmt = $pdo->prepare("
            INSERT INTO cotacoes (
                lead_id, 
                transportadora_nome, 
                valor_cotacao, 
                prazo_entrega, 
                observacoes, 
                data_envio, 
                status_cotacao
            ) VALUES (?, ?, ?, ?, ?, NOW(), 'pendente')
        ");
        
        $stmt->execute([
            $lead_id,
            $transportadora['nome_fantasia'],
            $valor_cotacao,
            $prazo_entrega,
            $observacoes
        ]);
        
        $stmt = $pdo->prepare("UPDATE leads SET status = 'cotado' WHERE id = ? AND status = 'novo'");
        $stmt->execute([$lead_id]);
        
        $pdo->commit();
        
        $mensagem = 'Cotação enviada com sucesso!';
        $tipo_mensagem = 'success';
        
        header("refresh:3;url=dashboard_cliente.php");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Cotação - Portal Cegonheiro</title>
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

        .route-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 16px 0;
        }

        .route-point {
            background: var(--primary-green);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .route-arrow {
            color: var(--primary-green);
            font-size: 1.5rem;
        }

        .info-item {
            margin-bottom: 16px;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
            font-size: 14px;
        }

        .info-value {
            color: var(--text-light);
            font-size: 14px;
        }

        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .bg-primary { background: var(--primary-green) !important; }
        .bg-success { background: #28a745 !important; }
        .bg-warning { background: #ffc107 !important; color: #212529 !important; }
        .bg-danger { background: #dc3545 !important; }
        .bg-info { background: #17a2b8 !important; }

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

        .section-title {
            color: var(--text-dark);
            font-weight: 600;
            margin: 25px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-green);
        }

        .currency-input {
            position: relative;
        }

        .currency-input::before {
            content: 'R$';
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-weight: 500;
            z-index: 1;
        }

        .currency-input input {
            padding-left: 40px;
        }

        .success-animation {
            animation: successPulse 0.6s ease-in-out;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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

            .route-display {
                flex-direction: column;
                gap: 10px;
            }

            .route-arrow {
                transform: rotate(90deg);
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
            <a href="dashboard_cliente.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="leads_cliente.php" class="menu-item">
                <i class="fas fa-users"></i>
                Leads Disponíveis
            </a>
            <a href="minhas_cotacoes.php" class="menu-item active">
                <i class="fas fa-file-invoice-dollar"></i>
                Minhas Cotações
            </a>
            <a href="historico_cotacoes.php" class="menu-item">
                <i class="fas fa-history"></i>
                Histórico
            </a>
            <a href="perfil_cliente.php" class="menu-item">
                <i class="fas fa-user-edit"></i>
                Meu Perfil
            </a>
            <a href="configuracoes_cliente.php" class="menu-item">
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
                    <strong>HOME</strong> > <a href="dashboard_cliente.php">Dashboard</a> > Enviar Cotação
                </div>
                <h1 class="page-title">Enviar Cotação</h1>
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

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show <?php echo $tipo_mensagem == 'success' ? 'success-animation' : ''; ?>" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Nova Cotação
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="cotacaoForm">
                            <h4 class="section-title">
                                <i class="fas fa-calculator"></i> Valores da Cotação
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="valor_cotacao" class="form-label">Valor da Cotação *</label>
                                    <div class="currency-input">
                                        <input type="text" class="form-control" name="valor_cotacao" id="valor_cotacao" required placeholder="0,00">
                                    </div>
                                    <small class="text-muted">Digite o valor total do transporte</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="prazo_entrega" class="form-label">Prazo de Entrega *</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="prazo_entrega" id="prazo_entrega" min="1" max="30" required>
                                        <span class="input-group-text">dias</span>
                                    </div>
                                    <small class="text-muted">Prazo em dias úteis para entrega</small>
                                </div>
                            </div>

                            <h4 class="section-title">
                                <i class="fas fa-sticky-note"></i> Observações
                            </h4>
                            
                            <div class="mb-3">
                                <label for="observacoes" class="form-label">Observações Adicionais</label>
                                <textarea class="form-control" name="observacoes" id="observacoes" rows="4" placeholder="Inclua informações importantes sobre o transporte, condições especiais, etc."></textarea>
                                <small class="text-muted">Máximo 500 caracteres</small>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Enviar Cotação
                                </button>
                                <a href="dashboard_cliente.php" class="btn btn-outline-secondary btn-lg ms-3">
                                    <i class="fas fa-arrow-left"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-info-circle"></i> Informações do Lead
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">Cliente</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['nome']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">E-mail</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telefone</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['telefone']); ?></div>
                        </div>

                        <div class="route-display">
                            <div class="route-point">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($lead['cidade_origem']); ?>
                            </div>
                            <i class="fas fa-arrow-right route-arrow"></i>
                            <div class="route-point">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($lead['cidade_destino']); ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Tipo de Veículo</div>
                            <div class="info-value">
                                <i class="fas fa-car text-primary me-2"></i>
                                <?php echo htmlspecialchars($lead['tipo_veiculo']); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ano/Modelo</div>
                            <div class="info-value"><?php echo htmlspecialchars($lead['ano_modelo']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Data Prevista</div>
                            <div class="info-value">
                                <i class="fas fa-calendar text-warning me-2"></i>
                                <?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?>
                            </div>
                        </div>

                        <?php if (!empty($lead['observacoes'])): ?>
                            <div class="info-item">
                                <div class="info-label">Observações do Cliente</div>
                                <div class="info-value" style="background: #f8f9fa; padding: 10px; border-radius: 8px; border-left: 4px solid var(--primary-green);">
                                    <?php echo nl2br(htmlspecialchars($lead['observacoes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-building"></i> Sua Transportadora
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">Nome Fantasia</div>
                            <div class="info-value"><?php echo htmlspecialchars($transportadora['nome_fantasia']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">CNPJ</div>
                            <div class="info-value"><?php echo formatarCNPJ($transportadora['cnpj']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telefone</div>
                            <div class="info-value"><?php echo formatarTelefone($transportadora['telefone']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-lightbulb"></i> Dicas
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Seja competitivo no preço</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Ofereça prazos realistas</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Inclua informações relevantes</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Responda rapidamente</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        document.getElementById('valor_cotacao').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            e.target.value = value;
        });

        document.getElementById('cotacaoForm').addEventListener('submit', function(e) {
            const valor = document.getElementById('valor_cotacao').value;
            const prazo = document.getElementById('prazo_entrega').value;
            const observacoes = document.getElementById('observacoes').value;
            
            if (!valor || valor === '0,00') {
                e.preventDefault();
                alert('Por favor, informe o valor da cotação.');
                document.getElementById('valor_cotacao').focus();
                return false;
            }
            
            if (!prazo || prazo <= 0) {
                e.preventDefault();
                alert('Por favor, informe um prazo válido.');
                document.getElementById('prazo_entrega').focus();
                return false;
            }
            
            if (observacoes.length > 500) {
                e.preventDefault();
                alert('As observações não podem exceder 500 caracteres.');
                document.getElementById('observacoes').focus();
                return false;
            }
            
            if (!confirm('Tem certeza que deseja enviar esta cotação?')) {
                e.preventDefault();
                return false;
            }
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        document.getElementById('observacoes').addEventListener('input', function(e) {
            const maxLength = 500;
            const currentLength = e.target.value.length;
            const remaining = maxLength - currentLength;
            
            let small = e.target.nextElementSibling;
            if (remaining < 50) {
                small.style.color = remaining < 0 ? '#dc3545' : '#ffc107';
                small.textContent = `${remaining} caracteres restantes`;
            } else {
                small.style.color = '#6c757d';
                small.textContent = 'Máximo 500 caracteres';
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

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('valor_cotacao').focus();
        });

        console.log('Página de envio de cotação carregada com sucesso!');
    </script>
</body>
</html>