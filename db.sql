-- ====================================================================================
-- SCRIPT SQL PARA (RE)CRIAÇÃO DA BASE DE DADOS CALORIQ
-- VERSÃO COM PAPÉIS CULINÁRIOS
--
-- ATENÇÃO: Este script APAGARÁ as tabelas existentes se elas já existirem,
-- para garantir uma estrutura limpa e consistente com as novas definições.
-- Faça backup de dados importantes se necessário.
-- ====================================================================================

-- Remove tabelas existentes na ordem correta para evitar erros de chave estrangeira
DROP TABLE IF EXISTS registros_agua;
DROP TABLE IF EXISTS restricoes_usuario;
DROP TABLE IF EXISTS planos_diarios_salvos_itens; -- Se existir uma tabela de itens de plano
DROP TABLE IF EXISTS planos_diarios_salvos;
DROP TABLE IF EXISTS alimentos;
DROP TABLE IF EXISTS grupos_alimentos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS niveis_atividade;
DROP TABLE IF EXISTS objetivos;

-- -----------------------------------------------------
-- Tabela `niveis_atividade`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS niveis_atividade (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_nivel VARCHAR(100) NOT NULL UNIQUE,
  fator_multiplicador DECIMAL(3,2) NOT NULL COMMENT 'Fator para multiplicar pela TMB para obter o GET'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO niveis_atividade (nome_nivel, fator_multiplicador) VALUES
('Sedentário (pouco ou nenhum exercício)', 1.20),
('Levemente Ativo (exercício leve 1-3 dias/semana)', 1.37),
('Moderadamente Ativo (exercício moderado 3-5 dias/semana)', 1.55),
('Muito Ativo (exercício intenso 6-7 dias/semana)', 1.72),
('Extremamente Ativo (exercício muito intenso e trabalho físico)', 1.90);

-- -----------------------------------------------------
-- Tabela `objetivos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS objetivos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_objetivo VARCHAR(100) NOT NULL UNIQUE,
  ajuste_calorico_percentual INT COMMENT 'Percentual de ajuste calórico. Ex: -20 para emagrecer, 0 para manter, 15 para ganhar massa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO objetivos (nome_objetivo, ajuste_calorico_percentual) VALUES
('Emagrecer Levemente (~0.25kg/semana)', -10),
('Emagrecer Moderadamente (~0.5kg/semana)', -20),
('Emagrecer Intensamente (~0.75-1kg/semana)', -25), -- Adicionado
('Manter Peso Atual', 0),
('Ganhar Massa Muscular Levemente (~0.25kg/semana)', 10),
('Ganhar Massa Muscular Moderadamente (~0.5kg/semana)', 15);

-- -----------------------------------------------------
-- Tabela `usuarios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  telefone VARCHAR(20) NULL, -- Adicionado
  senha_hash VARCHAR(255) NOT NULL,
  data_nasc DATE,
  sexo ENUM('Masculino', 'Feminino'),
  altura_cm SMALLINT,
  peso_kg DECIMAL(5,2),
  nivel_atividade_id INT,
  objetivo_id INT,
  renda_faixa ENUM('ate_1_sm', '1_a_2_sm', '2_a_3_sm', '3_a_5_sm', 'acima_5_sm', 'nao_informado') DEFAULT 'nao_informado' COMMENT 'SM = Salário Mínimo', -- Adicionado
  data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao_perfil TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Adicionado
  CONSTRAINT fk_usuario_nivel_atividade FOREIGN KEY (nivel_atividade_id) REFERENCES niveis_atividade(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_objetivo FOREIGN KEY (objetivo_id) REFERENCES objetivos(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela `grupos_alimentos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS grupos_alimentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_grupo VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO grupos_alimentos (nome_grupo) VALUES
('Frutas'),                                         -- ID 1
('Verduras, Legumes e Raízes/Tubérculos'),        -- ID 2
('Grãos, Cereais, Pães e Massas'),                  -- ID 3
('Carnes, Aves, Peixes e Ovos'),                    -- ID 4
('Leguminosas (Feijões, etc)'),                     -- ID 5 (nome ajustado)
('Leite, Queijos e Iogurtes'),                      -- ID 6
('Óleos, Gorduras e Oleaginosas'),                  -- ID 7
('Açúcares, Doces, Bebidas e Outros');              -- ID 8 (Bebidas não lácteas aqui)

-- -----------------------------------------------------
-- Tabela `alimentos`
-- Inclui a nova coluna `papeis_culinarios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS alimentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_alimento VARCHAR(150) NOT NULL UNIQUE,
  grupo_id INT,
  calorias_por_porcao DECIMAL(6,1) NOT NULL COMMENT 'Calorias para a porção descrita',
  porcao_descricao VARCHAR(150) NOT NULL COMMENT 'Descrição da porção (ex: 1 unidade média, 2 fatias, 100g)',
  papeis_culinarios TEXT COMMENT 'Lista de papéis separados por vírgula, ex: CARB_PRINCIPAL_REFEICAO,GUARNICAO_FARINACEA',
  -- Campos opcionais para macronutrientes (para o futuro):
  -- proteinas_g DECIMAL(5,1) DEFAULT NULL,
  -- carboidratos_g DECIMAL(5,1) DEFAULT NULL,
  -- gorduras_g DECIMAL(5,1) DEFAULT NULL,
  -- fibras_g DECIMAL(5,1) DEFAULT NULL,
  CONSTRAINT fk_alimento_grupo FOREIGN KEY (grupo_id) REFERENCES grupos_alimentos(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_nome_alimento (nome_alimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela `restricoes_usuario`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS restricoes_usuario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  alimento_id INT NOT NULL,
  tipo_restricao ENUM('alergia', 'intolerancia', 'nao_gosta') NOT NULL,
  data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_alimento_tipo (usuario_id, alimento_id, tipo_restricao),
  CONSTRAINT fk_restricao_usuario_id FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_restricao_alimento_id FOREIGN KEY (alimento_id) REFERENCES alimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela `registros_agua`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS registros_agua (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  data_registro DATE NOT NULL,
  quantidade_ml INT NOT NULL DEFAULT 0,
  meta_ml INT,
  data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_data_agua (usuario_id, data_registro),
  CONSTRAINT fk_agua_usuario_id FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela `planos_diarios_salvos` (Estrutura básica)
-- Para armazenar o plano gerado para um utilizador num dia específico.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS planos_diarios_salvos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  data_plano DATE NOT NULL,
  meta_calorica_calculada INT,
  total_calorias_plano_gerado INT,
  json_plano_completo TEXT COMMENT 'Armazena o array do plano completo como JSON',
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario_data_plano (usuario_id, data_plano),
  CONSTRAINT fk_plano_usuario_id FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ====================================================================================
-- INSERÇÃO DE DADOS INICIAIS PARA `alimentos` COM PAPÉIS CULINÁRIOS
-- Os IDs dos grupos são baseados na inserção acima.
-- Papeis Culinários:
-- CARB_PRINCIPAL_REFEICAO, CARB_CAFE_LANCHE_PÃES, CARB_CAFE_LANCHE_MASSAS_CEREAIS, GUARNICAO_FARINACEA
-- PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE, PROTEINA_OVO_REFEICAO_PRINCIPAL, PROTEINA_COMPLEMENTAR_LANCHE_CAFE
-- LEGUMINOSA_PRINCIPAL_REFEICAO
-- VEGETAL_FOLHOSO_SALADA, VEGETAL_FRUTO_SALADA, VEGETAL_RAIZ_SALADA_CRUA, VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO, VEGETAL_BASE_SOPA_CALDO
-- FRUTA_IN_NATURA_SOBREMESA, FRUTA_IN_NATURA_LANCHE_CAFE, FRUTA_PARA_SUCO_VITAMINA
-- LATICINIO_BEBIDA_CAFE, LATICINIO_IOGURTE_LANCHE_CAFE, LATICINIO_QUEIJO_ACOMPANHAMENTO, LATICINIO_CULINARIO
-- OLEO_GORDURA_COZINHAR, GORDURA_PARA_PASSAR_PÃES, OLEAGINOSA_PEQUENO_LANCHE
-- BEBIDA_AGUA, BEBIDA_CAFE_PURO, BEBIDA_CHA, BEBIDA_SUCO_FRUTA_NATURAL, BEBIDA_REFRIGERANTE_SUCO_PO
-- TEMPERO_SAL, TEMPERO_ALHO_CEBOLA_FRESCOS, TEMPERO_ERVAS_ESPECIARIAS_SECAS_FRESCAS, CONDIMENTO_VINAGRE
-- ADOCANTE_ACUCAR, DOCE_SIMPLES_SOBREMESA_OCASIONAL, ACHOCOLATADO_PO
-- ====================================================================================

INSERT INTO alimentos (nome_alimento, grupo_id, calorias_por_porcao, porcao_descricao, papeis_culinarios) VALUES
-- Frutas (Grupo ID: 1)
('Banana Prata/Nanica', 1, 95.0, '1 unidade média (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA,FRUTA_PARA_SUCO_VITAMINA'),
('Laranja Pera/Bahia', 1, 47.0, '1 unidade média (aprox. 130g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA,FRUTA_PARA_SUCO_VITAMINA'),
('Maçã Gala/Fuji', 1, 52.0, '1 unidade pequena (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA'),
('Mamão Formosa/Papaia', 1, 45.0, '1 fatia média (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA'),
('Melancia', 1, 30.0, '1 fatia média (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA,FRUTA_PARA_SUCO_VITAMINA'),
('Abacaxi Pérola', 1, 50.0, '1 fatia média (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA,FRUTA_PARA_SUCO_VITAMINA'),
('Manga Tommy/Espada', 1, 60.0, '1/2 unidade pequena (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA,FRUTA_PARA_SUCO_VITAMINA'),
('Goiaba (vermelha/branca)', 1, 68.0, '1 unidade média (aprox. 100g)', 'FRUTA_IN_NATURA_LANCHE_CAFE,FRUTA_IN_NATURA_SOBREMESA,FRUTA_PARA_SUCO_VITAMINA'),
('Maracujá Azedo (polpa)', 1, 60.0, 'Polpa de 1 unidade (aprox. 50g)', 'FRUTA_PARA_SUCO_VITAMINA'),
('Limão Tahiti (sumo)', 1, 15.0, 'Sumo de 1 unidade (aprox. 50ml)', 'FRUTA_PARA_SUCO_VITAMINA,TEMPERO_ERVAS_ESPECIARIAS_SECAS_FRESCAS'),

-- Verduras, Legumes e Raízes/Tubérculos (Grupo ID: 2)
('Alface (crespa, americana, lisa)', 2, 15.0, '5 folhas (aprox. 50g)', 'VEGETAL_FOLHOSO_SALADA'),
('Tomate (salada, italiano)', 2, 18.0, '1 unidade média (aprox. 100g)', 'VEGETAL_FRUTO_SALADA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Cebola Pera', 2, 40.0, '1/2 unidade média (aprox. 50g)', 'TEMPERO_ALHO_CEBOLA_FRESCOS,VEGETAL_FRUTO_SALADA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Alho', 2, 15.0, '1 dente (aprox. 5g)', 'TEMPERO_ALHO_CEBOLA_FRESCOS'),
('Pimentão (verde, amarelo, vermelho)', 2, 25.0, '1/2 unidade pequena (aprox. 40g)', 'VEGETAL_FRUTO_SALADA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,TEMPERO_ALHO_CEBOLA_FRESCOS'),
('Cenoura', 2, 41.0, '1 unidade pequena (aprox. 80g)', 'VEGETAL_RAIZ_SALADA_CRUA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Batata Inglesa', 2, 86.0, '1 unidade média cozida (aprox. 150g)', 'CARB_PRINCIPAL_REFEICAO,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Batata Doce', 2, 77.0, '1 unidade pequena cozida (aprox. 100g)', 'CARB_PRINCIPAL_REFEICAO,CARB_CAFE_LANCHE_MASSAS_CEREAIS,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Macaxeira (Aipim/Mandioca Mansa)', 2, 125.0, '1 pedaço médio cozido (aprox. 100g)', 'CARB_PRINCIPAL_REFEICAO,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Inhame', 2, 118.0, '1 pedaço médio cozido (aprox. 100g)', 'CARB_PRINCIPAL_REFEICAO,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Abóbora Japonesa (Cabotiá)', 2, 40.0, '1 fatia média cozida (aprox. 100g)', 'VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Abobrinha Italiana', 2, 17.0, '1 unidade pequena cozida (aprox. 100g)', 'VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Chuchu', 2, 19.0, '1/2 unidade média cozida (aprox. 80g)', 'VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO,VEGETAL_BASE_SOPA_CALDO'),
('Beterraba', 2, 44.0, '1/2 unidade média cozida (aprox. 80g)', 'VEGETAL_RAIZ_SALADA_CRUA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Repolho (verde/roxo)', 2, 25.0, '1/4 unidade pequena cru (aprox. 70g)', 'VEGETAL_FOLHOSO_SALADA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Couve Manteiga', 2, 32.0, '2 folhas grandes cruas (aprox. 60g)', 'VEGETAL_FOLHOSO_SALADA,VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Pepino Comum', 2, 15.0, '1/2 unidade média (aprox. 70g)', 'VEGETAL_FRUTO_SALADA'),
('Quiabo', 2, 33.0, '5 unidades médias cozidas (aprox. 80g)', 'VEGETAL_COZIDO_REFOGADO_ACOMPANHAMENTO'),
('Coentro, Cebolinha, Salsa (cheiro verde)', 2, 5.0, '1 colher de sopa picado (aprox. 5g)', 'TEMPERO_ERVAS_ESPECIARIAS_SECAS_FRESCAS'),

-- Grãos, Cereais, Pães e Massas (Grupo ID: 3)
('Arroz Branco Agulhinha Tipo 1 Cozido', 3, 128.0, '4 colheres de sopa (aprox. 100g)', 'CARB_PRINCIPAL_REFEICAO'),
('Arroz Integral Cozido', 3, 111.0, '4 colheres de sopa (aprox. 100g)', 'CARB_PRINCIPAL_REFEICAO'),
('Macarrão Comum (Espaguete/Parafuso) Cozido', 3, 158.0, '1 prato raso (aprox. 140g escorrido)', 'CARB_PRINCIPAL_REFEICAO'),
('Pão Francês (de Sal)', 3, 140.0, '1 unidade (aprox. 50g)', 'CARB_CAFE_LANCHE_PÃES'),
('Pão de Forma Tradicional Branco', 3, 75.0, '2 fatias (aprox. 50g)', 'CARB_CAFE_LANCHE_PÃES'),
('Pão de Forma Integral (simples)', 3, 140.0, '2 fatias (aprox. 50g)', 'CARB_CAFE_LANCHE_PÃES'),
('Biscoito Cream Cracker (Água e Sal)', 3, 120.0, '4 unidades (aprox. 28g)', 'CARB_CAFE_LANCHE_MASSAS_CEREAIS'),
('Biscoito Maria ou Maisena', 3, 100.0, '4 unidades (aprox. 24g)', 'CARB_CAFE_LANCHE_MASSAS_CEREAIS'),
('Farinha de Mandioca Branca (para farofa)', 3, 102.0, '2 colheres de sopa (aprox. 30g)', 'GUARNICAO_FARINACEA'),
('Cuscuz Nordestino (Flocão de Milho) Cozido', 3, 113.0, '1 fatia média (aprox. 100g)', 'CARB_CAFE_LANCHE_MASSAS_CEREAIS,CARB_PRINCIPAL_REFEICAO'),
('Aveia em Flocos', 3, 75.0, '2 colheres de sopa (aprox. 20g)', 'CARB_CAFE_LANCHE_MASSAS_CEREAIS'),
('Tapioca (goma hidratada pronta)', 3, 140.0, '1 disco médio (aprox. 100g de goma)', 'CARB_CAFE_LANCHE_MASSAS_CEREAIS'),

-- Carnes, Aves, Peixes e Ovos (Grupo ID: 4)
('Ovo de Galinha Inteiro (cozido/frito/mexido)', 4, 78.0, '1 unidade grande (aprox. 50g)', 'PROTEINA_OVO_REFEICAO_PRINCIPAL,PROTEINA_COMPLEMENTAR_LANCHE_CAFE'),
('Peito de Frango (grelhado/cozido/assado, sem pele)', 4, 165.0, '1 filé médio (aprox. 100g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'),
('Coxa/Sobrecoxa de Frango (assada/cozida, sem pele)', 4, 175.0, '1 unidade média (aprox. 100g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'),
('Carne Bovina Moída (patinho/acém) Refogada', 4, 185.0, '3 colheres de sopa (aprox. 100g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'),
('Bife Bovino (alcatra/coxão mole/patinho) Grelhado', 4, 135.0, '1 bife pequeno (aprox. 100g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'),
('Carne de Panela (músculo/acém) Cozida', 4, 200.0, '2 pedaços médios (aprox. 100g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'),
('Filé de Peixe Branco (tilápia/merluza) Grelhado/Assado', 4, 100.0, '1 filé médio (aprox. 120g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'),
('Sardinha em Lata com Óleo (drenada)', 4, 114.0, '1/2 lata (aprox. 60g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE,PROTEINA_COMPLEMENTAR_LANCHE_CAFE'),
('Linguiça Toscana de Porco (assada/grelhada)', 4, 300.0, '1 gomo (aprox. 80g)', 'PROTEINA_PRINCIPAL_REFEICAO_CARNE_AVE_PEIXE'), -- Uso mais esporádico
('Mortadela Comum', 4, 80.0, '2 fatias finas (aprox. 50g)', 'PROTEINA_COMPLEMENTAR_LANCHE_CAFE'),
('Presunto Cozido Magro', 4, 70.0, '2 fatias finas (aprox. 50g)', 'PROTEINA_COMPLEMENTAR_LANCHE_CAFE'),

-- Leguminosas (Grupo ID: 5)
('Feijão Carioca Cozido (simples)', 5, 76.0, '1 concha média (aprox. 100g)', 'LEGUMINOSA_PRINCIPAL_REFEICAO'),
('Feijão Preto Cozido (simples)', 5, 80.0, '1 concha média (aprox. 100g)', 'LEGUMINOSA_PRINCIPAL_REFEICAO'),
('Lentilha Cozida', 5, 116.0, '1/2 chávena (aprox. 100g)', 'LEGUMINOSA_PRINCIPAL_REFEICAO'),

-- Leite, Queijos e Iogurtes (Grupo ID: 6)
('Leite de Vaca Integral (líquido)', 6, 122.0, '1 copo (200ml)', 'LATICINIO_BEBIDA_CAFE,LATICINIO_CULINARIO'),
('Leite de Vaca Desnatado (líquido)', 6, 70.0, '1 copo (200ml)', 'LATICINIO_BEBIDA_CAFE,LATICINIO_CULINARIO'),
('Iogurte Natural Integral (sem açúcar)', 6, 104.0, '1 pote (170g)', 'LATICINIO_IOGURTE_LANCHE_CAFE'),
('Queijo Minas Frescal', 6, 70.0, '1 fatia média (aprox. 30g)', 'LATICINIO_QUEIJO_ACOMPANHAMENTO,PROTEINA_COMPLEMENTAR_LANCHE_CAFE'),
('Queijo Mussarela', 6, 90.0, '1 fatia (aprox. 30g)', 'LATICINIO_QUEIJO_ACOMPANHAMENTO,PROTEINA_COMPLEMENTAR_LANCHE_CAFE'),
('Requeijão Cremoso Tradicional', 6, 70.0, '1 colher de sopa (aprox. 30g)', 'LATICINIO_CULINARIO,GORDURA_PARA_PASSAR_PÃES'),

-- Óleos, Gorduras e Oleaginosas (Grupo ID: 7)
('Óleo de Soja/Girassol/Milho', 7, 90.0, '1 colher de sopa (10ml)', 'OLEO_GORDURA_COZINHAR'),
('Azeite de Oliva Comum', 7, 88.0, '1 colher de sopa (10ml)', 'OLEO_GORDURA_COZINHAR'),
('Margarina Cremosa (comum)', 7, 72.0, '1 ponta de faca (aprox. 10g)', 'GORDURA_PARA_PASSAR_PÃES'),
('Manteiga com Sal', 7, 72.0, '1 ponta de faca (aprox. 10g)', 'GORDURA_PARA_PASSAR_PÃES'),
('Amendoim Torrado (sem pele, sem sal)', 7, 170.0, '1 punhado pequeno (aprox. 30g)', 'OLEAGINOSA_PEQUENO_LANCHE'),

-- Açúcares, Doces, Bebidas e Outros (Grupo ID: 8)
('Açúcar Refinado/Cristal', 8, 20.0, '1 colher de chá (5g)', 'ADOCANTE_ACUCAR'),
('Café em Pó (para coar)', 8, 0.0, '1 colher de sopa (para preparo)', 'BEBIDA_CAFE_PURO'), -- Caloria do pó, não da bebida
('Café Coado (sem açúcar)', 8, 2.0, '1 chávena (150ml)', 'BEBIDA_CAFE_PURO'),
('Sal de Cozinha', 8, 0.0, '1 pitada', 'TEMPERO_SAL'),
('Vinagre de Álcool/Maçã', 8, 3.0, '1 colher de sopa (15ml)', 'CONDIMENTO_VINAGRE'),
('Achocolatado em Pó (comum)', 8, 75.0, '2 colheres de sopa (20g)', 'ACHOCOLATADO_PO'),
('Doce de Leite Pastoso', 8, 60.0, '1 colher de sopa (20g)', 'DOCE_SIMPLES_SOBREMESA_OCASIONAL'),
('Goiabada Cascão (tablete)', 8, 80.0, '1 fatia fina (30g)', 'DOCE_SIMPLES_SOBREMESA_OCASIONAL'),
('Suco em Pó (preparado com água)', 8, 10.0, '1 copo (200ml) (varia muito)', 'BEBIDA_REFRIGERANTE_SUCO_PO'), -- Caloria baixa, mas nutricionalmente pobre
('Refrigerante Comum (Cola/Guaraná)', 8, 85.0, '1 copo (200ml)', 'BEBIDA_REFRIGERANTE_SUCO_PO'); -- Uso ocasional

