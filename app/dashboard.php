<?php
// dashboard.php

// Inicia a sessão ANTES de qualquer output HTML
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o utilizador está logado, caso contrário redireciona para a página de login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'php_includes/config.php';
require_once 'php_includes/funcoes_nutricionais.php'; // Para os cálculos da meta

$id_usuario = $_SESSION["id_usuario"];
$nome_usuario = isset($_SESSION["nome_usuario"]) ? htmlspecialchars($_SESSION["nome_usuario"]) : 'Utilizador';

// --- Lógica para calcular a meta calórica diária do utilizador (reutilizada) ---
$peso_kg = $altura_cm = $data_nasc = $sexo = $nivel_atividade_id = $objetivo_id = null;
$fator_atividade = null;
$ajuste_calorico_percentual = null;
$meta_calorica_diaria_utilizador = null;
$erros_calculo_meta_dashboard = [];

$sql_user_data = "SELECT peso_kg, altura_cm, data_nasc, sexo, nivel_atividade_id, objetivo_id FROM usuarios WHERE id = ?";
if ($stmt_user_data = mysqli_prepare($conexao, $sql_user_data)) {
    mysqli_stmt_bind_param($stmt_user_data, "i", $id_usuario);
    if (mysqli_stmt_execute($stmt_user_data)) {
        mysqli_stmt_bind_result($stmt_user_data, $db_peso_kg, $db_altura_cm, $db_data_nasc, $db_sexo, $db_nivel_atividade_id, $db_objetivo_id);
        if (mysqli_stmt_fetch($stmt_user_data)) {
            $peso_kg = $db_peso_kg; $altura_cm = $db_altura_cm; $data_nasc = $db_data_nasc;
            $sexo = $db_sexo; $nivel_atividade_id = $db_nivel_atividade_id; $objetivo_id = $db_objetivo_id;
        } else { $erros_calculo_meta_dashboard[] = "Dados do utilizador não encontrados."; }
    } else { $erros_calculo_meta_dashboard[] = "Erro ao buscar dados do utilizador."; }
    mysqli_stmt_close($stmt_user_data);
} else { $erros_calculo_meta_dashboard[] = "Erro ao preparar busca de dados do utilizador."; }

if (empty($erros_calculo_meta_dashboard) && $nivel_atividade_id && $objetivo_id) {
    $sql_fator = "SELECT fator_multiplicador FROM niveis_atividade WHERE id = ?";
    if ($stmt_fator = mysqli_prepare($conexao, $sql_fator)) {
        mysqli_stmt_bind_param($stmt_fator, "i", $nivel_atividade_id);
        if (mysqli_stmt_execute($stmt_fator)) {
            mysqli_stmt_bind_result($stmt_fator, $db_fator_atividade);
            if (mysqli_stmt_fetch($stmt_fator)) { $fator_atividade = $db_fator_atividade; }
            else { $erros_calculo_meta_dashboard[] = "Nível de atividade não encontrado."; }
        } else { $erros_calculo_meta_dashboard[] = "Erro ao buscar fator de atividade."; }
        mysqli_stmt_close($stmt_fator);
    } else { $erros_calculo_meta_dashboard[] = "Erro ao preparar consulta do fator.";}

    $sql_ajuste = "SELECT ajuste_calorico_percentual FROM objetivos WHERE id = ?";
    if ($stmt_ajuste = mysqli_prepare($conexao, $sql_ajuste)) {
        mysqli_stmt_bind_param($stmt_ajuste, "i", $objetivo_id);
        if (mysqli_stmt_execute($stmt_ajuste)) {
            mysqli_stmt_bind_result($stmt_ajuste, $db_ajuste_calorico);
            if (mysqli_stmt_fetch($stmt_ajuste)) { $ajuste_calorico_percentual = $db_ajuste_calorico; }
            else { $erros_calculo_meta_dashboard[] = "Objetivo não encontrado."; }
        } else { $erros_calculo_meta_dashboard[] = "Erro ao buscar ajuste calórico."; }
        mysqli_stmt_close($stmt_ajuste);
    } else { $erros_calculo_meta_dashboard[] = "Erro ao preparar consulta do ajuste.";}

} elseif (empty($erros_calculo_meta_dashboard) && (!$nivel_atividade_id || !$objetivo_id)) {
    $erros_calculo_meta_dashboard[] = "Dados de perfil incompletos para calcular a meta. Por favor, <a href='perfil.php' class='link-erro'>atualize seu perfil</a>.";
}


