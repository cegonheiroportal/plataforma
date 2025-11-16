<?php
// leads_disponiveis_simples.php - Sistema de leads funcional
session_start();

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login_funcional.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Buscar leads disponíveis
$stmt = $pdo->query("SELECT * FROM leads WHERE status = 'novo' ORDER BY data_cadastro DESC");
$leads = $stmt->fetchAll();

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Disponíveis - Portal Cegonheiro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.2s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .leads-grid {
            display: grid;
            gap: 20px;
        }
        
        .lead-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        .lead-card:hover {
            transform: translateY(-5px);
        }
        
        .lead-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .lead-body {
            padding: 25px;
        }
        
        .lead-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-item strong {
            color: #333;
        }
        
        .route {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>🚛 Portal Cegonheiro</h1>
            </div>
            <div class="nav-links">
                <a href="dashboard_simples.php">🏠 Dashboard</a>
                <a href="leads_disponiveis_simples.php">📋 Leads</a>
                <a href="cadastro_cliente.php">🏢 Clientes</a>
                <span>👤 <?php echo htmlspecialchars($usuario['nome']); ?></span>
                <a href="logout.php">🚪 Sair</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h2>📋 Leads Disponíveis</h2>
            <p style="color: #666; margin-top: 10px;">
                Visualize e gerencie todos os leads de transporte disponíveis no sistema.
            </p>
        </div>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($leads); ?></div>
                <div>Total de Leads</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count(array_filter($leads, function($l) { return $l['tipo_veiculo'] === 'Carro'; })); ?></div>
                <div>Carros</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count(array_filter($leads, function($l) { return $l['tipo_veiculo'] === 'Moto'; })); ?></div>
                <div>Motos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    $hoje = new DateTime();
                    $urgentes = array_filter($leads, function($l) use ($hoje) {
                        $data_prevista = new DateTime($l['data_prevista']);
                        $diff = $hoje->diff($data_prevista)->days;
                        return $diff <= 7;
                    });
                    echo count($urgentes);
                    ?>
                </div>
                <div>Urgentes (7 dias)</div>
            </div>
        </div>
        
        <?php if (empty($leads)): ?>
            <div class="empty-state">
                <h3>📭 Nenhum lead disponível</h3>
                <p style="color: #666; margin: 15px 0;">
                    Não há leads novos no momento. Novos leads aparecerão aqui quando forem cadastrados.
                </p>
                <a href="cadastro_lead.php" class="btn btn-primary">➕ Cadastrar Lead de Teste</a>
            </div>
        <?php else: ?>
            <div class="leads-grid">
                <?php foreach ($leads as $lead): ?>
                    <?php
                    // Calcular urgência
                    $hoje = new DateTime();
                    $data_prevista = new DateTime($lead['data_prevista']);
                    $diff = $hoje->diff($data_prevista)->days;
                    $urgente = $diff <= 7;
                    ?>
                    
                    <div class="lead-card">
                        <div class="lead-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h3>🚗 <?php echo htmlspecialchars($lead['nome']); ?></h3>
                                <?php if ($urgente): ?>
                                    <span style="background: #dc3545; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                        🔥 URGENTE
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p style="opacity: 0.9; margin-top: 5px;">
                                Lead #<?php echo $lead['id']; ?> • 
                                Cadastrado em <?php echo date('d/m/Y', strtotime($lead['data_cadastro'])); ?>
                            </p>
                        </div>
                        
                        <div class="lead-body">
                            <div class="route">
                                <strong style="font-size: 18px;">
                                    📍 <?php echo htmlspecialchars($lead['cidade_origem']); ?> 
                                    ➡️ 
                                    🎯 <?php echo htmlspecialchars($lead['cidade_destino']); ?>
                                </strong>
                            </div>
                            
                            <div class="lead-info">
                                <div class="info-item">
                                    <span>📱</span>
                                    <div>
                                        <strong>Telefone:</strong><br>
                                        <?php echo htmlspecialchars($lead['telefone']); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span>📧</span>
                                    <div>
                                        <strong>Email:</strong><br>
                                        <?php echo htmlspecialchars($lead['email']); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span>🚗</span>
                                    <div>
                                        <strong>Veículo:</strong><br>
                                        <?php echo htmlspecialchars($lead['tipo_veiculo']); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span>🏷️</span>
                                    <div>
                                        <strong>Modelo:</strong><br>
                                        <?php echo htmlspecialchars($lead['ano_modelo']); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span>💰</span>
                                    <div>
                                        <strong>Valor:</strong><br>
                                        R\$ <?php echo number_format($lead['valor_veiculo'], 2, ',', '.'); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span>📅</span>
                                    <div>
                                        <strong>Data Prevista:</strong><br>
                                        <?php echo date('d/m/Y', strtotime($lead['data_prevista'])); ?>
                                        <?php if ($urgente): ?>
                                            <br><small style="color: #dc3545;">⚠️ <?php echo $diff; ?> dias</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($lead['observacoes']): ?>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                    <strong>📝 Observações:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($lead['observacoes'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <a href="detalhes_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-primary">
                                    👁️ Ver Detalhes
                                </a>
                                <a href="enviar_cotacao.php?id=<?php echo $lead['id']; ?>" class="btn">
                                    💰 Enviar Cotação
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 40px; text-align: center;">
            <a href="dashboard_simples.php" class="btn btn-primary">
                🏠 Voltar ao Dashboard
            </a>
        </div>
    </div>
</body>
</html>