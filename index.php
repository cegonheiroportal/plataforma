<?php
require_once 'app/config.php';

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Capturar e validar dados do formulário
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $telefone = trim($_POST['telefone']);
        $cidade_origem = trim($_POST['origem']);
        $cidade_destino = trim($_POST['destino']);
        $tipo_veiculo = $_POST['tipo'];
        $ano_modelo = trim($_POST['anomodelo']);
        $valor_veiculo = str_replace(['R$', '.', ',', ' '], ['', '', '.', ''], $_POST['valor_veiculo']);
        $data_prevista = $_POST['datprev'];
        $observacoes = trim($_POST['obs']);
        $aceite_lgpd = isset($_POST['lgpd']) ? 1 : 0;
        
        // Validações básicas
        if (empty($nome) || empty($email) || empty($telefone) || empty($cidade_origem) || 
            empty($cidade_destino) || empty($tipo_veiculo) || empty($ano_modelo) || 
            empty($valor_veiculo) || empty($data_prevista) || !$aceite_lgpd) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios e aceite os termos.');
        }
        
        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor, informe um email válido.');
        }
        
        // Validar valor do veículo
        if (!is_numeric($valor_veiculo) || $valor_veiculo <= 0) {
            throw new Exception('Por favor, informe um valor válido para o veículo.');
        }
        
        // Validar data (não pode ser no passado)
        if (strtotime($data_prevista) < strtotime('today')) {
            throw new Exception('A data prevista não pode ser no passado.');
        }
        
        // Capturar dados para analytics
        $ip_origem = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $utm_source = $_GET['utm_source'] ?? '';
        $utm_medium = $_GET['utm_medium'] ?? '';
        $utm_campaign = $_GET['utm_campaign'] ?? '';
        
        // Inserir no banco de dados
        $stmt = $pdo->prepare("
            INSERT INTO leads (
                nome, email, telefone, cidade_origem, cidade_destino, 
                tipo_veiculo, ano_modelo, valor_veiculo, data_prevista, observacoes, 
                aceite_lgpd, ip_origem, user_agent, utm_source, utm_medium, utm_campaign,
                status, data_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'novo', NOW())
        ");
        
        $result = $stmt->execute([
            $nome, $email, $telefone, $cidade_origem, $cidade_destino,
            $tipo_veiculo, $ano_modelo, $valor_veiculo, $data_prevista, $observacoes,
            $aceite_lgpd, $ip_origem, $user_agent, $utm_source, $utm_medium, $utm_campaign
        ]);
        
        if ($result) {
            $mensagem = '🎉 Solicitação enviada com sucesso! Em breve você receberá cotações das melhores transportadoras do Brasil.';
            $tipo_mensagem = 'success';
            
            // Limpar campos após sucesso
            $_POST = array();
            
        } else {
            throw new Exception('Erro ao salvar os dados. Tente novamente.');
        }
        
    } catch (Exception $e) {
        $mensagem = '❌ ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17598076191"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'AW-17598076191');
    </script>
    
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PYWHEM1YR5"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-PYWHEM1YR5');
</script>

<!-- Event snippet for Enviar formulário de lead conversion page -->
<script>
function gtag_report_conversion(url) {
  var callback = function () {
    if (typeof(url) != 'undefined') {
      window.location = url;
    }
  };
  gtag('event', 'conversion', {
      'send_to': 'AW-17598076191/3k6ECID47aUbEJ-qtcdB',
      'event_callback': callback
  });
  return false;
}
</script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO OTIMIZADO -->
    <title>Portal Cegonheiro - Transporte de Veículos | Cotação Online Grátis 2025</title>
    <meta name="description" content="🚗 Transporte de veículos seguro e confiável! Compare cotações das melhores transportadoras do Brasil. Carros, motos, SUVs. Cotação grátis em 2 minutos!">
    <meta name="keywords" content="transporte de veículos, cegonha, transportadora, cotação transporte carro, transporte moto, frete veículo, mudança carro, transporte automotivo, cegonheiro, reboque veículo">
    <meta name="author" content="Portal Cegonheiro">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Otimizado -->
    <meta property="og:title" content="Portal Cegonheiro - Transporte de Veículos Seguro e Confiável">
    <meta property="og:description" content="Compare cotações das melhores transportadoras do Brasil. Transporte seguro para carros, motos e utilitários. Cotação grátis!">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://portalcegonheiro.com.br">
    <meta property="og:image" content="https://portalcegonheiro.com.br/assets/og-image.jpg">
    <meta property="og:site_name" content="Portal Cegonheiro">
    <meta property="og:locale" content="pt_BR">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Portal Cegonheiro - Transporte de Veículos">
    <meta name="twitter:description" content="Compare cotações das melhores transportadoras do Brasil. Cotação grátis!">
    <meta name="twitter:image" content="https://portalcegonheiro.com.br/assets/twitter-image.jpg">
    
    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Portal Cegonheiro",
      "url": "https://portalcegonheiro.com.br",
      "logo": "https://portalcegonheiro.com.br/assets/logo.png",
      "description": "Plataforma líder em transporte de veículos no Brasil",
      "address": {
        "@type": "PostalAddress",
        "addressCountry": "BR"
      },
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+55-85-98583-2583",
        "contactType": "customer service"
      },
      "sameAs": [
        "https://www.facebook.com/portalcegonheiro",
        "https://www.instagram.com/portalcegonheiro"
      ]
    }
    </script>
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://portalcegonheiro.com.br">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --preto-principal: #2C2C2C;
            --preto-secundario: #3A3A3A;
            --cinza-escuro: #4A4A4A;
            --cinza-medio: #6A6A6A;
            --cinza-claro: #F8F9FA;
            --cinza-texto: #5A5A5A;
            --verde-principal: #00D084;
            --verde-hover: #00B570;
            --verde-claro: #E8FFF6;
            --branco: #FFFFFF;
            --gradiente-verde: linear-gradient(135deg, #00D084 0%, #00B570 100%);
            --gradiente-cinza: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);
            --gradiente-escuro: linear-gradient(135deg, #2C2C2C 0%, #3A3A3A 100%);
            --sombra-suave: 0 4px 20px rgba(0, 0, 0, 0.08);
            --sombra-media: 0 8px 30px rgba(0, 0, 0, 0.12);
            --sombra-forte: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--preto-principal);
            background: var(--branco);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* WhatsApp Button */
        .whatsapp-button {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 9999;
            width: 60px;
            height: 60px;
            background: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
            transition: all 0.3s ease;
            animation: pulse-whatsapp 2s infinite;
            text-decoration: none;
        }

        .whatsapp-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(37, 211, 102, 0.6);
        }

        .whatsapp-button i {
            color: white;
            font-size: 28px;
        }

        @keyframes pulse-whatsapp {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--sombra-media);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--verde-principal);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .logo i {
            font-size: 2rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        .nav {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav a {
            text-decoration: none;
            color: var(--cinza-texto);
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav a:hover {
            color: var(--verde-principal);
        }

        .nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradiente-verde);
            transition: width 0.3s ease;
        }

        .nav a:hover::after {
            width: 100%;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--preto-principal);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(0, 208, 132, 0.05) 100%), url('https://i.ibb.co/bMV2yrWd/img-topo-site.png') center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 120px 0 80px;
            color: var(--preto-principal);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 208, 132, 0.1);
            border: 1px solid var(--verde-principal);
            color: var(--verde-principal);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: var(--preto-principal);
        }

        .hero p {
            font-size: 1.2rem;
            color: var(--cinza-texto);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--gradiente-verde);
            color: var(--branco);
            padding: 1.2rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1.1rem;
            box-shadow: var(--sombra-media);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 208, 132, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--preto-principal);
            padding: 1.2rem 2.5rem;
            border: 2px solid var(--cinza-medio);
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .btn-secondary:hover {
            border-color: var(--verde-principal);
            color: var(--verde-principal);
            transform: translateY(-3px);
            box-shadow: var(--sombra-media);
        }

        .hero-microcopy {
            font-size: 0.9rem;
            color: var(--cinza-texto);
            font-style: italic;
        }

        /* Floating Elements */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: floatRandom 20s linear infinite;
            color: var(--verde-principal);
        }

        .floating-element:nth-child(1) { top: 20%; left: 10%; animation-delay: 0s; }
        .floating-element:nth-child(2) { top: 60%; right: 15%; animation-delay: 5s; }
        .floating-element:nth-child(3) { bottom: 30%; left: 20%; animation-delay: 10s; }

        @keyframes floatRandom {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(90deg); }
            50% { transform: translateY(-10px) rotate(180deg); }
            75% { transform: translateY(-30px) rotate(270deg); }
        }

        /* Trust Bar */
        .trust-bar {
            background: var(--branco);
            padding: 3rem 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .trust-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--branco);
            border-radius: 15px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-align: center;
            flex-direction: column;
            box-shadow: var(--sombra-suave);
        }

        .trust-item:hover {
            transform: translateY(-5px);
            border-color: var(--verde-principal);
            box-shadow: var(--sombra-media);
        }

        .trust-item i {
            color: var(--verde-principal);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .trust-item span {
            font-weight: 600;
            color: var(--preto-principal);
        }

        /* Form Section */
        .form-section {
            background: var(--gradiente-cinza);
            padding: 6rem 0;
            position: relative;
        }

        .form-container {
            max-width: 700px;
            margin: 0 auto;
            background: var(--branco);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: var(--sombra-forte);
            position: relative;
            z-index: 2;
        }

        .form-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--preto-principal);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            text-align: center;
            color: var(--cinza-medio);
            margin-bottom: 2.5rem;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--preto-principal);
            font-size: 0.9rem;
        }

        input, select, textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--branco);
            color: var(--preto-principal);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--verde-principal);
            box-shadow: 0 0 0 3px rgba(0, 208, 132, 0.1);
            transform: translateY(-2px);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #F8F9FA;
            border-radius: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-top: 0.2rem;
            transform: scale(1.2);
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--cinza-medio);
        }

        .form-submit {
            text-align: center;
        }

        .form-note {
            font-size: 0.8rem;
            color: var(--cinza-medio);
            text-align: center;
            margin-top: 1rem;
            font-style: italic;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: var(--verde-claro);
            color: var(--verde-hover);
            border-color: var(--verde-principal);
        }

        .alert-danger {
            background: #FFE6E6;
            color: #D32F2F;
            border-color: #D32F2F;
        }

        .alert-dismissible {
            position: relative;
            padding-right: 3rem;
        }

        .btn-close {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Sections */
        .section {
            padding: 6rem 0;
        }

        .section-dark {
            background: var(--gradiente-escuro);
            color: var(--branco);
        }

        .section-light {
            background: var(--branco);
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
        }

        .section-dark .section-title {
            color: var(--branco);
        }

        .section-light .section-title {
            color: var(--preto-principal);
        }

        .section-subtitle {
            text-align: center;
            margin-bottom: 4rem;
            font-size: 1.1rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .section-dark .section-subtitle {
            color: #B0B0B0;
        }

        .section-light .section-subtitle {
            color: var(--cinza-texto);
        }

        /* How It Works */
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .step {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .step::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradiente-verde);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .step:hover::before {
            transform: scaleX(1);
        }

        .step:hover {
            transform: translateY(-10px);
            border-color: var(--verde-principal);
            box-shadow: var(--sombra-media);
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: var(--gradiente-verde);
            color: var(--branco);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 1.5rem;
            box-shadow: var(--sombra-media);
        }

        .step h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--branco);
            margin-bottom: 1rem;
        }

        .step p {
            color: #B0B0B0;
            line-height: 1.6;
        }

        /* Benefits */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .benefit {
            background: var(--branco);
            padding: 2.5rem;
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: var(--sombra-suave);
        }

        .benefit::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 208, 132, 0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .benefit:hover::before {
            opacity: 1;
        }

        .benefit:hover {
            transform: translateY(-10px);
            border-color: var(--verde-principal);
            box-shadow: var(--sombra-media);
        }

        .benefit-icon {
            width: 80px;
            height: 80px;
            background: var(--gradiente-verde);
            color: var(--branco);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: var(--sombra-media);
        }

        .benefit h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--preto-principal);
            margin-bottom: 1rem;
        }

        .benefit p {
            color: var(--cinza-texto);
            line-height: 1.6;
        }

        /* Routes */
        .routes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .route {
            background: var(--gradiente-verde);
            color: var(--branco);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .route::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
            z-index: 1;
        }

        .route:hover::before {
            left: 100%;
        }

        .route:hover {
            transform: translateY(-5px);
            box-shadow: var(--sombra-media);
        }

        .routes-note {
            text-align: center;
            color: #B0B0B0;
            font-style: italic;
            font-size: 1.1rem;
        }

        /* Testimonials */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .testimonial {
            background: var(--branco);
            padding: 2.5rem;
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            box-shadow: var(--sombra-suave);
        }

        .testimonial::before {
            content: '"';
            position: absolute;
            top: 1rem;
            left: 1.5rem;
            font-size: 4rem;
            color: var(--verde-principal);
            opacity: 0.3;
            font-family: serif;
        }

        .testimonial:hover {
            transform: translateY(-5px);
            border-color: var(--verde-principal);
            box-shadow: var(--sombra-media);
        }

        .testimonial-text {
            font-style: italic;
            margin-bottom: 1.5rem;
            color: var(--cinza-texto);
            line-height: 1.6;
            font-size: 1.1rem;
        }

        .testimonial-author {
            font-weight: 600;
            color: var(--verde-principal);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .testimonial-author::before {
            content: '⭐⭐⭐⭐⭐';
            font-size: 0.8rem;
        }

        /* Security */
        .security-badges {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .security-badge:hover {
            transform: translateY(-5px);
            border-color: var(--verde-principal);
            box-shadow: var(--sombra-media);
        }

        .security-badge i {
            color: var(--verde-principal);
            font-size: 2rem;
        }

        /* FAQ */
        .faq-item {
            background: var(--branco);
            margin-bottom: 1rem;
            border-radius: 15px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--sombra-suave);
        }

        .faq-item:hover {
            border-color: var(--verde-principal);
        }

        .faq-question {
            background: transparent;
            border: none;
            width: 100%;
            padding: 2rem;
            text-align: left;
            font-weight: 600;
            color: var(--preto-principal);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            color: var(--verde-principal);
        }

        .faq-question i {
            transition: transform 0.3s ease;
            color: var(--verde-principal);
        }

        .faq-answer {
            padding: 0 2rem 2rem;
            color: var(--cinza-texto);
            display: none;
            line-height: 1.6;
            font-size: 1rem;
        }

        .faq-answer.active {
            display: block;
            animation: fadeInDown 0.3s ease;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Final CTA */
        .final-cta {
            background: var(--gradiente-verde);
            color: var(--branco);
            padding: 6rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .final-cta h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .final-cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        /* Footer */
        .footer {
            background: var(--gradiente-escuro);
            color: var(--branco);
            padding: 4rem 0 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-section h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--verde-principal);
        }

        .footer-section a {
            color: #B0B0B0;
            text-decoration: none;
            display: block;
            margin-bottom: 0.75rem;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--verde-principal);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            text-align: center;
            color: #B0B0B0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .nav {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--branco);
                flex-direction: column;
                padding: 1rem;
                box-shadow: var(--sombra-media);
            }

            .nav.active {
                display: flex;
            }

            .menu-toggle {
                display: block;
            }

            .section-title {
                font-size: 2rem;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
            }

            .routes-grid {
                grid-template-columns: 1fr;
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .security-badges {
                grid-template-columns: 1fr;
            }
            
            .whatsapp-button {
                bottom: 20px;
                right: 20px;
                width: 55px;
                height: 55px;
            }
            
            .whatsapp-button i {
                font-size: 24px;
            }
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--cinza-claro);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--verde-principal);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--verde-hover);
        }
    </style>
