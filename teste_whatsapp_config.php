<?php
require_once 'config.php';

// Verificar se está logado
if (!verificarLogin()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar ações de teste
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao == 'criar_configuracao') {
        try {
            // Criar configuração padrão para o usuário
            $stmt = $pdo->prepare("
                INSERT INTO user_configuracoes (
                    user_id, notificacoes_email, notificacoes_push, notificacoes_leads, 
                    notificacoes_cotacoes, notificacoes_whatsapp, tema_escuro, idioma, 
                    fuso_horario, privacidade_perfil, receber_newsletter
                ) VALUES (?, 1, 1, 1, 1, 1, 0, 'pt-BR', 'America/Sao_Paulo', 'publico', 1)
                ON DUPLICATE KEY UPDATE
                    notificacoes_whatsapp = 1
            ");
            $stmt->execute([$user_id]);
            
            $mensagem = '✅ Configuração padrão criada com sucesso!';
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            $mensagem = '❌ Erro ao criar configuração: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    } elseif ($acao == 'testar_notificacao') {
        try {
            // Simular teste de notificação
            require_once 'notificacao_whatsapp.php';
            
            // Criar um lead fictício para teste
            $lead_teste = [
                'id' => 999,
                'tipo_veiculo' => 'Sedan',
                'ano_modelo' => '2020/2021',
                'cidade_origem' => 'São Paulo',
                'cidade_destino' => 'Rio de Janeiro',
                'data_prevista' => date('Y-m-d', strtotime('+7 days')),
                'valor_veiculo' => 45000,
                'data_cadastro' => date('Y-m-d H:i:s')
            ];
            
            $mensagem = '🧪 Teste de notificação simulado! (Em produção, seria enviado via WhatsApp)';
            $tipo_mensagem = 'info';
        } catch (Exception $e) {
            $mensagem = '❌ Erro no teste: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
}

// Verificar configurações do usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM user_configuracoes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $config = $stmt->fetch();
} catch (Exception $e) {
    $config = null;
}

// Verificar dados do usuário
try {
    $stmt = $pdo->prepare("SELECT u.*, c.* FROM usuarios u LEFT JOIN clientes c ON u.email = c.email WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
} catch (Exception $e) {
    $usuario = null;
}

// Verificar estrutura da tabela
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM user_configuracoes");
    $colunas = $stmt->fetchAll();
} catch (Exception $e) {
    $colunas = [];
}

// Verificar se tem leads para teste
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads LIMIT 1");
    $total_leads = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $total_leads = 0;
}

// Verificar logs de notificação
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notificacoes_log 
        WHERE cliente_id = ? 
        ORDER BY data_envio DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $logs_notificacao = $stmt->fetchAll();
} catch (Exception $e) {
    $logs_notificacao = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste WhatsApp - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .test-header {
            background: linear-gradient(135deg, #00bc75, #07a368);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .test-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .test-card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-card-body {
            padding: 20px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .btn-test {
            background: #25d366;
            border-color: #25d366;
            color: white;
        }
        
        .btn-test:hover {
            background: #20c05a;
            border-color: #20c05a;
            color: white;
        }
        
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 16px 20px;
        }
        
        .table {
            font-size: 14px;
        }
        
        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        
        .nav-pills .nav-link.active {
            background: #00bc75;
        }
        
        .tab-content {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="test-header">
            <h1><i class="fab fa-whatsapp me-3"></i>Teste de Configurações WhatsApp</h1>
            <p class="mb-0">Diagnóstico completo do sistema de notificações</p>
        </div>

        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : ($tipo_mensagem == 'warning' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-3" id="testTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="status-tab" data-bs-toggle="pill" data-bs-target="#status" type="button">
                    <i class="fas fa-info-circle me-2"></i>Status Geral
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="config-tab" data-bs-toggle="pill" data-bs-target="#config" type="button">
                    <i class="fas fa-cog me-2"></i>Configurações
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="database-tab" data-bs-toggle="pill" data-bs-target="#database" type="button">
                    <i class="fas fa-database me-2"></i>Banco de Dados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="test-tab" data-bs-toggle="pill" data-bs-target="#test" type="button">
                    <i class="fas fa-flask me-2"></i>Testes
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="testTabsContent">
            <!-- Status Geral -->
            <div class="tab-pane fade show active" id="status" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="test-card">
                            <div class="test-card-header">
                                <i class="fas fa-user"></i>
                                Dados do Usuário
                            </div>
                            <div class="test-card-body">
                                <?php if ($usuario): ?>
                                    <div class="info-item">
                                        <span class="info-label">Nome:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($usuario['nome'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Telefone:</span>
                                        <span class="info-value">
                                            <?php if (!empty($usuario['telefone'])): ?>
                                                <span class="status-badge status-success">
                                                    <?php echo htmlspecialchars($usuario['telefone']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-danger">NÃO CADASTRADO</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status:</span>
                                        <span class="info-value">
                                            <span class="status-badge <?php echo $usuario['status'] == 'ativo' ? 'status-success' : 'status-danger'; ?>">
                                                <?php echo strtoupper($usuario['status'] ?? 'N/A'); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Tipo Cliente:</span>
                                        <span class="info-value"><?php echo strtoupper($usuario['tipo_cliente'] ?? 'N/A'); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Erro ao carregar dados do usuário
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="test-card">
                            <div class="test-card-header">
                                <i class="fas fa-bell"></i>
                                Status das Notificações
                            </div>
                            <div class="test-card-body">
                                <?php if ($config): ?>
                                    <div class="info-item">
                                        <span class="info-label">WhatsApp:</span>
                                        <span class="info-value">
                                            <span class="status-badge <?php echo $config['notificacoes_whatsapp'] ? 'status-success' : 'status-danger'; ?>">
                                                <?php echo $config['notificacoes_whatsapp'] ? 'ATIVADO' : 'DESATIVADO'; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value">
                                            <span class="status-badge <?php echo $config['notificacoes_email'] ? 'status-success' : 'status-danger'; ?>">
                                                <?php echo $config['notificacoes_email'] ? 'ATIVADO' : 'DESATIVADO'; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Leads:</span>
                                        <span class="info-value">
                                            <span class="status-badge <?php echo $config['notificacoes_leads'] ? 'status-success' : 'status-danger'; ?>">
                                                <?php echo $config['notificacoes_leads'] ? 'ATIVADO' : 'DESATIVADO'; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Push:</span>
                                        <span class="info-value">
                                            <span class="status-badge <?php echo $config['notificacoes_push'] ? 'status-success' : 'status-danger'; ?>">
                                                <?php echo $config['notificacoes_push'] ? 'ATIVADO' : 'DESATIVADO'; ?>
                                            </span>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Nenhuma configuração encontrada
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="acao" value="criar_configuracao">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus me-2"></i>Criar Configuração Padrão
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status do Sistema -->
                <div class="test-card">
                    <div class="test-card-header">
                        <i class="fas fa-server"></i>
                        Status do Sistema
                    </div>
                    <div class="test-card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-item">
                                    <span class="info-label">Tabela user_configuracoes:</span>
                                    <span class="status-badge status-success">EXISTE</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-item">
                                    <span class="info-label">Coluna notificacoes_whatsapp:</span>
                                    <span class="status-badge <?php echo in_array('notificacoes_whatsapp', array_column($colunas, 'Field')) ? 'status-success' : 'status-danger'; ?>">
                                        <?php echo in_array('notificacoes_whatsapp', array_column($colunas, 'Field')) ? 'EXISTE' : 'NÃO EXISTE'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-item">
                                    <span class="info-label">Leads no sistema:</span>
                                    <span class="status-badge <?php echo $total_leads > 0 ? 'status-success' : 'status-warning'; ?>">
                                        <?php echo $total_leads; ?> LEADS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnóstico WhatsApp -->
                <div class="test-card">
                    <div class="test-card-header">
                        <i class="fab fa-whatsapp"></i>
                        Diagnóstico WhatsApp
                    </div>
                    <div class="test-card-body">
                        <?php
                        $pode_whatsapp = !empty($usuario['telefone']) && ($config['notificacoes_whatsapp'] ?? 0);
                        $problemas = [];
                        
                        if (empty($usuario['telefone'])) {
                            $problemas[] = 'Telefone não cadastrado no perfil';
                        }
                        if (!($config['notificacoes_whatsapp'] ?? 0)) {
                            $problemas[] = 'Notificações WhatsApp desativadas';
                        }
                        if (!$config) {
                            $problemas[] = 'Configurações não encontradas';
                        }
                        ?>
                        
                        <div class="alert <?php echo $pode_whatsapp ? 'alert-success' : 'alert-danger'; ?>">
                            <h6 class="mb-2">
                                <i class="fas fa-<?php echo $pode_whatsapp ? 'check-circle' : 'times-circle'; ?> me-2"></i>
                                Status: <?php echo $pode_whatsapp ? 'PODE RECEBER WHATSAPP' : 'NÃO PODE RECEBER WHATSAPP'; ?>
                            </h6>
                            
                            <?php if (!$pode_whatsapp && !empty($problemas)): ?>
                                <p class="mb-2"><strong>Problemas encontrados:</strong></p>
                                <ul class="mb-2">
                                    <?php foreach ($problemas as $problema): ?>
                                        <li><?php echo $problema; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <p class="mb-0"><strong>Soluções:</strong></p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if (empty($usuario['telefone'])): ?>
                                        <a href="editar_perfil.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-user-edit me-1"></i>Cadastrar Telefone
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!($config['notificacoes_whatsapp'] ?? 0)): ?>
                                        <a href="configuracoes.php" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-cog me-1"></i>Ativar WhatsApp
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configurações -->
            <div class="tab-pane fade" id="config" role="tabpanel">
                <div class="test-card">
                    <div class="test-card-header">
                        <i class="fas fa-list"></i>
                        Todas as Configurações
                    </div>
                    <div class="test-card-body">
                        <?php if ($config): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Configuração</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Notificações Email</td>
                                            <td><?php echo $config['notificacoes_email'] ? 'Ativado' : 'Desativado'; ?></td>
                                            <td><span class="status-badge <?php echo $config['notificacoes_email'] ? 'status-success' : 'status-danger'; ?>">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Notificações Push</td>
                                            <td><?php echo $config['notificacoes_push'] ? 'Ativado' : 'Desativado'; ?></td>
                                            <td><span class="status-badge <?php echo $config['notificacoes_push'] ? 'status-success' : 'status-danger'; ?>">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Notificações Leads</td>
                                            <td><?php echo $config['notificacoes_leads'] ? 'Ativado' : 'Desativado'; ?></td>
                                            <td><span class="status-badge <?php echo $config['notificacoes_leads'] ? 'status-success' : 'status-danger'; ?>">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Notificações Cotações</td>
                                            <td><?php echo $config['notificacoes_cotacoes'] ? 'Ativado' : 'Desativado'; ?></td>
                                            <td><span class="status-badge <?php echo $config['notificacoes_cotacoes'] ? 'status-success' : 'status-danger'; ?>">●</span></td>
                                        </tr>
                                        <tr style="background: #f8f9fa;">
                                            <td><strong>Notificações WhatsApp</strong></td>
                                            <td><strong><?php echo $config['notificacoes_whatsapp'] ? 'Ativado' : 'Desativado'; ?></strong></td>
                                            <td><span class="status-badge <?php echo $config['notificacoes_whatsapp'] ? 'status-success' : 'status-danger'; ?>">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Tema Escuro</td>
                                            <td><?php echo $config['tema_escuro'] ? 'Ativado' : 'Desativado'; ?></td>
                                            <td><span class="status-badge <?php echo $config['tema_escuro'] ? 'status-info' : 'status-warning'; ?>">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Idioma</td>
                                            <td><?php echo $config['idioma']; ?></td>
                                            <td><span class="status-badge status-info">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Fuso Horário</td>
                                            <td><?php echo $config['fuso_horario']; ?></td>
                                            <td><span class="status-badge status-info">●</span></td>
                                        </tr>
                                        <tr>
                                            <td>Newsletter</td>
                                            <td><?php echo $config['receber_newsletter'] ? 'Ativado' : 'Desativado'; ?></td>
                                            <td><span class="status-badge <?php echo $config['receber_newsletter'] ? 'status-success' : 'status-danger'; ?>">●</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Nenhuma configuração encontrada para este usuário.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Banco de Dados -->
            <div class="tab-pane fade" id="database" role="tabpanel">
                <div class="test-card">
                    <div class="test-card-header">
                        <i class="fas fa-table"></i>
                        Estrutura da Tabela user_configuracoes
                    </div>
                    <div class="test-card-body">
                        <?php if (!empty($colunas)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Campo</th>
                                            <th>Tipo</th>
                                            <th>Nulo</th>
                                            <th>Chave</th>
                                            <th>Padrão</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($colunas as $coluna): ?>
                                            <tr <?php echo $coluna['Field'] == 'notificacoes_whatsapp' ? 'style="background: #e8f5e8;"' : ''; ?>>
                                                <td>
                                                    <strong><?php echo $coluna['Field']; ?></strong>
                                                    <?php if ($coluna['Field'] == 'notificacoes_whatsapp'): ?>
                                                        <span class="badge bg-success ms-2">WHATSAPP</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $coluna['Type']; ?></td>
                                                <td><?php echo $coluna['Null']; ?></td>
                                                <td><?php echo $coluna['Key']; ?></td>
                                                <td><?php echo $coluna['Default']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Erro ao carregar estrutura da tabela.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Logs de Notificação -->
                <?php if (!empty($logs_notificacao)): ?>
                    <div class="test-card">
                        <div class="test-card-header">
                            <i class="fas fa-history"></i>
                            Últimas Notificações (5 mais recentes)
                        </div>
                        <div class="test-card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Tipo</th>
                                            <th>Lead ID</th>
                                            <th>Status</th>
                                            <th>Erro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs_notificacao as $log): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i', strtotime($log['data_envio'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $log['tipo'] == 'whatsapp' ? 'success' : 'primary'; ?>">
                                                        <?php echo strtoupper($log['tipo']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $log['lead_id']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $log['status'] == 'enviado' ? 'status-success' : 'status-danger'; ?>">
                                                        <?php echo strtoupper($log['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $log['mensagem_erro'] ? substr($log['mensagem_erro'], 0, 50) . '...' : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Testes -->
            <div class="tab-pane fade" id="test" role="tabpanel">
                <div class="test-card">
                    <div class="test-card-header">
                        <i class="fas fa-flask"></i>
                        Testes de Funcionalidade
                    </div>
                    <div class="test-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Teste de Notificação Simulada</h6>
                                <p class="text-muted">Simula o envio de uma notificação WhatsApp</p>
                                <form method="POST">
                                    <input type="hidden" name="acao" value="testar_notificacao">
                                    <button type="submit" class="btn btn-test">
                                        <i class="fab fa-whatsapp me-2"></i>Simular Notificação
                                    </button>
                                </form>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Links Úteis</h6>
                                <div class="d-flex flex-column gap-2">
                                    <a href="configuracoes.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-cog me-2"></i>Ir para Configurações
                                    </a>
                                    <a href="editar_perfil.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-user-edit me-2"></i>Editar Perfil
                                    </a>
                                    <a href="dashboard.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-home me-2"></i>Voltar ao Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exemplo de Mensagem WhatsApp -->
                <div class="test-card">
                    <div class="test-card-header">
                        <i class="fas fa-comment"></i>
                        Preview da Mensagem WhatsApp
                    </div>
                    <div class="test-card-body">
                        <div class="code-block">
🚚 <strong>Portal Cegonheiro</strong> 🚚

Olá <strong><?php echo explode(' ', $usuario['nome'] ?? 'Cliente')[0]; ?></strong>! 👋

🆕 <strong>NOVO LEAD DISPONÍVEL!</strong>

📋 <strong>Detalhes do Transporte:</strong>
🚗 Veículo: <strong>Sedan</strong>
📅 Ano/Modelo: <strong>2020/2021</strong>
📍 Origem: <strong>São Paulo</strong>
🎯 Destino: <strong>Rio de Janeiro</strong>
📅 Data Prevista: <strong><?php echo date('d/m/Y', strtotime('+7 days')); ?></strong>
💰 Valor do Veículo: <strong>R$ 45.000,00</strong>

⏰ <strong>Cadastrado em:</strong> <?php echo date('d/m/Y H:i'); ?>

🔥 <strong>Não perca esta oportunidade!</strong>
Acesse o portal agora e envie sua cotação:

🌐 https://seudominio.com/leads_disponiveis.php

💡 <strong>Dica:</strong> Seja rápido! Os melhores leads são disputados.

---
Portal Cegonheiro - Conectando transportadoras e clientes
📱 Para parar de receber: /configuracoes
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Esta página é apenas para testes e diagnósticos. 
                <a href="configuracoes.php">Voltar para Configurações</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh da página a cada 30 segundos se estiver na aba ativa
        let autoRefresh;
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(autoRefresh);
            } else {
                autoRefresh = setInterval(() => {
                    if (confirm('Atualizar página para verificar mudanças?')) {
                        location.reload();
                    }
                }, 30000);
            }
        });

        // Highlight da coluna WhatsApp
        document.addEventListener('DOMContentLoaded', function() {
            const whatsappRows = document.querySelectorAll('tr:has(td:contains("WhatsApp"))');
            whatsappRows.forEach(row => {
                row.style.background = '#e8f5e8';
            });
        });

        console.log('🧪 Página de teste WhatsApp carregada');
        console.log('👤 Usuário ID:', <?php echo $user_id; ?>);
        console.log('📱 WhatsApp configurado:', <?php echo ($config['notificacoes_whatsapp'] ?? 0) ? 'true' : 'false'; ?>);
        console.log('📞 Telefone:', '<?php echo !empty($usuario['telefone']) ? 'Configurado' : 'Não configurado'; ?>');
    </script>
</body>
</html>