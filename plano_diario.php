<?php
// plano_diario.php

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
require_once 'php_includes/funcoes_nutricionais.php';
require_once 'php_includes/gerador_plano.php'; // Usaremos a versão mais recente do gerador

$id_usuario = $_SESSION["id_usuario"];
$nome_usuario = isset($_SESSION["nome_usuario"]) ? htmlspecialchars($_SESSION["nome_usuario"]) : 'Utilizador';

// --- Lógica para calcular a meta calórica diária do utilizador (reutilizada) ---
$peso_kg = $altura_cm = $data_nasc = $sexo = $nivel_atividade_id = $objetivo_id = null;
$fator_atividade = null;
$ajuste_calorico_percentual = null;
$meta_calorica_usuario = null;
$erros_calculo_meta_plano = [];

$sql_user_data = "SELECT peso_kg, altura_cm, data_nasc, sexo, nivel_atividade_id, objetivo_id FROM usuarios WHERE id = ?";
if ($stmt_user_data = mysqli_prepare($conexao, $sql_user_data)) {
    mysqli_stmt_bind_param($stmt_user_data, "i", $id_usuario);
    if (mysqli_stmt_execute($stmt_user_data)) {
        mysqli_stmt_bind_result($stmt_user_data, $db_peso_kg, $db_altura_cm, $db_data_nasc, $db_sexo, $db_nivel_atividade_id, $db_objetivo_id);
        if (mysqli_stmt_fetch($stmt_user_data)) {
            $peso_kg = $db_peso_kg; $altura_cm = $db_altura_cm; $data_nasc = $db_data_nasc;
            $sexo = $db_sexo; $nivel_atividade_id = $db_nivel_atividade_id; $objetivo_id = $db_objetivo_id;
        } else { $erros_calculo_meta_plano[] = "Dados do utilizador não encontrados."; }
    } else { $erros_calculo_meta_plano[] = "Erro ao buscar dados do utilizador."; }
    mysqli_stmt_close($stmt_user_data);
} else { $erros_calculo_meta_plano[] = "Erro ao preparar busca de dados do utilizador."; }

if (empty($erros_calculo_meta_plano) && $nivel_atividade_id && $objetivo_id) {
    $sql_fator = "SELECT fator_multiplicador FROM niveis_atividade WHERE id = ?";
    if ($stmt_fator = mysqli_prepare($conexao, $sql_fator)) {
        mysqli_stmt_bind_param($stmt_fator, "i", $nivel_atividade_id);
        if (mysqli_stmt_execute($stmt_fator)) {
            mysqli_stmt_bind_result($stmt_fator, $db_fator_atividade);
            if (mysqli_stmt_fetch($stmt_fator)) { $fator_atividade = $db_fator_atividade; }
            else { $erros_calculo_meta_plano[] = "Nível de atividade não encontrado."; }
        } else { $erros_calculo_meta_plano[] = "Erro ao buscar fator de atividade."; }
        mysqli_stmt_close($stmt_fator);
    } else { $erros_calculo_meta_plano[] = "Erro ao preparar consulta do fator.";}

    $sql_ajuste = "SELECT ajuste_calorico_percentual FROM objetivos WHERE id = ?";
    if ($stmt_ajuste = mysqli_prepare($conexao, $sql_ajuste)) {
        mysqli_stmt_bind_param($stmt_ajuste, "i", $objetivo_id);
        if (mysqli_stmt_execute($stmt_ajuste)) {
            mysqli_stmt_bind_result($stmt_ajuste, $db_ajuste_calorico);
            if (mysqli_stmt_fetch($stmt_ajuste)) { $ajuste_calorico_percentual = $db_ajuste_calorico; }
            else { $erros_calculo_meta_plano[] = "Objetivo não encontrado."; }
        } else { $erros_calculo_meta_plano[] = "Erro ao buscar ajuste calórico."; }
        mysqli_stmt_close($stmt_ajuste);
    } else { $erros_calculo_meta_plano[] = "Erro ao preparar consulta do ajuste.";}

} elseif (empty($erros_calculo_meta_plano) && (!$nivel_atividade_id || !$objetivo_id)) {
    $erros_calculo_meta_plano[] = "Dados de perfil incompletos para calcular a meta. Por favor, <a href='perfil.php' class='link-erro'>atualize seu perfil</a>.";
}

if (empty($erros_calculo_meta_plano) && $peso_kg && $altura_cm && $data_nasc && $sexo && $fator_atividade !== null && $ajuste_calorico_percentual !== null) {
    $idade_anos = calcularIdade($data_nasc);
    if ($idade_anos !== null) {
        $tmb = calcularTMB($peso_kg, $altura_cm, $idade_anos, $sexo);
        if ($tmb !== null) {
            $get = calcularGET($tmb, $fator_atividade);
            if ($get !== null) {
                $meta_calorica_usuario = calcularMetaCalorica($get, $ajuste_calorico_percentual);
                if ($meta_calorica_usuario === null) { $erros_calculo_meta_plano[] = "Não foi possível calcular a Meta Calórica."; }
            } else { $erros_calculo_meta_plano[] = "Não foi possível calcular o GET."; }
        } else { $erros_calculo_meta_plano[] = "Não foi possível calcular a TMB."; }
    } else { $erros_calculo_meta_plano[] = "Não foi possível calcular a idade."; }
} elseif (empty($erros_calculo_meta_plano) && (!$peso_kg || !$altura_cm || !$data_nasc || !$sexo || $fator_atividade === null || $ajuste_calorico_percentual === null)) {
     $erros_calculo_meta_plano[] = "Faltam dados essenciais para o cálculo da meta calórica. Por favor, <a href='perfil.php' class='link-erro'>complete seu perfil</a>.";
}

