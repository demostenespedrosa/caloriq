<?php
// agua.php

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
// funcoes_nutricionais.php não é estritamente necessário aqui se o peso já estiver na sessão ou for buscado diretamente.
// Mas é bom ter para consistência, caso precise de alguma função auxiliar no futuro.
require_once 'php_includes/funcoes_nutricionais.php';

$id_usuario = $_SESSION["id_usuario"];
$data_hoje = date("Y-m-d");

$peso_kg_atual = null;
$meta_agua_ml = 2000; // Meta padrão inicial
$consumo_hoje_ml = 0;
$mensagem_agua_html = ''; // Para mensagens de erro ou sucesso
$erros_agua_page = [];

// 1. Buscar o peso atual do utilizador para calcular a meta de água
$sql_peso = "SELECT peso_kg FROM usuarios WHERE id = ?";
if ($stmt_peso = mysqli_prepare($conexao, $sql_peso)) {
    mysqli_stmt_bind_param($stmt_peso, "i", $id_usuario);
    if (mysqli_stmt_execute($stmt_peso)) {
        mysqli_stmt_bind_result($stmt_peso, $db_peso_kg);
        if (mysqli_stmt_fetch($stmt_peso) && $db_peso_kg !== null && $db_peso_kg > 0) {
            $peso_kg_atual = $db_peso_kg;
            $meta_agua_ml = round($peso_kg_atual * 35); // Exemplo: 35ml por kg de peso
        } else {
            // Não define erro aqui, usa meta padrão e avisa na interface se peso não disponível
        }
    } else {
        $erros_agua_page[] = "Erro ao buscar peso para cálculo da meta: " . mysqli_stmt_error($stmt_peso);
    }
    mysqli_stmt_close($stmt_peso);
} else {
    $erros_agua_page[] = "Erro ao preparar busca de peso: " . mysqli_error($conexao);
}


// 2. Processar adição de água (quando o formulário é submetido)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_agua_ml'])) {
    $agua_adicionada_ml = (int)$_POST['add_agua_ml'];

    if ($agua_adicionada_ml > 0) {
        $sql_check_registo = "SELECT id, quantidade_ml FROM registros_agua WHERE usuario_id = ? AND data_registro = ?";
        if ($stmt_check = mysqli_prepare($conexao, $sql_check_registo)) {
            mysqli_stmt_bind_param($stmt_check, "is", $id_usuario, $data_hoje);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            $registo_id_atual = null;
            $quantidade_existente_ml = 0;

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                mysqli_stmt_bind_result($stmt_check, $registo_id_atual, $quantidade_existente_ml);
                mysqli_stmt_fetch($stmt_check);
                $nova_quantidade_ml = $quantidade_existente_ml + $agua_adicionada_ml;

                $sql_update_agua = "UPDATE registros_agua SET quantidade_ml = ?, meta_ml = ? WHERE id = ?";
                if ($stmt_update = mysqli_prepare($conexao, $sql_update_agua)) {
                    mysqli_stmt_bind_param($stmt_update, "iii", $nova_quantidade_ml, $meta_agua_ml, $registo_id_atual);
                    if (!mysqli_stmt_execute($stmt_update)) {
                        $erros_agua_page[] = "Erro ao atualizar consumo: " . mysqli_stmt_error($stmt_update);
                    }
                    mysqli_stmt_close($stmt_update);
                } else { $erros_agua_page[] = "Erro ao preparar atualização: " . mysqli_error($conexao); }
            } else {
                // Insere novo registo
                $nova_quantidade_ml = $agua_adicionada_ml;
                $sql_insert_agua = "INSERT INTO registros_agua (usuario_id, data_registro, quantidade_ml, meta_ml) VALUES (?, ?, ?, ?)";
                if ($stmt_insert = mysqli_prepare($conexao, $sql_insert_agua)) {
                    mysqli_stmt_bind_param($stmt_insert, "isii", $id_usuario, $data_hoje, $nova_quantidade_ml, $meta_agua_ml);
                    if (!mysqli_stmt_execute($stmt_insert)) {
                        $erros_agua_page[] = "Erro ao registar consumo: " . mysqli_stmt_error($stmt_insert);
                    }
                    mysqli_stmt_close($stmt_insert);
                } else { $erros_agua_page[] = "Erro ao preparar inserção: " . mysqli_error($conexao); }
            }
            mysqli_stmt_close($stmt_check);

            if(empty($erros_agua_page)) {
                $mensagem_agua_html = "<div class='mensagem sucesso anim-fade-in'>+{$agua_adicionada_ml}ml adicionados! Continue assim!</div>";
                $consumo_hoje_ml = $nova_quantidade_ml; // Atualiza para exibição imediata
            }
        } else {
            $erros_agua_page[] = "Erro ao verificar registo de água: " . mysqli_error($conexao);
        }
    }
}

