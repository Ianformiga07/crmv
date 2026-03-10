-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 09/03/2026 às 04:34
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `crmv_cursos`
--
CREATE DATABASE IF NOT EXISTS `crmv_cursos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `crmv_cursos`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_alternativas`
--

DROP TABLE IF EXISTS `tbl_alternativas`;
CREATE TABLE `tbl_alternativas` (
  `alternativa_id` int(10) UNSIGNED NOT NULL,
  `questao_id` int(10) UNSIGNED NOT NULL,
  `texto` text NOT NULL,
  `correta` tinyint(1) NOT NULL DEFAULT 0,
  `ordem` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_aulas`
--

DROP TABLE IF EXISTS `tbl_aulas`;
CREATE TABLE `tbl_aulas` (
  `aula_id` int(10) UNSIGNED NOT NULL,
  `modulo_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `youtube_id` varchar(100) DEFAULT NULL COMMENT 'ID do vídeo no YouTube (parte após ?v=)',
  `link_externo` varchar(500) DEFAULT NULL COMMENT 'Link para plataforma EAD ou recurso externo',
  `duracao_min` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Duração em minutos',
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_avaliacoes`
--

DROP TABLE IF EXISTS `tbl_avaliacoes`;
CREATE TABLE `tbl_avaliacoes` (
  `avaliacao_id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `modulo_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = avaliação geral do curso; preenchido = avaliação do módulo',
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('PROVA','QUESTIONARIO','PESQUISA') NOT NULL DEFAULT 'PROVA',
  `nota_minima` decimal(5,2) NOT NULL DEFAULT 6.00,
  `tempo_limite` smallint(6) DEFAULT NULL COMMENT 'em minutos',
  `tentativas_max` tinyint(4) NOT NULL DEFAULT 1,
  `randomizar` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_categorias`
--

DROP TABLE IF EXISTS `tbl_categorias`;
CREATE TABLE `tbl_categorias` (
  `categoria_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(500) DEFAULT NULL,
  `cor_hex` varchar(7) NOT NULL DEFAULT '#1a6b3c',
  `icone_fa` varchar(60) DEFAULT NULL,
  `ordem` smallint(6) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_categorias`
--

INSERT INTO `tbl_categorias` (`categoria_id`, `nome`, `descricao`, `cor_hex`, `icone_fa`, `ordem`, `ativo`, `criado_em`) VALUES
(1, 'Clínica Veterinária', 'Cursos de clínica geral', '#1a6b3c', 'fa-stethoscope', 1, 1, '2026-03-06 17:14:27'),
(2, 'Cirurgia', 'Cursos e workshops de cirurgia', '#15385c', 'fa-scalpel', 2, 1, '2026-03-06 17:14:27'),
(3, 'Diagnóstico por Imagem', 'Ultrassonografia, radiologia', '#c9a227', 'fa-x-ray', 3, 1, '2026-03-06 17:14:27'),
(4, 'Medicina de Animais Silvestres', 'Fauna silvestre e exóticos', '#2d6a4f', 'fa-paw', 4, 1, '2026-03-06 17:14:27'),
(5, 'Saúde Pública', 'Vigilância sanitária e zoonoses', '#6d3b47', 'fa-shield-virus', 5, 1, '2026-03-06 17:14:27'),
(6, 'Administração e Ética', 'Gestão e deontologia veterinária', '#374151', 'fa-balance-scale', 6, 1, '2026-03-06 17:14:27'),
(7, 'Bem-estar Animal', 'Etologia e bem-estar', '#7c3aed', 'fa-heart', 7, 1, '2026-03-06 17:14:27'),
(8, 'Palestras Científicas', 'Palestras e conferências', '#0d2137', 'fa-microphone', 8, 1, '2026-03-06 17:14:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_certificados`
--

DROP TABLE IF EXISTS `tbl_certificados`;
CREATE TABLE `tbl_certificados` (
  `cert_id` int(10) UNSIGNED NOT NULL,
  `matricula_id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `emitido_em` datetime NOT NULL DEFAULT current_timestamp(),
  `qr_path` varchar(200) DEFAULT NULL,
  `pdf_path` varchar(200) DEFAULT NULL,
  `valido` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_certificados`
--

INSERT INTO `tbl_certificados` (`cert_id`, `matricula_id`, `codigo`, `emitido_em`, `qr_path`, `pdf_path`, `valido`) VALUES
(1, 1, 'QA5P-E5TN-GSZY', '2026-03-06 23:01:57', NULL, NULL, 1),
(2, 2, 'CWW6-JCD6-SNND', '2026-03-07 00:07:30', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_configuracoes`
--

DROP TABLE IF EXISTS `tbl_configuracoes`;
CREATE TABLE `tbl_configuracoes` (
  `config_id` int(10) UNSIGNED NOT NULL,
  `chave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(200) DEFAULT NULL,
  `tipo` enum('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `atualizado_por` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_configuracoes`
--

INSERT INTO `tbl_configuracoes` (`config_id`, `chave`, `valor`, `descricao`, `tipo`, `atualizado_em`, `atualizado_por`) VALUES
(1, 'site_nome', 'CRMV/TO — Educação Continuada', 'Nome do sistema', 'texto', NULL, NULL),
(2, 'site_email', 'educacao@crmvto.gov.br', 'E-mail oficial', 'texto', NULL, NULL),
(3, 'cert_validade_anos', '5', 'Validade dos certificados', 'numero', NULL, NULL),
(4, 'sla_alerta_dias', '30', 'Dias para alerta de prazo', 'numero', NULL, NULL),
(5, 'upload_max_mb', '10', 'Tamanho máximo de upload', 'numero', NULL, NULL),
(6, 'cert_rodape', 'Conselho Regional de Medicina Veterinária do Estado do Tocantins', 'Texto do rodapé do certificado', 'texto', NULL, NULL),
(7, 'presidente_nome', 'Presidente do CRMV-TO', 'Nome do presidente para o certificado', 'texto', NULL, NULL),
(8, 'presidente_titulo', 'Médico(a) Veterinário(a)', 'Título do presidente', 'texto', NULL, NULL),
(9, 'cfmv_numero', '0000', 'Número de inscrição no CFMV', 'texto', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_cursos`
--

DROP TABLE IF EXISTS `tbl_cursos`;
CREATE TABLE `tbl_cursos` (
  `curso_id` int(10) UNSIGNED NOT NULL,
  `categoria_id` int(10) UNSIGNED DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `capa` varchar(120) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `tipo` enum('CURSO','PALESTRA','WORKSHOP','CONGRESSO','WEBINAR') NOT NULL DEFAULT 'CURSO',
  `modalidade` enum('PRESENCIAL','EAD','HIBRIDO') NOT NULL DEFAULT 'PRESENCIAL',
  `carga_horaria` decimal(5,1) NOT NULL DEFAULT 0.0,
  `vagas` smallint(6) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `horario` varchar(100) DEFAULT NULL,
  `local_nome` varchar(150) DEFAULT NULL,
  `local_cidade` varchar(80) DEFAULT NULL,
  `local_uf` char(2) DEFAULT 'TO',
  `local_endereco` varchar(200) DEFAULT NULL,
  `link_ead` varchar(300) DEFAULT NULL,
  `youtube_id` varchar(30) DEFAULT NULL,
  `valor` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` enum('RASCUNHO','PUBLICADO','ENCERRADO','CANCELADO') NOT NULL DEFAULT 'RASCUNHO',
  `cert_modelo` text DEFAULT NULL,
  `cert_conteudo_programatico` mediumtext DEFAULT NULL COMMENT 'HTML do conteúdo programático (verso do certificado)',
  `cert_validade` smallint(6) DEFAULT NULL,
  `cert_obs` text DEFAULT NULL COMMENT 'Observação interna do certificado (não aparece impresso)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `instrutor_id` int(11) DEFAULT NULL,
  `requer_avaliacao` tinyint(1) NOT NULL DEFAULT 0,
  `avaliacao_com_nota` tinyint(1) NOT NULL DEFAULT 0,
  `nota_minima` decimal(5,2) DEFAULT 70.00,
  `tentativas_maximas` smallint(6) NOT NULL DEFAULT 3,
  `cert_frente_html` text DEFAULT NULL,
  `cert_verso_html` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_cursos`
--

INSERT INTO `tbl_cursos` (`curso_id`, `categoria_id`, `titulo`, `descricao`, `capa`, `observacoes`, `tipo`, `modalidade`, `carga_horaria`, `vagas`, `data_inicio`, `data_fim`, `horario`, `local_nome`, `local_cidade`, `local_uf`, `local_endereco`, `link_ead`, `youtube_id`, `valor`, `status`, `cert_modelo`, `cert_conteudo_programatico`, `cert_validade`, `cert_obs`, `ativo`, `criado_em`, `atualizado_em`, `criado_por`, `instrutor_id`, `requer_avaliacao`, `avaliacao_com_nota`, `nota_minima`, `tentativas_maximas`, `cert_frente_html`, `cert_verso_html`) VALUES
(1, 1, 'dsadasdasdasdasd', 'sdasdsadsadsad', 'capa_1772848884_5e30e1af.jpg', '', 'CURSO', 'PRESENCIAL', 12.0, NULL, '2026-03-09', '2026-03-10', '', 'Auditório da adapec', 'Palmas', 'TO', 'Quadra ARSE 51 Alameda 9', '', '', 0.00, 'ENCERRADO', NULL, NULL, NULL, NULL, 1, '2026-03-06 23:01:24', '2026-03-07 00:11:28', 2, NULL, 0, 0, 70.00, 3, NULL, NULL),
(2, 7, 'Curso de Bovinos', 'Curso de bem estar animal', 'capa_1772852805_4a1854ca.jpg', '', 'CURSO', 'EAD', 15.0, 50, '2026-03-09', '2026-03-11', '', '', '', 'TO', '', '', 'vNK58tL6J70', 0.00, 'PUBLICADO', NULL, NULL, NULL, NULL, 1, '2026-03-07 00:06:45', NULL, 2, NULL, 0, 0, 70.00, 3, NULL, NULL),
(3, 2, 'testet testando', 'asdasdsadasdasd', 'capa_1772853053_d7586b18.jpeg', '', 'PALESTRA', 'PRESENCIAL', 4.0, 14, '2026-03-09', '2026-03-09', '', 'Auditório da adapec', 'Palmas', 'TO', 'Quadra ARSE 51 Alameda 9', '', '', 0.00, 'PUBLICADO', NULL, NULL, NULL, NULL, 1, '2026-03-07 00:10:53', '2026-03-07 00:11:17', 1, NULL, 0, 0, 70.00, 3, NULL, NULL),
(4, 7, 'Curso Febre Aftosa', 'sadasdsadsadsada', 'capa_1773026231_e46de8b9.jpeg', 'asdasdasdasdsad', 'CURSO', 'EAD', 12.0, 51, '2026-03-09', '2026-03-11', '', '', '', 'TO', '', '', 'c4XeTP11EI8', 0.00, 'PUBLICADO', NULL, '<div><b>Conteúdo Programático:</b></div><div><ul><li>Panorama atual da piscicultura tocantinense (ascendência da atividade, municípios / região mais produtivos e identificação das espécies de importância</li><li>econômica no Tocantins);</li><li>Ascendência da atividade, municípios / região mais produtivos e identificação das espécies de importância econômica no Tocantins;</li><li>Câmara Setorial da Piscicultura;</li><li>Qualidade da água na piscicultura (Parâmetros Físico-químicos);</li><li>Abordagem sobre o programa Estadual de Sanidade dos Animais Aquáticos;</li><li>Boas práticas manejo sanitário e medidas de biosseguridade em pisciculturas;</li><li>Cadastramento de pisciculturas (POP PESAA n° 01);</li><li>Vigilâncias em estabelecimentos aquícolas (POP PESAA n° 02);</li><li>Coleta de material, acondicionamento e envio de amostras pelo serviço veterinário oficial estadual;</li><li>Preenchimento de formulários de coleta de amostra e ou atendimentos oficiais;</li><li>Abordagem sobre o Epicolletct5 (vigilância PESAA);</li><li>Protocolos sanitários em estabelecimento quarentenário;</li><li>Principais enfermidades que acometem os peixes, identificação das doenças, diagnóstico clínico, prevenção;</li><li>Avaliações</li></ul></div><div><b>INSTRUTORES</b></div><div><ul><li>Thiago Fontolan Tardivo – Zootenista/Diretor de Desenvolvimento da AQUICULTURA - SEPEA/TO, Secretário executivo da Câmara Setorial da</li><li>Piscicultura-CSP/TO, e docente do na UniCatólica.</li><li>Marina Karina de Veiga Cabral Delphino – Médica Vet./ Gerente de Soluções de Saúde e Qualidade dos Peixes Grupo GenoMar Docente do Curso de</li><li>Medicina Veterinária do UniCatólica</li><li>Patrícia Oliveira Maciel - Médica Vet./ Pesquisadora na área temática de sanidade de organismos aquáticos da Embrapa Pesca e aquicultura Tocantins.</li><li>Andrey Chama da Costa - Eng. de Pesca/ Gerente de piscicultura do Ruraltins.</li><li>Elias Mendes – Médico Veterinário/Responsável Técnico do PESAA/TO.</li><li>César Romero - Médico Veterinário Responsável Técnico do Núcleo de Vigilância</li></ul></div>', 0, '', 1, '2026-03-09 00:14:56', '2026-03-09 00:17:11', 2, NULL, 0, 0, 70.00, 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_curso_instrutores`
--

DROP TABLE IF EXISTS `tbl_curso_instrutores`;
CREATE TABLE `tbl_curso_instrutores` (
  `inst_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `titulo_profis` varchar(200) DEFAULT NULL,
  `instituicao` varchar(200) DEFAULT NULL,
  `crmv` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `foto` varchar(300) DEFAULT NULL,
  `ordem` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_curso_materiais`
--

DROP TABLE IF EXISTS `tbl_curso_materiais`;
CREATE TABLE `tbl_curso_materiais` (
  `material_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `titulo` varchar(300) NOT NULL,
  `descricao` varchar(500) DEFAULT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'ARQUIVO',
  `arquivo_nome` varchar(300) DEFAULT NULL,
  `arquivo_path` varchar(500) DEFAULT NULL,
  `arquivo_tamanho_kb` int(11) DEFAULT NULL,
  `arquivo_mime` varchar(100) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `visivel_antes` tinyint(1) NOT NULL DEFAULT 0,
  `requer_matricula` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` smallint(6) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_instrutores`
--

DROP TABLE IF EXISTS `tbl_instrutores`;
CREATE TABLE `tbl_instrutores` (
  `instrutor_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `titulo` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `curriculo` text DEFAULT NULL,
  `foto` varchar(200) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `assinatura_img` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_log_atividades`
--

DROP TABLE IF EXISTS `tbl_log_atividades`;
CREATE TABLE `tbl_log_atividades` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `acao` varchar(50) NOT NULL,
  `descricao` varchar(300) DEFAULT NULL,
  `tabela_ref` varchar(60) DEFAULT NULL,
  `registro_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(200) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_log_atividades`
--

INSERT INTO `tbl_log_atividades` (`log_id`, `usuario_id`, `acao`, `descricao`, `tabela_ref`, `registro_id`, `ip_address`, `user_agent`, `criado_em`) VALUES
(1, 1, 'LOGIN', 'Login realizado', 'tbl_usuarios', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 17:20:31'),
(2, 1, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 21:41:24'),
(3, 1, 'LOGIN', 'Login realizado', 'tbl_usuarios', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 21:41:33'),
(4, 1, 'CRIAR_USUARIO', 'Criou veterinário: Ian Leandro Cardoso Formiga', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:02:32'),
(5, 1, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:17:07'),
(6, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:17:13'),
(7, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:41:02'),
(8, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:41:09'),
(9, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:43:32'),
(10, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:43:54'),
(11, 2, 'CRIAR_USUARIO', 'Criou veterinário: Laura Regina da Silva Morais', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:45:13'),
(12, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:45:29'),
(13, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:45:36'),
(14, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:45:48'),
(15, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 22:47:05'),
(16, 2, 'CRIAR_CURSO', 'Criou curso: dsadasdasdasdasd', 'tbl_cursos', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 23:01:24'),
(17, 2, 'EMITIR_CERT', 'Certificado emitido: QA5P-E5TN-GSZY para Laura Regina da Silva Morais', 'tbl_certificados', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 23:01:57'),
(18, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 23:52:16'),
(19, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 23:52:43'),
(20, 2, 'CRIAR_CURSO', 'Criou curso: Curso de Bovinos', 'tbl_cursos', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 00:06:45'),
(21, 2, 'EMITIR_CERT', 'Certificado emitido: CWW6-JCD6-SNND para Laura Regina da Silva Morais', 'tbl_certificados', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 00:07:30'),
(22, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 00:08:31'),
(23, 1, 'LOGIN', 'Login realizado', 'tbl_usuarios', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 00:08:41'),
(24, 1, 'CRIAR_CURSO', 'Criou curso: testet testando', 'tbl_cursos', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 00:10:53'),
(25, 1, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 00:12:19'),
(26, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 10:42:23'),
(27, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 10:42:33'),
(28, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 10:42:39'),
(29, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 11:05:11'),
(30, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 11:05:20'),
(31, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:39:22'),
(32, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:39:40'),
(33, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:40:45'),
(34, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:40:53'),
(35, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:41:56'),
(36, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:42:01'),
(37, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:55:09'),
(38, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:55:16'),
(39, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:10:53'),
(40, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:11:02'),
(41, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:13:18'),
(42, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:13:23'),
(43, 2, 'CRIAR_CURSO', 'Criou curso: Curso Febre Aftosa', 'tbl_cursos', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:14:56'),
(44, 2, 'EDITAR_CURSO', 'Editou curso: Curso Febre Aftosa', 'tbl_cursos', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:17:11'),
(45, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:20:07'),
(46, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:20:14'),
(47, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:23:10'),
(48, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:23:19'),
(49, 3, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:28:10'),
(50, 2, 'LOGIN', 'Login realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:28:24'),
(51, 2, 'MATRICULAR', 'Matriculou Laura Regina da Silva Morais no curso #4 (status: ATIVA)', 'tbl_matriculas', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:29:42'),
(52, 2, 'LOGOUT', 'Logout realizado', 'tbl_usuarios', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:29:59'),
(53, 3, 'LOGIN', 'Login realizado', 'tbl_usuarios', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 00:30:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_materiais`
--

DROP TABLE IF EXISTS `tbl_materiais`;
CREATE TABLE `tbl_materiais` (
  `material_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `modulo_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = material geral do curso; preenchido = material do módulo',
  `nome_arquivo` varchar(160) NOT NULL,
  `nome_original` varchar(220) NOT NULL,
  `tamanho` int(11) NOT NULL DEFAULT 0,
  `tipo_mime` varchar(80) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `criado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_materiais`
--

INSERT INTO `tbl_materiais` (`material_id`, `curso_id`, `modulo_id`, `nome_arquivo`, `nome_original`, `tamanho`, `tipo_mime`, `criado_em`, `criado_por`) VALUES
(1, 1, NULL, 'mat_1_1772848884_06e56d3f.pdf', 'Proposta sistema Projeto Social.pdf', 217596, 'application/pdf', '2026-03-06 23:01:24', 2),
(2, 2, NULL, 'mat_2_1772852805_a6b2daf0.pdf', 'Turma_02___Treinamento_em_Vigilância_e_Coleta_para_envio_de_Amostras_para_Diagnóstico_de_Doenças_em_Peixes_de_Cultivo-Certificado_Turma_02___Treinamento_em_Vigilância_e_Coleta_para_envio_de_Amostras_para_Diagn.pdf', 540526, 'application/pdf', '2026-03-07 00:06:45', 2),
(3, 2, NULL, 'mat_2_1772852805_9f003dc5.docx', 'proposta_estilizada_projeto_social.docx', 31524, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-07 00:06:45', 2),
(4, 2, NULL, 'mat_2_1772852805_560e67ac.pdf', 'Proposta sistema Projeto Social.pdf', 217596, 'application/pdf', '2026-03-07 00:06:45', 2),
(5, 2, NULL, 'mat_2_1772852805_64102f37.docx', 'proposta_sistema_projeto_social.docx', 27336, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-07 00:06:45', 2),
(6, 3, NULL, 'mat_3_1772853053_5f272bdd.pdf', 'Proposta sistema Projeto Social.pdf', 217596, 'application/pdf', '2026-03-07 00:10:53', 1),
(7, 4, NULL, 'mat_4_1773026231_4009c658.pdf', 'mat_2_1772852805_a6b2daf0.pdf', 540526, 'application/pdf', '2026-03-09 00:17:11', 2),
(8, 4, NULL, 'mat_4_1773026231_49391c9c.pdf', 'Turma_02___Treinamento_em_Vigilância_e_Coleta_para_envio_de_Amostras_para_Diagnóstico_de_Doenças_em_Peixes_de_Cultivo-Certificado_Turma_02___Treinamento_em_Vigilância_e_Coleta_para_envio_de_Amostras_para_Diagn.pdf', 540526, 'application/pdf', '2026-03-09 00:17:11', 2),
(9, 4, NULL, 'mat_4_1773026231_de974e1c.docx', 'proposta_estilizada_projeto_social.docx', 31524, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-09 00:17:11', 2),
(10, 4, NULL, 'mat_4_1773026231_7924f433.docx', 'proposta_sistema_projeto_social.docx', 27336, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-09 00:17:11', 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_matriculas`
--

DROP TABLE IF EXISTS `tbl_matriculas`;
CREATE TABLE `tbl_matriculas` (
  `matricula_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `status` enum('ATIVA','CONCLUIDA','CANCELADA','REPROVADO') NOT NULL DEFAULT 'ATIVA',
  `nota_final` decimal(5,2) DEFAULT NULL,
  `presenca_percent` decimal(5,2) DEFAULT NULL,
  `certificado_gerado` tinyint(1) NOT NULL DEFAULT 0,
  `certificado_codigo` varchar(20) DEFAULT NULL,
  `certificado_emitido_em` datetime DEFAULT NULL,
  `progresso_ead` tinyint(4) NOT NULL DEFAULT 0,
  `matriculado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_matriculas`
--

INSERT INTO `tbl_matriculas` (`matricula_id`, `usuario_id`, `curso_id`, `status`, `nota_final`, `presenca_percent`, `certificado_gerado`, `certificado_codigo`, `certificado_emitido_em`, `progresso_ead`, `matriculado_em`, `atualizado_em`) VALUES
(1, 3, 1, 'CONCLUIDA', NULL, NULL, 1, 'QA5P-E5TN-GSZY', '2026-03-06 23:01:57', 0, '2026-03-06 23:01:57', '2026-03-06 23:01:57'),
(2, 3, 2, 'CONCLUIDA', NULL, NULL, 1, 'CWW6-JCD6-SNND', '2026-03-07 00:07:30', 0, '2026-03-07 00:07:30', '2026-03-07 00:07:30'),
(3, 3, 4, 'ATIVA', NULL, NULL, 0, NULL, NULL, 0, '2026-03-09 00:29:42', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_modulos`
--

DROP TABLE IF EXISTS `tbl_modulos`;
CREATE TABLE `tbl_modulos` (
  `modulo_id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_perfis`
--

DROP TABLE IF EXISTS `tbl_perfis`;
CREATE TABLE `tbl_perfis` (
  `perfil_id` int(10) UNSIGNED NOT NULL,
  `perfil_nome` varchar(50) NOT NULL,
  `perfil_descricao` varchar(200) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_perfis`
--

INSERT INTO `tbl_perfis` (`perfil_id`, `perfil_nome`, `perfil_descricao`, `ativo`, `criado_em`) VALUES
(1, 'Administrador', 'Acesso total ao sistema', 1, '2026-03-06 17:14:27'),
(2, 'Veterinário', 'Acesso à área do participante', 1, '2026-03-06 17:14:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_questoes`
--

DROP TABLE IF EXISTS `tbl_questoes`;
CREATE TABLE `tbl_questoes` (
  `questao_id` int(10) UNSIGNED NOT NULL,
  `avaliacao_id` int(10) UNSIGNED NOT NULL,
  `enunciado` text NOT NULL,
  `tipo` enum('MULTIPLA','VF','DISSERTATIVA') NOT NULL DEFAULT 'MULTIPLA',
  `pontos` decimal(4,2) NOT NULL DEFAULT 1.00,
  `ordem` smallint(6) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tbl_usuarios`
--

DROP TABLE IF EXISTS `tbl_usuarios`;
CREATE TABLE `tbl_usuarios` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `perfil_id` int(10) UNSIGNED NOT NULL DEFAULT 2,
  `nome_completo` varchar(150) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `rg` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `sexo` enum('M','F','O') DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `crmv_numero` varchar(20) DEFAULT NULL,
  `crmv_uf` char(2) NOT NULL DEFAULT 'TO',
  `especialidade` varchar(100) DEFAULT NULL,
  `instituicao` varchar(150) DEFAULT NULL,
  `cep` varchar(9) DEFAULT NULL,
  `logradouro` varchar(150) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(80) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `senha_salt` varchar(64) NOT NULL,
  `token_reset` varchar(100) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  `ultimo_acesso` datetime DEFAULT NULL,
  `tentativas_login` tinyint(4) NOT NULL DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `foto_perfil` varchar(200) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `criado_por` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tbl_usuarios`
--

INSERT INTO `tbl_usuarios` (`usuario_id`, `perfil_id`, `nome_completo`, `cpf`, `rg`, `data_nascimento`, `sexo`, `email`, `telefone`, `celular`, `crmv_numero`, `crmv_uf`, `especialidade`, `instituicao`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `senha_hash`, `senha_salt`, `token_reset`, `token_expira`, `ultimo_acesso`, `tentativas_login`, `bloqueado_ate`, `ativo`, `foto_perfil`, `criado_em`, `atualizado_em`, `criado_por`) VALUES
(1, 1, 'Administrador CRMV/TO', '000.000.000-00', NULL, NULL, NULL, 'admin@crmvto.gov.br', NULL, NULL, NULL, 'TO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$iFfCICMMbcmQaVbxV0aZ..U.nSNb0X6Ybu2Ge8.MgEInJ6Y4idaAG', '1c003c3db25d4845573f3552ed9b7229', NULL, NULL, '2026-03-07 00:08:41', 0, NULL, 1, NULL, '2026-03-06 17:19:47', '2026-03-07 00:08:41', NULL),
(2, 1, 'Ian Leandro Cardoso Formiga', '04426330731', '1140811', '1997-12-06', 'M', 'formigaian@gmail.com', '63992863557', '63992863557', '123456852', 'TO', 'Pets', 'Sant Cane', '77021668', 'Quadra ARSE 51 Alameda 9', '9', 'Casa', 'Plano Diretor Sul', 'Palmas', 'TO', '$2y$12$aDB2vaPhI5i9FeS7TbZPueTIjJbAW29oCIRWs3iWOkw.gFBZTRKWS', 'f4194a06cf6d890409e15afe9d20e8fd', NULL, NULL, '2026-03-09 00:28:24', 0, NULL, 1, NULL, '2026-03-06 22:02:32', '2026-03-09 00:28:24', 1),
(3, 2, 'Laura Regina da Silva Morais', '95814844000', '1140811', '2000-12-06', 'F', 'lrmorais29@gmail.com', '63992863557', '63992863557', '123546', 'TO', 'Pets', 'Sant Cane', '77021668', 'Quadra ARSE 51 Alameda 9', '9', NULL, 'Plano Diretor Sul', 'Palmas', 'TO', '$2y$12$oHa3CxhwokYKnJS.ucNVMusohr0JtGetyk31pJEnYFuLGy4V32bzG', 'd866be9306040c764ccca87bb54a50e8', NULL, NULL, '2026-03-09 00:30:06', 0, NULL, 1, NULL, '2026-03-06 22:45:13', '2026-03-09 00:30:06', 2);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_dashboard_totais`
-- (Veja abaixo para a visão atual)
--
DROP VIEW IF EXISTS `vw_dashboard_totais`;
CREATE TABLE `vw_dashboard_totais` (
`total_veterinarios` bigint(21)
,`total_cursos` bigint(21)
,`cursos_publicados` bigint(21)
,`total_matriculas` bigint(21)
,`total_certificados` bigint(21)
,`novos_este_mes` bigint(21)
,`cursos_este_mes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_dashboard_totais`
--
DROP TABLE IF EXISTS `vw_dashboard_totais`;

DROP VIEW IF EXISTS `vw_dashboard_totais`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_dashboard_totais`  AS SELECT (select count(0) from `tbl_usuarios` where `tbl_usuarios`.`ativo` = 1 and `tbl_usuarios`.`perfil_id` = 2) AS `total_veterinarios`, (select count(0) from `tbl_cursos` where `tbl_cursos`.`ativo` = 1) AS `total_cursos`, (select count(0) from `tbl_cursos` where `tbl_cursos`.`status` = 'PUBLICADO' and `tbl_cursos`.`ativo` = 1) AS `cursos_publicados`, (select count(0) from `tbl_matriculas`) AS `total_matriculas`, (select count(0) from `tbl_matriculas` where `tbl_matriculas`.`certificado_gerado` = 1) AS `total_certificados`, (select count(0) from `tbl_usuarios` where `tbl_usuarios`.`ativo` = 1 and `tbl_usuarios`.`perfil_id` = 2 and month(`tbl_usuarios`.`criado_em`) = month(current_timestamp()) and year(`tbl_usuarios`.`criado_em`) = year(current_timestamp())) AS `novos_este_mes`, (select count(0) from `tbl_cursos` where `tbl_cursos`.`ativo` = 1 and month(`tbl_cursos`.`criado_em`) = month(current_timestamp()) and year(`tbl_cursos`.`criado_em`) = year(current_timestamp())) AS `cursos_este_mes` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `tbl_alternativas`
--
ALTER TABLE `tbl_alternativas`
  ADD PRIMARY KEY (`alternativa_id`),
  ADD KEY `questao_id` (`questao_id`);

--
-- Índices de tabela `tbl_aulas`
--
ALTER TABLE `tbl_aulas`
  ADD PRIMARY KEY (`aula_id`),
  ADD KEY `idx_modulo` (`modulo_id`);

--
-- Índices de tabela `tbl_avaliacoes`
--
ALTER TABLE `tbl_avaliacoes`
  ADD PRIMARY KEY (`avaliacao_id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `idx_aval_modulo` (`modulo_id`);

--
-- Índices de tabela `tbl_categorias`
--
ALTER TABLE `tbl_categorias`
  ADD PRIMARY KEY (`categoria_id`);

--
-- Índices de tabela `tbl_certificados`
--
ALTER TABLE `tbl_certificados`
  ADD PRIMARY KEY (`cert_id`),
  ADD UNIQUE KEY `matricula_id` (`matricula_id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `tbl_configuracoes`
--
ALTER TABLE `tbl_configuracoes`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `tbl_cursos`
--
ALTER TABLE `tbl_cursos`
  ADD PRIMARY KEY (`curso_id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Índices de tabela `tbl_curso_instrutores`
--
ALTER TABLE `tbl_curso_instrutores`
  ADD PRIMARY KEY (`inst_id`),
  ADD KEY `idx_ci_curso` (`curso_id`);

--
-- Índices de tabela `tbl_curso_materiais`
--
ALTER TABLE `tbl_curso_materiais`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `idx_cm_curso` (`curso_id`);

--
-- Índices de tabela `tbl_instrutores`
--
ALTER TABLE `tbl_instrutores`
  ADD PRIMARY KEY (`instrutor_id`);

--
-- Índices de tabela `tbl_log_atividades`
--
ALTER TABLE `tbl_log_atividades`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_log_usuario` (`usuario_id`),
  ADD KEY `idx_log_criado` (`criado_em`);

--
-- Índices de tabela `tbl_materiais`
--
ALTER TABLE `tbl_materiais`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `idx_mat_curso` (`curso_id`),
  ADD KEY `idx_modulo_id` (`modulo_id`);

--
-- Índices de tabela `tbl_matriculas`
--
ALTER TABLE `tbl_matriculas`
  ADD PRIMARY KEY (`matricula_id`),
  ADD UNIQUE KEY `uq_mat` (`usuario_id`,`curso_id`),
  ADD UNIQUE KEY `certificado_codigo` (`certificado_codigo`),
  ADD KEY `curso_id` (`curso_id`);

--
-- Índices de tabela `tbl_modulos`
--
ALTER TABLE `tbl_modulos`
  ADD PRIMARY KEY (`modulo_id`),
  ADD KEY `idx_curso` (`curso_id`);

--
-- Índices de tabela `tbl_perfis`
--
ALTER TABLE `tbl_perfis`
  ADD PRIMARY KEY (`perfil_id`);

--
-- Índices de tabela `tbl_questoes`
--
ALTER TABLE `tbl_questoes`
  ADD PRIMARY KEY (`questao_id`),
  ADD KEY `avaliacao_id` (`avaliacao_id`);

--
-- Índices de tabela `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  ADD PRIMARY KEY (`usuario_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `perfil_id` (`perfil_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `tbl_alternativas`
--
ALTER TABLE `tbl_alternativas`
  MODIFY `alternativa_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_aulas`
--
ALTER TABLE `tbl_aulas`
  MODIFY `aula_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_avaliacoes`
--
ALTER TABLE `tbl_avaliacoes`
  MODIFY `avaliacao_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_categorias`
--
ALTER TABLE `tbl_categorias`
  MODIFY `categoria_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `tbl_certificados`
--
ALTER TABLE `tbl_certificados`
  MODIFY `cert_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `tbl_configuracoes`
--
ALTER TABLE `tbl_configuracoes`
  MODIFY `config_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `tbl_cursos`
--
ALTER TABLE `tbl_cursos`
  MODIFY `curso_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `tbl_curso_instrutores`
--
ALTER TABLE `tbl_curso_instrutores`
  MODIFY `inst_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_curso_materiais`
--
ALTER TABLE `tbl_curso_materiais`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_instrutores`
--
ALTER TABLE `tbl_instrutores`
  MODIFY `instrutor_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_log_atividades`
--
ALTER TABLE `tbl_log_atividades`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT de tabela `tbl_materiais`
--
ALTER TABLE `tbl_materiais`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `tbl_matriculas`
--
ALTER TABLE `tbl_matriculas`
  MODIFY `matricula_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tbl_modulos`
--
ALTER TABLE `tbl_modulos`
  MODIFY `modulo_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_perfis`
--
ALTER TABLE `tbl_perfis`
  MODIFY `perfil_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `tbl_questoes`
--
ALTER TABLE `tbl_questoes`
  MODIFY `questao_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `usuario_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `tbl_alternativas`
--
ALTER TABLE `tbl_alternativas`
  ADD CONSTRAINT `tbl_alternativas_ibfk_1` FOREIGN KEY (`questao_id`) REFERENCES `tbl_questoes` (`questao_id`);

--
-- Restrições para tabelas `tbl_aulas`
--
ALTER TABLE `tbl_aulas`
  ADD CONSTRAINT `fk_aula_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `tbl_modulos` (`modulo_id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tbl_avaliacoes`
--
ALTER TABLE `tbl_avaliacoes`
  ADD CONSTRAINT `tbl_avaliacoes_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `tbl_cursos` (`curso_id`);

--
-- Restrições para tabelas `tbl_certificados`
--
ALTER TABLE `tbl_certificados`
  ADD CONSTRAINT `tbl_certificados_ibfk_1` FOREIGN KEY (`matricula_id`) REFERENCES `tbl_matriculas` (`matricula_id`);

--
-- Restrições para tabelas `tbl_cursos`
--
ALTER TABLE `tbl_cursos`
  ADD CONSTRAINT `tbl_cursos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `tbl_categorias` (`categoria_id`);

--
-- Restrições para tabelas `tbl_matriculas`
--
ALTER TABLE `tbl_matriculas`
  ADD CONSTRAINT `tbl_matriculas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_usuarios` (`usuario_id`),
  ADD CONSTRAINT `tbl_matriculas_ibfk_2` FOREIGN KEY (`curso_id`) REFERENCES `tbl_cursos` (`curso_id`);

--
-- Restrições para tabelas `tbl_modulos`
--
ALTER TABLE `tbl_modulos`
  ADD CONSTRAINT `fk_modulo_curso` FOREIGN KEY (`curso_id`) REFERENCES `tbl_cursos` (`curso_id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tbl_questoes`
--
ALTER TABLE `tbl_questoes`
  ADD CONSTRAINT `tbl_questoes_ibfk_1` FOREIGN KEY (`avaliacao_id`) REFERENCES `tbl_avaliacoes` (`avaliacao_id`);

--
-- Restrições para tabelas `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  ADD CONSTRAINT `tbl_usuarios_ibfk_1` FOREIGN KEY (`perfil_id`) REFERENCES `tbl_perfis` (`perfil_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
