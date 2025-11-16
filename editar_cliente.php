<?php
require_once 'config.php';

if (!verificarLogin() || ($_SESSION['nivel_acesso'] != 'administrador' && $_SESSION['nivel_acesso'] != 'funcionario')) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';
$cliente = null;

// Buscar cliente para edição
if (isset($_GET['id'])) {
    $cliente_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        header('Location: clientes_admin.php');
        exit;
    }
} else {
    header('Location: clientes_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']);
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
        $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
        $endereco = trim($_POST['endereco']);
        $bairro = trim($_POST['bairro']);
        $numero = trim($_POST['numero']);
        $plano = $_POST['plano'];
        $data_vencimento = $_POST['data_vencimento'];
        $status = $_POST['status'];
        
        // Atualizar cliente
        $stmt = $pdo->prepare("
            UPDATE clientes SET 
            nome = ?, email = ?, telefone = ?, cpf = ?, cep = ?, 
            endereco = ?, bairro = ?, numero = ?, plano = ?, 
            data_vencimento = ?, status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nome, $email, $telefone, $cpf, $cep, 
            $endereco, $bairro, $numero, $plano, 
            $data_vencimento, $status, $cliente_id
        ]);
        
        $mensagem = 'Cliente atualizado com sucesso!';
        $tipo_mensagem = 'success';
        
        // Recarregar dados do cliente
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $mensagem = 'E-mail já cadastrado no sistema.';
        } else {
            $mensagem = 'Erro ao atualizar cliente. Tente novamente.';
        }
        $tipo_mensagem = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - Portal Cegonheiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            --sidebar-width: 280px;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: var(--text-dark);
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

        .logo img {
            height: 40px;
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

        .breadcrumb-nav {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .form-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--white);
        }

        .form-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-body {
            padding: 24px;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 188, 117, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
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

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://i.ibb.co/tpmsDyMm/img-logo-portal-03.png" alt="Portal Cegonheiro">
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="clientes_admin.php" class="menu-item active">
                <i class="fas fa-building"></i>
                Clientes
            </a>
            <a href="usuarios_admin.php" class="menu-item">
                <i class="fas fa-users"></i>
                Usuários
            </a>
            <a href="cadastro_cliente.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                Novo Cliente
            </a>
            <a href="cadastro_usuario.php" class="menu-item">
                <i class="fas fa-user-cog"></i>
                Novo Usuário
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb-nav">
                    <strong>HOME</strong> > Dashboard > <a href="clientes_admin.php">Clientes</a> > Editar Cliente
                </div>
                <h1 class="page-title">Editar Cliente</h1>
            </div>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-edit"></i>
                    Editar Dados do Cliente
                </h2>
            </div>
            
            <div class="form-body">
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $mensagem; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="editarForm">
                    <h4 class="section-title">
                        <i class="fas fa-building"></i> Dados da Empresa
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                                                       <label for="nome" class="form-label">Nome da Empresa *</label>
                            <input type="text" class="form-control" name="nome" id="nome" 
                                   value="<?php echo htmlspecialchars($cliente['nome']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">E-mail *</label>
                            <input type="email" class="form-control" name="email" id="email" 
                                   value="<?php echo htmlspecialchars($cliente['email']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefone" class="form-label">Telefone *</label>
                            <input type="text" class="form-control" name="telefone" id="telefone" 
                                   value="<?php echo htmlspecialchars($cliente['telefone']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cpf" class="form-label">CPF/CNPJ *</label>
                            <input type="text" class="form-control" name="cpf" id="cpf" 
                                   value="<?php echo htmlspecialchars($cliente['cpf']); ?>" required>
                        </div>
                    </div>

                    <h4 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Endereço
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="cep" class="form-label">CEP *</label>
                            <input type="text" class="form-control" name="cep" id="cep" 
                                   value="<?php echo htmlspecialchars($cliente['cep']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="endereco" class="form-label">Endereço *</label>
                            <input type="text" class="form-control" name="endereco" id="endereco" 
                                   value="<?php echo htmlspecialchars($cliente['endereco']); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="numero" class="form-label">Número *</label>
                            <input type="text" class="form-control" name="numero" id="numero" 
                                   value="<?php echo htmlspecialchars($cliente['numero']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bairro" class="form-label">Bairro *</label>
                            <input type="text" class="form-control" name="bairro" id="bairro" 
                                   value="<?php echo htmlspecialchars($cliente['bairro']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="ativo" <?php echo $cliente['status'] == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo $cliente['status'] == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                    </div>

                    <h4 class="section-title">
                        <i class="fas fa-credit-card"></i> Plano e Pagamento
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="plano" class="form-label">Plano *</label>
                            <select class="form-select" name="plano" id="plano" required>
                                <option value="">Selecione o plano</option>
                                <option value="basico" <?php echo $cliente['plano'] == 'basico' ? 'selected' : ''; ?>>Básico - R\$ 99/mês</option>
                                <option value="intermediario" <?php echo $cliente['plano'] == 'intermediario' ? 'selected' : ''; ?>>Intermediário - R\$ 199/mês</option>
                                <option value="premium" <?php echo $cliente['plano'] == 'premium' ? 'selected' : ''; ?>>Premium - R\$ 299/mês</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_vencimento" class="form-label">Data de Vencimento *</label>
                            <input type="date" class="form-control" name="data_vencimento" id="data_vencimento" 
                                   value="<?php echo $cliente['data_vencimento']; ?>" required>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        <a href="clientes_admin.php" class="btn btn-outline-secondary btn-lg ms-3">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Máscaras de input
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.replace(/^(\d\d)(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length > 5) {
                value = value.replace(/^(\d\d)(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d\d)(\d{0,5})/, '($1) $2');
            }
            e.target.value = value;
        });

        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                // CNPJ
                value = value.replace(/(\d{2})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                // CPF
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            e.target.value = value;
        });

        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });

        // Validação do formulário
        document.getElementById('editarForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#00bc75';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
                return false;
            }
        });

        console.log('Página de edição de cliente carregada com sucesso!');
    </script>
</body>
</html>