if (empty($erros_calculo_meta_dashboard) && $peso_kg && $altura_cm && $data_nasc && $sexo && $fator_atividade !== null && $ajuste_calorico_percentual !== null) {
    $idade_anos = calcularIdade($data_nasc);
    if ($idade_anos !== null) {
        $tmb = calcularTMB($peso_kg, $altura_cm, $idade_anos, $sexo);
        if ($tmb !== null) {
            $get = calcularGET($tmb, $fator_atividade);
            if ($get !== null) {
                $meta_calorica_diaria_utilizador = calcularMetaCalorica($get, $ajuste_calorico_percentual);
                if ($meta_calorica_diaria_utilizador === null) { $erros_calculo_meta_dashboard[] = "Não foi possível calcular a Meta Calórica."; }
            } else { $erros_calculo_meta_dashboard[] = "Não foi possível calcular o GET."; }
        } else { $erros_calculo_meta_dashboard[] = "Não foi possível calcular a TMB."; }
    } else { $erros_calculo_meta_dashboard[] = "Não foi possível calcular a idade."; }
} elseif (empty($erros_calculo_meta_dashboard) && (!$peso_kg || !$altura_cm || !$data_nasc || !$sexo || $fator_atividade === null || $ajuste_calorico_percentual === null)) {
     $erros_calculo_meta_dashboard[] = "Faltam dados essenciais para o cálculo da meta calórica. Por favor, <a href='perfil.php' class='link-erro'>complete seu perfil</a>.";
}

// Consumo de água de hoje (simplificado para o dashboard)
$consumo_hoje_ml = 0;
$data_hoje = date("Y-m-d");
$sql_consumo_agua = "SELECT quantidade_ml FROM registros_agua WHERE usuario_id = ? AND data_registro = ?";
if ($stmt_agua = mysqli_prepare($conexao, $sql_consumo_agua)) {
    mysqli_stmt_bind_param($stmt_agua, "is", $id_usuario, $data_hoje);
    if (mysqli_stmt_execute($stmt_agua)) {
        mysqli_stmt_bind_result($stmt_agua, $db_consumo_hoje_ml);
        if (mysqli_stmt_fetch($stmt_agua)) {
            $consumo_hoje_ml = $db_consumo_hoje_ml;
        }
    }
    mysqli_stmt_close($stmt_agua);
}
$meta_agua_ml_dashboard = ($peso_kg && $peso_kg > 0) ? round($peso_kg * 35) : 2000; // Meta padrão se peso não disponível
$percentagem_agua_dashboard = ($meta_agua_ml_dashboard > 0) ? round(($consumo_hoje_ml / $meta_agua_ml_dashboard) * 100) : 0;
if ($percentagem_agua_dashboard > 100) $percentagem_agua_dashboard = 100;


