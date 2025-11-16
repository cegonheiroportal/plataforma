<?php
// verificar_usuarios.php - Script para verificar estrutura da tabela usuarios
require_once 'config.php';

echo "<h2>Verificação da Tabela Usuarios</h2>";

try {
    // Verificar se a tabela usuarios existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Tabela 'usuarios' não existe!</p>";
        exit;
    }
    
    // Mostrar estrutura da tabela
    echo "<h3>Estrutura atual da tabela 'usuarios':</h3>";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $campos = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($campos as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "<td>{$campo['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar se as colunas necessárias existem
    $colunas_necessarias = ['tipo_cliente', 'nivel_acesso'];
    echo "<h3>Verificação de colunas necessárias:</h3>";
    
    foreach ($colunas_necessarias as $coluna) {
        $existe = false;
        foreach ($campos as $campo) {
            if ($campo['Field'] === $coluna) {
                $existe = true;
                break;
            }
        }
        
        if ($existe) {
            echo "<p style='color: green;'>✅ Coluna '$coluna' existe</p>";
        } else {
            echo "<p style='color: red;'>❌ Coluna '$coluna' NÃO existe</p>";
        }
    }
    
    // Mostrar alguns usuários existentes
    echo "<h3>Usuários existentes:</h3>";
    $stmt = $pdo->query("SELECT * FROM usuarios LIMIT 5");
    $usuarios = $stmt->fetchAll();
    
    if (!empty($usuarios)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr>";
        foreach (array_keys($usuarios[0]) as $coluna) {
            echo "<th>$coluna</th>";
        }
        echo "</tr>";
        
        foreach ($usuarios as $usuario) {
            echo "<tr>";
            foreach ($usuario as $valor) {
                echo "<td>" . htmlspecialchars($valor) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum usuário encontrado.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>