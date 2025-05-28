<?php
// php_includes/gerador_plano.php

/**
 * Gera um plano alimentar diário estruturado por componentes de refeição e papéis culinários.
 * VERSÃO 4.3: Adicionada inicialização defensiva para variáveis de meta de refeição
 * para tentar resolver avisos de "Undefined variable".
 *
 * @param int $meta_calorica_total_utilizador A meta calórica diária total do utilizador.
 * @param mysqli $conexao A ligação à base de dados.
 * @param int $id_usuario O ID do utilizador.
 * @return array|null O plano alimentar gerado ou null em caso de erro.
 */
function gerarPlanoAlimentarDia(int $meta_calorica_total_utilizador, mysqli $conexao, int $id_usuario): ?array {
    if ($meta_calorica_total_utilizador <= 0) {
        error_log("GeradorPlanoV4.3: Meta calórica inválida para utilizador ID {$id_usuario}: {$meta_calorica_total_utilizador}");
        return null;
    }

    // 1. Buscar restrições alimentares do utilizador
    $alimentos_restritos_ids = [];
    $sql_restricoes = "SELECT alimento_id FROM restricoes_usuario WHERE usuario_id = ?";
    if ($stmt_restricoes = mysqli_prepare($conexao, $sql_restricoes)) {
        mysqli_stmt_bind_param($stmt_restricoes, "i", $id_usuario);
        if (mysqli_stmt_execute($stmt_restricoes)) {
            $resultado_restricoes = mysqli_stmt_get_result($stmt_restricoes);
            while ($restricao = mysqli_fetch_assoc($resultado_restricoes)) {
                $alimentos_restritos_ids[] = $restricao['alimento_id'];
            }
            mysqli_free_result($resultado_restricoes);
        } else { error_log("GeradorPlanoV4.3: Erro ao buscar restrições: " . mysqli_stmt_error($stmt_restricoes)); }
        mysqli_stmt_close($stmt_restricoes);
    } else { error_log("GeradorPlanoV4.3: Erro ao preparar restrições: " . mysqli_error($conexao)); }

    // 2. Buscar todos os alimentos permitidos e processar papéis culinários
    $todos_alimentos_permitidos_map = [];
    $sql_alimentos = "SELECT id, nome_alimento, calorias_por_porcao, porcao_descricao, grupo_id, papeis_culinarios FROM alimentos";
    if (!empty($alimentos_restritos_ids)) {
        $placeholders = implode(',', array_fill(0, count($alimentos_restritos_ids), '?'));
        $sql_alimentos .= " WHERE id NOT IN ({$placeholders})";
    }

    if ($stmt_alimentos = mysqli_prepare($conexao, $sql_alimentos)) {
        if (!empty($alimentos_restritos_ids)) {
            $tipos = str_repeat('i', count($alimentos_restritos_ids));
            mysqli_stmt_bind_param($stmt_alimentos, $tipos, ...$alimentos_restritos_ids);
        }
        if (mysqli_stmt_execute($stmt_alimentos)) {
            $resultado_alimentos = mysqli_stmt_get_result($stmt_alimentos);
            while ($alimento = mysqli_fetch_assoc($resultado_alimentos)) {
                $papeis = !empty($alimento['papeis_culinarios']) ? array_map('trim', explode(',', strtoupper($alimento['papeis_culinarios']))) : [];
                $alimento['papeis_array'] = $papeis;
                $todos_alimentos_permitidos_map[$alimento['id']] = $alimento;
            }
            if(isset($resultado_alimentos)) mysqli_free_result($resultado_alimentos);
        } else { error_log("GeradorPlanoV4.3: Erro ao buscar alimentos: " . mysqli_stmt_error($stmt_alimentos)); }
        mysqli_stmt_close($stmt_alimentos);
    } else {
         error_log("GeradorPlanoV4.3: Erro ao preparar alimentos: " . mysqli_error($conexao));
         return null;
    }

    if (empty($todos_alimentos_permitidos_map)) {
        error_log("GeradorPlanoV4.3: Nenhum alimento permitido encontrado para utilizador ID {$id_usuario}.");
        return null;
    }
    // Embaralhar para variedade na seleção
    $chaves_alimentos_permitidos = array_keys($todos_alimentos_permitidos_map);
    shuffle($chaves_alimentos_permitidos);
    $todos_alimentos_permitidos_randomizado = [];
    foreach($chaves_alimentos_permitidos as $chave){
        $todos_alimentos_permitidos_randomizado[$chave] = $todos_alimentos_permitidos_map[$chave];
    }
    $todos_alimentos_permitidos_map = $todos_alimentos_permitidos_randomizado;


    // 3. Estrutura das refeições (mantida da v4.2)
     $estrutura_refeicoes = [
        ["nome" => "Café da Manhã", "horario_sugerido" => "07:00 - 08:00", "percentual_cal" => 0.20, "min_itens_total" => 3, "max_itens_total" => 5, "fator_excesso_permitido" => 1.30,
            "componentes" => [
                ["slot" => "CarboidratoBaseCafe", "papeis_desejados" => ["CARB_CAFE_LANCHE_PÃES", "CARB_CAFE_LANCHE_MASSAS_CEREAIS"], "grupo_id_pref" => 3, "min_oc" => 1, "max_oc" => 1, "priorizar_nomes" => ["pão francês", "pão de forma integral", "tapioca", "cuscuz nordestino", "aveia"]],
                ["slot" => "ProteinaLaticinioCafe", "papeis_desejados" => ["LATICINIO_BEBIDA_CAFE", "LATICINIO_IOGURTE_LANCHE_CAFE", "LATICINIO_QUEIJO_ACOMPANHAMENTO", "PROTEINA_COMPLEMENTAR_LANCHE_CAFE", "PROTEINA_OVO_REFEICAO_PRINCIPAL"], "grupo_id_pref" => [6,4], "min_oc" => 1, "max_oc" => 1, "priorizar_nomes" => ["leite", "iogurte natural", "queijo minas", "ovo cozido", "ovos mexidos"]],
                ["slot" => "FrutaCafe", "papeis_desejados" => ["FRUTA_IN_NATURA_LANCHE_CAFE"], "grupo_id_pref" => 1, "min_oc" => 1, "max_oc" => 1],
                ["slot" => "BebidaQuenteCafe", "fixo" => ["nome_alimento" => "Café Coado (sem açúcar)", "calorias_por_porcao" => 2.0, "porcao_descricao" => "1 chávena (150ml)", "papeis_array" => ["BEBIDA_CAFE_PURO"]]]
            ]],
        ["nome" => "Lanche da Manhã", "horario_sugerido" => "10:00 - 10:30", "percentual_cal" => 0.10, "min_itens_total" => 1, "max_itens_total" => 2, "fator_excesso_permitido" => 1.40,
            "componentes" => [
                ["slot" => "LancheManhaPrincipal", "papeis_desejados" => ["FRUTA_IN_NATURA_LANCHE_CAFE", "OLEAGINOSA_PEQUENO_LANCHE", "LATICINIO_IOGURTE_LANCHE_CAFE"], "grupo_id_pref" => [1, 7, 6], "min_oc" => 1, "max_oc" => 1]
            ]],
        ["nome" => "Almoço", "horario_sugerido" => "12:30 - 13:30", "percentual_cal" => 0.35, "min_itens_total" => 5, "max_itens_total" => 8, "fator_excesso_permitido" => 1.25,
            "componentes" => [
                ["slot" => "ProteinaPrincipalAlmoco", "papeis_desejados" => ["PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE", "PROTEINA_OVO_REFEICAO_PRINCIPAL"], "grupo_id_pref" => 4, "min_oc" => 1, "max_oc" => 1, "evitar_nomes" => ["clara de ovo", "gema de ovo"]],
                ["slot" => "CarboidratoPrincipalAlmoco", "papeis_desejados" => ["CARB_PRINCIPAL_REFEICAO"], "grupo_id_pref" => 3, "min_oc" => 1, "max_oc" => 1, "priorizar_nomes" => ["arroz branco cozido", "arroz integral cozido", "macarrão comum cozido"]],
                ["slot" => "LeguminosaAlmoco", "papeis_desejados" => ["LEGUMINOSA_PRINCIPAL_REFEICAO"], "grupo_id_pref" => 5, "min_oc" => 1, "max_oc" => 1, "priorizar_nomes" => ["feijão carioca cozido", "feijão preto cozido"]],
                ["slot" => "VegetalCruAlmoco", "papeis_desejados" => ["VEGETAL_FOLHOSO_SALADA", "VEGETAL_FRUTO_SALADA", "VEGETAL_RAIZ_SALADA_CRUA"], "grupo_id_pref" => 2, "min_oc" => 1, "max_oc" => 2],
                ["slot" => "VegetalCozidoAlmoco", "papeis_desejados" => ["VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO"], "grupo_id_pref" => 2, "min_oc" => 0, "max_oc" => 1],
                ["slot" => "SobremesaAlmoco", "papeis_desejados" => ["FRUTA_IN_NATURA_SOBREMESA"], "grupo_id_pref" => 1, "min_oc" => 1, "max_oc" => 1],
                ["slot" => "BebidaAlmoco", "fixo" => ["nome_alimento" => "Água", "calorias_por_porcao" => 0.0, "porcao_descricao" => "À vontade", "papeis_array" => ["BEBIDA_AGUA"]]]
            ]],
        ["nome" => "Lanche da Tarde", "horario_sugerido" => "16:00 - 16:30", "percentual_cal" => 0.10, "min_itens_total" => 1, "max_itens_total" => 3, "fator_excesso_permitido" => 1.40,
            "componentes" => [
                 ["slot" => "LancheTardePrincipal", "papeis_desejados" => ["FRUTA_IN_NATURA_LANCHE_CAFE", "LATICINIO_IOGURTE_LANCHE_CAFE", "CARB_CAFE_LANCHE_PÃES", "OLEAGINOSA_PEQUENO_LANCHE"], "grupo_id_pref" => [1, 6, 3, 7], "min_oc" => 1, "max_oc" => 1]
            ]],
        ["nome" => "Jantar", "horario_sugerido" => "19:30 - 20:30", "percentual_cal" => 0.25, "min_itens_total" => 3, "max_itens_total" => 6, "fator_excesso_permitido" => 1.30,
            "componentes" => [
                ["slot" => "ProteinaJantar", "papeis_desejados" => ["PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE", "PROTEINA_OVO_REFEICAO_PRINCIPAL"], "grupo_id_pref" => 4, "min_oc" => 1, "max_oc" => 1, "evitar_nomes" => ["clara de ovo", "gema de ovo"]],
                ["slot" => "AcompanhamentoJantar", "papeis_desejados" => ["VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO", "VEGETAL_BASE_SOPA_CALDO", "CARB_PRINCIPAL_REFEICAO", "VEGETAL_FOLHOSO_SALADA", "VEGETAL_FRUTO_SALADA"], "grupo_id_pref" => [2,3], "min_oc" => 1, "max_oc" => 2, "priorizar_nomes" => ["sopa de legumes", "batata doce cozida", "macaxeira cozida", "salada", "cuscuz"]],
                ["slot" => "BebidaJantar", "fixo" => ["nome_alimento" => "Água", "calorias_por_porcao" => 0.0, "porcao_descricao" => "À vontade", "papeis_array" => ["BEBIDA_AGUA"]]]
            ]],
    ];

    $plano_final = [
        "data" => date("d/m/Y"),
        "meta_calorica_total_calculada_utilizador" => $meta_calorica_total_utilizador,
        "refeicoes" => [],
        "total_calorias_plano_gerado" => 0
    ];
    $ids_alimentos_usados_no_dia_todo = [];

    // 4. Gerar cada refeição
    foreach ($estrutura_refeicoes as $refeicao_info) {
        // ** INICIALIZAÇÃO DEFENSIVA DAS VARIÁVEIS DA REFEIÇÃO **
        $percentual_cal_refeicao = isset($refeicao_info['percentual_cal']) ? (float)$refeicao_info['percentual_cal'] : 0.25; // Default
        $fator_excesso_refeicao = isset($refeicao_info['fator_excesso_permitido']) ? (float)$refeicao_info['fator_excesso_permitido'] : 1.25; // Default

        $meta_cal_refeicao = round($meta_calorica_total_utilizador * $percentual_cal_refeicao);
        // Garante que a meta da refeição não seja ridiculamente baixa se a meta total for muito baixa
        if ($meta_cal_refeicao < 50 && $meta_calorica_total_utilizador > 500) { // Ex: se meta total é 1200, 10% é 120. Se for 500, 10% é 50.
            $meta_cal_refeicao = max(50, round($meta_calorica_total_utilizador * 0.05)); // Mínimo de 50kcal ou 5% da meta total
        } elseif ($meta_cal_refeicao <= 0) {
             $meta_cal_refeicao = 100; // Um valor pequeno positivo se o cálculo der errado
        }

        $limite_sup_cal_refeicao = $meta_cal_refeicao * $fator_excesso_refeicao;
        $limite_inf_cal_refeicao = $meta_cal_refeicao * 0.85;
        $calorias_refeicao_atual = 0;
        // ** FIM DA INICIALIZAÇÃO DEFENSIVA **

        $itens_refeicao = [];
        $ids_alimentos_usados_nesta_refeicao = [];
        $alimentos_disponiveis_para_slots_refeicao = $todos_alimentos_permitidos_map;

        // Preencher os "componentes" (slots) da refeição
        if (!empty($refeicao_info['componentes'])) {
            foreach ($refeicao_info['componentes'] as $componente_info) {
                // ... (resto da lógica de preenchimento de componentes da V4.2) ...
                // Esta parte é extensa e não precisa mudar para esta correção específica.
                // Apenas garantimos que as variáveis de meta da refeicao estão definidas.
                 if (isset($componente_info['fixo'])) {
                    if (count($itens_refeicao) < $refeicao_info['max_itens_total']) {
                        $itens_refeicao[] = [ "alimento_id" => $componente_info['fixo']['nome_alimento'], "alimento" => $componente_info['fixo']['nome_alimento'], "quantidade" => $componente_info['fixo']['porcao_descricao'], "calorias_aprox" => (float)$componente_info['fixo']['calorias_por_porcao']];
                        $calorias_refeicao_atual += (float)$componente_info['fixo']['calorias_por_porcao'];
                    }
                    continue;
                }
                $papeis_desejados_slot = (array)$componente_info['papeis_desejados'];
                $grupo_id_prefs_slot = isset($componente_info['grupo_id_pref']) ? (array)$componente_info['grupo_id_pref'] : [];
                $min_oc = $componente_info['min_oc'];
                $max_oc = $componente_info['max_oc'] ?? $min_oc;
                $priorizar_nomes_slot = $componente_info['priorizar_nomes'] ?? [];
                $evitar_nomes_slot = $componente_info['evitar_nomes'] ?? [];
                $ocorrencias_slot_adicionadas = 0;

                for ($oc_idx = 0; $oc_idx < $max_oc; $oc_idx++) {
                    if (count($itens_refeicao) >= $refeicao_info['max_itens_total']) break;
                    if ($ocorrencias_slot_adicionadas >= $min_oc && $calorias_refeicao_atual >= $meta_cal_refeicao * 0.90) break;
                    $candidatos_para_slot_filtrados = [];
                    $chaves_disponiveis_slot = array_keys($alimentos_disponiveis_para_slots_refeicao);
                    shuffle($chaves_disponiveis_slot);
                    foreach ($chaves_disponiveis_slot as $al_id) {
                        $al_data = $alimentos_disponiveis_para_slots_refeicao[$al_id];
                        if (in_array($al_id, $ids_alimentos_usados_nesta_refeicao)) continue;
                        $tem_papel_desejado = false; foreach ($papeis_desejados_slot as $papel_des) { if (in_array($papel_des, $al_data['papeis_array'])) { $tem_papel_desejado = true; break; }}
                        if (!$tem_papel_desejado) continue;
                        if (!empty($grupo_id_prefs_slot) && !in_array($al_data['grupo_id'], $grupo_id_prefs_slot)) continue;
                        $evitar_este = false; if(!empty($evitar_nomes_slot)){ foreach($evitar_nomes_slot as $evitar){ if(stripos($al_data['nome_alimento'], $evitar) !== false) { $evitar_este = true; break; }}} if($evitar_este) continue;
                        if (!in_array($al_id, $ids_alimentos_usados_no_dia_todo)) { $candidatos_para_slot_filtrados[$al_id] = $al_data; }
                    }
                    if(empty($candidatos_para_slot_filtrados)){
                         foreach ($chaves_disponiveis_slot as $al_id) {
                            $al_data = $alimentos_disponiveis_para_slots_refeicao[$al_id];
                            if (in_array($al_id, $ids_alimentos_usados_nesta_refeicao)) continue;
                            $tem_papel_desejado = false; foreach ($papeis_desejados_slot as $papel_des) { if (in_array($papel_des, $al_data['papeis_array'])) { $tem_papel_desejado = true; break; }}
                            if (!$tem_papel_desejado) continue;
                            if (!empty($grupo_id_prefs_slot) && !in_array($al_data['grupo_id'], $grupo_id_prefs_slot)) continue;
                             $evitar_este = false; if(!empty($evitar_nomes_slot)){ foreach($evitar_nomes_slot as $evitar){ if(stripos($al_data['nome_alimento'], $evitar) !== false) { $evitar_este = true; break; }}} if($evitar_este) continue;
                            $candidatos_para_slot_filtrados[$al_id] = $al_data;
                        }
                    }
                    if(empty($candidatos_para_slot_filtrados)) continue;
                    $alimento_escolhido_slot = null;
                    if(!empty($priorizar_nomes_slot)){
                        $candidatos_priorizados_temp_slot = [];
                        foreach ($candidatos_para_slot_filtrados as $cand_id => $cand_data) { foreach ($priorizar_nomes_slot as $nome_p) { if (stripos($cand_data['nome_alimento'], $nome_p) !== false) { $candidatos_priorizados_temp_slot[$cand_id] = $cand_data; break; }}}
                        $lista_a_considerar_para_slot = !empty($candidatos_priorizados_temp_slot) ? $candidatos_priorizados_temp_slot : $candidatos_para_slot_filtrados;
                        uasort($lista_a_considerar_para_slot, function($a, $b) { return $a['calorias_por_porcao'] <=> $b['calorias_por_porcao']; });
                        foreach($lista_a_considerar_para_slot as $cand_data){ if (($calorias_refeicao_atual + $cand_data['calorias_por_porcao']) <= $limite_sup_cal_refeicao || $ocorrencias_slot_adicionadas < $min_oc) { $alimento_escolhido_slot = $cand_data; break; }}
                    } else {
                        uasort($candidatos_para_slot_filtrados, function($a, $b){ return $a['calorias_por_porcao'] <=> $b['calorias_por_porcao']; });
                        foreach($candidatos_para_slot_filtrados as $cand_data){ if (($calorias_refeicao_atual + $cand_data['calorias_por_porcao']) <= $limite_sup_cal_refeicao || $ocorrencias_slot_adicionadas < $min_oc) { $alimento_escolhido_slot = $cand_data; break; }}
                    }
                    if(!$alimento_escolhido_slot && $ocorrencias_slot_adicionadas < $min_oc && !empty($candidatos_para_slot_filtrados)){ $alimento_escolhido_slot = reset($candidatos_para_slot_filtrados); }
                    if($alimento_escolhido_slot){
                        $itens_refeicao[] = ["alimento_id" => $alimento_escolhido_slot['id'], "alimento" => $alimento_escolhido_slot['nome_alimento'], "quantidade" => $alimento_escolhido_slot['porcao_descricao'], "calorias_aprox" => (float)$alimento_escolhido_slot['calorias_por_porcao']];
                        $calorias_refeicao_atual += (float)$alimento_escolhido_slot['calorias_por_porcao'];
                        $ids_alimentos_usados_nesta_refeicao[] = $alimento_escolhido_slot['id'];
                        if (!in_array($alimento_escolhido_slot['id'], $ids_alimentos_usados_no_dia_todo)) $ids_alimentos_usados_no_dia_todo[] = $alimento_escolhido_slot['id'];
                        unset($alimentos_disponiveis_para_slots_refeicao[$alimento_escolhido_slot['id']]);
                        $ocorrencias_slot_adicionadas++;
                    } else { break; }
                }
            }
        }
        
        $tentativas_finais_comp = 0;
        while(count($itens_refeicao) < $refeicao_info['max_itens_total'] && $calorias_refeicao_atual < $limite_inf_cal_refeicao && $tentativas_finais_comp < 10){
            $tentativas_finais_comp++;
            $candidatos_comp_finais = [];
            $temp_disp_refeicao = $todos_alimentos_permitidos_map;
            if(isset($refeicao_info['grupos_permitidos'])){
                 $temp_disp_refeicao = array_filter($temp_disp_refeicao, function ($al) use ($refeicao_info) { return !empty($refeicao_info['grupos_permitidos']) && array_intersect((array)$al['grupo_id'], $refeicao_info['grupos_permitidos']); });
            }
            foreach ($temp_disp_refeicao as $al_id => $al_data) { if (in_array($al_id, $ids_alimentos_usados_nesta_refeicao)) continue; if (!in_array($al_id, $ids_alimentos_usados_no_dia_todo)) { $candidatos_comp_finais[$al_id] = $al_data; }}
            if(empty($candidatos_comp_finais)){ foreach ($temp_disp_refeicao as $al_id => $al_data) { if (in_array($al_id, $ids_alimentos_usados_nesta_refeicao)) continue; $candidatos_comp_finais[$al_id] = $al_data; }}
            if(empty($candidatos_comp_finais)) break;
            uasort($candidatos_comp_finais, function($a, $b) use ($meta_cal_refeicao, $calorias_refeicao_atual, $limite_sup_cal_refeicao) {
                $cal_a = $a['calorias_por_porcao']; $cal_b = $b['calorias_por_porcao'];
                $cal_falt_refeicao = $meta_cal_refeicao - $calorias_refeicao_atual;
                $diff_a = abs($cal_falt_refeicao - $cal_a); $diff_b = abs($cal_falt_refeicao - $cal_b);
                if (($calorias_refeicao_atual + $cal_a) <= $limite_sup_cal_refeicao && ($calorias_refeicao_atual + $cal_b) > $limite_sup_cal_refeicao) return -1;
                if (($calorias_refeicao_atual + $cal_a) > $limite_sup_cal_refeicao && ($calorias_refeicao_atual + $cal_b) <= $limite_sup_cal_refeicao) return 1;
                return $diff_a <=> $diff_b;
            });
            $alimento_final_escolhido = null;
            foreach($candidatos_comp_finais as $cand_final){ if(($calorias_refeicao_atual + $cand_final['calorias_por_porcao']) <= $limite_sup_cal_refeicao){ $alimento_final_escolhido = $cand_final; break; }}
            if(!$alimento_final_escolhido && $calorias_refeicao_atual < $meta_cal_refeicao * 0.7 && count($itens_refeicao) < $refeicao_info['min_itens_total'] +1 && !empty($candidatos_comp_finais) ){ $alimento_final_escolhido = reset($candidatos_comp_finais); if(($calorias_refeicao_atual + $alimento_final_escolhido['calorias_por_porcao']) > $limite_sup_cal_refeicao * 1.1) { $alimento_final_escolhido = null; }}
            if($alimento_final_escolhido){
                 $itens_refeicao[] = ["alimento_id" => $alimento_final_escolhido['id'], "alimento" => $alimento_final_escolhido['nome_alimento'], "quantidade" => $alimento_final_escolhido['porcao_descricao'], "calorias_aprox" => (float)$alimento_final_escolhido['calorias_por_porcao']];
                $calorias_refeicao_atual += (float)$alimento_final_escolhido['calorias_por_porcao'];
                $ids_alimentos_usados_nesta_refeicao[] = $alimento_final_escolhido['id'];
                if (!in_array($alimento_final_escolhido['id'], $ids_alimentos_usados_no_dia_todo)) $ids_alimentos_usados_no_dia_todo[] = $alimento_final_escolhido['id'];
            } else { break; }
        }

        $plano_final['refeicoes'][] = [
            "nome" => $refeicao_info['nome'],
            "horario_sugerido" => $refeicao_info['horario_sugerido'],
            "itens" => $itens_refeicao,
            "total_calorias_refeicao" => round($calorias_refeicao_atual)
        ];
        $plano_final['total_calorias_plano_gerado'] += round($calorias_refeicao_atual);
    }
    return $plano_final;
}
?>