// 3. Buscar o consumo de água de hoje para exibição (SEMPRE, para refletir o estado atual)
if (empty($erros_agua_page) || $_SERVER["REQUEST_METHOD"] != "POST") { // Se não houve erro no POST ou é GET
    $sql_consumo_hoje = "SELECT quantidade_ml FROM registros_agua WHERE usuario_id = ? AND data_registro = ?";
    if ($stmt_consumo = mysqli_prepare($conexao, $sql_consumo_hoje)) {
        mysqli_stmt_bind_param($stmt_consumo, "is", $id_usuario, $data_hoje);
        if (mysqli_stmt_execute($stmt_consumo)) {
            mysqli_stmt_bind_result($stmt_consumo, $db_consumo_hoje_ml);
            if (mysqli_stmt_fetch($stmt_consumo)) {
                $consumo_hoje_ml = $db_consumo_hoje_ml;
            } else {
                $consumo_hoje_ml = 0; // Garante que é zero se não houver registo
            }
        } else {
            $erros_agua_page[] = "Erro ao buscar consumo de hoje: " . mysqli_stmt_error($stmt_consumo);
        }
        mysqli_stmt_close($stmt_consumo);
    } else {
        $erros_agua_page[] = "Erro ao preparar busca de consumo: " . mysqli_error($conexao);
    }
}


$percentagem_consumida = 0;
if ($meta_agua_ml > 0) {
    $percentagem_consumida = round(($consumo_hoje_ml / $meta_agua_ml) * 100);
}
// Não limita a 100% aqui, para o texto poder mostrar mais, mas a barra pode ser limitada no CSS/JS se necessário.

