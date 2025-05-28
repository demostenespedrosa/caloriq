<?php
// php_includes/funcoes_nutricionais.php

/**
 * Calcula a Idade a partir da data de nascimento.
 *
 * @param string $data_nasc_str Data de nascimento no formato 'AAAA-MM-DD'.
 * @return int|null A idade em anos ou null se a data for inválida ou futura.
 */
function calcularIdade(string $data_nasc_str): ?int {
    if (empty($data_nasc_str) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $data_nasc_str)) {
        return null; // Formato inválido
    }
    try {
        $data_nascimento = new DateTime($data_nasc_str);
        $data_atual = new DateTime();

        if ($data_nascimento > $data_atual) {
            return null; // Data de nascimento no futuro
        }
        $diferenca = $data_atual->diff($data_nascimento);
        return $diferenca->y; // Retorna os anos completos
    } catch (Exception $e) {
        error_log("Erro ao calcular idade para data {$data_nasc_str}: " . $e->getMessage());
        return null; // Erro na criação do objeto DateTime
    }
}

/**
 * Calcula o Índice de Massa Corporal (IMC).
 * IMC = Peso (kg) / (Altura (m))^2
 *
 * @param float $peso_kg Peso do utilizador em quilogramas.
 * @param int $altura_cm Altura do utilizador em centímetros.
 * @return float|null O valor do IMC arredondado para 2 casas decimais, ou null se os dados forem inválidos.
 */
function calcularIMC(float $peso_kg, int $altura_cm): ?float {
    if ($altura_cm <= 0 || $peso_kg <= 0) {
        return null; // Dados inválidos (altura ou peso não podem ser zero ou negativos)
    }
    $altura_m = $altura_cm / 100; // Converter altura para metros
    if ($altura_m == 0) return null; // Evita divisão por zero se altura_cm for muito pequena

    $imc = $peso_kg / ($altura_m * $altura_m);
    return round($imc, 2);
}

/**
 * Interpreta o valor do IMC e retorna a classificação.
 *
 * @param float $imc O valor do IMC calculado.
 * @return string A interpretação textual do IMC. Retorna "IMC inválido" se $imc for null.
 */
function interpretarIMC(?float $imc): string {
    if ($imc === null) {
        return "IMC não pôde ser calculado";
    }
    if ($imc < 18.5) {
        return "Abaixo do peso";
    } elseif ($imc >= 18.5 && $imc <= 24.9) {
        return "Peso normal (Eutrófico)";
    } elseif ($imc >= 25.0 && $imc <= 29.9) {
        return "Sobrepeso (Pré-obeso)";
    } elseif ($imc >= 30.0 && $imc <= 34.9) {
        return "Obesidade Grau I";
    } elseif ($imc >= 35.0 && $imc <= 39.9) {
        return "Obesidade Grau II (Severa)";
    } elseif ($imc >= 40.0) {
        return "Obesidade Grau III (Mórbida)";
    } else {
        return "Valor de IMC não classificado"; // Caso algum valor estranho passe
    }
}

/**
 * Calcula a Taxa Metabólica Basal (TMB) usando a fórmula de Mifflin-St Jeor.
 * É considerada uma das fórmulas mais precisas para a população em geral.
 * Homens: TMB = (10 * peso em kg) + (6.25 * altura em cm) - (5 * idade em anos) + 5
 * Mulheres: TMB = (10 * peso em kg) + (6.25 * altura em cm) - (5 * idade em anos) - 161
 *
 * @param float $peso_kg Peso em quilogramas.
 * @param int $altura_cm Altura em centímetros.
 * @param int $idade_anos Idade em anos.
 * @param string $sexo 'Masculino' ou 'Feminino'.
 * @return float|null A TMB em kcal/dia, arredondada, ou null se os dados forem inválidos.
 */
