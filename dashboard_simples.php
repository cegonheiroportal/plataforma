<?php
// dashboard_simples.php - Dashboard após login
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

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

// Contar leads disponíveis
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE status = 'novo'");
$total_leads = $stmt->fetch()['total'];

// Contar clientes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE status = 'ativo'");
$total_clientes = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Cegonheiro</title>
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
        
        .logo h1 {
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #667eea;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>🚛 Portal Cegonheiro</h1>
            </div>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($usuario['nome']); ?></span>
                <span>|</span>
                <span><?php echo ucfirst($usuario['nivel_acesso']); ?></span>
                <a href="logout.php" style="color: white; text-decoration: none;">🚪 Sair</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-card">
            <h2>🎉 Bem-vindo ao Portal Cegonheiro!</h2>
            <p style="margin-top: 10px; color: #666;">
                Olá, <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong>! 
                Você está logado como <strong><?php echo $usuario['nivel_acesso']; ?></strong>.
            </p>
            <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 8px;">
                <h3 style="color: #155724;">✅ Login Funcionando Perfeitamente!</h3>
                <p style="color: #155724; margin-top: 5px;">
                    Sessão ativa desde: <?php echo date('d/m/Y H:i:s'); ?>
                </p>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_leads; ?></div>
                <h3>📋 Leads Disponíveis</h3>
                <p>Novos leads aguardando cotação</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_clientes; ?></div>
                <h3>🏢 Clientes Ativos</h3>
                <p>Transportadoras cadastradas</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">✅</div>
                <h3>🔑 Sistema Online</h3>
                <p>Todos os serviços funcionando</p>
            </div>
        </div>
        
        <div class="actions-grid">
            <div class="action-card">
                <h3>📋 Gerenciar Leads</h3>
                <p>Visualize e gerencie todos os leads disponíveis no sistema.</p>
                <div style="margin-top: 20px;">
                    <a href="leads_disponiveis_simples.php" class="btn">Ver Leads</a>
                </div>
            </div>
            
            <div class="action-card">
                <h3>🏢 Gerenciar Clientes</h3>
                <p>Cadastre e gerencie transportadoras e seus planos.</p>
                <div style="margin-top: 20px;">
                    <a href="cadastro_cliente.php" class="btn">Gerenciar Clientes</a>
                </div>
            </div>
            
            <div class="action-card">
                <h3>👤 Gerenciar Usuários</h3>
                <p>Cadastre novos usuários e gerencie permissões.</p>
                <div style="margin-top: 20px;">
                    <a href="cadastro_usuario.php" class="btn">Gerenciar Usuários</a>
                </div>
            </div>
            
            <div class="action-card">
                <h3>⚙️ Configurações</h3>
                <p>Configure parâmetros do sistema e preferências.</p>
                <div style="margin-top: 20px;">
                    <a href="#" class="btn btn-secondary">Configurações</a>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <h3>🔍 Informações da Sessão (Debug)</h3>
            <div style="background: white; padding: 20px; border-radius: 10px; margin-top: 15px; text-align: left;">
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;">
<?php print_r($_SESSION); ?>
                </pre>
            </div>
        </div>
    </div>
</body>
</html>