// --- Geração do Plano Alimentar ---
$plano_alimentar_gerado = null;
$data_plano_exibicao = date("d/m/Y"); // Para exibição

if (empty($erros_calculo_meta_plano) && $meta_calorica_usuario !== null) {
    $plano_alimentar_gerado = gerarPlanoAlimentarDia($meta_calorica_usuario, $conexao, $id_usuario);
    if ($plano_alimentar_gerado === null) {
        $erros_calculo_meta_plano[] = "Não foi possível gerar um plano alimentar no momento. Verifique se há alimentos cadastrados ou tente novamente mais tarde.";
    } else {
        $data_plano_exibicao = $plano_alimentar_gerado['data']; // Usa a data do plano gerado
    }
} elseif (empty($erros_calculo_meta_plano)) {
    $erros_calculo_meta_plano[] = "Meta calórica não pôde ser determinada, impossível gerar plano.";
}

if(isset($conexao) && $conexao instanceof mysqli) { mysqli_close($conexao); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Plano Diário - CalorIQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/icons/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --cor-primaria: #A7F3D0;
            --cor-primaria-escura: #059669;
            --cor-primaria-muito-escura: #047857;
            --cor-fundo-app: #F0FDF4;
            --cor-texto-principal: #1F2937;
            --cor-texto-secundario: #4B5563;
            --cor-surface-container: #FFFFFF;
            --cor-surface-container-high: #F3F4F6; /* Um pouco mais escuro que o branco para contraste sutil */
            --cor-outline: #D1D5DB;
            --cor-erro: #EF4444;
            --radius-card: 16px;
            --radius-botao: 20px;
            --sombra-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; overscroll-behavior-y: contain; }
        body {
            font-family: 'Inter', 'Roboto', sans-serif;
            background-color: var(--cor-fundo-app);
            color: var(--cor-texto-principal);
            line-height: 1.6;
            padding-top: env(safe-area-inset-top, 20px);
            padding-bottom: calc(65px + env(safe-area-inset-bottom, 10px));
            display: flex;
            flex-direction: column;
        }
        .main-content { flex-grow: 1; padding: 20px 16px; overflow-y: auto; }
        .header-plano { text-align: center; margin-bottom: 24px; }
        .header-plano h1 { font-size: 1.8rem; font-weight: 700; color: var(--cor-primaria-escura); margin-bottom: 4px; }
        .header-plano .data-plano { font-size: 1rem; color: var(--cor-texto-secundario); margin-bottom: 8px; }
        .header-plano .meta-calorica-info {
            background-color: var(--cor-primaria);
            color: var(--cor-primaria-muito-escura);
            padding: 8px 12px;
            border-radius: var(--radius-botao);
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }
        .mensagem.erro {
            background-color: #fee2e2; color: var(--cor-erro); padding: 12px;
            border-radius: var(--radius-card); margin-bottom: 16px;
            border: 1px solid #fca5a5; font-size: 0.9rem;
        }
        .mensagem.erro .link-erro { color: var(--cor-erro); font-weight: 600; text-decoration: underline;}

        .refeicao-card {
            background-color: var(--cor-surface-container);
            border-radius: var(--radius-card);
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: var(--sombra-card);
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0; /* Começa invisível para animação */
        }
        /* Animação de entrada para os cards */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Delay para cada card */
        <?php for ($i=0; $i<5; $i++) { echo ".refeicao-card:nth-child(".($i+1).") { animation-delay: ".($i * 0.1)."s; }"; } ?>

        .refeicao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--cor-outline);
        }
        .refeicao-header h2 {
            font-size: 1.25rem; /* 20px */
            font-weight: 600;
            color: var(--cor-primaria-escura);
        }
        .refeicao-header .horario-sugerido {
            font-size: 0.8rem; /* 13px */
            color: var(--cor-texto-secundario);
            background-color: var(--cor-surface-container-high);
            padding: 4px 8px;
            border-radius: 12px;
        }
        .lista-alimentos { list-style: none; padding: 0; margin-bottom: 16px; }
        .lista-alimentos li {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Alinha itens no topo se o texto quebrar */
            padding: 10px 0;
            border-bottom: 1px solid var(--cor-surface-container-high);
            font-size: 0.95rem;
        }
        .lista-alimentos li:last-child { border-bottom: none; }
        .alimento-info { flex-grow: 1; margin-right: 10px; }
        .alimento-info .nome { display: block; font-weight: 500; color: var(--cor-texto-principal); }
        .alimento-info .quantidade { font-size: 0.85rem; color: var(--cor-texto-secundario); }
        .alimento-calorias {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--cor-primaria-escura);
            white-space: nowrap; /* Evita quebra de linha nas calorias */
        }
        .total-refeicao {
            text-align: right;
            font-weight: 600;
            color: var(--cor-primaria-muito-escura);
            margin-top: 12px;
            font-size: 1rem;
            padding-top: 12px;
            border-top: 1px solid var(--cor-outline);
        }
        .plano-total-geral {
            margin-top: 24px;
            padding: 16px;
            background-color: var(--cor-primaria);
            border-radius: var(--radius-card);
            text-align: center;
        }
        .plano-total-geral p {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--cor-primaria-muito-escura);
            margin: 0;
        }
        .plano-total-geral span { font-size: 1.4rem; }
        .empty-plan-message {
            text-align: center; padding: 20px; background-color: var(--cor-surface-container-high);
            border-radius: var(--radius-card); color: var(--cor-texto-secundario);
        }

        /* Barra de Navegação Inferior (mesmo estilo do dashboard) */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; width: 100%;
            background-color: var(--cor-surface-container); border-top: 1px solid var(--cor-outline);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-around;
            align-items: stretch; height: calc(65px + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px); z-index: 1000;
        }
        .nav-item {
            color: var(--cor-texto-secundario); text-decoration: none; display: flex; flex-direction: column;
            align-items: center; justify-content: center; flex-grow: 1; text-align: center;
            font-size: 0.7rem; font-weight: 500; padding: 8px 4px 4px 4px;
            transition: color 0.2s ease-in-out; position: relative;
        }
        .nav-item svg { width: 26px; height: 26px; margin-bottom: 3px; }
        .nav-item:hover { color: var(--cor-primaria-escura); }
        .nav-item.active { color: var(--cor-primaria-escura); font-weight: 700; }
        .nav-item.active::before {
            content: ''; position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
            width: 48px; height: 28px; background-color: var(--cor-primaria);
            border-radius: 14px; z-index: -1;
        }
    </style>