</head>

<body>
    <!-- WhatsApp Button -->
    <a href="https://wa.me/5585985832583?text=Olá! Preciso transportar meu veículo e gostaria de receber cotações!" 
       class="whatsapp-button" 
       target="_blank"
       aria-label="Solicitar cotação via WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Header -->
    <!-- Header -->
<header class="header" id="header">
    <div class="container">
        <div class="header-content">
            <a href="#inicio" class="logo">
                <img src="https://i.ibb.co/tpmsDyMm/img-logo-portal-03.png" alt="Portal Cegonheiro" style="height: 40px; width: auto;">
            </a>
            <nav class="nav" id="nav">
                <a href="#inicio">Início</a>
                <a href="#como-funciona">Como Funciona</a>
                <a href="#beneficios">Vantagens</a>
                <a href="#depoimentos">Avaliações</a>
                <a href="#contato">Contato</a>
                <a href="app/login.php">Área do Cliente</a>
            </nav>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

    <!-- Hero Section -->
    <section id="inicio" class="hero">
        <div class="floating-elements">
            <i class="fas fa-truck floating-element"></i>
            <i class="fas fa-car floating-element"></i>
            <i class="fas fa-motorcycle floating-element"></i>
        </div>
        
        <div class="container">
            <div class="hero-content" data-aos="fade-up">
                <div class="hero-badge">
                    <i class="fas fa-bolt"></i>
                    Cotação gratuita em poucos minutos
                </div>
                
                <h1>Transporte de Veículos Seguro e Confiável em Todo o Brasil</h1>
                
                <p>Conectamos você às melhores transportadoras verificadas do país. Compare preços, escolha a melhor opção e transporte seu carro, moto ou utilitário com total segurança e economia.</p>
                
                <div class="cta-buttons">
                    <a href="#formulario" class="btn-primary">
                        <i class="fas fa-calculator"></i>
                        Cotação Gratuita Agora
                    </a>
                    <a href="https://wa.me/5585985832583?text=Olá! Preciso transportar meu veículo e gostaria de receber cotações!" class="btn-secondary" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                        Falar no WhatsApp
                                 </a>
                </div>
                
                <div class="hero-microcopy">
                    ⚡ Resposta em minutos • 🛡️ 100% seguro • 💰 Compare e economize
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Bar -->
    <section class="trust-bar">
        <div class="container">
                        <div class="trust-items">
                <div class="trust-item" data-aos="fade-up" data-aos-delay="100">
                    <i class="fas fa-shield-check"></i>
                    <span>Transportadoras Verificadas</span>
                </div>
                <div class="trust-item" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-certificate"></i>
                    <span>Licenciadas e Seguradas</span>
                </div>
                <div class="trust-item" data-aos="fade-up" data-aos-delay="300">
                    <i class="fas fa-lock"></i>
                    <span>Dados Protegidos</span>
                </div>
                <div class="trust-item" data-aos="fade-up" data-aos-delay="400">
                    <i class="fas fa-clock"></i>
                    <span>Resposta Rápida</span>
                </div>
                <div class="trust-item" data-aos="fade-up" data-aos-delay="500">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Melhor Preço Garantido</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Form Section -->
    <section id="formulario" class="form-section">
        <div class="container">
            <div class="form-container" data-aos="zoom-in">
                <h2 class="form-title">Solicite Sua Cotação Gratuita</h2>
                <p class="form-subtitle">Preencha os dados abaixo e receba propostas das melhores transportadoras do Brasil</p>
                
                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $tipo_mensagem == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $mensagem; ?>
                        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="leadForm">
                    <div class="form-group">
                        <label for="nome">Nome completo *</label>
                        <input type="text" id="nome" name="nome" required placeholder="Seu nome completo" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" required placeholder="seu@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="telefone">WhatsApp *</label>
                            <input type="tel" id="telefone" name="telefone" required placeholder="(11) 99999-9999" value="<?php echo isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="origem">Cidade de origem *</label>
                            <input type="text" id="origem" name="origem" required placeholder="Ex: São Paulo, SP" value="<?php echo isset($_POST['origem']) ? htmlspecialchars($_POST['origem']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="destino">Cidade de destino *</label>
                            <input type="text" id="destino" name="destino" required placeholder="Ex: Rio de Janeiro, RJ" value="<?php echo isset($_POST['destino']) ? htmlspecialchars($_POST['destino']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo">Tipo de veículo *</label>
                            <select id="tipo" name="tipo" required>
                                <option value="">Selecione o tipo</option>
                                <option value="Carro" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Carro') ? 'selected' : ''; ?>>🚗 Carro de Passeio</option>
                                <option value="Moto" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Moto') ? 'selected' : ''; ?>>🏍️ Motocicleta</option>
                                <option value="SUV" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'SUV') ? 'selected' : ''; ?>>🚙 SUV</option>
                                <option value="Utilitário" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Utilitário') ? 'selected' : ''; ?>>🚐 Utilitário</option>
                                <option value="Caminhonete" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Caminhonete') ? 'selected' : ''; ?>>🛻 Caminhonete</option>
                                <option value="Outro" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'Outro') ? 'selected' : ''; ?>>🚛 Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="anomodelo">Marca/Modelo/Ano *</label>
                            <input type="text" id="anomodelo" name="anomodelo" required placeholder="Ex: Honda Civic 2020" value="<?php echo isset($_POST['anomodelo']) ? htmlspecialchars($_POST['anomodelo']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="valor_veiculo">Valor do Veículo *</label>
                            <input type="text" id="valor_veiculo" name="valor_veiculo" required placeholder="Ex: R$ 50.000,00" value="<?php echo isset($_POST['valor_veiculo']) ? htmlspecialchars($_POST['valor_veiculo']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="datprev">Data prevista para transporte *</label>
                            <input type="date" id="datprev" name="datprev" required value="<?php echo isset($_POST['datprev']) ? $_POST['datprev'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="obs">Observações especiais</label>
                        <textarea id="obs" name="obs" placeholder="Informações adicionais sobre o transporte (opcional)"><?php echo isset($_POST['obs']) ? htmlspecialchars($_POST['obs']) : ''; ?></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="lgpd" name="lgpd" required>
                        <label for="lgpd">
                            Aceito os <a href="#termos" style="color: var(--verde-principal); font-weight: 600;">Termos de Uso</a> e autorizo o compartilhamento dos meus dados com transportadoras parceiras para cotação. Estou ciente da <a href="#politica" style="color: var(--verde-principal); font-weight: 600;">Política de Privacidade</a>. *
                        </label>
                    </div>
                    
                    <div class="form-submit">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Solicitar Cotações Gratuitas
                        </button>
                    </div>
                    
                    <div class="form-note">
                        🔒 Seus dados estão seguros conosco. Conectamos você diretamente com as melhores transportadoras do Brasil.
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="como-funciona" class="section section-dark">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Como Funciona Nosso Serviço</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Processo simples e eficiente para conectar você às melhores transportadoras
            </p>
            
            <div class="steps">
                <div class="step" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-number">1</div>
                    <h3>Solicite Sua Cotação</h3>
                    <p>Preencha nosso formulário com os dados do seu veículo e rota. Nossa tecnologia analisa sua solicitação e identifica as transportadoras mais adequadas</p>
                </div>
                
                <div class="step" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-number">2</div>
                    <h3>Receba Propostas Personalizadas</h3>
                    <p>Em poucos minutos, você recebe cotações de transportadoras verificadas e licenciadas, todas adequadas às suas necessidades específicas</p>
                </div>
                
                <div class="step" data-aos="fade-up" data-aos-delay="400">
                    <div class="step-number">3</div>
                    <h3>Compare e Contrate</h3>
                    <p>Analise as propostas, compare preços e condições, escolha a melhor opção e contrate diretamente com a transportadora de sua preferência</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section id="beneficios" class="section section-light">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Por Que Escolher o Portal Cegonheiro?</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                A plataforma mais confiável do Brasil para transporte de veículos
            </p>
            
            <div class="benefits-grid">
                <div class="benefit" data-aos="fade-up" data-aos-delay="200">
                    <div class="benefit-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Seleção Inteligente</h3>
                    <p>Nossa tecnologia seleciona automaticamente as melhores transportadoras para sua rota, garantindo qualidade e economia</p>
                </div>
                
                <div class="benefit" data-aos="fade-up" data-aos-delay="300">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <h3>Transportadoras Verificadas</h3>
                    <p>Todas as empresas parceiras passam por rigoroso processo de verificação, possuem licenças e seguros obrigatórios</p>
                </div>
                
                <div class="benefit" data-aos="fade-up" data-aos-delay="400">
                    <div class="benefit-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Cotações Rápidas</h3>
                    <p>Receba múltiplas cotações em poucos minutos, sem precisar pesquisar e ligar para várias empresas</p>
                </div>
                
                <div class="benefit" data-aos="fade-up" data-aos-delay="500">
                    <div class="benefit-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Melhor Custo-Benefício</h3>
                    <p>Compare preços e condições para encontrar a opção que oferece o melhor valor para seu transporte</p>
                </div>
                
                <div class="benefit" data-aos="fade-up" data-aos-delay="600">
                    <div class="benefit-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3>Suporte Completo</h3>
                    <p>Nossa equipe acompanha todo o processo, desde a cotação até a entrega do seu veículo</p>
                </div>
                
                <div class="benefit" data-aos="fade-up" data-aos-delay="700">
                    <div class="benefit-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Suporte Especializado</h3>
                    <p>Nossa equipe está sempre disponível para ajudar em qualquer etapa do processo de transporte</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Routes -->
    <section class="section section-dark">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Principais Rotas Atendidas</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Cobertura nacional com foco nas principais rotas do país
            </p>
            
            <div class="routes-grid">
                <div class="route" data-aos="fade-up" data-aos-delay="200">São Paulo ↔ Rio de Janeiro</div>
                <div class="route" data-aos="fade-up" data-aos-delay="250">São Paulo ↔ Belo Horizonte</div>
                <div class="route" data-aos="fade-up" data-aos-delay="300">São Paulo ↔ Brasília</div>
                <div class="route" data-aos="fade-up" data-aos-delay="350">São Paulo ↔ Salvador</div>
                <div class="route" data-aos="fade-up" data-aos-delay="400">Rio de Janeiro ↔ Belo Horizonte</div>
                <div class="route" data-aos="fade-up" data-aos-delay="450">São Paulo ↔ Curitiba</div>
                <div class="route" data-aos="fade-up" data-aos-delay="500">São Paulo ↔ Porto Alegre</div>
                <div class="route" data-aos="fade-up" data-aos-delay="550">São Paulo ↔ Recife</div>
                <div class="route" data-aos="fade-up" data-aos-delay="600">São Paulo ↔ Fortaleza</div>
            </div>
            
            <p class="routes-note" data-aos="fade-up" data-aos-delay="700">
                Atendemos todas as rotas do Brasil. Nossa rede de parceiros cresce constantemente!
            </p>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="depoimentos" class="section section-light">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Avaliações de Nossos Clientes</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Milhares de veículos transportados com segurança e satisfação total
            </p>
            
            <div class="testimonials-grid">
                <div class="testimonial" data-aos="fade-up" data-aos-delay="200">
                    <p class="testimonial-text">
                        Precisava transportar meu carro de São Paulo para Salvador e o Portal Cegonheiro me conectou com 3 transportadoras em menos de 10 minutos. Escolhi a melhor opção e tudo correu perfeitamente!
                    </p>
                    <div class="testimonial-author">Marina Silva - São Paulo</div>
                </div>
                
                <div class="testimonial" data-aos="fade-up" data-aos-delay="300">
                    <p class="testimonial-text">
                        Excelente plataforma! O processo é muito simples e rápido. Recebi cotações personalizadas e economizei mais de R$ 500 comparado a outras opções que pesquisei.
                    </p>
                    <div class="testimonial-author">Carlos Roberto - Rio de Janeiro</div>
                </div>
                
                <div class="testimonial" data-aos="fade-up" data-aos-delay="400">
                    <p class="testimonial-text">
                        Serviço super profissional e transparente. A transportadora que escolhi foi muito cuidadosa com minha moto. Recomendo para quem quer praticidade e segurança.
                    </p>
                    <div class="testimonial-author">Ana Paula - Belo Horizonte</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security -->
    <section class="section section-dark">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Segurança e Confiabilidade</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Seus dados e seu veículo protegidos por tecnologia de ponta
            </p>
            
            <div class="security-badges">
                <div class="security-badge" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-lock"></i>
                    <span>Criptografia SSL 256-bit</span>
                </div>
                <div class="security-badge" data-aos="fade-up" data-aos-delay="300">
                    <i class="fas fa-shield-check"></i>
                    <span>Conformidade LGPD</span>
                </div>
                <div class="security-badge" data-aos="fade-up" data-aos-delay="400">
                    <i class="fas fa-certificate"></i>
                    <span>Transportadoras Licenciadas</span>
                </div>
                <div class="security-badge" data-aos="fade-up" data-aos-delay="500">
                    <i class="fas fa-phone"></i>
                    <span>Suporte Dedicado</span>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section section-light">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Perguntas Frequentes</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Tire suas dúvidas sobre nosso serviço de transporte de veículos
            </p>
            
            <div class="faq-list">
                <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
                    <button class="faq-question">
                        Vocês são uma transportadora?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Não, somos uma plataforma que conecta você às melhores transportadoras do Brasil. Analisamos sua solicitação e encontramos os parceiros mais adequados para seu transporte, garantindo qualidade e economia.
                    </div>
                </div>
                
                <div class="faq-item" data-aos="fade-up" data-aos-delay="300">
                    <button class="faq-question">
                        Como funciona o processo de cotação?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Você preenche nosso formulário com os dados do transporte, nossa tecnologia analisa e conecta você com as transportadoras mais adequadas. Em poucos minutos você recebe múltiplas cotações personalizadas.
                    </div>
                </div>
                
                <div class="faq-item" data-aos="fade-up" data-aos-delay="400">
                    <button class="faq-question">
                        É seguro transportar meu veículo?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Sim! Todas as transportadoras parceiras são verificadas, licenciadas e possuem seguro obrigatório. Além disso, nossa equipe acompanha todo o processo para garantir sua tranquilidade.
                    </div>
                </div>
                
                <div class="faq-item" data-aos="fade-up" data-aos-delay="500">
                    <button class="faq-question">
                        Quanto tempo demora para receber as cotações?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Nossa tecnologia trabalha rapidamente. Normalmente você recebe as primeiras cotações em poucos minutos após enviar sua solicitação.
                    </div>
                </div>
                
                <div class="faq-item" data-aos="fade-up" data-aos-delay="600">
                    <button class="faq-question">
                        O serviço tem algum custo?
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Não! Nosso serviço de cotação é completamente gratuito. Você só paga diretamente à transportadora escolhida pelo serviço de transporte.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="final-cta">
        <div class="container">
            <h2 data-aos="fade-up">Pronto para Transportar Seu Veículo?</h2>
            <p data-aos="fade-up" data-aos-delay="100">
                Junte-se a milhares de clientes satisfeitos que confiaram no Portal Cegonheiro
            </p>
            <a href="#formulario" class="btn-primary" data-aos="fade-up" data-aos-delay="200">
                <i class="fas fa-calculator"></i>
                Solicitar Cotação Gratuita
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contato" class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Portal Cegonheiro</h3>
                    <p>A plataforma mais confiável do Brasil para transporte de veículos. Conectamos você às melhores transportadoras com tecnologia avançada e segurança total.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Serviços</h3>
                    <a href="#formulario">Cotação Gratuita</a>
                    <a href="#como-funciona">Como Funciona</a>
                    <a href="#beneficios">Nossas Vantagens</a>
                    <a href="#depoimentos">Avaliações</a>
                </div>
                
                <div class="footer-section">
                    <h3>Suporte</h3>
                    <a href="https://wa.me/5585985832583?text=Olá! Preciso de ajuda com transporte de veículo" >WhatsApp</a>
                    <a href="mailto:contato@portalcegonheiro.com.br">E-mail</a>
                    <a href="#faq">Perguntas Frequentes</a>
                    
                </div>
                
                <div class="footer-section">
                    <h3>Contato</h3>
                    <a href="tel:+5585985832583">(85) 98583-2583</a>
                    <a href="mailto:contato@portalcegonheiro.com.br">contato@portalcegonheiro.com.br</a>
                    <p>Atendimento: Segunda a Sexta, 8h às 18h</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 Portal Cegonheiro. Todos os direitos reservados. CNPJ: 00.000.000/0001-00</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const nav = document.getElementById('nav');
        
        menuToggle.addEventListener('click', function() {
            nav.classList.toggle('active');
            const icon = menuToggle.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu if open
                    nav.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.add('fa-bars');
                    icon.classList.remove('fa-times');
                }
            });
        });

        // FAQ functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                const answer = this.nextElementSibling;
                const icon = this.querySelector('i');
                
                // Close all other answers
                document.querySelectorAll('.faq-answer').forEach(otherAnswer => {
                    if (otherAnswer !== answer) {
                        otherAnswer.classList.remove('active');
                        otherAnswer.previousElementSibling.querySelector('i').style.transform = 'rotate(0deg)';
                    }
                });
                
                // Toggle current answer
                answer.classList.toggle('active');
                icon.style.transform = answer.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
            });
        });

        // Form handling
        const form = document.getElementById('leadForm');

        form.addEventListener('submit', function(e) {
            // Validação básica
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (field.id === 'valor_veiculo') {
                    // Validação especial para valor do veículo
                    const value = field.value.replace(/[^\d,]/g, '').replace(',', '.');
                    if (!value || parseFloat(value) <= 0) {
                        field.style.borderColor = '#D32F2F';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#00D084';
                    }
                } else if (!field.value.trim()) {
                    field.style.borderColor = '#D32F2F';
                    isValid = false;
                } else {
                    field.style.borderColor = '#00D084';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios corretamente.');
                return false;
            }
        });

        // Phone mask
        const phoneInput = document.getElementById('telefone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
            value = value.replace(/(\d)(\d{4})$/, '$1-$2');
            e.target.value = value;
        });

        // Máscara para valor do veículo
        const valorInput = document.getElementById('valor_veiculo');
        valorInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length === 0) {
                e.target.value = '';
                return;
            }
            
            // Adiciona zeros à esquerda se necessário
            value = value.padStart(3, '0');
            
            // Adiciona vírgula para centavos
            value = value.slice(0, -2) + ',' + value.slice(-2);
            
            // Adiciona pontos para milhares
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            
            // Adiciona R$
            e.target.value = 'R$ ' + value;
        });

        // Validação do valor do veículo
        valorInput.addEventListener('blur', function() {
            const value = this.value.replace(/[^\d,]/g, '').replace(',', '.');
            if (!value || parseFloat(value) <= 0) {
                this.style.borderColor = '#D32F2F';
            } else {
                this.style.borderColor = '#00D084';
            }
        });

        // Set minimum date to today
        const dateInput = document.getElementById('datprev');
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);

        // Google Analytics Events
        function gtag_report_conversion(url) {
            var callback = function () {
                if (typeof(url) != 'undefined') {
                    window.location = url;
                }
            };
            gtag('event', 'conversion', {
                'send_to': 'AW-17598076191/3k6ECID47aUbEJ-qtcdB',
                'event_callback': callback
            });
            return false;
        }

        // Trigger conversion on form submit
        form.addEventListener('submit', function() {
            gtag('event', 'generate_lead', {
                'event_category': 'form',
                'event_label': 'cotacao_veiculo',
                'value': 1
            });
        });

        // Track WhatsApp clicks
        document.querySelectorAll('a[href*="wa.me"]').forEach(link => {
            link.addEventListener('click', function() {
                gtag('event', 'whatsapp_click', {
                    'event_category': 'engagement',
                    'event_label': 'whatsapp_contact'
                });
            });
        });

        // Track scroll depth
        let maxScroll = 0;
        window.addEventListener('scroll', function() {
            const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;
                if (maxScroll >= 25 && maxScroll < 50) {
                    gtag('event', 'scroll', {
                        'event_category': 'engagement',
                        'event_label': '25_percent'
                    });
                } else if (maxScroll >= 50 && maxScroll < 75) {
                    gtag('event', 'scroll', {
                        'event_category': 'engagement',
                        'event_label': '50_percent'
                    });
                } else if (maxScroll >= 75) {
                    gtag('event', 'scroll', {
                        'event_category': 'engagement',
                        'event_label': '75_percent'
                    });
                }
            }
        });
    </script>
