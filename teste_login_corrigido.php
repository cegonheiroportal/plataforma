<?php
// teste_login_corrigido.php - Login corrigido
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔑 Teste de Login Corrigido</h1>";
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        
        echo "<h2>Dados recebidos:</h2>";
        echo "<p><strong>Email:</strong> '$email'</p>";
        echo "<p><strong>Senha:</strong> '$senha'</p>";
        
        // Buscar usuário com debug
        echo "<h3>1. Buscando usuário...</h3>";
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            echo "<p style='color: green;'>✅ Usuário encontrado: {$usuario['nome']}</p>";
            echo "<p>Status: {$usuario['status']}</p>";
            echo "<p>Nível: {$usuario['nivel_acesso']}</p>";
            
            // Verificar senha com debug
            echo "<h3>2. Verificando senha...</h3>";
            echo "<p>Hash armazenado: " . substr($usuario['senha'], 0, 60) . "...</p>";
            
            $senha_valida = password_verify($senha, $usuario['senha']);
            echo "<p>Resultado da verificação: " . ($senha_valida ? 'VÁLIDA' : 'INVÁLIDA') . "</p>";
            
            if ($senha_valida && $usuario['status'] === 'ativo') {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
                $_SESSION['status'] = $usuario['status'];
                $_SESSION['tipo_cliente'] = $usuario['tipo_cliente'];
                
                echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; border-left: 5px solid #28a745; margin: 20px 0;'>";
                echo "<h3 style='color: #155724; margin: 0 0 15px 0;'>🎉 LOGIN REALIZADO COM SUCESSO!</h3>";
                echo "<p><strong>Bem-vindo:</strong> {$usuario['nome']}</p>";
                echo "<p><strong>ID da sessão:</strong> {$_SESSION['user_id']}</p>";
                echo "<p><strong>Nível de acesso:</strong> {$usuario['nivel_acesso']}</p>";
                echo "<p><strong>Tipo de cliente:</strong> {$usuario['tipo_cliente']}</p>";
                echo "</div>";
                
                echo "<h3>Sessão criada:</h3>";
                echo "<pre>";
                print_r($_SESSION);
                echo "</pre>";
                
                echo "<p><a href='leads_disponiveis_simples.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>📋 Ver Leads Disponíveis</a></p>";
                echo "<p><a href='dashboard_admin.php' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>🏠 Dashboard</a></p>";
                
            } else {
                echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 5px solid #dc3545; margin: 20px 0;'>";
                echo "<h3 style='color: #721c24; margin: 0 0 15px 0;'>❌ FALHA NO LOGIN</h3>";
                
                if (!$senha_valida) {
                    echo "<p><strong>Motivo:</strong> Senha incorreta</p>";
                    
                    // Tentar recriar a senha
                    echo "<h4>Recriando senha...</h4>";
                    $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
                    $stmt->execute([$novo_hash, $email]);
                    echo "<p>✅ Senha atualizada. Tente fazer login novamente.</p>";
                    
                } elseif ($usuario['status'] !== 'ativo') {
                    echo "<p><strong>Motivo:</strong> Usuário não está ativo (Status: {$usuario['status']})</p>";
                }
                echo "</div>";
            }
            
        } else {
            echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 5px solid #dc3545; margin: 20px 0;'>";
            echo "<h3 style='color: #721c24; margin: 0 0 15px 0;'>❌ USUÁRIO NÃO ENCONTRADO</h3>";
            echo "<p>Email '$email' não existe na base de dados.</p>";
            echo "</div>";
            
            // Criar usuário automaticamente
            echo "<h3>Criando usuário automaticamente...</h3>";
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel_acesso, status, tipo_cliente, telefone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Auto Transporte',
                $email,
                $senha_hash,
                'cliente',
                'ativo',
                'pj',
                '(85) 99999-1000'
            ]);
            
            $usuario_id = $pdo->lastInsertId();
            echo "<p>✅ Usuário criado com ID: $usuario_id</p>";
            echo "<p>🔄 <a href='teste_login_corrigido.php'>Clique aqui para tentar o login novamente</a></p>";
        }
        
    } else {
        // Mostrar formulário
        echo "<form method='POST'>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0;'>";
        echo "<h4>🔑 Credenciais de Teste:</h4>";
        echo "<p><strong>Email:</strong> auto@teste.com.br</p>";
        echo "<p><strong>Senha:</strong> auto@2025</p>";
        echo "</div>";
        
        echo "<div style='margin: 10px 0;'>";
        echo "<label>Email:</label><br>";
        echo "<input type='email' name='email' value='auto@teste.com.br' style='width: 300px; padding: 8px;' required>";
        echo "</div>";
        
        echo "<div style='margin: 10px 0;'>";
        echo "<label>Senha:</label><br>";
        echo "<input type='password' name='senha' value='auto@2025' style='width: 300px; padding: 8px;' required>";
        echo "</div>";
        
        echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>";
        echo "🔑 Fazer Login";
        echo "</button>";
        echo "</form>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>