if(isset($conexao) && $conexao instanceof mysqli) { mysqli_close($conexao); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - CalorIQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/icons/favicon.ico" type="image/x-icon"> <style>
        :root {
            --cor-primaria: #A7F3D0; /* Verde Menta Pastel Principal (mais claro para fundos de card) */
            --cor-primaria-escura: #059669; /* Verde Menta Escuro (para textos, botões, acentos) */
            --cor-primaria-muito-escura: #047857;
            --cor-fundo-app: #F0FDF4; /* Verde Menta Bem Claro (quase branco) */
            --cor-texto-principal: #1F2937; /* Cinza escuro */
            --cor-texto-secundario: #4B5563; /* Cinza médio */
            --cor-surface-container: #FFFFFF; /* Branco para cards principais */
            --cor-surface-container-low: #F9FAFB; /* Cinza muito claro para fundos sutis */
            --cor-outline: #D1D5DB; /* Cinza claro para bordas */
            --cor-erro: #EF4444;
            --cor-sucesso: #10B981;

            --radius-card: 16px; /* Material 3 usa bordas mais pronunciadas */
            --radius-botao: 20px; /* Botões totalmente arredondados (pílula) */
            --sombra-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --sombra-card-elevada: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.07);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent; /* Remove highlight azul no toque em mobile */
        }

        html, body {
            height: 100%;
            overscroll-behavior-y: contain; /* Evita scroll "elástico" da página inteira em mobile */
        }

        body {
            font-family: 'Inter', 'Roboto', sans-serif;
            background-color: var(--cor-fundo-app);
            color: var(--cor-texto-principal);
            line-height: 1.6;
            padding-top: env(safe-area-inset-top, 20px); /* Espaço para notch/status bar */
            padding-bottom: calc(65px + env(safe-area-inset-bottom, 10px)); /* Espaço para nav bar e safe area inferior */
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex-grow: 1;
            padding: 20px 16px;
            overflow-y: auto; /* Permite scroll apenas no conteúdo principal */
        }

        .header-dashboard {
            margin-bottom: 24px;
        }

        .header-dashboard h1 {
            font-size: 1.8rem; /* 28px */
            font-weight: 700;
            color: var(--cor-primaria-escura);
            margin-bottom: 4px;
        }

        .header-dashboard p {
            font-size: 1rem; /* 16px */
            color: var(--cor-texto-secundario);
        }
        
        .mensagem.erro {
            background-color: #fee2e2; color: var(--cor-erro); padding: 12px;
            border-radius: var(--radius-card); margin-bottom: 16px;
            border: 1px solid #fca5a5; font-size: 0.9rem;
        }
        .mensagem.erro .link-erro { color: var(--cor-erro); font-weight: 600; text-decoration: underline;}


        .dashboard-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: 1fr; /* Uma coluna por padrão */
        }

        /* Para telas maiores, podemos ter 2 colunas */
        @media (min-width: 600px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }
        
        .card {
            background-color: var(--cor-surface-container);
            border-radius: var(--radius-card);
            padding: 20px;
            box-shadow: var(--sombra-card);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--sombra-card-elevada);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .card-header svg {
            width: 24px;
            height: 24px;
            color: var(--cor-primaria-escura);
            margin-right: 12px;
        }
        .card-header h2 {
            font-size: 1.125rem; /* 18px */
            font-weight: 600;
            color: var(--cor-texto-principal);
        }

        .card-content p {
            font-size: 0.95rem;
            color: var(--cor-texto-secundario);
            margin-bottom: 8px;
        }
        .card-content strong {
            font-size: 1.5rem; /* 24px */
            color: var(--cor-primaria-escura);
            font-weight: 700;
            display: block;
            margin-bottom: 16px;
        }
        
        .progress-bar-agua {
            width: 100%;
            height: 8px;
            background-color: var(--cor-primaria);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-bar-agua div {
            height: 100%;
            width: <?php echo $percentagem_agua_dashboard; ?>%;
            background-color: var(--cor-primaria-escura);
            border-radius: 4px;
            transition: width 0.5s ease-in-out;
        }
        .agua-detalhes {
            font-size: 0.875rem;
            color: var(--cor-texto-secundario);
            margin-top: 8px;
        }

        .btn-primary {
            background-color: var(--cor-primaria-escura);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: var(--radius-botao);
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            cursor: pointer;
            transition: background-color 0.2s ease-out;
            margin-top: auto; /* Empurra o botão para baixo no card flex */
        }
        .btn-primary:hover {
            background-color: var(--cor-primaria-muito-escura);
        }
        .btn-primary svg { /* Para ícones dentro de botões */
            width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;
        }


        /* Barra de Navegação Inferior */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            background-color: var(--cor-surface-container);
            border-top: 1px solid var(--cor-outline);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-around;
            align-items: stretch;
            height: calc(65px + env(safe-area-inset-bottom, 0px)); /* Altura da barra + safe area */
            padding-bottom: env(safe-area-inset-bottom, 0px); /* Padding para safe area */
            z-index: 1000;
        }
        .nav-item {
            color: var(--cor-texto-secundario);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            text-align: center;
            font-size: 0.7rem; /* 11px */
            font-weight: 500;
            padding: 8px 4px 4px 4px; /* Ajustado para melhor espaçamento */
            transition: color 0.2s ease-in-out;
            position: relative; /* Para o indicador ativo */
        }
        .nav-item svg {
            width: 26px; /* Aumentado */
            height: 26px; /* Aumentado */
            margin-bottom: 3px;
        }
        .nav-item:hover {
            color: var(--cor-primaria-escura);
        }
        .nav-item.active {
            color: var(--cor-primaria-escura);
            font-weight: 700;
        }
        /* Indicador de item ativo (Material 3 style) */
        .nav-item.active::before {
            content: '';
            position: absolute;
            top: 6px; /* Ajustar para centralizar */
            left: 50%;
            transform: translateX(-50%);
            width: 48px; /* Largura do indicador */
            height: 28px; /* Altura do indicador */
            background-color: var(--cor-primaria); /* Cor do indicador */
            border-radius: 14px; /* Metade da altura para pílula */
            z-index: -1; /* Atrás do ícone e texto */
        }
    </style>
