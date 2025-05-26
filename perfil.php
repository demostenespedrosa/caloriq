<?php
// perfil.php

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
$id_usuario = $_SESSION["id_usuario"];
$nome_usuario_sessao = isset($_SESSION["nome_usuario"]) ? htmlspecialchars($_SESSION["nome_usuario"]) : 'Utilizador';


// Inicializa variáveis
$nome = $email_display = $telefone = $data_nasc = $sexo = $altura_cm = $peso_kg = $nivel_atividade_id = $objetivo_id = $renda_faixa = "";
$erros_perfil = [];
$mensagem_perfil_html = '';

// --- BUSCAR DADOS PARA PREENCHER O FORMULÁRIO ---
// Níveis de Atividade
$niveis_atividade_opcoes = [];
$sql_niveis = "SELECT id, nome_nivel FROM niveis_atividade ORDER BY id";
if($resultado_niveis = mysqli_query($conexao, $sql_niveis)){
    while($linha = mysqli_fetch_assoc($resultado_niveis)){ $niveis_atividade_opcoes[] = $linha; }
    mysqli_free_result($resultado_niveis);
} else { $erros_perfil[] = "Erro ao buscar níveis de atividade."; }

// Objetivos
$objetivos_opcoes = [];
$sql_objetivos = "SELECT id, nome_objetivo FROM objetivos ORDER BY id";
if($resultado_objetivos = mysqli_query($conexao, $sql_objetivos)){
    while($linha = mysqli_fetch_assoc($resultado_objetivos)){ $objetivos_opcoes[] = $linha; }
    mysqli_free_result($resultado_objetivos);
} else { $erros_perfil[] = "Erro ao buscar objetivos."; }

// Todos os Alimentos para seleção de restrições
$todos_alimentos_para_restricao = [];
$sql_todos_alimentos = "SELECT a.id, a.nome_alimento, ga.nome_grupo FROM alimentos a LEFT JOIN grupos_alimentos ga ON a.grupo_id = ga.id ORDER BY ga.nome_grupo, a.nome_alimento";
if($resultado_todos_alimentos = mysqli_query($conexao, $sql_todos_alimentos)){
    while($alimento_item = mysqli_fetch_assoc($resultado_todos_alimentos)){
        $grupo = $alimento_item['nome_grupo'] ?? 'Sem Grupo';
        $todos_alimentos_para_restricao[$grupo][] = $alimento_item;
    }
    mysqli_free_result($resultado_todos_alimentos);
} else { $erros_perfil[] = "Erro ao buscar lista de alimentos para restrições."; }

// Restrições atuais do utilizador
$restricoes_atuais_utilizador = [];
$sql_restricoes_atuais = "SELECT r.id as restricao_id, r.alimento_id, a.nome_alimento, r.tipo_restricao
                          FROM restricoes_usuario r
                          JOIN alimentos a ON r.alimento_id = a.id
                          WHERE r.usuario_id = ?
                          ORDER BY a.nome_alimento";
if($stmt_rest_atuais = mysqli_prepare($conexao, $sql_restricoes_atuais)) {
    mysqli_stmt_bind_param($stmt_rest_atuais, "i", $id_usuario);
    if(mysqli_stmt_execute($stmt_rest_atuais)){
        $res_rest_atuais = mysqli_stmt_get_result($stmt_rest_atuais);
        while($restricao_db = mysqli_fetch_assoc($res_rest_atuais)){ // Renomeada variável local
            $restricoes_atuais_utilizador[] = $restricao_db;
        }
        mysqli_free_result($res_rest_atuais);
    } else { $erros_perfil[] = "Erro ao buscar restrições atuais: " . mysqli_stmt_error($stmt_rest_atuais); }
    mysqli_stmt_close($stmt_rest_atuais);
} else { $erros_perfil[] = "Erro ao preparar busca de restrições atuais: " . mysqli_error($conexao); }


// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Dados Pessoais
    $nome_post = trim(htmlspecialchars($_POST['nome']));
    $telefone_post = trim(htmlspecialchars($_POST['telefone']));
    $data_nasc_post = trim(htmlspecialchars($_POST['data_nasc']));
    $sexo_post = isset($_POST['sexo']) ? trim(htmlspecialchars($_POST['sexo'])) : null;
    $altura_cm_post = trim(htmlspecialchars($_POST['altura_cm']));
    $peso_kg_post = trim(htmlspecialchars($_POST['peso_kg']));
    $nivel_atividade_id_post = isset($_POST['nivel_atividade_id']) ? (int)$_POST['nivel_atividade_id'] : null;
    $objetivo_id_post = isset($_POST['objetivo_id']) ? (int)$_POST['objetivo_id'] : null;
    $renda_faixa_post = isset($_POST['renda_faixa']) ? trim(htmlspecialchars($_POST['renda_faixa'])) : null;

    // Validações
    if (empty($nome_post)) $erros_perfil[] = "O nome é obrigatório.";
    if (!empty($telefone_post) && !preg_match('/^[0-9\s\(\)\-]+$/', $telefone_post)) $erros_perfil[] = "Formato de telefone inválido.";
    if (empty($data_nasc_post)) $erros_perfil[] = "Data de nascimento é obrigatória.";
    if (empty($sexo_post)) $erros_perfil[] = "Sexo é obrigatório.";
    if (empty($altura_cm_post) || !is_numeric($altura_cm_post) || $altura_cm_post <= 50 || $altura_cm_post > 300) $erros_perfil[] = "Altura inválida.";
    $peso_kg_norm = str_replace(',', '.', $peso_kg_post);
    if (empty($peso_kg_post) || !is_numeric($peso_kg_norm) || $peso_kg_norm <= 20 || $peso_kg_norm > 500) {
        $erros_perfil[] = "Peso inválido.";
    } else {
        $peso_kg_post = $peso_kg_norm;
    }
    if (empty($nivel_atividade_id_post)) $erros_perfil[] = "Nível de atividade é obrigatório.";
    if (empty($objetivo_id_post)) $erros_perfil[] = "Objetivo é obrigatório.";
    if (empty($renda_faixa_post)) $erros_perfil[] = "Faixa de renda é obrigatória.";

    // Validação e atualização de senha
    $nova_senha = $_POST['nova_senha'];
    $confirmar_nova_senha = $_POST['confirmar_nova_senha'];
    $senha_hash_update = null;
    if (!empty($nova_senha) || !empty($confirmar_nova_senha)) {
        if (empty($nova_senha)) $erros_perfil[] = "Nova senha não pode ser vazia se a confirmação estiver preenchida.";
        elseif (strlen($nova_senha) < 6) $erros_perfil[] = "A nova senha deve ter pelo menos 6 caracteres.";
        if ($nova_senha !== $confirmar_nova_senha) $erros_perfil[] = "As novas senhas não coincidem.";
        if (empty(array_filter($erros_perfil, function($e) { return strpos($e, 'senha') !== false; }))) { // Se não houve erros de senha
            $senha_hash_update = password_hash($nova_senha, PASSWORD_DEFAULT);
        }
    }

    // Processar remoção de restrições
    if (isset($_POST['remover_restricao']) && is_array($_POST['remover_restricao'])) {
        // ... (lógica de remoção mantida da versão anterior do perfil.php) ...
        $ids_remover = $_POST['remover_restricao'];
        if (!empty($ids_remover)) {
            $sql_delete_restricao = "DELETE FROM restricoes_usuario WHERE id = ? AND usuario_id = ?";
            if ($stmt_delete = mysqli_prepare($conexao, $sql_delete_restricao)) {
                foreach ($ids_remover as $restricao_id_para_remover) {
                    mysqli_stmt_bind_param($stmt_delete, "ii", $restricao_id_para_remover_int, $id_usuario);
                    $restricao_id_para_remover_int = (int)$restricao_id_para_remover;
                    if(!mysqli_stmt_execute($stmt_delete)){ $erros_perfil[] = "Erro ao remover restrição ID: " . $restricao_id_para_remover_int; }
                }
                mysqli_stmt_close($stmt_delete);
            } else { $erros_perfil[] = "Erro ao preparar remoção de restrições."; }
        }
    }

    // Processar adição de nova restrição
    $novo_alimento_restrito_id = isset($_POST['novo_alimento_restrito']) && $_POST['novo_alimento_restrito'] !== '' ? (int)$_POST['novo_alimento_restrito'] : null;
    $novo_tipo_restricao = isset($_POST['novo_tipo_restricao']) && $_POST['novo_tipo_restricao'] !== '' ? trim(htmlspecialchars($_POST['novo_tipo_restricao'])) : null;

    if ($novo_alimento_restrito_id && $novo_tipo_restricao) {
        // ... (lógica de adição mantida da versão anterior do perfil.php, incluindo verificação de duplicados) ...
        $sql_check_exist = "SELECT id FROM restricoes_usuario WHERE usuario_id = ? AND alimento_id = ? AND tipo_restricao = ?";
        $ja_existe = false;
        if($stmt_check = mysqli_prepare($conexao, $sql_check_exist)){
            mysqli_stmt_bind_param($stmt_check, "iis", $id_usuario, $novo_alimento_restrito_id, $novo_tipo_restricao);
            mysqli_stmt_execute($stmt_check); mysqli_stmt_store_result($stmt_check);
            if(mysqli_stmt_num_rows($stmt_check) > 0) $ja_existe = true;
            mysqli_stmt_close($stmt_check);
        }
        if (!$ja_existe) {
            $sql_add_restricao = "INSERT INTO restricoes_usuario (usuario_id, alimento_id, tipo_restricao) VALUES (?, ?, ?)";
            if ($stmt_add = mysqli_prepare($conexao, $sql_add_restricao)) {
                mysqli_stmt_bind_param($stmt_add, "iis", $id_usuario, $novo_alimento_restrito_id, $novo_tipo_restricao);
                if (!mysqli_stmt_execute($stmt_add)) { $erros_perfil[] = "Erro ao adicionar restrição: " . mysqli_stmt_error($stmt_add); }
                mysqli_stmt_close($stmt_add);
            } else { $erros_perfil[] = "Erro ao preparar adição de restrição."; }
        }
    }

    // Se não houver erros, atualiza os dados
    if (empty($erros_perfil)) {
        $sql_update = "UPDATE usuarios SET nome = ?, telefone = ?, data_nasc = ?, sexo = ?, altura_cm = ?, peso_kg = ?, nivel_atividade_id = ?, objetivo_id = ?, renda_faixa = ?";
        $tipos_param = "ssssidiis"; // s:string, i:integer, d:double
        $params_array = [
            $nome_post, $telefone_post, $data_nasc_post, $sexo_post,
            (int)$altura_cm_post, (float)$peso_kg_post, $nivel_atividade_id_post,
            $objetivo_id_post, $renda_faixa_post
        ];

        if ($senha_hash_update !== null) {
            $sql_update .= ", senha_hash = ?";
            $tipos_param .= "s";
            $params_array[] = $senha_hash_update;
        }
        $sql_update .= " WHERE id = ?";
        $tipos_param .= "i";
        $params_array[] = $id_usuario;

        if ($stmt_update = mysqli_prepare($conexao, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, $tipos_param, ...$params_array);
            if (mysqli_stmt_execute($stmt_update)) {
                $mensagem_perfil_html = "<div class='mensagem sucesso anim-fade-in'>Perfil atualizado com sucesso!</div>";
                if ($_SESSION["nome_usuario"] !== $nome_post) $_SESSION["nome_usuario"] = $nome_post;
                // Recarregar restrições após modificações
                $restricoes_atuais_utilizador = []; // Limpa para recarregar
                if($stmt_rest_reload = mysqli_prepare($conexao, $sql_restricoes_atuais)) {
                    mysqli_stmt_bind_param($stmt_rest_reload, "i", $id_usuario);
                    if(mysqli_stmt_execute($stmt_rest_reload)){
                        $res_rest_reload = mysqli_stmt_get_result($stmt_rest_reload);
                        while($rest_reload = mysqli_fetch_assoc($res_rest_reload)){ $restricoes_atuais_utilizador[] = $rest_reload; }
                        mysqli_free_result($res_rest_reload);
                    }
                    mysqli_stmt_close($stmt_rest_reload);
                }
            } else { $erros_perfil[] = "Erro ao atualizar perfil: " . mysqli_stmt_error($stmt_update); }
            mysqli_stmt_close($stmt_update);
        } else { $erros_perfil[] = "Erro ao preparar atualização: " . mysqli_error($conexao); }
    }

    if (!empty($erros_perfil) && empty($mensagem_perfil_html)) { // Se houve erros e nenhuma msg de sucesso
        $mensagem_perfil_html = "<div class='mensagem erro'><strong>Ops! Verifique os erros:</strong><ul>";
        foreach ($erros_perfil as $e) { $mensagem_perfil_html .= "<li>" . htmlspecialchars($e) . "</li>"; }
        $mensagem_perfil_html .= "</ul></div>";
    }
}

