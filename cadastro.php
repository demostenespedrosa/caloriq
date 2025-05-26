<?php
// cadastro.php

// Inicia a sessão (embora não seja estritamente usada aqui, é uma boa prática para consistência)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php_includes/config.php';

// Inicializa variáveis para repopular o formulário e para mensagens
$nome = $email_cad = $telefone = $data_nasc = $sexo = $altura_cm = $peso_kg = "";
$nivel_atividade_id_cad = $objetivo_id_cad = $renda_faixa_cad = ""; // Adicionado _cad para evitar conflito com variáveis de outras lógicas se incluídas
$erros_cadastro = [];
$mensagem_cadastro_html = '';

// Busca opções para os selects de Nível de Atividade e Objetivos
$niveis_atividade_opcoes = [];
$sql_niveis = "SELECT id, nome_nivel FROM niveis_atividade ORDER BY id";
if($resultado_niveis = mysqli_query($conexao, $sql_niveis)){
    while($linha = mysqli_fetch_assoc($resultado_niveis)){ $niveis_atividade_opcoes[] = $linha; }
    mysqli_free_result($resultado_niveis);
} else { $erros_cadastro[] = "Erro ao carregar níveis de atividade."; }

$objetivos_opcoes = [];
$sql_objetivos = "SELECT id, nome_objetivo FROM objetivos ORDER BY id";
if($resultado_objetivos = mysqli_query($conexao, $sql_objetivos)){
    while($linha = mysqli_fetch_assoc($resultado_objetivos)){ $objetivos_opcoes[] = $linha; }
    mysqli_free_result($resultado_objetivos);
} else { $erros_cadastro[] = "Erro ao carregar objetivos."; }


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta e sanitiza os dados do formulário
    $nome = trim(htmlspecialchars($_POST['nome']));
    $email_cad = trim(htmlspecialchars($_POST['email']));
    $telefone = trim(htmlspecialchars($_POST['telefone']));
    $senha_cad = $_POST['senha']; // Senha será hasheada
    $confirmar_senha_cad = $_POST['confirmar_senha'];
    $data_nasc = trim(htmlspecialchars($_POST['data_nasc']));
    $sexo = isset($_POST['sexo']) ? trim(htmlspecialchars($_POST['sexo'])) : null;
    $altura_cm = trim(htmlspecialchars($_POST['altura_cm']));
    $peso_kg_input = trim(htmlspecialchars($_POST['peso_kg'])); // Input pode ter vírgula
    $nivel_atividade_id_cad = isset($_POST['nivel_atividade_id']) ? (int)$_POST['nivel_atividade_id'] : null;
    $objetivo_id_cad = isset($_POST['objetivo_id']) ? (int)$_POST['objetivo_id'] : null;
    $renda_faixa_cad = isset($_POST['renda_faixa']) ? trim(htmlspecialchars($_POST['renda_faixa'])) : null;

    // Validações
    if (empty($nome)) $erros_cadastro[] = "O nome completo é obrigatório.";
    if (empty($email_cad)) {
        $erros_cadastro[] = "O email é obrigatório.";
    } elseif (!filter_var($email_cad, FILTER_VALIDATE_EMAIL)) {
        $erros_cadastro[] = "Formato de email inválido.";
    } else {
        $sql_check_email = "SELECT id FROM usuarios WHERE email = ?";
        if($stmt_check_email = mysqli_prepare($conexao, $sql_check_email)){
            mysqli_stmt_bind_param($stmt_check_email, "s", $email_cad);
            if(mysqli_stmt_execute($stmt_check_email)){
                mysqli_stmt_store_result($stmt_check_email);
                if(mysqli_stmt_num_rows($stmt_check_email) > 0){
                    $erros_cadastro[] = "Este email já está registado. Tente fazer <a href='index.php' class='link-erro'>login</a>.";
                }
            } else { $erros_cadastro[] = "Erro ao verificar email. Tente novamente."; }
            mysqli_stmt_close($stmt_check_email);
        } else { $erros_cadastro[] = "Erro interno ao verificar email."; }
    }
    if (!empty($telefone) && !preg_match('/^(\(?\d{2}\)?\s?)?\d{4,5}[\s\-]?\d{4}$/', $telefone)) {
         $erros_cadastro[] = "Formato de telefone inválido. Use (XX) XXXXX-XXXX ou similar.";
    }
    if (empty($senha_cad)) {
        $erros_cadastro[] = "A senha é obrigatória.";
    } elseif (strlen($senha_cad) < 6) {
        $erros_cadastro[] = "A senha deve ter pelo menos 6 caracteres.";
    }
    if ($senha_cad !== $confirmar_senha_cad) {
        $erros_cadastro[] = "As senhas não coincidem.";
    }
    if (empty($data_nasc)) $erros_cadastro[] = "Data de nascimento é obrigatória.";
    if (empty($sexo)) $erros_cadastro[] = "Sexo biológico é obrigatório.";
    if (empty($altura_cm) || !is_numeric($altura_cm) || $altura_cm <= 50 || $altura_cm > 300) $erros_cadastro[] = "Altura inválida (entre 50 e 300 cm).";

    $peso_kg = str_replace(',', '.', $peso_kg_input); // Normaliza para ponto decimal
    if (empty($peso_kg_input) || !is_numeric($peso_kg) || $peso_kg <= 20 || $peso_kg > 500) {
        $erros_cadastro[] = "Peso inválido (entre 20 e 500 kg).";
    }

    if (empty($nivel_atividade_id_cad)) $erros_cadastro[] = "Nível de atividade física é obrigatório.";
    if (empty($objetivo_id_cad)) $erros_cadastro[] = "O seu objetivo principal é obrigatório.";
    if (empty($renda_faixa_cad)) $erros_cadastro[] = "A faixa de renda é obrigatória.";


    // Se não houver erros, tenta inserir no banco de dados
    if (empty($erros_cadastro)) {
        $senha_hash = password_hash($senha_cad, PASSWORD_DEFAULT);
        $sql_insert = "INSERT INTO usuarios (nome, email, telefone, senha_hash, data_nasc, sexo, altura_cm, peso_kg, nivel_atividade_id, objetivo_id, renda_faixa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt_insert = mysqli_prepare($conexao, $sql_insert)) {
            mysqli_stmt_bind_param($stmt_insert, "ssssssdisis",
                $nome, $email_cad, $telefone, $senha_hash, $data_nasc, $sexo,
                $altura_cm_int, $peso_kg_float, $nivel_atividade_id_cad, $objetivo_id_cad, $renda_faixa_cad
            );
            $altura_cm_int = (int)$altura_cm;
            $peso_kg_float = (float)$peso_kg;

            if (mysqli_stmt_execute($stmt_insert)) {
                header("location: index.php?cadastro=sucesso");
                exit;
            } else {
                $erros_cadastro[] = "Oops! Algo deu errado com o registo. Tente novamente mais tarde. Erro: " . mysqli_stmt_error($stmt_insert);
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            $erros_cadastro[] = "Erro interno ao preparar o registo. Tente novamente mais tarde.";
        }
    }

    if (!empty($erros_cadastro)) {
        $mensagem_cadastro_html = "<div class='mensagem erro'><strong>Ops! Verifique os erros:</strong><ul>";
        foreach ($erros_cadastro as $erro) {
            $mensagem_cadastro_html .= "<li>" . $erro . "</li>"; // HTML já está no erro de email existente
        }
        $mensagem_cadastro_html .= "</ul></div>";
    }
}
if(isset($conexao) && $conexao instanceof mysqli) { mysqli_close($conexao); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Crie sua Conta - CalorIQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/icons/favicon.ico" type="image/x-icon">
    <style>
        :root { /* Tema CalorIQ */
            --cor-primaria: #A7F3D0; --cor-primaria-escura: #059669; --cor-primaria-muito-escura: #047857;
            --cor-fundo-app: #F0FDF4; --cor-texto-principal: #1F2937; --cor-texto-secundario: #4B5563;
            --cor-surface-container: #FFFFFF; --cor-outline: #D1D5DB; --cor-erro: #EF4444; --cor-sucesso: #10B981;
            --radius-card: 24px; --radius-input: 12px; --radius-botao: 20px;
            --sombra-card: 0 10px 15px -3px rgba(0,0,0,0.07), 0 4px 6px -4px rgba(0,0,0,0.07);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { min-height: 100%; /* Garante que o body ocupe pelo menos a altura da tela */ }
        body {
            font-family: 'Inter', 'Roboto', sans-serif; background-color: var(--cor-fundo-app);
            color: var(--cor-texto-principal); line-height: 1.6;
            display: flex; align-items: center; justify-content: center;
            padding: 40px env(safe-area-inset-left, 16px) 40px env(safe-area-inset-right, 16px); /* Mais padding vertical */
            padding-top: env(safe-area-inset-top, 40px);
            padding-bottom: env(safe-area-inset-bottom, 40px);
        }
        .auth-container {
            background-color: var(--cor-surface-container); padding: 32px 28px; border-radius: var(--radius-card);
            box-shadow: var(--sombra-card); width: 100%; max-width: 480px; /* Um pouco mais largo para o formulário de cadastro */
            text-align: center; animation: fadeInScale 0.5s ease-out forwards;
        }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        .auth-header h1 { font-size: 2.2rem; font-weight: 700; color: var(--cor-primaria-escura); margin-bottom: 8px; }
        .auth-header p { font-size: 1rem; color: var(--cor-texto-secundario); margin-bottom: 28px; }
        
        .form-grid { display: grid; gap: 0 16px; } /* Para campos lado a lado */
        @media (min-width: 400px) { /* Ajusta para 2 colunas em telas um pouco maiores */
            .form-grid-2-cols { grid-template-columns: 1fr 1fr; }
        }

        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--cor-texto-secundario); margin-bottom: 6px; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="tel"],
        .form-group input[type="password"], .form-group input[type="date"], .form-group input[type="number"],
        .form-group select {
            width: 100%; padding: 12px 14px; border: 1px solid var(--cor-outline);
            border-radius: var(--radius-input); background-color: var(--cor-surface-container);
            font-size: 0.95rem; color: var(--cor-texto-principal); transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: var(--cor-primaria-escura);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
        }
        .form-group small { font-size: 0.75rem; color: var(--cor-texto-secundario); margin-top: 4px; display: block; }

        .btn-auth { /* Mesmo estilo do login */
            background-color: var(--cor-primaria-escura); color: white; padding: 14px 24px; border: none;
            border-radius: var(--radius-botao); font-size: 1rem; font-weight: 600; text-decoration: none;
            text-align: center; display: block; width: 100%; cursor: pointer;
            transition: background-color 0.2s ease-out, transform 0.1s ease; margin-top: 24px;
        }
        .btn-auth:hover { background-color: var(--cor-primaria-muito-escura); }
        .btn-auth:active { transform: scale(0.98); }
        .auth-link { margin-top: 24px; font-size: 0.9rem; }
        .auth-link a, .link-erro { color: var(--cor-primaria-escura); text-decoration: none; font-weight: 600; }
        .auth-link a:hover, .link-erro:hover { text-decoration: underline; }

        .mensagem { padding: 12px; border-radius: var(--radius-input); margin-bottom: 20px; font-size: 0.9rem; text-align: left; }
        .mensagem.sucesso { background-color: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;}
        .mensagem.erro { background-color: #FEE2E2; color: var(--cor-erro); border: 1px solid #FCA5A5;}
        .mensagem ul { list-style-position: inside; padding-left: 5px; margin:0; }
        .anim-fade-in { animation: fadeInSimple 0.5s ease-out; }
        @keyframes fadeInSimple { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <div class="auth-container">
        <header class="auth-header">
            <h1>Crie sua Conta</h1>
            <p>É rapidinho! Preencha seus dados para começar no CalorIQ.</p>
        </header>

        <?php if (!empty($mensagem_cadastro_html)) echo $mensagem_cadastro_html; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required placeholder="Seu nome como ele é">
            </div>
            <div class="form-grid form-grid-2-cols">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_cad); ?>" required placeholder="seu_email@dominio.com">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone (Opcional)</label>
                    <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone); ?>" placeholder="(XX) XXXXX-XXXX">
                </div>
            </div>
            <div class="form-grid form-grid-2-cols">
                <div class="form-group">
                    <label for="senha">Crie uma Senha</label>
                    <input type="password" id="senha" name="senha" required placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label for="confirmar_senha">Confirme a Senha</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required placeholder="Repita a senha">
                </div>
            </div>
            <hr style="border:none; border-top:1px solid var(--cor-outline); margin: 24px 0;">

            <div class="form-grid form-grid-2-cols">
                <div class="form-group">
                    <label for="data_nasc">Data de Nascimento</label>
                    <input type="date" id="data_nasc" name="data_nasc" value="<?php echo htmlspecialchars($data_nasc); ?>" required max="<?php echo date('Y-m-d', strtotime('-10 years')); // Ex: idade mínima de 10 anos ?>">
                </div>
                <div class="form-group">
                    <label for="sexo">Sexo Biológico</label>
                    <select id="sexo" name="sexo" required>
                        <option value="" disabled <?php echo empty($sexo) ? 'selected' : ''; ?>>Selecione...</option>
                        <option value="Masculino" <?php echo ($sexo == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Feminino" <?php echo ($sexo == 'Feminino') ? 'selected' : ''; ?>>Feminino</option>
                    </select>
                </div>
            </div>
             <div class="form-grid form-grid-2-cols">
                <div class="form-group">
                    <label for="altura_cm">Altura (cm)</label>
                    <input type="number" id="altura_cm" name="altura_cm" value="<?php echo htmlspecialchars($altura_cm); ?>" required placeholder="Ex: 165">
                </div>
                <div class="form-group">
                    <label for="peso_kg">Peso Atual (kg)</label>
                    <input type="text" inputmode="decimal" id="peso_kg" name="peso_kg" value="<?php echo htmlspecialchars(str_replace('.', ',', $peso_kg)); ?>" required placeholder="Ex: 65,5">
                </div>
            </div>

            <hr style="border:none; border-top:1px solid var(--cor-outline); margin: 24px 0;">
            <div class="form-group">
                <label for="nivel_atividade_id">Qual seu nível de atividade física diário?</label>
                <select id="nivel_atividade_id" name="nivel_atividade_id" required>
                    <option value="" disabled <?php echo empty($nivel_atividade_id_cad) ? 'selected' : ''; ?>>Selecione uma opção...</option>
                    <?php foreach ($niveis_atividade_opcoes as $op): ?>
                        <option value="<?php echo htmlspecialchars($op['id']); ?>" <?php echo ($nivel_atividade_id_cad == $op['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($op['nome_nivel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="objetivo_id">Qual seu principal objetivo com o CalorIQ?</label>
                <select id="objetivo_id" name="objetivo_id" required>
                    <option value="" disabled <?php echo empty($objetivo_id_cad) ? 'selected' : ''; ?>>Selecione um objetivo...</option>
                    <?php foreach ($objetivos_opcoes as $op): ?>
                        <option value="<?php echo htmlspecialchars($op['id']); ?>" <?php echo ($objetivo_id_cad == $op['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($op['nome_objetivo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="form-group">
                <label for="renda_faixa">Qual a sua faixa de renda familiar mensal?</label>
                <select id="renda_faixa" name="renda_faixa" required>
                    <option value="" disabled <?php echo empty($renda_faixa_cad) ? 'selected' : ''; ?>>Selecione uma faixa...</option>
                    <option value="ate_1_sm" <?php echo ($renda_faixa_cad == 'ate_1_sm') ? 'selected' : ''; ?>>Até 1 Salário Mínimo</option>
                    <option value="1_a_2_sm" <?php echo ($renda_faixa_cad == '1_a_2_sm') ? 'selected' : ''; ?>>De 1 a 2 Salários Mínimos</option>
                    <option value="2_a_3_sm" <?php echo ($renda_faixa_cad == '2_a_3_sm') ? 'selected' : ''; ?>>De 2 a 3 Salários Mínimos</option>
                    <option value="3_a_5_sm" <?php echo ($renda_faixa_cad == '3_a_5_sm') ? 'selected' : ''; ?>>De 3 a 5 Salários Mínimos</option>
                    <option value="acima_5_sm" <?php echo ($renda_faixa_cad == 'acima_5_sm') ? 'selected' : ''; ?>>Acima de 5 Salários Mínimos</option>
                    <option value="nao_informado" <?php echo ($renda_faixa_cad == 'nao_informado') ? 'selected' : ''; ?>>Prefiro não informar</option>
                </select>
                <small>Esta informação nos ajuda a sugerir alimentos que cabem no seu bolso.</small>
            </div>

            <button type="submit" class="btn-auth">Criar Minha Conta</button>
        </form>

        <p class="auth-link">
            Já possui uma conta? <a href="index.php">Faça Login</a>
        </p>
    </div>
</body>
</html>