if(isset($conexao) && $conexao instanceof mysqli) { mysqli_close($conexao); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Minha Hidratação - CalorIQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/icons/favicon.ico" type="image/x-icon">
    <style>
        :root { /* Mesmo tema do dashboard */
            --cor-primaria: #A7F3D0; --cor-primaria-escura: #059669; --cor-primaria-muito-escura: #047857;
            --cor-fundo-app: #F0FDF4; --cor-texto-principal: #1F2937; --cor-texto-secundario: #4B5563;
            --cor-surface-container: #FFFFFF; --cor-surface-container-high: #F3F4F6;
            --cor-outline: #D1D5DB; --cor-erro: #EF4444; --cor-sucesso: #10B981;
            --radius-card: 16px; --radius-botao: 20px; --radius-botao-quadrado: 12px;
            --sombra-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; overscroll-behavior-y: contain; }
        body {
            font-family: 'Inter', 'Roboto', sans-serif; background-color: var(--cor-fundo-app);
            color: var(--cor-texto-principal); line-height: 1.6;
            padding-top: env(safe-area-inset-top, 20px);
            padding-bottom: calc(65px + env(safe-area-inset-bottom, 10px));
            display: flex; flex-direction: column;
        }
        .main-content { flex-grow: 1; padding: 20px 16px; overflow-y: auto; display: flex; flex-direction: column; align-items: center;}
        .header-agua { text-align: center; margin-bottom: 24px; }
        .header-agua h1 { font-size: 1.8rem; font-weight: 700; color: var(--cor-primaria-escura); margin-bottom: 4px; }
        .header-agua p { font-size: 1rem; color: var(--cor-texto-secundario); }

        .agua-card {
            background-color: var(--cor-surface-container); border-radius: var(--radius-card);
            padding: 24px; box-shadow: var(--sombra-card); width: 100%; max-width: 450px;
            text-align: center; margin-bottom: 24px;
        }
        .meta-display { margin-bottom: 20px; }
        .meta-display p { font-size: 1rem; color: var(--cor-texto-secundario); }
        .meta-display strong { font-size: 1.8rem; color: var(--cor-primaria-escura); display: block; }
        
        .progresso-agua-wrapper {
            position: relative;
            width: 180px; /* Tamanho do círculo */
            height: 180px;
            margin: 0 auto 20px auto;
        }
        .progresso-agua-bg, .progresso-agua-valor {
            fill: none;
            stroke-width: 16; /* Espessura da linha */
            transform: rotate(-90deg 90px 90px); /* Começa do topo */
        }
        .progresso-agua-bg { stroke: var(--cor-primaria); }
        .progresso-agua-valor {
            stroke: var(--cor-primaria-escura);
            stroke-linecap: round; /* Pontas arredondadas */
            transition: stroke-dashoffset 0.5s ease-out;
        }
        .progresso-agua-texto {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            font-size: 1.5rem; font-weight: 700; color: var(--cor-primaria-escura);
        }
        .progresso-agua-texto small { font-size: 0.8rem; display: block; color: var(--cor-texto-secundario); font-weight: 500;}

        .consumo-atual-texto { font-size: 1rem; color: var(--cor-texto-secundario); margin-bottom: 24px; }
        .consumo-atual-texto strong { color: var(--cor-texto-principal); font-weight: 600;}

        .botoes-add-agua { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px; }
        .btn-add-agua {
            background-color: var(--cor-primaria);
            color: var(--cor-primaria-muito-escura);
            border: none; padding: 14px 10px;
            border-radius: var(--radius-botao-quadrado); font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: background-color 0.2s ease-out, transform 0.1s ease;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .btn-add-agua svg { width: 28px; height: 28px; margin-bottom: 6px; }
        .btn-add-agua:hover { background-color: #90e8c0; }
        .btn-add-agua:active { transform: scale(0.96); }
        
        .mensagem { padding: 12px; border-radius: var(--radius-card); margin-top: 20px; font-size: 0.9rem; text-align: center; }
        .mensagem.sucesso { background-color: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;}
        .mensagem.erro { background-color: #FEE2E2; color: var(--cor-erro); border: 1px solid #FCA5A5;}
        .anim-fade-in { animation: fadeInSimple 0.5s ease-out; }
        @keyframes fadeInSimple { from { opacity: 0; } to { opacity: 1; } }

        /* Barra de Navegação Inferior */
        .bottom-nav { /* ... (mesmo estilo do dashboard) ... */
            position: fixed; bottom: 0; left: 0; right: 0; width: 100%;
            background-color: var(--cor-surface-container); border-top: 1px solid var(--cor-outline);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-around;
            align-items: stretch; height: calc(65px + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px); z-index: 1000;
        }
        .nav-item { /* ... (mesmo estilo do dashboard) ... */
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
        <header class="header-agua">
            <h1>Minha Hidratação</h1>
            <p>Lembre-se da importância de beber água regularmente!</p>
        </header>

        <div class="agua-card">
            <div class="meta-display">
                <p>Sua meta diária recomendada:</p>
                <strong><?php echo number_format($meta_agua_ml, 0, ',', '.'); ?> ml</strong>
                <?php if ($peso_kg_atual === null || $peso_kg_atual <= 0): ?>
                    <small style="color: var(--cor-texto-secundario); display:block; margin-top:4px;">(Meta padrão. <a href="perfil.php" style="color:var(--cor-primaria-escura); text-decoration:underline;">Atualize seu peso</a> para uma meta personalizada.)</small>
                <?php endif; ?>
            </div>

            <div class="progresso-agua-wrapper">
                <svg viewBox="0 0 180 180" class="progresso-svg">
                    <circle class="progresso-agua-bg" cx="90" cy="90" r="82"></circle>
                    <circle class="progresso-agua-valor" id="progressoAguaValor" cx="90" cy="90" r="82"
                            stroke-dasharray="515" stroke-dashoffset="515"></circle> </svg>
                <div class="progresso-agua-texto">
                    <span id="percentagemAguaTexto"><?php echo round($percentagem_consumida); ?>%</span>
                    <small>atingido</small>
                </div>
            </div>
            
            <p class="consumo-atual-texto">Consumido hoje: <strong id="consumoAguaTexto"><?php echo number_format($consumo_hoje_ml, 0, ',', '.'); ?> ml</strong></p>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="botoes-add-agua">
                    <button type="submit" name="add_agua_ml" value="250" class="btn-add-agua">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Copo (250ml)
                    </button>
                    <button type="submit" name="add_agua_ml" value="500" class="btn-add-agua">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Garrafa P (500ml)
                    </button>
                    <button type="submit" name="add_agua_ml" value="750" class="btn-add-agua">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Garrafa M (750ml)
                    </button>
                     <button type="submit" name="add_agua_ml" value="1000" class="btn-add-agua">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Garrafa G (1L)
                    </button>
                </div>
            </form>
        </div> <?php if (!empty($erros_agua_page)): ?>
            <div class="mensagem erro" style="max-width: 450px; width:100%;">
                <strong>Ops! Ocorreu um erro:</strong>
                <ul><?php foreach ($erros_agua_page as $erro): ?><li><?php echo $erro; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($mensagem_agua_html)) echo $mensagem_agua_html; ?>

    </div> <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" /></svg>Início</a>
        <a href="plano_diario.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5M12 15L12 18" /></svg>Plano</a>
        <a href="agua.php" class="nav-item active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>Água</a>
        <a href="perfil.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>Perfil</a>
    </nav>

    <script>
        // Pequeno script para animar a barra de progresso circular SVG
        document.addEventListener('DOMContentLoaded', function () {
            const progressoValorEl = document.getElementById('progressoAguaValor');
            const percentagemPHP = <?php echo $percentagem_consumida > 100 ? 100 : ($percentagem_consumida < 0 ? 0 : $percentagem_consumida); ?>; // Limita entre 0 e 100 para a barra
            const perimetro = 515; // 2 * PI * raio (82)
            
            // Calcula o offset para a percentagem (0% = perimetro total, 100% = 0)
            const offset = perimetro - (percentagemPHP / 100) * perimetro;
            
            // Aplica o offset com uma pequena animação via CSS transition (já definida no style)
            // Para um efeito mais imediato, pode-se definir direto, mas a transição CSS é mais suave.
            // Adicionando um pequeno delay para garantir que a transição CSS seja aplicada após o carregamento.
            setTimeout(() => {
                 progressoValorEl.style.strokeDashoffset = offset;
            }, 100); // 100ms delay
        });
    </script>
</body>
</html>