// --- BUSCAR DADOS ATUAIS DO UTILIZADOR PARA PREENCHER O FORMULÁRIO ---
$sql_user_load = "SELECT nome, email, telefone, data_nasc, sexo, altura_cm, peso_kg, nivel_atividade_id, objetivo_id, renda_faixa FROM usuarios WHERE id = ?";
if ($stmt_load = mysqli_prepare($conexao, $sql_user_load)) {
    mysqli_stmt_bind_param($stmt_load, "i", $id_usuario);
    if (mysqli_stmt_execute($stmt_load)) {
        mysqli_stmt_bind_result($stmt_load, $db_nome, $db_email, $db_telefone, $db_data_nasc, $db_sexo, $db_altura_cm, $db_peso_kg, $db_nivel_atividade_id, $db_objetivo_id, $db_renda_faixa);
        if (mysqli_stmt_fetch($stmt_load)) {
            // Preenche as variáveis do formulário se não for um POST ou se o POST teve erros (para manter os valores do POST em caso de erro)
            if ($_SERVER["REQUEST_METHOD"] != "POST" || !empty($erros_perfil)) {
                $nome = $db_nome;
                $telefone = $db_telefone;
                $data_nasc = $db_data_nasc;
                $sexo = $db_sexo;
                $altura_cm = $db_altura_cm;
                $peso_kg = $db_peso_kg;
                $nivel_atividade_id = $db_nivel_atividade_id;
                $objetivo_id = $db_objetivo_id;
                $renda_faixa = $db_renda_faixa;
            } else { // Se foi um POST sem erros, usa os valores do POST para repopular (já estão sanitizados)
                $nome = $nome_post; $telefone = $telefone_post; $data_nasc = $data_nasc_post; $sexo = $sexo_post;
                $altura_cm = $altura_cm_post; $peso_kg = $peso_kg_post;
                $nivel_atividade_id = $nivel_atividade_id_post; $objetivo_id = $objetivo_id_post; $renda_faixa = $renda_faixa_post;
            }
            $email_display = $db_email; // Email é sempre do DB (não editável)
        } else { $mensagem_perfil_html .= "<div class='mensagem erro'>Erro ao carregar dados do perfil.</div>"; }
    } else { $mensagem_perfil_html .= "<div class='mensagem erro'>Erro ao executar busca de dados: " . mysqli_stmt_error($stmt_load) . "</div>"; }
    mysqli_stmt_close($stmt_load);
} else { $mensagem_perfil_html .= "<div class='mensagem erro'>Erro ao preparar consulta de dados: " . mysqli_error($conexao) . "</div>"; }