function calcularTMB(float $peso_kg, int $altura_cm, int $idade_anos, string $sexo): ?float {
    if ($peso_kg <= 0 || $altura_cm <= 0 || $idade_anos <= 0 || empty($sexo)) {
        return null; // Dados de entrada inválidos
    }

    $tmb_calculada = 0;
    if (strtolower($sexo) === 'masculino') {
        $tmb_calculada = (10 * $peso_kg) + (6.25 * $altura_cm) - (5 * $idade_anos) + 5;
    } elseif (strtolower($sexo) === 'feminino') {
        $tmb_calculada = (10 * $peso_kg) + (6.25 * $altura_cm) - (5 * $idade_anos) - 161;
    } else {
        return null; // Sexo inválido
    }

    return round($tmb_calculada); // Arredonda para o inteiro mais próximo
}

/**
 * Calcula o Gasto Energético Total (GET) ou Necessidade Calórica Diária (NCD).
 * GET = TMB * Fator de Atividade Física (FAF)
 *
 * @param float $tmb A Taxa Metabólica Basal calculada.
 * @param float $fator_atividade O fator multiplicador correspondente ao nível de atividade física.
 * @return float|null O GET em kcal/dia, arredondado, ou null se os dados forem inválidos.
 */
function calcularGET(float $tmb, float $fator_atividade): ?float {
    if ($tmb <= 0 || $fator_atividade <= 0) {
        return null; // TMB ou fator de atividade inválidos
    }
    $get_calculado = $tmb * $fator_atividade;
    return round($get_calculado);
}

/**
 * Calcula a Meta Calórica Diária com base no GET e no percentual de ajuste do objetivo.
 * Ex: Para emagrecer, o ajuste é negativo; para ganhar massa, é positivo.
 *
 * @param float $get O Gasto Energético Total calculado.
 * @param int $ajuste_calorico_percentual Percentual de ajuste (ex: -20 para emagrecer, 0 para manter, 15 para ganhar).
 * @return float|null A meta calórica diária em kcal/dia, arredondada, ou null se os dados forem inválidos.
 */
function calcularMetaCalorica(float $get, int $ajuste_calorico_percentual): ?float {
    if ($get <= 0) {
        return null; // GET inválido
    }
    // O ajuste percentual pode ser zero, positivo ou negativo.
    // Se for -100 ou menor, resultaria em zero ou negativo, o que não é ideal.
    // Adicionar uma pequena validação para evitar metas extremamente baixas ou negativas.
    if ($ajuste_calorico_percentual <= -80) { // Ex: um ajuste de -80% já é muito agressivo.
        // Poderia logar um aviso ou ajustar para um mínimo seguro.
        // Por agora, vamos permitir, mas em um sistema real, isso precisaria de mais regras.
    }

    $ajuste_decimal = $ajuste_calorico_percentual / 100;
    $meta_calculada = $get * (1 + $ajuste_decimal);

    // Garante que a meta não seja absurdamente baixa (ex: abaixo de um mínimo vital, como 1000-1200 kcal)
    // Esta é uma simplificação; um mínimo seguro real depende de muitos fatores.
    if ($meta_calculada < 1000 && $meta_calculada > 0) {
        // Poderia ajustar para 1000 ou logar um aviso.
        // Por agora, retorna o calculado.
    }
    if ($meta_calculada <=0) return null; // Meta não pode ser zero ou negativa.

    return round($meta_calculada);
}

/*
    NOTAS SOBRE AS FUNÇÕES:
    1.  VALIDAÇÃO DE ENTRADA: Todas as funções incluem verificações básicas para os
        parâmetros de entrada para evitar erros e retornar `null` se os dados
        forem inadequados.
    2.  PRECISÃO: As fórmulas usadas (Mifflin-St Jeor para TMB) são estimativas.
        Resultados de calculadoras online podem variar ligeiramente devido a
        diferentes fórmulas ou fatores de atividade.
    3.  ARREDONDAMENTO: Os resultados finais (TMB, GET, Meta Calórica) são arredondados
        para o número inteiro mais próximo, pois calorias são geralmente
        manuseadas como inteiros na prática. O IMC é arredondado para 2 casas decimais.
    4.  USO: Estas funções devem ser chamadas nos scripts PHP onde os cálculos
        são necessários (ex: `dashboard.php`, `plano_diario.php`, ou no futuro
        `gerador_plano.php` para obter a meta do utilizador).
*/

?>
