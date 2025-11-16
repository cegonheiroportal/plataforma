<?php
// corrigir_usuarios.php - Script para corrigir estrutura da tabela usuarios
require_once 'config.php';

echo "<h2>Correção da Estrutura da Tabela Usuarios</h2>";

try {
    // Verificar se as colunas necessárias existem e adicioná-las se necessário
    $stmt = $pdo->query("DESCRIBE usuarios");
    $campos_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Adicionar coluna tipo_cliente se não existir
    if (!in_array('tipo_cliente', $campos_existentes)) {
        echo "<p>Adicionando coluna 'tipo_cliente'...</p>";
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN tipo_cliente ENUM('pf','pj') NOT NULL DEFAULT 'pf' AFTER senha");
        echo "<p style='color: green;'>✅ Coluna 'tipo_cliente' adicionada</p>";
    } else {
        echo "<p style='color: green;'>✅ Coluna 'tipo_cliente' já existe</p>";
    }
    
    // Adicionar coluna nivel_acesso se não existir
    if (!in_array('nivel_acesso', $campos_existentes)) {
        echo "<p>Adicionando coluna 'nivel_acesso'...</p>";
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN nivel_acesso ENUM('cliente','admin') NOT NULL DEFAULT 'cliente' AFTER tipo_cliente");
        echo "<p style='color: green;'>✅ Coluna 'nivel_acesso' adicionada</p>";
    } else {
        echo "<p style='color: green;'>✅ Coluna 'nivel_acesso' já existe</p>";
    }
    
    // Adicionar outras colunas que podem estar faltando
    $colunas_opcionais = [
        'empresa' => "ALTER TABLE usuarios ADD COLUMN empresa VARCHAR(255) DEFAULT NULL AFTER nivel_acesso",
        'cnpj' => "ALTER TABLE usuarios ADD COLUMN cnpj VARCHAR(18) DEFAULT NULL AFTER empresa",
        'endereco' => "ALTER TABLE usuarios ADD COLUMN endereco TEXT DEFAULT NULL AFTER cnpj",
        'cidade' => "ALTER TABLE usuarios ADD COLUMN cidade VARCHAR(255) DEFAULT NULL AFTER endereco",
        'estado' => "ALTER TABLE usuarios ADD COLUMN estado VARCHAR(2) DEFAULT NULL AFTER cidade",
        'cep' => "ALTER TABLE usuarios ADD COLUMN cep VARCHAR(10) DEFAULT NULL AFTER estado",
        'ativo' => "ALTER TABLE usuarios ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER cep"
    ];
    
    foreach ($colunas_opcionais as $coluna => $sql) {
        if (!in_array($coluna, $campos_existentes)) {
            echo "<p>Adicionando coluna '$coluna'...</p>";
            try {
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ Coluna '$coluna' adicionada</p>";
            } catch (Exception $e) {
                echo "<p style='color: orange;'>⚠️ Erro ao adicionar coluna '$coluna': " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h3>Estrutura final da tabela:</h3>";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $campos = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($campos as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='popular_dados_teste_corrigido.php'>Agora popular dados de teste</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>