</body>
</html>

<?php
// No arquivo onde os leads são cadastrados (ex: cadastro_lead.php ou admin)

// Após inserir o lead no banco de dados
if ($lead_inserido_com_sucesso) {
    $lead_id = $pdo->lastInsertId();
    
    // Enviar notificações WhatsApp
    require_once 'notificacao_whatsapp.php';
    
    // Executar em background para não atrasar a resposta
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    $resultado_whatsapp = notificarNovoLeadWhatsApp($lead_id);
    
    if ($resultado_whatsapp['sucesso']) {
        error_log("WhatsApp enviado para {$resultado_whatsapp['total_enviados']} clientes");
    } else {
        error_log("Erro no WhatsApp: " . $resultado_whatsapp['erro']);
    }
}
?>


<?php
// No arquivo onde os leads são cadastrados
if ($lead_inserido_com_sucesso) {
    $lead_id = $pdo->lastInsertId();
    
    // Enviar notificações
    require_once 'notificacao_email.php';
    require_once 'notificacao_whatsapp.php';
    
    // Executar em background para não atrasar a resposta
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Email
    $resultado_email = notificarNovoLeadEmail($lead_id);
    if ($resultado_email['sucesso']) {
        error_log("Email enviado para {$resultado_email['total_enviados']} clientes");
    }
    
    // WhatsApp
    $resultado_whatsapp = notificarNovoLeadWhatsApp($lead_id);
    if ($resultado_whatsapp['sucesso']) {
        error_log("WhatsApp enviado para {$resultado_whatsapp['total_enviados']} clientes");
    }
}
?>