</head>
<body>

    <div class="main-content">
        <header class="header-plano">
            <h1>Meu Plano Diário</h1>
            <p class="data-plano">Para o dia: <?php echo htmlspecialchars($data_plano_exibicao); ?></p>
            <?php if ($meta_calorica_usuario !== null && empty($erros_calculo_meta_plano)): ?>
                <div class="meta-calorica-info">
                    Sua Meta: <strong><?php echo number_format($meta_calorica_usuario, 0, ',', '.'); ?> kcal</strong>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($erros_calculo_meta_plano)): ?>
            <div class="mensagem erro">
                <strong>Atenção:</strong>
                <ul>
                    <?php foreach ($erros_calculo_meta_plano as $erro): ?>
                        <li><?php echo $erro; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($plano_alimentar_gerado !== null && !empty($plano_alimentar_gerado['refeicoes'])): ?>
            <?php foreach ($plano_alimentar_gerado['refeicoes'] as $refeicao): ?>
                <?php if (!empty($refeicao['itens'])): // Só mostra o card se a refeição tiver itens ?>
                <div class="refeicao-card">
                    <div class="refeicao-header">
                        <h2><?php echo htmlspecialchars($refeicao['nome']); ?></h2>
                        <span class="horario-sugerido"><?php echo htmlspecialchars($refeicao['horario_sugerido']); ?></span>
                    </div>
                    <ul class="lista-alimentos">
                        <?php foreach ($refeicao['itens'] as $item): ?>
                            <li>
                                <div class="alimento-info">
                                    <span class="nome"><?php echo htmlspecialchars($item['alimento']); ?></span>
                                    <span class="quantidade"><?php echo htmlspecialchars($item['quantidade']); ?></span>
                                </div>
                                <span class="alimento-calorias"><?php echo number_format($item['calorias_aprox'], 0, ',', '.'); ?> kcal</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="total-refeicao">Total da Refeição: <?php echo number_format($refeicao['total_calorias_refeicao'], 0, ',', '.'); ?> kcal</p>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="plano-total-geral">
                <p>Total Estimado do Plano: <span><?php echo number_format($plano_alimentar_gerado['total_calorias_plano_gerado'], 0, ',', '.'); ?> kcal</span></p>
            </div>

        <?php elseif (empty($erros_calculo_meta_plano)): // Se não houve erro na meta, mas plano não foi gerado ?>
            <div class="empty-plan-message">
                <p>Não foi possível gerar um plano alimentar para hoje.</p>
                <p>Isso pode acontecer se não houver alimentos suficientes cadastrados que se encaixem nas suas necessidades e restrições, ou se sua meta calórica for muito específica.</p>
                <p>Por favor, verifique seu <a href="perfil.php" class="link-erro">perfil</a> e tente novamente mais tarde.</p>
            </div>
        <?php endif; ?>

    </div> <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" /></svg>
            Início
        </a>
        <a href="plano_diario.php" class="nav-item active"> <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5M12 15L12 18" /></svg>
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
