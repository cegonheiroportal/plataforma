<?php
// forcar_usuario.php - Forçar criação do usuário Auto Transporte
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=joaocr74_cegonha;charset=utf8mb4", 'joaocr74_lima', 'davi@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔧 Forçar Criação do Usuário Auto Transporte</h1>";
    
    // 1. Deletar usuário se existir
    echo "<h2>1. Removendo usuário existente (se houver)</h2>";
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE email = ?");
    $stmt->execute(['auto@teste.com.br']);
    echo "<p>✅ Usuário removido (se existia)</p>";
    
    // 2. Criar usuário do zero
    echo "<h2>2. Criando usuário do zero</h2>";
    
    $dados = [
        'nome' => 'Auto Transporte',
        'email' => 'auto@teste.com.br',
        'senha' => password_hash('auto@2025', PASSWORD_DEFAULT),
        'nivel_acesso' => 'cliente',
        'status' => 'ativo',
        'tipo_cliente' => 'pj',
        'telefone' => '(85) 99999-1000'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel_acesso, status, tipo_cliente, telefone) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $dados['nome'],
        $dados['email'],
        $dados['senha'],
        $dados['nivel_acesso'],
        $dados['status'],
        $dados['tipo_cliente'],
        $dados['telefone']
    ]);
    
    $usuario_id = $pdo->lastInsertId();
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724;'>✅ Usuário criado com sucesso!</h3>";
    echo "<p><strong>ID:</strong> $usuario_id</p>";
    echo "<p><strong>Nome:</strong> {$dados['nome']}</p>";
    echo "<p><strong>Email:</strong> {$dados['email']}</p>";
    echo "<p><strong>Senha:</strong> auto@2025</p>";
    echo "<p><strong>Nível:</strong> {$dados['nivel_acesso']}</p>";
    echo "<p><strong>Status:</strong> {$dados['status']}</p>";
    echo "</div>";
    
    // 3. Testar senha imediatamente
    echo "<h2>3. Testando senha</h2>";
    $teste_senha = password_verify('auto@2025', $dados['senha']);
    
    if ($teste_senha) {
        echo "<p style='color: green; font-size: 18px;'>✅ SENHA FUNCIONANDO PERFEITAMENTE!</p>";
    } else {
        echo "<p style='color: red; font-size: 18px;'>❌ Problema com a senha</p>";
    }
    
    // 4. Verificar na base de dados
    echo "<h2>4. Verificando na base de dados</h2>";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['auto@teste.com.br']);
    $usuario_verificacao = $stmt->fetch();
    
    if ($usuario_verificacao) {
        echo "<p style='color: green;'>✅ Usuário encontrado na base de dados</p>";
        echo "<p>Hash da senha: " . substr($usuario_verificacao['senha'], 0, 60) . "...</p>";
        
        $teste_final = password_verify('auto@2025', $usuario_verificacao['senha']);
        echo "<p>Teste final da senha: " . ($teste_final ? '✅ SUCESSO' : '❌ FALHA') . "</p>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 Usuário Auto Transporte criado e testado!</h2>";
    echo "<p><a href='teste_login_corrigido.php' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>🔑 Testar Login Agora</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>