</head>
<body>

    <div class="main-content">
        <header class="header-dashboard">
            <h1>Olá, <?php echo $nome_usuario; ?>!</h1>
            <p>Pronto para cuidar da sua alimentação hoje?</p>
        </header>

        <?php if (!empty($erros_calculo_meta_dashboard)): ?>
            <div class="mensagem erro">
                <strong>Atenção:</strong>
                <ul>
                    <?php foreach ($erros_calculo_meta_dashboard as $erro): ?>
                        <li><?php echo $erro; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013-3.866A8.237 8.237 0 009 3c1.186 0 2.303.25 3.362.703V5.214z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214C14.302 4.195 13.072 3.374 12 3c-1.028.374-2.258 1.195-3.362 2.214m6.724 0a34.143 34.143 0 00-6.724 0M12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
                    <h2>Sua Meta Diária</h2>
                </div>
                <div class="card-content">
                    <?php if ($meta_calorica_diaria_utilizador !== null): ?>
                        <strong><?php echo number_format($meta_calorica_diaria_utilizador, 0, ',', '.'); ?> kcal</strong>
                        <p>Esta é a sua necessidade calórica estimada para hoje, com base no seu perfil e objetivo.</p>
                    <?php else: ?>
                        <p>Não foi possível calcular sua meta. <a href="perfil.php" class="link-erro">Complete seu perfil</a>.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5M12 15L12 18" /></svg>
                    <h2>Plano do Dia</h2>
                </div>
                <div class="card-content">
                    <p>Veja as sugestões de refeições para hoje e organize sua alimentação.</p>
                    </div>
                <a href="plano_diario.php" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639l4.418-4.418c.723-.723 1.89-.723 2.612 0l1.582 1.581c.723.723.723 1.89 0 2.612l-4.418 4.418a1.012 1.012 0 01-.639 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M17.964 12.322a1.012 1.012 0 010-.639l-4.418-4.418c-.723-.723-1.89-.723-2.612 0l-1.581 1.581c-.723.723-.723 1.89 0 2.612l4.418 4.418a1.012 1.012 0 01.639 0z" /></svg>
                    Ver meu Plano
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                    <h2>Hidratação</h2>
                </div>
                <div class="card-content">
                    <p>Meta: <?php echo number_format($meta_agua_ml_dashboard, 0, ',', '.'); ?> ml</p>
                    <div class="progress-bar-agua">
                        <div></div>
                    </div>
                    <p class="agua-detalhes">Consumido: <?php echo number_format($consumo_hoje_ml, 0, ',', '.'); ?> ml (<?php echo $percentagem_agua_dashboard; ?>%)</p>
                </div>
                <a href="agua.php" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Registar Água
                </a>
            </div>
            
            <div class="card">
                <div class="card-header">
                     <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-4.5A3.375 3.375 0 0012.75 9H11.25A3.375 3.375 0 007.5 12.375V18.75m9 0h-9" /></svg>
                    <h2>Meu Progresso</h2>
                </div>
                <div class="card-content">
                    <p>Em breve: Acompanhe suas conquistas e evolução aqui!</p>
                </div>
                 <a href="#" class="btn-primary" style="background-color: var(--cor-texto-secundario); cursor: not-allowed;" disabled>Ver Detalhes</a>
            </div>

        </div> </div> <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item active">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" /></svg>
            Início
        </a>
        <a href="plano_diario.php" class="nav-item">
             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5M12 15L12 18" /></svg>
            Plano
        </a>
        <a href="agua.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
            Água
        </a>
        <a href="perfil.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
            Perfil
        </a>
    </nav>

</body>
</html>