if(isset($conexao) && $conexao instanceof mysqli) { mysqli_close($conexao); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Meu Perfil - CalorIQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/icons/favicon.ico" type="image/x-icon">
    <style>
        :root { /* Tema CalorIQ */
            --cor-primaria: #A7F3D0; --cor-primaria-escura: #059669; --cor-primaria-muito-escura: #047857;
            --cor-fundo-app: #F0FDF4; --cor-texto-principal: #1F2937; --cor-texto-secundario: #4B5563;
            --cor-surface-container: #FFFFFF; --cor-surface-container-high: #F3F4F6;
            --cor-outline: #D1D5DB; --cor-erro: #EF4444; --cor-sucesso: #10B981;
            --radius-card: 16px; --radius-input: 12px; --radius-botao: 20px;
            --sombra-card: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; overscroll-behavior-y: contain; }
        body {
            font-family: 'Inter', 'Roboto', sans-serif; background-color: var(--cor-fundo-app);
            color: var(--cor-texto-principal); line-height: 1.5; /* Ajustado para melhor leitura em formulários */
            padding-top: env(safe-area-inset-top, 20px);
            padding-bottom: calc(65px + env(safe-area-inset-bottom, 10px)); /* Espaço para nav e safe area */
            display: flex; flex-direction: column;
        }
        .main-content { flex-grow: 1; padding: 20px 16px; overflow-y: auto; }
        .header-perfil { text-align: center; margin-bottom: 24px; }
        .header-perfil h1 { font-size: 1.8rem; font-weight: 700; color: var(--cor-primaria-escura); }
        
        .form-section { margin-bottom: 32px; }
        .form-section h2 {
            font-size: 1.25rem; font-weight: 600; color: var(--cor-primaria-escura);
            margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--cor-primaria);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 0.9rem; font-weight: 500;
            color: var(--cor-texto-secundario); margin-bottom: 8px;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="password"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%; padding: 12px 16px;
            border: 1px solid var(--cor-outline);
            border-radius: var(--radius-input);
            background-color: var(--cor-surface-container);
            font-size: 1rem; color: var(--cor-texto-principal);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: var(--cor-primaria-escura);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
        }
        .form-group input[readonly] { background-color: var(--cor-surface-container-high); cursor: not-allowed; }
        .form-group small { font-size: 0.8rem; color: var(--cor-texto-secundario); margin-top: 6px; display: block; }

        .restricoes-lista { list-style: none; padding: 0; margin-bottom: 20px; }
        .restricoes-lista li {
            background-color: var(--cor-surface-container-high); border: 1px solid var(--cor-outline);
            padding: 10px 12px; margin-bottom: 8px; border-radius: var(--radius-input);
            display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;
        }
        .restricoes-lista .tipo-restricao-label {
            font-size: 0.75rem; text-transform: uppercase; color: #fff;
            padding: 3px 8px; border-radius: 10px; margin-left: 8px; font-weight: 500;
        }
        .tipo-alergia { background-color: #F87171; } /* Vermelho mais suave */
        .tipo-intolerancia { background-color: #FBBF24; } /* Amarelo/Laranja */
        .tipo-nao_gosta { background-color: #9CA3AF; } /* Cinza */

        .add-restricao-grid { display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: flex-end; }
        .add-restricao-grid .form-group { margin-bottom: 0; }

        .btn {
            background-color: var(--cor-primaria-escura); color: white;
            padding: 14px 24px; border: none; border-radius: var(--radius-botao);
            font-size: 1rem; font-weight: 600; text-decoration: none; text-align: center;
            display: block; width: 100%; cursor: pointer;
            transition: background-color 0.2s ease-out, transform 0.1s ease;
            margin-top: 24px;
        }
        .btn:hover { background-color: var(--cor-primaria-muito-escura); }
        .btn:active { transform: scale(0.98); }
        
        .btn-remover {
            background-color: transparent; border: 1px solid var(--cor-erro); color: var(--cor-erro);
            padding: 6px 10px; border-radius: var(--radius-botao-quadrado); font-size: 0.8rem; font-weight: 500;
            cursor: pointer; transition: background-color 0.2s, color 0.2s;
        }
        .btn-remover:hover { background-color: var(--cor-erro); color: white; }
        
        .mensagem { padding: 12px; border-radius: var(--radius-card); margin-bottom: 20px; font-size: 0.9rem; text-align: left; }
        .mensagem.sucesso { background-color: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;}
        .mensagem.erro { background-color: #FEE2E2; color: var(--cor-erro); border: 1px solid #FCA5A5;}
        .mensagem ul { list-style-position: inside; padding-left: 5px; }
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
        <header class="header-perfil">
            <h1>Meu Perfil</h1>
        </header>

        <?php if (!empty($mensagem_perfil_html)) echo $mensagem_perfil_html; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
            <div class="form-section">
                <h2>Dados Pessoais</h2>
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email_display" value="<?php echo htmlspecialchars($email_display); ?>" readonly>
                    <small>O email não pode ser alterado.</small>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone (Opcional)</label>
                    <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone); ?>" placeholder="(XX) XXXXX-XXXX">
                </div>
                <div class="form-group">
                    <label for="data_nasc">Data de Nascimento</label>
                    <input type="date" id="data_nasc" name="data_nasc" value="<?php echo htmlspecialchars($data_nasc); ?>" required max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="sexo">Sexo Biológico</label>
                    <select id="sexo" name="sexo" required>
                        <option value="" disabled <?php echo empty($sexo) ? 'selected' : ''; ?>>Selecione...</option>
                        <option value="Masculino" <?php echo ($sexo == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Feminino" <?php echo ($sexo == 'Feminino') ? 'selected' : ''; ?>>Feminino</option>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="renda_faixa">Faixa de Renda Familiar Mensal</label>
                    <select id="renda_faixa" name="renda_faixa" required>
                        <option value="" disabled <?php echo empty($renda_faixa) ? 'selected' : ''; ?>>Selecione...</option>
                        <option value="ate_1_sm" <?php echo ($renda_faixa == 'ate_1_sm') ? 'selected' : ''; ?>>Até 1 Salário Mínimo</option>
                        <option value="1_a_2_sm" <?php echo ($renda_faixa == '1_a_2_sm') ? 'selected' : ''; ?>>De 1 a 2 Salários Mínimos</option>
                        <option value="2_a_3_sm" <?php echo ($renda_faixa == '2_a_3_sm') ? 'selected' : ''; ?>>De 2 a 3 Salários Mínimos</option>
                        <option value="3_a_5_sm" <?php echo ($renda_faixa == '3_a_5_sm') ? 'selected' : ''; ?>>De 3 a 5 Salários Mínimos</option>
                        <option value="acima_5_sm" <?php echo ($renda_faixa == 'acima_5_sm') ? 'selected' : ''; ?>>Acima de 5 Salários Mínimos</option>
                        <option value="nao_informado" <?php echo ($renda_faixa == 'nao_informado') ? 'selected' : ''; ?>>Prefiro não informar</option>
                    </select>
                     <small>Esta informação ajuda a sugerir alimentos mais acessíveis.</small>
                </div>
            </div>

            <div class="form-section">
                <h2>Minhas Medidas e Objetivos</h2>
                <div class="form-group">
                    <label for="altura_cm">Altura (cm)</label>
                    <input type="number" id="altura_cm" name="altura_cm" placeholder="Ex: 175" value="<?php echo htmlspecialchars($altura_cm); ?>" required min="50" max="300">
                </div>
                <div class="form-group">
                    <label for="peso_kg">Peso Atual (kg)</label>
                    <input type="text" inputmode="decimal" id="peso_kg" name="peso_kg" placeholder="Ex: 70.5 ou 70,5" value="<?php echo htmlspecialchars(str_replace('.', ',', $peso_kg)); ?>" required>
                </div>
                <div class="form-group">
                    <label for="nivel_atividade_id">Nível de Atividade Física</label>
                    <select id="nivel_atividade_id" name="nivel_atividade_id" required>
                        <option value="" disabled <?php echo empty($nivel_atividade_id) ? 'selected' : ''; ?>>Selecione...</option>
                        <?php foreach ($niveis_atividade_opcoes as $op): echo "<option value='".htmlspecialchars($op['id'])."' ".($nivel_atividade_id == $op['id'] ? 'selected' : '').">".htmlspecialchars($op['nome_nivel'])."</option>"; endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="objetivo_id">Meu Principal Objetivo</label>
                    <select id="objetivo_id" name="objetivo_id" required>
                        <option value="" disabled <?php echo empty($objetivo_id) ? 'selected' : ''; ?>>Selecione...</option>
                        <?php foreach ($objetivos_opcoes as $op): echo "<option value='".htmlspecialchars($op['id'])."' ".($objetivo_id == $op['id'] ? 'selected' : '').">".htmlspecialchars($op['nome_objetivo'])."</option>"; endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h2>Alterar Senha</h2>
                <div class="form-group">
                    <label for="nova_senha">Nova Senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo 6 caracteres">
                    <small>Deixe em branco se não quiser alterar.</small>
                </div>
                <div class="form-group">
                    <label for="confirmar_nova_senha">Confirmar Nova Senha</label>
                    <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha">
                </div>
            </div>

            <div class="form-section">
                <h2>Minhas Restrições Alimentares</h2>
                <?php if (!empty($restricoes_atuais_utilizador)): ?>
                    <ul class="restricoes-lista">
                        <?php foreach ($restricoes_atuais_utilizador as $restricao): ?>
                            <li>
                                <span>
                                    <?php echo htmlspecialchars($restricao['nome_alimento']); ?>
                                    <span class="tipo-restricao-label tipo-<?php echo strtolower(htmlspecialchars($restricao['tipo_restricao'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($restricao['tipo_restricao']))); ?>
                                    </span>
                                </span>
                                <button type="submit" name="remover_restricao[]" value="<?php echo $restricao['restricao_id']; ?>" class="btn-remover" onclick="return confirm('Tem a certeza que deseja remover esta restrição?');">Remover</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: var(--cor-texto-secundario); font-size:0.9rem;">Ainda não adicionou nenhuma restrição alimentar.</p>
                <?php endif; ?>

                <h3 style="font-size:1.1rem; margin-top:24px; margin-bottom:12px; color:var(--cor-primaria-escura);">Adicionar Nova Restrição</h3>
                <div class="add-restricao-grid">
                    <div class="form-group">
                        <label for="novo_alimento_restrito">Alimento</label>
                        <select id="novo_alimento_restrito" name="novo_alimento_restrito">
                            <option value="">Selecione um alimento...</option>
                            <?php if(!empty($todos_alimentos_para_restricao)): ?>
                                <?php foreach($todos_alimentos_para_restricao as $grupo_nome => $alimentos_no_grupo): ?>
                                    <optgroup label="<?php echo htmlspecialchars($grupo_nome); ?>">
                                        <?php foreach($alimentos_no_grupo as $alimento_opt): ?>
                                            <option value="<?php echo htmlspecialchars($alimento_opt['id']); ?>">
                                                <?php echo htmlspecialchars($alimento_opt['nome_alimento']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php else: ?>
                                 <option value="" disabled>Nenhum alimento na base para seleção.</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group" style="min-width: 180px;"> <label for="novo_tipo_restricao">Tipo</label>
                        <select id="novo_tipo_restricao" name="novo_tipo_restricao">
                            <option value="">Selecione o tipo...</option>
                            <option value="alergia">Alergia</option>
                            <option value="intolerancia">Intolerância</option>
                            <option value="nao_gosta">Não Gosto</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn">Salvar Alterações no Perfil</button>
        </form>
    </div>

    <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" /></svg>Início</a>
        <a href="plano_diario.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5M12 15L12 18" /></svg>Plano</a>
        <a href="agua.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>Água</a>
        <a href="perfil.php" class="nav-item active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>Perfil</a>
    </nav>
</body>
</html>
