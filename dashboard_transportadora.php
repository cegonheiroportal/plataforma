<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login_funcional.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();
    
    // Contar leads
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'novo'");
    $total_leads = $stmt->fetch()['total'];
    
    // Buscar alguns leads
    $stmt = $pdo->query("SELECT * FROM leads WHERE status = 'novo' ORDER BY data_cadastro DESC LIMIT 5");
    $leads = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Cegonheiro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 0;
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .welcome {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }
        .leads-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .leads-header {
            background: #3498db;
            color: white;
            padding: 20px;
        }
        .lead-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        .lead-item:last-child {
            border-bottom: none;
        }
        .lead-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .lead-route {
            color: #666;
            margin-bottom: 15px;
        }
        .lead-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        .nav-link {
            color: white;
            text-decoration: none;
            margin: 0 10px;
        }
        .nav-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🚛 Portal Cegonheiro</h1>
            <div>
                <a href="leads_disponiveis.php" class="nav-link">📋 Todos os Leads</a>
                <a href="logout.php" class="nav-link">🚪 Sair</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>Bem-vindo, <?php echo htmlspecialchars($usuario['nome']); ?>!</h2>
            <p>Acompanhe seus pedidos de transporte e receba as melhores cotações.</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_leads; ?></div>
                <div>Leads Disponíveis</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div>Cotações Enviadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">0</div>
                <div>Contratos Fechados</div>
            </div>
        </div>
        
        <div class="leads-section">
            <div class="leads-header">
                <h3>📋 Leads Recentes</h3>
            </div>
            
            <?php if (empty($leads)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <h4>📭 Nenhum lead disponível</h4>
                    <p>Novos pedidos aparecerão aqui quando forem cadastrados.</p>
                </div>
            <?php else: ?>
                <?php foreach ($leads as $lead): ?>
                    <div class="lead-item">
                        <div class="lead-title"><?php echo htmlspecialchars($lead['nome']); ?></div>
                        <div class="lead-route">
                            📍 <?php echo htmlspecialchars($lead['cidade_origem']); ?> 
                            ➡️ 
                            🎯 <?php echo htmlspecialchars($lead['cidade_destino']); ?>
                        </div>
                        
                        <div class="lead-details">
                            <div>🚗 <strong>Veículo:</strong> <?php echo htmlspecialchars($lead['tipo_veiculo']); ?></div>
                            <div>🏷️ <strong>Modelo:</strong> <?php echo htmlspecialchars($lead['ano_modelo']); ?></div>
                            <div>💰 <strong>Valor:</strong> R\$ <?php echo number_format($lead['valor_veiculo'], 2, ',', '.'); ?></div>
                            <div>📅 <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?></div>
                        </div>
                        
                        <div>
                            <a href="detalhes_lead.php?id=<?php echo $lead['id']; ?>" class="btn">👁️ Ver Detalhes</a>
                            <a href="enviar_cotacao.php?id=<?php echo $lead['id']; ?>" class="btn btn-success">💰 Enviar Cotação</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="padding: 20px; text-align: center; background: #f8f9fa;">
                    <a href="leads_disponiveis.php" class="btn">📋 Ver Todos os Leads</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>